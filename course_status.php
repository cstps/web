<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "수업 상태 변경";


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

$new_status =
    isset($_POST['status'])
        ? intval($_POST['status'])
        : -1;


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
    !in_array(
        $new_status,
        array(0, 1),
        true
    )
) {

    $view_errors =
        "<h2>수업 상태 값이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        status
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


// ============================================================
// 6. Course 수정 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_edit($course_id)
) {

    $view_errors =
        "<h2>이 수업의 상태를 변경할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 현재 상태와 같으면 바로 복귀
// ============================================================

$current_status =
    intval($course_rows[0]['status']);


if ($current_status === $new_status) {

    header(
        "Location: course_view.php?course_id=".$course_id
    );

    exit(0);
}


// ============================================================
// 8. Course 상태 변경
// ============================================================

pdo_query(
    "UPDATE course
     SET
        status = ?,
        updated_at = CURRENT_TIMESTAMP
     WHERE course_id = ?",
    $new_status,
    $course_id
);


// ============================================================
// 9. Course 종료
//
// 학생의 해당 Course Contest 참가권한만 회수한다.
// 교사의 m{cid}, 차시 visible/status, 제출기록은 유지한다.
// ============================================================

if ($new_status === 0) {

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


    $student_rows = pdo_query(
        "SELECT
            user_id
         FROM course_student
         WHERE course_id = ?
           AND status = 1
         ORDER BY user_id",
        $course_id
    );


    if (!is_array($student_rows)) {
        $student_rows = array();
    }


    foreach ($contest_rows as $contest) {

        $contest_id =
            isset($contest['contest_id'])
                ? intval($contest['contest_id'])
                : 0;


        if ($contest_id <= 0) {
            continue;
        }


        $rightstr =
            "c".$contest_id;


        foreach ($student_rows as $student) {

            if (
                !isset($student['user_id']) ||
                trim($student['user_id']) === ''
            ) {
                continue;
            }


            pdo_query(
                "DELETE FROM privilege
                 WHERE user_id = ?
                   AND rightstr = ?",
                trim($student['user_id']),
                $rightstr
            );
        }
    }
}


// ============================================================
// 10. Course 재개
//
// 활성 학생에게
// 활성 + 공개 상태의 Course Contest 참가권한을 다시 부여한다.
// ============================================================

elseif ($new_status === 1) {

    $contest_rows = pdo_query(
        "SELECT
            contest_id
         FROM course_contest
         WHERE course_id = ?
           AND status = 1
           AND visible = 1
         ORDER BY contest_id",
        $course_id
    );


    if (!is_array($contest_rows)) {
        $contest_rows = array();
    }


    $student_rows = pdo_query(
        "SELECT
            user_id
         FROM course_student
         WHERE course_id = ?
           AND status = 1
         ORDER BY user_id",
        $course_id
    );


    if (!is_array($student_rows)) {
        $student_rows = array();
    }


    foreach ($contest_rows as $contest) {

        $contest_id =
            isset($contest['contest_id'])
                ? intval($contest['contest_id'])
                : 0;


        if ($contest_id <= 0) {
            continue;
        }


        $rightstr =
            "c".$contest_id;


        foreach ($student_rows as $student) {

            if (
                !isset($student['user_id']) ||
                trim($student['user_id']) === ''
            ) {
                continue;
            }


            $student_user_id =
                trim($student['user_id']);


            $privilege_rows = pdo_query(
                "SELECT
                    user_id
                 FROM privilege
                 WHERE user_id = ?
                   AND rightstr = ?
                 LIMIT 1",
                $student_user_id,
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
                    $student_user_id,
                    $rightstr
                );
            }
        }
    }
}


// ============================================================
// 11. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);