<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "문제 직접 선택";


if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id = $_SESSION[$OJ_NAME.'_user_id'];

$course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;

$lesson_no =
    isset($_GET['lesson_no'])
        ? intval($_GET['lesson_no'])
        : 0;


if ($course_id <= 0 || $lesson_no <= 0) {

    $view_errors =
        "<h2>수업 번호 또는 차시 번호가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
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


if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 차시를 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$duplicate_rows = pdo_query(
    "SELECT contest_id
     FROM course_contest
     WHERE course_id = ?
       AND lesson_no = ?
     LIMIT 1",
    $course_id,
    $lesson_no
);


if (
    $duplicate_rows &&
    isset($duplicate_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>".$lesson_no."차시는 이미 등록되어 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$view_search =
    isset($_GET['search'])
        ? trim($_GET['search'])
        : '';

$selected_ids_raw =
    isset($_GET['selected_ids'])
        ? trim($_GET['selected_ids'])
        : '';

$view_selected_problem_ids = array();


if ($selected_ids_raw !== '') {

    $selected_parts = preg_split(
        '/[\s,]+/',
        $selected_ids_raw
    );

    foreach ($selected_parts as $problem_id) {

        $problem_id = intval($problem_id);

        if ($problem_id > 0) {
            $view_selected_problem_ids[$problem_id] = true;
        }
    }
}

$view_selected_ids_text =
    implode(
        ',',
        array_keys($view_selected_problem_ids)
    );

$is_admin =
    isset(
        $_SESSION[$OJ_NAME . '_administrator']
    );


// ============================================================
// 내가 생성한 문제 ID
//
// problem 전체 행마다 EXISTS를 실행하지 않고
// 현재 사용자의 문제 권한을 먼저 한 번 조회한다.
// ============================================================

$owned_problem_ids =
    array();


if (!$is_admin) {

    $owned_rows =
        pdo_query(
            "SELECT rightstr
         FROM privilege
         WHERE user_id = ?
           AND defunct = 'N'
           AND rightstr LIKE 'p%'",
            $user_id
        );


    if (is_array($owned_rows)) {

        foreach ($owned_rows as $owned_row) {

            $rightstr =
                isset($owned_row['rightstr'])
                ? trim($owned_row['rightstr'])
                : '';


            if (
                preg_match(
                    '/^p([0-9]+)$/',
                    $rightstr,
                    $matches
                )
            ) {

                $problem_id =
                    intval($matches[1]);


                if ($problem_id > 0) {

                    $owned_problem_ids[$problem_id] = true;
                }
            }
        }
    }
}


$params =
    array();


$sql =
    "SELECT
        p.problem_id,
        p.title,
        p.source,
        p.defunct,
        p.accepted,
        p.submit,
        p.allow_reuse

     FROM problem p

     WHERE ";

$permission_sql =
    "";


if ($is_admin) {

    $permission_sql =
        "1 = 1";
} elseif (
    !empty($owned_problem_ids)
) {

    $owned_placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($owned_problem_ids),
                '?'
            )
        );


    $permission_sql =
        "(
            (
                p.defunct = 'N'
                AND p.allow_reuse = 1
            )

            OR

            p.problem_id IN (
                " . $owned_placeholders . "
            )
        )";


    foreach (
        array_keys($owned_problem_ids)
        as $owned_problem_id
    ) {

        $params[] =
            intval($owned_problem_id);
    }
} else {

    $permission_sql =
        "(
            p.defunct = 'N'
            AND p.allow_reuse = 1
        )";
}


$sql .=
    $permission_sql;


if ($view_search !== '') {

    if (ctype_digit($view_search)) {

        // 숫자만 입력하면 문제번호 정확검색
        // problem_id는 PK이므로 가장 빠르게 조회 가능
        $sql .=
            " AND p.problem_id = ?";


        $params[] =
            intval($view_search);
    } else {

        $search_like =
            "%" . $view_search . "%";


        $sql .=
            " AND
                (
                    p.title LIKE ?
                    OR p.source LIKE ?
                )";


        $params[] =
            $search_like;

        $params[] =
            $search_like;
    }
}

if ($view_search === '') {

    // 검색어가 없을 때는 최근 문제만 빠르게 표시
    $sql .=
        " ORDER BY p.problem_id DESC
          LIMIT 50";
} else {

    // 실제 검색 시에는 검색 결과 범위를 확대
    $sql .=
        " ORDER BY p.problem_id DESC
          LIMIT 300";
}


$view_problem_rows =
    pdo_query(
        $sql,
        ...$params
    );

if (!is_array($view_problem_rows)) {
    $view_problem_rows = array();
}


require(
    "template/".
    $OJ_TEMPLATE.
    "/course_problem_select.php"
);