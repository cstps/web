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

$visible =
    isset($_POST['visible'])
        ? intval($_POST['visible'])
        : -1;


if (
    $course_id <= 0 ||
    $contest_id <= 0 ||
    !in_array($visible, array(0, 1), true)
) {

    $view_errors = "<h2>잘못된 요청 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. Course 접근 및 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 해당 Contest가 실제 Course 소속인지 확인
// ============================================================

$rows = pdo_query(
    "SELECT id
     FROM course_contest
     WHERE course_id = ?
       AND contest_id = ?
     LIMIT 1",
    $course_id,
    $contest_id
);


if (
    !$rows ||
    !isset($rows[0]['id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 공개 상태 변경
// ============================================================

pdo_query(
    "UPDATE course_contest
     SET visible = ?
     WHERE course_id = ?
       AND contest_id = ?",
    $visible,
    $course_id,
    $contest_id
);

// ============================================================
// 7. 학생 Contest 참가 권한 동기화
// ============================================================

$student_rows = pdo_query(
    "SELECT user_id
     FROM course_student
     WHERE course_id = ?
       AND status = 1",
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


    // --------------------------------------------------------
    // 공개
    // → 참가 권한 부여
    // --------------------------------------------------------

    if ($visible === 1) {

        $exists = pdo_query(
            "SELECT 1
             FROM privilege
             WHERE user_id = ?
               AND rightstr = ?
             LIMIT 1",
            $student_user_id,
            $rightstr
        );


        if (!$exists) {

            pdo_query(
                "INSERT INTO privilege
                    (user_id, rightstr)
                 VALUES (?, ?)",
                $student_user_id,
                $rightstr
            );
        }
    }


    // --------------------------------------------------------
    // 숨김
    // → 참가 권한 제거
    // --------------------------------------------------------

    else {

        pdo_query(
            "DELETE FROM privilege
             WHERE user_id = ?
               AND rightstr = ?",
            $student_user_id,
            $rightstr
        );
    }
}

// ============================================================
// 8. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);