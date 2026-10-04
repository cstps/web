<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/setlang.php');
require_once('./include/permission_functions.inc.php');
require_once('./include/course_functions.inc.php');

$view_title = '학생 코드 모니터링';


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
// 2. Contest 번호 확인
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;


if ($cid <= 0) {

    $view_errors =
        '<h2>잘못된 대회 번호입니다.</h2>';

    require(
        'template/'.
        $OJ_TEMPLATE.
        '/error.php'
    );

    exit(0);
}


// ============================================================
// 3. 교사용 열람 권한 확인
//
// administrator
// 해당 Contest의 m{cid}
// 연결된 Course의 owner / teacher
// ============================================================

if (!oj_can_view_contest_process($cid)) {

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
// 4. Contest 정보
// ============================================================

$contest_rows =
    pdo_query(
        "SELECT
            contest_id,
            title
         FROM contest
         WHERE contest_id = ?
         LIMIT 1",
        $cid
    );


if (
    !$contest_rows ||
    !isset($contest_rows[0]['contest_id'])
) {

    $view_errors =
        '<h2>대회를 찾을 수 없습니다.</h2>';

    require(
        'template/'.
        $OJ_TEMPLATE.
        '/error.php'
    );

    exit(0);
}


$contest_title =
    (string)$contest_rows[0]['title'];


// ============================================================
// 5. 연결 Course 확인
// ============================================================

$course_id = 0;

$course_rows =
    pdo_query(
        "SELECT
            course_id
         FROM course_contest
         WHERE contest_id = ?
           AND status = 1
         LIMIT 1",
        $cid
    );


if (
    $course_rows &&
    isset($course_rows[0]['course_id'])
) {

    $course_id =
        intval(
            $course_rows[0]['course_id']
        );
}


// ============================================================
// 6. Contest 문제 목록
// ============================================================

$contest_problems =
    array();

$problem_rows =
    pdo_query(
        "SELECT
            problem_id,
            num,
            title
         FROM contest_problem
         WHERE contest_id = ?
         ORDER BY num ASC",
        $cid
    );


if ($problem_rows) {

    foreach ($problem_rows as $row) {

        $problem_id =
            intval($row['problem_id']);

        $problem_num =
            intval($row['num']);

        $contest_problems[$problem_id] =
            array(
                'problem_id' => $problem_id,
                'num' => $problem_num,
                'label' => chr(ord('A') + $problem_num),
                'title' => (string)$row['title']
            );
    }
}


// ============================================================
// 7. 모니터링 대상 학생 목록
//
// 우선순위:
// - 연결 Course의 활성 학생
// - c{cid} Contest 참가자
// - 실제 제출 학생
// - draft가 존재하는 학생
//
// 중복 user_id는 한 명으로 합친다.
// ============================================================

$monitor_students =
    array();


// ------------------------------------------------------------
// 7-1. Course 활성 학생
// ------------------------------------------------------------

if ($course_id > 0) {

    $rows =
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


    if ($rows) {

        foreach ($rows as $row) {

            $uid =
                trim(
                    (string)$row['user_id']
                );

            if ($uid === '') {
                continue;
            }

            $monitor_students[$uid] =
                array(
                    'user_id' => $uid,
                    'nick' => isset($row['nick'])
                        ? (string)$row['nick']
                        : ''
                );
        }
    }
}


// ------------------------------------------------------------
// 7-2. c{cid} Contest 참가자
// ------------------------------------------------------------

$rows =
    pdo_query(
        "SELECT
            p.user_id,
            u.nick
         FROM privilege p
         LEFT JOIN users u
                ON u.user_id = p.user_id
         WHERE p.rightstr = ?
         ORDER BY p.user_id ASC",
        'c'.$cid
    );


if ($rows) {

    foreach ($rows as $row) {

        $uid =
            trim(
                (string)$row['user_id']
            );

        if ($uid === '') {
            continue;
        }

        if (!isset($monitor_students[$uid])) {

            $monitor_students[$uid] =
                array(
                    'user_id' => $uid,
                    'nick' => isset($row['nick'])
                        ? (string)$row['nick']
                        : ''
                );
        }
    }
}


// ------------------------------------------------------------
// 7-3. 실제 Contest 제출 학생
// ------------------------------------------------------------

$rows =
    pdo_query(
        "SELECT DISTINCT
            s.user_id,
            u.nick
         FROM solution s
         LEFT JOIN users u
                ON u.user_id = s.user_id
         WHERE s.contest_id = ?
           AND s.problem_id > 0
         ORDER BY s.user_id ASC",
        $cid
    );


if ($rows) {

    foreach ($rows as $row) {

        $uid =
            trim(
                (string)$row['user_id']
            );

        if ($uid === '') {
            continue;
        }

        if (!isset($monitor_students[$uid])) {

            $monitor_students[$uid] =
                array(
                    'user_id' => $uid,
                    'nick' => isset($row['nick'])
                        ? (string)$row['nick']
                        : ''
                );
        }
    }
}


// ------------------------------------------------------------
// 7-4. 현재 draft가 존재하는 학생
// ------------------------------------------------------------

$rows =
    pdo_query(
        "SELECT DISTINCT
            d.user_id,
            u.nick
         FROM student_code_draft d
         LEFT JOIN users u
                ON u.user_id = d.user_id
         WHERE d.contest_id = ?
         ORDER BY d.user_id ASC",
        $cid
    );


if ($rows) {

    foreach ($rows as $row) {

        $uid =
            trim(
                (string)$row['user_id']
            );

        if ($uid === '') {
            continue;
        }

        if (!isset($monitor_students[$uid])) {

            $monitor_students[$uid] =
                array(
                    'user_id' => $uid,
                    'nick' => isset($row['nick'])
                        ? (string)$row['nick']
                        : ''
                );
        }
    }
}


ksort(
    $monitor_students,
    SORT_NATURAL | SORT_FLAG_CASE
);


// ============================================================
// 8. 학생별 최신 draft 메타정보
//
// 코드 본문은 이 화면에서 조회하지 않는다.
// ============================================================

$draft_map =
    array();

$draft_rows =
    pdo_query(
        "SELECT
            user_id,
            problem_id,
            language,
            updated_at
         FROM student_code_draft
         WHERE contest_id = ?
         ORDER BY user_id ASC,
                  problem_id ASC",
        $cid
    );


if ($draft_rows) {

    foreach ($draft_rows as $row) {

        $uid =
            trim(
                (string)$row['user_id']
            );

        $problem_id =
            intval(
                $row['problem_id']
            );

        if (
            $uid === '' ||
            !isset(
                $contest_problems[$problem_id]
            )
        ) {
            continue;
        }

        if (!isset($draft_map[$uid])) {
            $draft_map[$uid] = array();
        }

        $draft_map[$uid][$problem_id] =
            array(
                'language' =>
                    intval($row['language']),

                'updated_at' =>
                    (string)$row['updated_at']
            );
    }
}


// ============================================================
// 9. Template
// ============================================================

require(
    'template/'.
    $OJ_TEMPLATE.
    '/contest_code_monitor.php'
);
