<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "교사 관리";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. course_id 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;


if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
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


$view_course = $course_rows[0];


// ============================================================
// 4. 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 교사 관리 권한 확인
// ============================================================

if (!course_can_manage_teachers($course_id)) {

    $view_errors =
        "<h2>이 수업의 교사를 관리할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_message = '';
$view_error_message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $teacher_user_id =
        isset($_POST['user_id'])
            ? trim($_POST['user_id'])
            : '';

    $teacher_role =
        isset($_POST['role'])
            ? trim($_POST['role'])
            : '';


    if (
        $teacher_user_id === '' ||
        strlen($teacher_user_id) > 48
    ) {

        $view_error_message =
            "교사 아이디가 올바르지 않습니다.";
    }
    elseif (
        !in_array(
            $teacher_role,
            array('teacher', 'assistant'),
            true
        )
    ) {

        $view_error_message =
            "교사 역할이 올바르지 않습니다.";
    }
    else {

        // 실제 사용자 계정 확인
        $user_rows = pdo_query(
            "SELECT
                user_id,
                nick
             FROM users
             WHERE user_id = ?
             LIMIT 1",
            $teacher_user_id
        );


        if (
            !$user_rows ||
            !isset($user_rows[0]['user_id'])
        ) {

            $view_error_message =
                "존재하지 않는 사용자입니다.";
        }
        else {

            // 기존 Course 교사 등록 여부 확인
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
                $teacher_rows &&
                isset($teacher_rows[0]['user_id']) &&
                intval($teacher_rows[0]['status']) === 1
            ) {

                $view_error_message =
                    "이미 이 수업의 담당 교사로 등록되어 있습니다.";
            }
            elseif (
                $teacher_rows &&
                isset($teacher_rows[0]['user_id'])
            ) {

                // 이전에 제외된 교사 재등록
                pdo_query(
                    "UPDATE course_teacher
                     SET
                        role = ?,
                        status = 1,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE course_id = ?
                       AND user_id = ?",
                    $teacher_role,
                    $course_id,
                    $teacher_user_id
                );


                $view_message =
                    "교사를 다시 등록했습니다.";
            }
            else {

                // 신규 등록
                pdo_query(
                    "INSERT INTO course_teacher
                    (
                        course_id,
                        user_id,
                        role,
                        status
                    )
                    VALUES (?, ?, ?, 1)",
                    $course_id,
                    $teacher_user_id,
                    $teacher_role
                );


                $view_message =
                    "교사를 등록했습니다.";
            }


            // ------------------------------------------------
            // teacher 역할이면 기존 Course Contest에
            // m{cid} 관리 권한 동기화
            // ------------------------------------------------

            if (
                $view_error_message === '' &&
                $teacher_role === 'teacher'
            ) {

                $contest_rows = pdo_query(
                    "SELECT contest_id
                     FROM course_contest
                     WHERE course_id = ?
                       AND status = 1",
                    $course_id
                );


                if (is_array($contest_rows)) {

                    foreach ($contest_rows as $contest) {

                        $cid =
                            intval($contest['contest_id']);

                        if ($cid <= 0) {
                            continue;
                        }


                        $rightstr = "m".$cid;


                        if (
                            !$right_rows ||
                            !isset($right_rows[0])
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


                        if (!$right_rows) {

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
            }
            // ============================================================
            // assistant 권한 정리
            //
            // 보조교사로 신규 등록 또는 재등록한 경우
            // 혹시 남아 있을 수 있는 이 Course의 m{cid} 권한을 제거한다.
            // ============================================================

            elseif (
                $view_error_message === '' &&
                $teacher_role === 'assistant'
            ) {

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

                    $cid =
                        isset($contest['contest_id'])
                            ? intval($contest['contest_id'])
                            : 0;


                    if ($cid <= 0) {
                        continue;
                    }


                    pdo_query(
                        "DELETE FROM privilege
                        WHERE user_id = ?
                        AND rightstr = ?",
                        $teacher_user_id,
                        "m".$cid
                    );
                }
            }
        }
    }
}


// ============================================================
// 6. 담당 교사 목록
// ============================================================

$view_teachers = pdo_query(
    "SELECT
        ct.user_id,
        ct.role,
        ct.status,
        ct.joined_at,
        ct.updated_at,

        u.nick,
        u.school

     FROM course_teacher ct

     LEFT JOIN users u
       ON u.user_id = ct.user_id

     WHERE ct.course_id = ?
       AND ct.status = 1

     ORDER BY
        CASE ct.role
            WHEN 'owner' THEN 1
            WHEN 'teacher' THEN 2
            WHEN 'assistant' THEN 3
            ELSE 9
        END,
        ct.user_id",
    $course_id
);


if (!is_array($view_teachers)) {
    $view_teachers = array();
}

// ============================================================
// 7. 제외된 교사 목록
// ============================================================

$view_removed_teachers = pdo_query(
    "SELECT
        ct.user_id,
        ct.role,
        ct.status,
        ct.joined_at,
        ct.updated_at,

        u.nick,
        u.school

     FROM course_teacher ct

     LEFT JOIN users u
       ON u.user_id = ct.user_id

     WHERE ct.course_id = ?
       AND ct.status = 0

     ORDER BY
        ct.updated_at DESC,
        ct.user_id",
    $course_id
);


if (!is_array($view_removed_teachers)) {
    $view_removed_teachers = array();
}


// ============================================================
// 8. 화면 출력
// ============================================================


require(
    "template/".$OJ_TEMPLATE."/course_teachers.php"
);