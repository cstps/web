<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = '수업 코드 모니터링';


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
//
// assistant는 현재 코드 모니터링에서 제외한다.
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        '<h2>학생 코드를 모니터링할 권한이 없습니다.</h2>';

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


$course =
    $course_rows[0];


// ============================================================
// 5. Course 활성 학생
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
                        : '',

                'current' =>
                    null
            );
    }
}


// ============================================================
// 6. Course에 연결된 Contest에서 작성 중인 draft 조회
//
// 최신순으로 가져온 뒤 학생마다 첫 행만 사용한다.
// 따라서 학생의 "현재 작성 위치"가 된다.
// ============================================================

$draft_rows =
    pdo_query(
        "SELECT
            d.user_id,
            d.problem_id,
            d.contest_id,
            d.language,
            d.updated_at,

            TIMESTAMPDIFF(
                SECOND,
                d.updated_at,
                NOW()
            ) AS age_seconds,

            cc.lesson_no,
            cc.sort_order,

            c.title AS contest_title,

            cp.num AS problem_num,
            p.title AS problem_title

         FROM student_code_draft d

         INNER JOIN course_contest cc
                 ON cc.contest_id = d.contest_id
                AND cc.course_id = ?
                AND cc.status = 1

         INNER JOIN contest c
                 ON c.contest_id = d.contest_id

         INNER JOIN contest_problem cp
                 ON cp.contest_id = d.contest_id
                AND cp.problem_id = d.problem_id

         INNER JOIN problem p
                 ON p.problem_id = d.problem_id

         ORDER BY
            d.user_id ASC,
            d.updated_at DESC,
            d.id DESC",
        $course_id
    );


if ($draft_rows) {

    foreach ($draft_rows as $row) {

        $user_id =
            trim(
                (string)$row['user_id']
            );


        // 현재 Course의 활성 학생만 표시한다.
        if (
            $user_id === '' ||
            !isset($course_students[$user_id])
        ) {
            continue;
        }


        // 이미 더 최신 draft를 찾았으면 건너뛴다.
        if (
            $course_students[$user_id]['current']
            !== null
        ) {
            continue;
        }


        $problem_num =
            intval(
                $row['problem_num']
            );


        $language_id =
            intval(
                $row['language']
            );


        $course_students[$user_id]['current'] =
            array(
                'contest_id' =>
                    intval($row['contest_id']),

                'contest_title' =>
                    (string)$row['contest_title'],

                'lesson_no' =>
                    intval($row['lesson_no']),

                'problem_id' =>
                    intval($row['problem_id']),

                'problem_num' =>
                    $problem_num,

                'problem_label' =>
                    chr(
                        ord('A') +
                        $problem_num
                    ),

                'problem_title' =>
                    (string)$row['problem_title'],

                'language' =>
                    $language_id,

                'language_name' =>
                    isset(
                        $language_name[
                            $language_id
                        ]
                    )
                        ? (string)$language_name[
                            $language_id
                        ]
                        : 'Language '.$language_id,

                'updated_at' =>
                    (string)$row['updated_at'],

                // 마지막 자동저장 후 2분 이내만
                // 현재 작성 중으로 판단한다.
                'active' =>
                    intval($row['age_seconds']) <= 120
            );
    }
}


// ============================================================
// 7. 모니터링 요약
// ============================================================

$monitor_total_count =
    count($course_students);

$monitor_active_count =
    0;

foreach ($course_students as $student) {

    if (
        isset($student['current']) &&
        $student['current'] &&
        !empty($student['current']['active'])
    ) {
        $monitor_active_count++;
    }
}

$monitor_wait_count =
    $monitor_total_count
    -
    $monitor_active_count;


// ============================================================
// 차시별 작성 중 인원
// ============================================================

$monitor_lesson_counts =
    array();

foreach ($course_students as $student) {

    if (
        !isset($student['current']) ||
        !$student['current'] ||
        empty($student['current']['active'])
    ) {
        continue;
    }

    $lesson_no =
        intval(
            $student['current']['lesson_no']
        );

    if ($lesson_no <= 0) {
        continue;
    }

    if (!isset($monitor_lesson_counts[$lesson_no])) {
        $monitor_lesson_counts[$lesson_no] = 0;
    }

    $monitor_lesson_counts[$lesson_no]++;
}

ksort(
    $monitor_lesson_counts,
    SORT_NUMERIC
);


// ============================================================
// 8. 학생 표시 순서 정렬
//
// 1순위: 현재 작성 중
// 2순위: 마지막 저장시각이 최근인 학생
// 3순위: 학생 아이디
// ============================================================

uasort(
    $course_students,
    function ($a, $b) {

        $a_current =
            isset($a['current'])
                ? $a['current']
                : null;

        $b_current =
            isset($b['current'])
                ? $b['current']
                : null;


        $a_active =
            $a_current &&
            !empty($a_current['active'])
                ? 1
                : 0;

        $b_active =
            $b_current &&
            !empty($b_current['active'])
                ? 1
                : 0;


        if ($a_active !== $b_active) {

            return
                $b_active
                -
                $a_active;
        }


        $a_time =
            $a_current
                ? (string)$a_current['updated_at']
                : '';

        $b_time =
            $b_current
                ? (string)$b_current['updated_at']
                : '';


        if ($a_time !== $b_time) {

            return strcmp(
                $b_time,
                $a_time
            );
        }


        return strcmp(
            (string)$a['user_id'],
            (string)$b['user_id']
        );
    }
);


// ============================================================
// 9. Template
// ============================================================

require(
    'template/'.
    $OJ_TEMPLATE.
    '/course_code_monitor.php'
);
