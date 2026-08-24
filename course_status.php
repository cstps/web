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
// created:
// 학생 참가권한을 기존 방식으로 삭제
//
// linked:
// Course가 추가하고 추적한 권한만 회수
//
// 교사 m{cid}, 차시 visible/status, 제출기록은 유지한다.
// ============================================================

if ($new_status === 0) {

    $contest_rows = pdo_query(
        "SELECT
            contest_id,
            link_type
         FROM course_contest
         WHERE course_id = ?
           AND link_type IN ('created', 'linked')
         ORDER BY
            lesson_no,
            contest_id",
        $course_id
    );


    if (!is_array($contest_rows)) {
        $contest_rows = array();
    }


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


    foreach ($contest_rows as $contest) {

        $contest_id =
            isset($contest['contest_id'])
                ? intval($contest['contest_id'])
                : 0;

        $link_type =
            isset($contest['link_type'])
                ? trim($contest['link_type'])
                : '';


        if (
            $contest_id <= 0 ||
            !in_array(
                $link_type,
                array('created', 'linked'),
                true
            )
        ) {
            continue;
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


            // ------------------------------------------------
            // Course에서 생성한 Contest
            // ------------------------------------------------

            if ($link_type === 'created') {

                pdo_query(
                    "DELETE FROM privilege
                     WHERE user_id = ?
                       AND rightstr = ?",
                    $student_user_id,
                    "c".$contest_id
                );


                continue;
            }


            // ------------------------------------------------
            // 기존 Contest 연결
            // ------------------------------------------------

            if ($link_type === 'linked') {

                course_revoke_linked_student_right(
                    $course_id,
                    $contest_id,
                    $student_user_id
                );
            }
        }
    }
}

// ============================================================
// 10. Course 재개
//
// 활성 학생에게 활성·공개 차시 권한을 다시 부여한다.
//
// created:
// 기존 방식으로 권한 부여
//
// linked:
// 기존 수동 권한은 보존하고,
// 권한이 없을 때 Course가 추가·추적한다.
// ============================================================

elseif ($new_status === 1) {

    $contest_rows = pdo_query(
        "SELECT
            contest_id,
            link_type
         FROM course_contest
         WHERE course_id = ?
            AND status = 1
            AND
            (
                link_type = 'created'

                OR

                (
                    link_type = 'linked'
                    AND visible = 1
                )
            )
         ORDER BY
            lesson_no,
            contest_id",
        $course_id
    );


    if (!is_array($contest_rows)) {
        $contest_rows = array();
    }


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


    foreach ($contest_rows as $contest) {

        $contest_id =
            isset($contest['contest_id'])
                ? intval($contest['contest_id'])
                : 0;

        $link_type =
            isset($contest['link_type'])
                ? trim($contest['link_type'])
                : '';


        if (
            $contest_id <= 0 ||
            !in_array(
                $link_type,
                array('created', 'linked'),
                true
            )
        ) {
            continue;
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


            // ------------------------------------------------
            // Course에서 생성한 Contest
            // ------------------------------------------------

            if ($link_type === 'created') {

                $rightstr =
                    "c".$contest_id;


                $privilege_rows = pdo_query(
                    "SELECT user_id
                     FROM privilege
                     WHERE user_id = ?
                       AND rightstr = ?
                       AND valuestr = 'true'
                       AND defunct = 'N'
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
                            rightstr,
                            valuestr,
                            defunct
                        )
                        VALUES (?, ?, 'true', 'N')",
                        $student_user_id,
                        $rightstr
                    );
                }


                continue;
            }


            // ------------------------------------------------
            // 기존 Contest 연결
            //
            // Course 상태가 이미 1로 변경되었고
            // 조회 대상도 status=1, visible=1이므로
            // 공통 함수의 조건 검사를 통과한다.
            // ------------------------------------------------

            if ($link_type === 'linked') {

                course_grant_linked_student_right(
                    $course_id,
                    $contest_id,
                    $student_user_id
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