<?php

require_once(
    __DIR__ .
    '/admin-init.php'
);


header(
    'Cache-Control: private, no-store'
);

header(
    'Content-Type: application/json; charset=utf-8'
);


$fail = function (
    $status,
    $message
) {

    http_response_code(
        intval($status)
    );

    echo json_encode(
        array(
            'success' => false,
            'message' => $message
        ),
        JSON_UNESCAPED_UNICODE |
            JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
};


// ============================================================
// 검색 대상 대회와 접근 권한
//
// 생성 화면:
// 전역 대회 생성 권한 필요
//
// 수정 화면:
// 전역 권한 또는 해당 대회 관리 권한 필요
// ============================================================

$contest_id =
    0;


if (
    array_key_exists(
        'cid',
        $_GET
    )
) {
    if (
        !is_scalar(
            $_GET['cid']
        )
    ) {
        $fail(
            422,
            '대회 번호 형식이 올바르지 않습니다.'
        );
    }


    $contest_id_raw =
        trim(
            (string)$_GET['cid']
        );


    if (
        preg_match(
            '/^[1-9][0-9]*$/D',
            $contest_id_raw
        ) !== 1 ||
        strlen($contest_id_raw) > 10 ||
        (
            strlen($contest_id_raw) === 10 &&
            strcmp(
                $contest_id_raw,
                '2147483647'
            ) > 0
        )
    ) {
        $fail(
            422,
            '대회 번호 형식이 올바르지 않습니다.'
        );
    }


    $contest_id =
        intval(
            $contest_id_raw
        );
}


$can_search_problems =
    oj_can_manage_admin_contests();


if (
    !$can_search_problems &&
    $contest_id > 0
) {
    $can_search_problems =
        oj_can_manage_contest(
            $contest_id
        );
}


if (!$can_search_problems) {
    $fail(
        403,
        '문제를 선택할 권한이 없습니다.'
    );
}

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'GET'
) {

    header('Allow: GET');

    $fail(
        405,
        'GET 요청만 허용됩니다.'
    );
}


// ============================================================
// GET 문자열 입력
// ============================================================

$get_string = function (
    $name,
    $default = ''
) use ($fail) {

    if (!isset($_GET[$name])) {
        return $default;
    }

    if (!is_scalar($_GET[$name])) {

        $fail(
            422,
            '검색 조건의 형식이 올바르지 않습니다: ' .
                $name
        );
    }

    return trim(
        (string)$_GET[$name]
    );
};


$search =
    $get_string(
        'search',
        ''
    );

$scope =
    $get_string(
        'scope',
        'my'
    );


if (
    !in_array(
        $scope,
        array(
            'my',
            'available'
        ),
        true
    )
) {

    $fail(
        422,
        '검색 범위가 올바르지 않습니다.'
    );
}


if (
    preg_match(
        '//u',
        $search
    ) !== 1
) {
    $fail(
        422,
        '검색어의 문자 인코딩이 올바르지 않습니다.'
    );
}


if (
    strpos(
        $search,
        "\0"
    ) !== false
) {
    $fail(
        422,
        '검색어에 사용할 수 없는 문자가 포함되어 있습니다.'
    );
}


if (strlen($search) > 300) {

    $fail(
        422,
        '검색어가 너무 깁니다.'
    );
}


$user_id =
    (string)$_SESSION[$OJ_NAME .
        '_user_id'];

$is_admin =
    oj_is_admin();


// ============================================================
// 조회 조건
// ============================================================

$where =
    array();

$params =
    array();


$owned_problem_condition =
    "
    EXISTS (
        SELECT 1
        FROM privilege AS pr
        WHERE pr.user_id = ?
          AND pr.defunct = 'N'
          AND pr.rightstr =
              CONCAT(
                  'p',
                  p.problem_id
              )
    )
    ";


if ($scope === 'my') {

    $where[] =
        $owned_problem_condition;

    $params[] =
        $user_id;
} elseif (!$is_admin) {

    $where[] =
        "
        (
            (
                p.defunct = 'N'
                AND p.allow_reuse = 1
            )

            OR

            " .
        $owned_problem_condition .
        "
        )
        ";

    $params[] =
        $user_id;
}


// ============================================================
// 검색어
// ============================================================

if ($search !== '') {

    if (ctype_digit($search)) {

        $problem_id =
            intval($search);

        if ($problem_id <= 0) {
            $where[] = '1 = 0';
        } else {
            $where[] =
                'p.problem_id = ?';

            $params[] =
                $problem_id;
        }
    } else {

        $search_like =
            '%' .
            $search .
            '%';

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
    empty($where)
    ? ''
    : ' WHERE ' .
    implode(
        ' AND ',
        $where
    );


$result_limit =
    $search === ''
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

    FROM problem AS p

    " .
    $where_sql .
    "

    ORDER BY
        p.problem_id DESC

    LIMIT " .
    $result_limit;


$query_arguments =
    array_merge(
        array($sql),
        $params
    );

$rows =
    call_user_func_array(
        'pdo_query',
        $query_arguments
    );


if (!is_array($rows)) {

    $fail(
        500,
        '문제 목록을 불러오지 못했습니다.'
    );
}


// ============================================================
// JSON 결과
// ============================================================

$problems =
    array();


foreach ($rows as $row) {

    $problems[] =
        array(
            'problem_id' =>
            intval($row['problem_id']),

            'title' =>
            isset($row['title'])
                ? (string)$row['title']
                : '',

            'source' =>
            isset($row['source'])
                ? (string)$row['source']
                : '',

            'defunct' =>
            isset($row['defunct'])
                ? (string)$row['defunct']
                : '',

            'accepted' =>
            intval($row['accepted']),

            'submit' =>
            intval($row['submit']),

            'allow_reuse' =>
            intval($row['allow_reuse'])
        );
}


$json =
    json_encode(
        array(
            'success' => true,
            'problems' => $problems
        ),
        JSON_UNESCAPED_UNICODE |
            JSON_INVALID_UTF8_SUBSTITUTE
    );


if ($json === false) {

    $fail(
        500,
        '문제 목록을 변환하지 못했습니다.'
    );
}


echo $json;
