<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "수업 학생 관리";

// ============================================================
// Course 학생 Contest 참가 권한 추가
//
// created:
// 기존 방식 유지
// 활성 차시라면 Course가 직접 권한을 관리한다.
//
// linked:
// 활성·공개 차시만 추적형 권한을 부여한다.
// 기존 수동 권한이 있으면 그대로 보존한다.
// ============================================================

function course_add_student_contest_privileges(
    $course_id,
    $user_id
) {

    $course_id =
        intval($course_id);

    $user_id =
        trim($user_id);


    if (
        $course_id <= 0 ||
        $user_id === ''
    ) {
        return;
    }


    $contest_rows = pdo_query(
        "SELECT
            contest_id,
            link_type,
            visible

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
        return;
    }


    foreach ($contest_rows as $contest) {

        $contest_id =
            isset($contest['contest_id'])
                ? intval($contest['contest_id'])
                : 0;

        $link_type =
            isset($contest['link_type'])
                ? $contest['link_type']
                : '';


        if ($contest_id <= 0) {
            continue;
        }


        // ----------------------------------------------------
        // Course에서 생성한 Contest
        //
        // 기존 권한 동기화 방식 유지
        // ----------------------------------------------------

        if ($link_type === 'created') {

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
                $user_id,
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
                    $user_id,
                    $rightstr
                );
            }


            continue;
        }


        // ----------------------------------------------------
        // 기존 Contest 연결
        //
        // 공통 함수가 다음을 다시 확인한다.
        // - Course 활성
        // - 학생 활성
        // - 차시 활성·공개
        // - link_type = linked
        //
        // 권한이 없을 때만 추가하고 추적한다.
        // ----------------------------------------------------

        if ($link_type === 'linked') {

            course_grant_linked_student_right(
                $course_id,
                $contest_id,
                $user_id
            );
        }
    }
}

// ============================================================
// Course 학생 Contest 참가 권한 제거
//
// created:
// Course 전용 Contest이므로 기존 방식대로 권한 삭제
//
// linked:
// Course가 실제 추가하고 추적한 권한만 한 행 회수
// 기존 수동 권한은 삭제하지 않음
// ============================================================

function course_remove_student_contest_privileges(
    $course_id,
    $user_id
) {

    $course_id =
        intval($course_id);

    $user_id =
        trim($user_id);


    if (
        $course_id <= 0 ||
        $user_id === ''
    ) {
        return;
    }


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
        return;
    }


    foreach ($contest_rows as $contest) {

        $contest_id =
            isset($contest['contest_id'])
                ? intval($contest['contest_id'])
                : 0;

        $link_type =
            isset($contest['link_type'])
                ? $contest['link_type']
                : '';


        if ($contest_id <= 0) {
            continue;
        }


        // ----------------------------------------------------
        // Course에서 생성한 Contest
        // ----------------------------------------------------

        if ($link_type === 'created') {

            pdo_query(
                "DELETE FROM privilege
                 WHERE user_id = ?
                   AND rightstr = ?",
                $user_id,
                "c".$contest_id
            );


            continue;
        }


        // ----------------------------------------------------
        // 기존 Contest 연결
        //
        // 추적 테이블에 status=1인 행이 있는 경우에만
        // 실제 privilege 한 행을 회수한다.
        // ----------------------------------------------------

        if ($link_type === 'linked') {

            course_revoke_linked_student_right(
                $course_id,
                $contest_id,
                $user_id
            );
        }
    }
}
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

