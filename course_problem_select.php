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

$can_view_all_problems = (
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator']) ||
    isset($_SESSION[$OJ_NAME.'_problem_editor'])
);


$sql =
    "SELECT
        p.problem_id,
        p.title,
        p.source,
        p.defunct,
        p.accepted,
        p.submit
     FROM problem p";

$params = array();


if ($can_view_all_problems) {

    $sql .= " WHERE 1 = 1";

} else {

    /*
     * 일반 사용자는 공개 문제 또는 자신이 생성한 문제만 선택할 수 있다.
     */
    $sql .=
        " WHERE
        (
            p.defunct = 'N'

            OR EXISTS
            (
                SELECT 1
                FROM privilege pv
                WHERE pv.user_id = ?
                  AND pv.rightstr =
                      CONCAT('p', p.problem_id)
                  AND pv.defunct = 'N'
            )
        )";

    $params[] = $user_id;
}


if ($view_search !== '') {

    $search_like =
        "%".$view_search."%";

    if (ctype_digit($view_search)) {

        $sql .=
            " AND
            (
                p.problem_id = ?
                OR p.title LIKE ?
                OR p.source LIKE ?
            )";

        $params[] = intval($view_search);
        $params[] = $search_like;
        $params[] = $search_like;

    } else {

        $sql .=
            " AND
            (
                p.title LIKE ?
                OR p.source LIKE ?
            )";

        $params[] = $search_like;
        $params[] = $search_like;
    }
}


$sql .=
    " ORDER BY p.problem_id DESC
      LIMIT 50";


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