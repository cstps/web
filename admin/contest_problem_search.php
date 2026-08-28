<?php

require_once("../include/db_info.inc.php");

header(
    "Content-Type: application/json; charset=utf-8"
);


if (
    !isset(
        $_SESSION[$OJ_NAME . '_user_id']
    )
) {

    http_response_code(401);

    echo json_encode(
        array(
            'success' => false,
            'message' => '로그인이 필요합니다.'
        )
    );

    exit;
}


if (
    !isset(
        $_SESSION[$OJ_NAME . '_administrator']
    ) &&
    !isset(
        $_SESSION[$OJ_NAME . '_contest_creator']
    )
) {

    http_response_code(403);

    echo json_encode(
        array(
            'success' => false,
            'message' => '문제를 선택할 권한이 없습니다.'
        )
    );

    exit;
}


$user_id =
    $_SESSION[$OJ_NAME . '_user_id'];


$is_admin =
    isset(
        $_SESSION[$OJ_NAME . '_administrator']
    );


$search =
    isset($_GET['search'])
    ? trim($_GET['search'])
    : '';


$scope =
    isset($_GET['scope'])
    ? trim($_GET['scope'])
    : 'my';

// ============================================================
// 내가 생성한 문제 ID
//
// scope=my
// → 관리자라도 자신의 문제만 조회해야 하므로 필요
//
// scope=available
// → 관리자가 아닌 경우 자신의 비공개/재사용 제한 문제도
//   사용 가능 목록에 포함해야 하므로 필요
// ============================================================

$owned_problem_ids =
    array();


$need_owned_problem_ids =
    (
        $scope === 'my'
        ||
        !$is_admin
    );


if ($need_owned_problem_ids) {

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


$where =
    array();

$params =
    array();


// ============================================================
// 조회 범위
// ============================================================

if ($scope === 'my') {

    // --------------------------------------------------------
    // 내가 만든 문제
    // --------------------------------------------------------

    if (empty($owned_problem_ids)) {

        // 소유 문제가 없으면 결과 없음
        $where[] =
            "1 = 0";
    } else {

        $owned_placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($owned_problem_ids),
                    '?'
                )
            );


        $where[] =
            "p.problem_id IN (" .
            $owned_placeholders .
            ")";


        foreach (
            array_keys($owned_problem_ids)
            as $owned_problem_id
        ) {

            $params[] =
                intval($owned_problem_id);
        }
    }
} elseif (!$is_admin) {

    // --------------------------------------------------------
    // 사용 가능한 전체 문제
    //
    // 자신의 문제
    // → 공개/비공개, allow_reuse 관계없이 사용 가능
    //
    // 다른 사용자의 문제
    // → 공개 + allow_reuse=1
    // --------------------------------------------------------

    if (!empty($owned_problem_ids)) {

        $owned_placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($owned_problem_ids),
                    '?'
                )
            );


        $where[] =
            "
                (
                    (
                        p.defunct = 'N'
                        AND p.allow_reuse = 1
                    )
    
                    OR
    
                    p.problem_id IN (
                        " . $owned_placeholders . "
                    )
                )
                ";


        foreach (
            array_keys($owned_problem_ids)
            as $owned_problem_id
        ) {

            $params[] =
                intval($owned_problem_id);
        }
    } else {

        $where[] =
            "
                (
                    p.defunct = 'N'
                    AND p.allow_reuse = 1
                )
                ";
    }
}


// ============================================================
// 검색 조건
// ============================================================

if ($search !== '') {

    $search_like =
        '%' . $search . '%';


    if (ctype_digit($search)) {

        // 숫자만 입력하면 문제번호 정확검색
        // problem_id PRIMARY KEY 활용
        $where[] =
            "p.problem_id = ?";


        $params[] =
            intval($search);
    } else {

        $where[] =
            "
            (
                p.title LIKE ?
                OR p.source LIKE ?
            )
            ";

        $params[] =
            $search_like;

        $params[] =
            $search_like;
    }
}


$where_sql =
    count($where) > 0
    ? " WHERE " .
    implode(
        " AND ",
        $where
    )
    : "";


$result_limit =
    ($search === '')
    ? 50
    : 300;

$sql =
    "
    SELECT
        p.problem_id,
        p.title,
        p.source,
        p.defunct,
        p.accepted,
        p.submit,
        p.allow_reuse

    FROM problem p

    $where_sql

    ORDER BY
        p.problem_id DESC

    LIMIT " . $result_limit;


$rows =
    pdo_query(
        $sql,
        ...$params
    );


if (!is_array($rows)) {
    $rows = array();
}


$result =
    array();


foreach ($rows as $row) {

    $result[] =
        array(

            'problem_id' =>
            intval(
                $row['problem_id']
            ),

            'title' =>
            (string)$row['title'],

            'source' =>
            (string)$row['source'],

            'defunct' =>
            (string)$row['defunct'],

            'accepted' =>
            intval(
                $row['accepted']
            ),

            'submit' =>
            intval(
                $row['submit']
            ),

            'allow_reuse' =>
            intval(
                $row['allow_reuse']
            )
        );
}


echo json_encode(
    array(
        'success' => true,
        'problems' => $result
    ),
    JSON_UNESCAPED_UNICODE
);
