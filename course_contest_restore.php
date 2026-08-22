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

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. 입력값 확인
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$contest_id =
    isset($_POST['contest_id'])
        ? intval($_POST['contest_id'])
        : 0;


if (
    $course_id <= 0 ||
    $contest_id <= 0
) {

    $view_errors =
        "<h2>잘못된 차시 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 복원할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 제거된 차시인지 확인
// ============================================================

$link_rows = pdo_query(
    "SELECT
        id,
        status,
        link_type
     FROM course_contest
     WHERE course_id = ?
       AND contest_id = ?
     LIMIT 1",
    $course_id,
    $contest_id
);


if (
    !$link_rows ||
    !isset($link_rows[0]['id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (intval($link_rows[0]['status']) !== 0) {

    $view_errors =
        "<h2>이미 활성 상태인 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$link_type =
    isset($link_rows[0]['link_type'])
        ? trim($link_rows[0]['link_type'])
        : '';


if (
    !in_array(
        $link_type,
        array('created', 'linked'),
        true
    )
) {

    $view_errors =
        "<h2>차시 연결 유형이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. 차시 복원
//
// 복원 시 visible은 0으로 둔다.
// 교사가 확인 후 직접 공개하도록 한다.
// ============================================================

pdo_query(
    "UPDATE course_contest
     SET
        status = 1,
        visible = 0
     WHERE course_id = ?
       AND contest_id = ?
       AND status = 0",
    $course_id,
    $contest_id
);


// ============================================================
// 7. 현재 수강 중인 학생에게 Contest 참가권한 복원
//
// created:
// - 현재 수강생에게 c{cid} 권한을 다시 부여한다.
//
// linked:
// - 기존 Contest 참가권한을 변경하지 않는다.
// ============================================================

// ============================================================
// 7. created Contest 학생 참가권한 복원
//
// linked는 visible=0으로 복원되므로 권한을 부여하지 않는다.
// 이후 공개할 때 추적형 권한을 부여한다.
// ============================================================

if ($link_type === 'created') {

    $student_rows = pdo_query(
        "SELECT user_id
         FROM course_student
         WHERE course_id = ?
           AND status = 1
         ORDER BY user_id",
        $course_id
    );


    if (!is_array($student_rows)) {
        $student_rows = array();
    }


    foreach ($student_rows as $student) {

        if (
            !isset($student['user_id']) ||
            trim($student['user_id']) === ''
        ) {
            continue;
        }


        $student_user_id =
            trim($student['user_id']);

        $rightstr =
            "c".$contest_id;


        $exists = pdo_query(
            "SELECT 1
             FROM privilege
             WHERE user_id = ?
               AND rightstr = ?
               AND valuestr = 'true'
               AND defunct = 'N'
             LIMIT 1",
            $student_user_id,
            $rightstr
        );


        if (!$exists) {

            pdo_query(
                "INSERT INTO privilege
                (
                    user_id,
                    rightstr,
                    valuestr,
                    defunct
                )
                VALUES (?, ?, 'true', 'N')",
                $student_user_id,
                $rightstr
            );
        }
    }
}

// ============================================================
// 8. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);