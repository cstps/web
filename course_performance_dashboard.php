<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title =
    '수행평가 현황';

// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        '<h2>로그인이 필요합니다.</h2>';

    require(
        'template/'.
        $OJ_TEMPLATE.
        '/error.php'
    );

    exit(0);
}

// ============================================================
// 2. Course 번호 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

if ($course_id <= 0) {

    $view_errors =
        '<h2>잘못된 수업 번호입니다.</h2>';

    require(
        'template/'.
        $OJ_TEMPLATE.
        '/error.php'
    );

    exit(0);
}

// ============================================================
// 3. 교사용 권한 확인
//
// 허용:
// - administrator
// - Course owner
// - Course teacher
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        '<h2>수행평가 현황을 볼 권한이 없습니다.</h2>';

    require(
        'template/'.
        $OJ_TEMPLATE.
        '/error.php'
    );

    exit(0);
}

// ============================================================
// 4. Course 정보
// ============================================================

$course_rows =
    pdo_query(
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

    $view_errors =
        '<h2>수업을 찾을 수 없습니다.</h2>';

    require(
        'template/'.
        $OJ_TEMPLATE.
        '/error.php'
    );

    exit(0);
}

$view_course =
    $course_rows[0];

// ============================================================
// 5. Course 활성 차시
// ============================================================

$view_contests =
    pdo_query(
        "SELECT
            cc.id,
            cc.contest_id,
            cc.lesson_no,
            cc.sort_order,
            cc.visible,

            c.title,
            c.start_time,
            c.end_time,
            c.defunct

         FROM course_contest cc

         LEFT JOIN contest c
           ON c.contest_id = cc.contest_id

         WHERE cc.course_id = ?
           AND cc.status = 1

         ORDER BY
            cc.lesson_no,
            cc.sort_order,
            cc.contest_id",
        $course_id
    );

if (!is_array($view_contests)) {
    $view_contests = array();
}

// ============================================================
// 6. 현재 선택 차시
// ============================================================

$selected_contest_id =
    isset($_GET['contest_id'])
        ? intval($_GET['contest_id'])
        : 0;

$view_selected_contest =
    null;

// URL로 받은 Contest가 현재 Course에 속하는지 확인
foreach ($view_contests as $contest) {

    $contest_id =
        intval($contest['contest_id']);

    if (
        $selected_contest_id > 0 &&
        $contest_id === $selected_contest_id
    ) {

        $view_selected_contest =
            $contest;

        break;
    }
}

// 선택값이 없거나 잘못되었으면 첫 활성 차시
if (
    $view_selected_contest === null &&
    count($view_contests) > 0
) {

    $view_selected_contest =
        $view_contests[0];

    $selected_contest_id =
        intval(
            $view_selected_contest['contest_id']
        );
}

// ============================================================
// 7. Course 활성 학생
// ============================================================

$course_students =
    array();

$student_rows =
    pdo_query(
        "SELECT
            cs.user_id,
            u.nick

         FROM course_student cs

         LEFT JOIN users u
           ON u.user_id = cs.user_id

         WHERE cs.course_id = ?
           AND cs.status = 1

         ORDER BY cs.user_id ASC",
        $course_id
    );

if ($student_rows) {

    foreach ($student_rows as $row) {

        $user_id =
            trim(
                (string)$row['user_id']
            );

        if ($user_id === '') {
            continue;
        }

        $course_students[$user_id] =
            array(
                'user_id' =>
                    $user_id,

                'nick' =>
                    isset($row['nick'])
                        ? (string)$row['nick']
                        : $user_id
            );
    }
}

// ============================================================
// 8. 선택한 Contest의 기존 공통 현황 데이터 재사용
// ============================================================

$contest_problems =
    array();

$student_matrix =
    array();

$teacher_note_count_map =
    array();

$attention_student_count =
    0;

$total_student_count =
    count($course_students);

$problem_class_summary =
    array();

if ($selected_contest_id > 0) {

    $cid =
        $selected_contest_id;

    require(
        './include/contest_process_data.inc.php'
    );

    // ========================================================
    // 9. Course 활성 학생으로 최종 제한
    //
    // contest_process 공통 데이터에는
    // c{cid} 참가자 및 실제 제출자가 포함될 수 있으므로
    // 수행평가 현황에서는 현재 Course 활성 학생만 남긴다.
    //
    // 동시에 한 번도 제출하지 않은 Course 학생도
    // 빈 현황 행으로 생성한다.
    // ========================================================

    $course_student_matrix =
        array();

    foreach (
        $course_students
        as
        $user_id => $student
    ) {

        if (isset($student_matrix[$user_id])) {

            $course_student_matrix[$user_id] =
                $student_matrix[$user_id];

            // Course의 현재 닉네임을 우선 사용
            $course_student_matrix[$user_id]['nick'] =
                $student['nick'];

        } else {

            $course_student_matrix[$user_id] =
                array(
                    'user_id' =>
                        $user_id,

                    'nick' =>
                        $student['nick'],

                    'problems' =>
                        array(),

                    'total_submit' =>
                        0,

                    'total_ai' =>
                        0,

                    'solved_count' =>
                        0
                );
        }
    }

    ksort(
        $course_student_matrix
    );

    $student_matrix =
        $course_student_matrix;

    $total_student_count =
        count($student_matrix);
}

// ============================================================
// 10. Template
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/course_performance_dashboard.php"
);

?>