if (
    !isset($_GET['course_id']) ||
    intval($_GET['course_id']) <= 0
) {

    $view_errors = "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($_GET['course_id']);


// ============================================================
// 3. Course 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        school,
        school_year,
        semester,
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

    $view_errors = "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_course = $course_rows[0];


// ============================================================
// 4. 접근 권한 확인
// ============================================================

if (!course_can_access($course_id)) {

    $view_errors =
        "<h2>이 수업의 학생 정보를 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 학생 관리 권한
// ============================================================

$view_can_manage_students =
    course_can_manage_students($course_id);

// ============================================================
// 6. 학생 추가 처리
// - 단일 등록
// - 엑셀 복사/붙여넣기 일괄 등록
// ============================================================

$view_message = '';
$view_error_message = '';

$view_bulk_results = array();

$view_bulk_summary = array(
    'added'      => 0,
    'reactivated'=> 0,
    'duplicate'  => 0,
    'failed'     => 0
);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --------------------------------------------------------
    // 학생 관리 권한 재확인
    // --------------------------------------------------------

    if (!$view_can_manage_students) {

        $view_errors =
            "<h2>학생을 관리할 권한이 없습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }


    // --------------------------------------------------------
    // 종료된 수업에는 학생 추가 금지
    // --------------------------------------------------------

    if (intval($view_course['status']) !== 1) {

        $view_error_message =
            "종료된 수업에서는 학생 정보를 변경할 수 없습니다";

    }
    else {

        $post_action =
            isset($_POST['action'])
                ? trim($_POST['action'])
                : '';


        // ====================================================
        // 6-1. 단일 학생 등록
        // ====================================================

        if ($post_action === 'add_single') {

            $student_user_id =
                isset($_POST['student_user_id'])
                    ? trim($_POST['student_user_id'])
                    : '';

            $student_no =
                isset($_POST['student_no'])
                    ? trim($_POST['student_no'])
                    : '';


            if ($student_user_id === '') {

                $view_error_message =
                    "학생 아이디를 입력하세요.";

            }
            elseif (strlen($student_user_id) > 48) {

                $view_error_message =
                    "학생 아이디가 너무 깁니다.";

            }
            elseif (strlen($student_no) > 20) {

                $view_error_message =
                    "학생 번호가 너무 깁니다.";

            }
            else {

                $user_rows = pdo_query(
                    "SELECT
                        user_id,
                        nick,
                        school,
                        defunct
                     FROM users
                     WHERE user_id = ?
                     LIMIT 1",
                    $student_user_id
                );


                if (
                    !$user_rows ||
                    !isset($user_rows[0]['user_id'])
                ) {

                    $view_error_message =
                        "존재하지 않는 사용자입니다.";

                }
                elseif (
                    isset($user_rows[0]['defunct']) &&
                    $user_rows[0]['defunct'] === 'Y'
                ) {

                    $view_error_message =
                        "사용이 중지된 계정은 등록할 수 없습니다.";

                }
                else {

                    $existing_rows = pdo_query(
                        "SELECT status
                           FROM course_student
                          WHERE course_id = ?
                            AND user_id = ?
                          LIMIT 1",
                        $course_id,
                        $student_user_id
                    );


                    if (
                        $existing_rows &&
                        isset($existing_rows[0]['status'])
                    ) {

                        if (
                            intval($existing_rows[0]['status']) === 1
                        ) {

                            $view_error_message =
                                "이미 이 수업에 등록된 학생입니다.";

                        }
                        else {

                            pdo_query(
                                "UPDATE course_student
                                    SET student_no = ?,
                                        status = 1,
                                        left_at = NULL,
                                        updated_at = CURRENT_TIMESTAMP
                                  WHERE course_id = ?
                                    AND user_id = ?",
                                ($student_no === '' ? null : $student_no),
                                $course_id,
                                $student_user_id
                            );

                            course_add_student_contest_privileges(
                                $course_id,
                                $student_user_id
                            );

                            $view_message =
                                "학생을 다시 수강 등록했습니다.";
                        }

                    }
                    else {

                        pdo_query(
                            "INSERT INTO course_student
                                (
                                    course_id,
                                    user_id,
                                    student_no,
                                    status
                                )
                             VALUES
                                (?, ?, ?, 1)",
                            $course_id,
                            $student_user_id,
                            ($student_no === '' ? null : $student_no)
                        );
                        
                        // Course Contest 참가 권한 추가
                        course_add_student_contest_privileges(
                            $course_id,
                            $student_user_id
                        );

                        $view_message =
                            "학생을 수업에 등록했습니다.";
                    }
                }
            }
        }


        // ====================================================
        // 6-2. 학생 일괄 등록
        // ====================================================

        elseif ($post_action === 'add_bulk') {

            $bulk_text =
                isset($_POST['bulk_students'])
                    ? trim($_POST['bulk_students'])
                    : '';


            if ($bulk_text === '') {

                $view_error_message =
                    "등록할 학생 목록을 입력하세요.";

            }
            else {

                // Windows / Linux / Mac 줄바꿈 모두 처리
                $lines = preg_split(
                    '/\r\n|\r|\n/',
                    $bulk_text
                );

                // 지나치게 많은 데이터 입력 방지
                if (count($lines) > 500) {

                    $view_error_message =
                        "한 번에 최대 500명까지 등록할 수 있습니다.";

                }
                else {

                    $line_no = 0;

                    foreach ($lines as $line) {

                        $line_no++;

                        $line = trim($line);

                        // 빈 줄 무시
                        if ($line === '') {
                            continue;
                        }


                        // ------------------------------------
                        // Excel 복사 시 탭(\t) 우선 분리
                        //
                        // 1열 : user_id
                        // 2열 : student_no (선택)
                        // ------------------------------------

                        if (strpos($line, "\t") !== false) {

                            $parts = explode("\t", $line);

                        }
                        else {

                            // 직접 입력한 경우 공백도 허용
                            $parts = preg_split(
                                '/\s+/',
                                $line
                            );
                        }


                        // 앞뒤 공백 제거
                        $parts = array_map(
                            'trim',
                            $parts
                        );


                        // 1열 또는 2열만 허용
                        if (
                            count($parts) < 1 ||
                            count($parts) > 2
                        ) {

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => '',
                                'result'  => 'failed',
                                'message' => '입력 형식이 올바르지 않습니다.'
                            );

                            $view_bulk_summary['failed']++;

                            continue;
                        }


                        $student_user_id =
                            isset($parts[0])
                                ? trim($parts[0])
                                : '';

                        $student_no =
                            isset($parts[1])
                                ? trim($parts[1])
                                : '';


                        // ------------------------------------
                        // 기본 입력 검증
                        // ------------------------------------

                        if ($student_user_id === '') {

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => '',
                                'result'  => 'failed',
                                'message' => '학생 아이디가 없습니다.'
                            );

                            $view_bulk_summary['failed']++;

                            continue;
                        }


                        if (strlen($student_user_id) > 48) {

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => $student_user_id,
                                'result'  => 'failed',
                                'message' => '학생 아이디가 너무 깁니다.'
                            );

                            $view_bulk_summary['failed']++;

                            continue;
                        }


                        if (strlen($student_no) > 20) {

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => $student_user_id,
                                'result'  => 'failed',
                                'message' => '학생 번호가 너무 깁니다.'
                            );

                            $view_bulk_summary['failed']++;

                            continue;
                        }


                        // ------------------------------------
                        // users 계정 확인
                        // ------------------------------------

                        $user_rows = pdo_query(
                            "SELECT
                                user_id,
                                nick,
                                school,
                                defunct
                             FROM users
                             WHERE user_id = ?
                             LIMIT 1",
                            $student_user_id
                        );


                        if (
                            !$user_rows ||
                            !isset($user_rows[0]['user_id'])
                        ) {

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => $student_user_id,
                                'result'  => 'failed',
                                'message' => '존재하지 않는 사용자'
                            );

                            $view_bulk_summary['failed']++;

                            continue;
                        }


                        if (
                            isset($user_rows[0]['defunct']) &&
                            $user_rows[0]['defunct'] === 'Y'
                        ) {

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => $student_user_id,
                                'result'  => 'failed',
                                'message' => '사용 중지 계정'
                            );

                            $view_bulk_summary['failed']++;

                            continue;
                        }


                        // ------------------------------------
                        // 기존 Course 등록 확인
                        // ------------------------------------

                        $existing_rows = pdo_query(
                            "SELECT status
                               FROM course_student
                              WHERE course_id = ?
                                AND user_id = ?
                              LIMIT 1",
                            $course_id,
                            $student_user_id
                        );


                        if (
                            $existing_rows &&
                            isset($existing_rows[0]['status'])
                        ) {

                            // --------------------------------
                            // 이미 수강 중
                            // --------------------------------

                            if (
                                intval(
                                    $existing_rows[0]['status']
                                ) === 1
                            ) {

                                $view_bulk_results[] = array(
                                    'line'    => $line_no,
                                    'user_id' => $student_user_id,
                                    'result'  => 'duplicate',
                                    'message' => '이미 등록됨'
                                );

                                $view_bulk_summary['duplicate']++;

                                continue;
                            }


                            // --------------------------------
                            // 수강 종료 학생 재등록
                            // --------------------------------

                            pdo_query(
                                "UPDATE course_student
                                    SET student_no = ?,
                                        status = 1,
                                        left_at = NULL,
                                        updated_at = CURRENT_TIMESTAMP
                                  WHERE course_id = ?
                                    AND user_id = ?",
                                ($student_no === '' ? null : $student_no),
                                $course_id,
                                $student_user_id
                            );
                            
                            course_add_student_contest_privileges(
                                $course_id,
                                $student_user_id
                            );

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => $student_user_id,
                                'result'  => 'reactivated',
                                'message' => '재등록 완료'
                            );

                            $view_bulk_summary['reactivated']++;

                        }
                        else {

                            // --------------------------------
                            // 신규 등록
                            // --------------------------------

                            pdo_query(
                                "INSERT INTO course_student
                                    (
                                        course_id,
                                        user_id,
                                        student_no,
                                        status
                                    )
                                 VALUES
                                    (?, ?, ?, 1)",
                                $course_id,
                                $student_user_id,
                                ($student_no === '' ? null : $student_no)
                            );

                            course_add_student_contest_privileges(
                                $course_id,
                                $student_user_id
                            );

                            $view_bulk_results[] = array(
                                'line'    => $line_no,
                                'user_id' => $student_user_id,
                                'result'  => 'added',
                                'message' => '등록 완료'
                            );

                            $view_bulk_summary['added']++;
                        }
                    }


                    $view_message =
                        "학생 일괄 등록 처리가 완료되었습니다.";
                }
            }
        }
        // ====================================================
        // 6-3. 학생 수강 종료
        // ====================================================

        elseif ($post_action === 'deactivate_student') {

            $target_user_id =
                isset($_POST['target_user_id'])
                    ? trim($_POST['target_user_id'])
                    : '';


            if ($target_user_id === '') {

                $view_error_message =
                    "잘못된 학생 정보입니다.";

            }
            else {

                $student_rows = pdo_query(
                    "SELECT status
                    FROM course_student
                    WHERE course_id = ?
                        AND user_id = ?
                    LIMIT 1",
                    $course_id,
                    $target_user_id
                );


                if (
                    !$student_rows ||
                    !isset($student_rows[0]['status'])
                ) {

                    $view_error_message =
                        "이 수업에 등록되지 않은 학생입니다.";

                }
                elseif (
                    intval($student_rows[0]['status']) !== 1
                ) {

                    $view_error_message =
                        "이미 수강 종료된 학생입니다.";

                }
                else {

                    pdo_query(
                        "UPDATE course_student
                            SET status = 0,
                                left_at = CURRENT_TIMESTAMP,
                                updated_at = CURRENT_TIMESTAMP
                        WHERE course_id = ?
                            AND user_id = ?
                            AND status = 1",
                        $course_id,
                        $target_user_id
                    );
                    course_remove_student_contest_privileges(
                        $course_id,
                        $target_user_id
                    );

                    $view_message =
                        "학생의 수강을 종료했습니다.";
                }
            }
        }


        // ====================================================
        // 6-4. 학생 재등록
        // ====================================================

        elseif ($post_action === 'reactivate_student') {

            $target_user_id =
                isset($_POST['target_user_id'])
                    ? trim($_POST['target_user_id'])
                    : '';


            if ($target_user_id === '') {

                $view_error_message =
                    "잘못된 학생 정보입니다.";

            }
            else {

                $student_rows = pdo_query(
                    "SELECT status
                    FROM course_student
                    WHERE course_id = ?
                        AND user_id = ?
                    LIMIT 1",
                    $course_id,
                    $target_user_id
                );


                if (
                    !$student_rows ||
                    !isset($student_rows[0]['status'])
                ) {

                    $view_error_message =
                        "이 수업에 등록되지 않은 학생입니다.";

                }
                elseif (
                    intval($student_rows[0]['status']) === 1
                ) {

                    $view_error_message =
                        "이미 수강 중인 학생입니다.";

                }
                else {

                    pdo_query(
                        "UPDATE course_student
                            SET status = 1,
                                left_at = NULL,
                                updated_at = CURRENT_TIMESTAMP
                        WHERE course_id = ?
                            AND user_id = ?",
                        $course_id,
                        $target_user_id
                    );
                    course_add_student_contest_privileges(
                        $course_id,
                        $target_user_id
                    );
                    $view_message =
                        "학생을 다시 수강 등록했습니다.";
                }
            }
        }

        // ====================================================
        // 6-5. 학생 번호 수정
        // ====================================================

        elseif ($post_action === 'update_student_no') {

            $target_user_id =
                isset($_POST['target_user_id'])
                    ? trim($_POST['target_user_id'])
                    : '';

            $new_student_no =
                isset($_POST['student_no'])
                    ? trim($_POST['student_no'])
                    : '';


            // ------------------------------------------------
            // 입력값 확인
            // ------------------------------------------------

            if ($target_user_id === '') {

                $view_error_message =
                    "잘못된 학생 정보입니다.";

            }
            elseif (strlen($new_student_no) > 20) {

                $view_error_message =
                    "학생 번호는 20자 이내로 입력하세요.";

            }
            else {

                // --------------------------------------------
                // 이 수업에 실제 등록된 학생인지 확인
                // --------------------------------------------

                $student_rows = pdo_query(
                    "SELECT user_id
                    FROM course_student
                    WHERE course_id = ?
                        AND user_id = ?
                    LIMIT 1",
                    $course_id,
                    $target_user_id
                );


                if (
                    !$student_rows ||
                    !isset($student_rows[0]['user_id'])
                ) {

                    $view_error_message =
                        "이 수업에 등록되지 않은 학생입니다.";

                }
                else {

                    // ----------------------------------------
                    // 빈 값은 NULL로 저장
                    // ----------------------------------------

                    pdo_query(
                        "UPDATE course_student
                            SET student_no = ?,
                                updated_at = CURRENT_TIMESTAMP
                        WHERE course_id = ?
                            AND user_id = ?",
                        ($new_student_no === ''
                            ? null
                            : $new_student_no),
                        $course_id,
                        $target_user_id
                    );


                    $view_message =
                        "학생 번호를 수정했습니다.";
                }
            }
        }

        // ====================================================
        // 알 수 없는 POST 요청
        // ====================================================

        else {

            $view_error_message =
                "잘못된 요청입니다.";
        }
    }
}

