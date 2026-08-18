<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors =
        "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. 입력값
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$teacher_user_id =
    isset($_POST['user_id'])
        ? trim($_POST['user_id'])
        : '';

$new_role =
    isset($_POST['role'])
        ? trim($_POST['role'])
        : '';


// ============================================================
// 4. 기본값 검증
// ============================================================

if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    $teacher_user_id === '' ||
    strlen($teacher_user_id) > 48
) {

    $view_errors =
        "<h2>교사 아이디가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// owner는 이 화면에서 지정할 수 없다.
// 책임교사 변경은 나중에 별도 기능으로 처리한다.
if (
    !in_array(
        $new_role,
        array('teacher', 'assistant'),
        true
    )
) {

    $view_errors =
        "<h2>교사 역할이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course 존재 및 상태 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        status,
        created_by
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);


if (
    !$course_rows ||
    !isset($course_rows[0]['course_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 교사 역할은 변경할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 교사 관리 권한 확인
//
// 화면에서 버튼이 보이지 않더라도
// 직접 POST 요청을 보낼 수 있으므로 서버에서 다시 검사한다.
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_teachers($course_id)
) {

    $view_errors =
        "<h2>이 수업의 교사를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 변경 대상 교사 확인
// ============================================================

$teacher_rows = pdo_query(
    "SELECT
        user_id,
        role,
        status
     FROM course_teacher
     WHERE course_id = ?
       AND user_id = ?
     LIMIT 1",
    $course_id,
    $teacher_user_id
);


if (
    !$teacher_rows ||
    !isset($teacher_rows[0]['user_id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 교사입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$current_role =
    isset($teacher_rows[0]['role'])
        ? trim($teacher_rows[0]['role'])
        : '';

$current_status =
    isset($teacher_rows[0]['status'])
        ? intval($teacher_rows[0]['status'])
        : 0;


// ============================================================
// 8. 제외된 교사는 역할 변경 불가
// ============================================================

if ($current_status !== 1) {

    $view_errors =
        "<h2>현재 활동 중인 담당 교사가 아닙니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. owner 역할 보호
//
// 책임교사는 일반 역할 변경으로 teacher / assistant로
// 변경하지 않는다.
// ============================================================

if ($current_role === 'owner') {

    $view_errors =
        "<h2>책임교사의 역할은 이 화면에서 변경할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 10. 현재 역할과 동일한 경우
//
// DB를 불필요하게 수정하지 않고 목록으로 돌아간다.
// ============================================================

if ($current_role === $new_role) {

    header(
        "Location: course_teachers.php?course_id=".$course_id
    );

    exit(0);
}


// ============================================================
// 11. 역할 변경
// ============================================================

pdo_query(
    "UPDATE course_teacher
     SET
        role = ?,
        updated_at = CURRENT_TIMESTAMP
     WHERE course_id = ?
       AND user_id = ?
       AND status = 1",
    $new_role,
    $course_id,
    $teacher_user_id
);


// ============================================================
// 12. assistant → teacher
//
// 담당교사가 되면 현재 Course의 활성 차시에 대해
// Contest 관리 권한 m{cid}를 부여한다.
//
// 이미 권한이 존재하는 경우에는 중복 추가하지 않는다.
// ============================================================

if ($new_role === 'teacher') {

    $contest_rows = pdo_query(
        "SELECT
            contest_id
         FROM course_contest
         WHERE course_id = ?
           AND status = 1
         ORDER BY contest_id",
        $course_id
    );


    if (!is_array($contest_rows)) {
        $contest_rows = array();
    }


    foreach ($contest_rows as $contest) {

        if (!isset($contest['contest_id'])) {
            continue;
        }


        $contest_id =
            intval($contest['contest_id']);


        if ($contest_id <= 0) {
            continue;
        }


        $rightstr =
            "m".$contest_id;


        $privilege_rows = pdo_query(
            "SELECT
                user_id
             FROM privilege
             WHERE user_id = ?
               AND rightstr = ?
             LIMIT 1",
            $teacher_user_id,
            $rightstr
        );


        if (
            !$privilege_rows ||
            !isset($privilege_rows[0]['user_id'])
        ) {

            pdo_query(
                "INSERT INTO privilege
                (
                    user_id,
                    rightstr
                )
                VALUES (?, ?)",
                $teacher_user_id,
                $rightstr
            );
        }
    }
}


// ============================================================
// 13. teacher → assistant
//
// 보조교사가 되면 이 Course에서 부여된 Contest 관리
// 권한을 회수한다.
//
// 주의:
// 사용자가 가진 모든 m 권한을 삭제하면 안 된다.
// 다른 Course 또는 일반 Contest의 m 권한일 수도 있으므로
// 현재 Course에 연결된 Contest의 m{cid}만 삭제한다.
// ============================================================

elseif ($new_role === 'assistant') {

    $contest_rows = pdo_query(
        "SELECT
            contest_id
         FROM course_contest
         WHERE course_id = ?
         ORDER BY contest_id",
        $course_id
    );


    if (!is_array($contest_rows)) {
        $contest_rows = array();
    }


    foreach ($contest_rows as $contest) {

        if (!isset($contest['contest_id'])) {
            continue;
        }


        $contest_id =
            intval($contest['contest_id']);


        if ($contest_id <= 0) {
            continue;
        }


        pdo_query(
            "DELETE FROM privilege
             WHERE user_id = ?
               AND rightstr = ?",
            $teacher_user_id,
            "m".$contest_id
        );
    }
}


// ============================================================
// 14. 교사 관리 화면으로 복귀
// ============================================================

header(
    "Location: course_teachers.php?course_id=".$course_id
);

exit(0);