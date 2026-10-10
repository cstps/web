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
        lesson_id,
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

$lesson_id =
    isset($link_rows[0]['lesson_id'])
        ? intval($link_rows[0]['lesson_id'])
        : 0;


// Lesson 미배정 Contest도 복구할 수 있다.
// 복구 후에는 미배정 Activity로 관리한다.


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
// 6-1. 연결된 Contest Activity 상태 복구
//
// 복구 직후에는 비공개 상태를 유지한다.
// ============================================================

pdo_query(
    "UPDATE course_activity ca
     INNER JOIN course_activity_contest cac
         ON cac.activity_id = ca.activity_id
     SET
         ca.status = 1,
         ca.visible = 0
     WHERE cac.course_contest_id = ?
       AND ca.course_id = ?
       AND ca.activity_type = 'contest'",
    intval($link_rows[0]['id']),
    $course_id
);


// Lesson은 Contest 복구와 독립적으로 관리한다.
// 기존 비활성 Lesson은 자동으로 활성화하지 않는다.


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