// ============================================================
// Course 전체 활성 차시 문제 수
// ============================================================

$course_problem_count_rows = pdo_query(
    "SELECT COUNT(*) AS problem_count

     FROM course_contest cc

     INNER JOIN contest_problem cp
       ON cp.contest_id = cc.contest_id

     WHERE cc.course_id = ?
       AND cc.status = 1",
    $course_id
);

$view_course_problem_count =
    isset($course_problem_count_rows[0]['problem_count'])
        ? intval($course_problem_count_rows[0]['problem_count'])
        : 0;


// ============================================================
// 7. 학생 목록 조회
//
// LEFT JOIN:
// course_student 기록은 존재하지만 users 계정이 없어진 경우도
// 확인할 수 있도록 한다.
// ============================================================

$view_students = pdo_query(
    "SELECT
        cs.user_id,
        cs.student_no,
        cs.status,
        cs.joined_at,
        cs.left_at,

        u.nick,
        u.school,
        u.email,
        u.defunct,

        (
            SELECT COUNT(*)
            FROM solution s
            INNER JOIN course_contest cc
                ON cc.contest_id = s.contest_id
            AND cc.status = 1
            WHERE cc.course_id = cs.course_id
              AND s.user_id = cs.user_id
        ) AS course_submit_count,

                (
            SELECT COUNT(
                DISTINCT CONCAT(
                    s.contest_id,
                    ':',
                    s.problem_id
                )
            )
            FROM solution s
            INNER JOIN course_contest cc
                ON cc.contest_id = s.contest_id
               AND cc.status = 1
            WHERE cc.course_id = cs.course_id
              AND s.user_id = cs.user_id
              AND s.result = 4
        ) AS course_solved_count,

        (
            SELECT MAX(s.in_date)

            FROM solution s

            INNER JOIN course_contest cc
                ON cc.contest_id = s.contest_id
               AND cc.status = 1

            WHERE cc.course_id = cs.course_id
              AND s.user_id = cs.user_id
        ) AS course_last_activity

     FROM course_student cs

     LEFT JOIN users u
       ON u.user_id = cs.user_id

     WHERE cs.course_id = ?

     ORDER BY
        cs.status DESC,
        CASE
            WHEN cs.student_no IS NULL
              OR cs.student_no = ''
            THEN 1
            ELSE 0
        END,
        cs.student_no,
        cs.user_id",
    $course_id
);

if (!is_array($view_students)) {
    $view_students = array();
}


// ============================================================
// 8. 현재 / 종료 학생 수
// ============================================================

$view_active_student_count = 0;
$view_inactive_student_count = 0;

foreach ($view_students as $student) {

    if (intval($student['status']) === 1) {
        $view_active_student_count++;
    }
    else {
        $view_inactive_student_count++;
    }
}

// ============================================================
// 9. 화면 출력
// ============================================================

// 페이지 전체에서 사용할 CSRF 필드 1개 생성
ob_start();
include("./csrf.php");
$view_csrf_input = ob_get_clean();

require("template/".$OJ_TEMPLATE."/course_students.php");