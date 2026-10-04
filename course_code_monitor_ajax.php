<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/course_functions.inc.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');


// ============================================================
// JSON 응답
// ============================================================

function course_code_monitor_json(
    $ok,
    $message,
    $status_code = 200,
    $extra = array()
) {

    http_response_code($status_code);

    echo json_encode(
        array_merge(
            array(
                'ok' => (bool)$ok,
                'message' => (string)$message
            ),
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    course_code_monitor_json(
        false,
        '로그인이 필요합니다.',
        401
    );
}


// ============================================================
// 2. Course 번호 확인
// ============================================================

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;


if ($course_id <= 0) {

    course_code_monitor_json(
        false,
        '잘못된 수업 번호입니다.',
        400
    );
}


// ============================================================
// 3. 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    course_code_monitor_json(
        false,
        '학생 코드를 모니터링할 권한이 없습니다.',
        403
    );
}


// ============================================================
// 4. Course 학생 목록
// ============================================================

$students =
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

        $students[$user_id] =
            array(
                'user_id' => $user_id,
                'nick' => isset($row['nick'])
                    ? (string)$row['nick']
                    : '',
                'current' => null
            );
    }
}


// ============================================================
// 5. 학생별 가장 최근 draft
// ============================================================

$draft_rows =
    pdo_query(
        "SELECT
            d.id,
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

        if (
            $user_id === '' ||
            !isset($students[$user_id])
        ) {
            continue;
        }

        if (
            $students[$user_id]['current']
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


        $students[$user_id]['current'] =
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
// 6. JSON용 배열 변환
// ============================================================

// ============================================================
// 6. 학생 표시 순서 정렬
//
// 작성 중 학생을 위에 두고,
// 같은 상태에서는 최근 저장 학생을 우선한다.
// ============================================================

uasort(
    $students,
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
// 7. JSON용 배열 변환
// ============================================================

$result =
    array();

foreach ($students as $student) {

    $result[] =
        array(
            'user_id' =>
                $student['user_id'],

            'nick' =>
                $student['nick'],

            'current' =>
                $student['current']
        );
}


// ============================================================
// 7. 응답
// ============================================================

course_code_monitor_json(
    true,
    '',
    200,
    array(
        'course_id' => $course_id,
        'server_time' => date('Y-m-d H:i:s'),
        'students' => $result
    )
);
