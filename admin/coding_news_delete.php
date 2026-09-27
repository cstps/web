<?php

require_once(
    __DIR__ . "/admin-init.php"
);

header(
    "Content-Type: text/plain; charset=UTF-8"
);

if (
    !isset($_SERVER["REQUEST_METHOD"]) ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    header("Allow: POST");
    http_response_code(405);
    exit("POST 요청만 허용됩니다.");
}

if (!oj_can_manage_admin_notice()) {
    http_response_code(403);
    exit("IT NEWS를 삭제할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

$news_id =
    isset($_POST["news_id"])
    ? (int)$_POST["news_id"]
    : 0;

$return_page =
    isset($_POST["return_page"])
    ? max(
        1,
        (int)$_POST["return_page"]
    )
    : 1;

$return_keyword =
    isset($_POST["return_keyword"])
    ? trim(
        (string)$_POST["return_keyword"]
    )
    : "";

if ($news_id <= 0) {
    http_response_code(400);
    exit("IT NEWS 번호가 올바르지 않습니다.");
}

$news_rows =
    pdo_query(
        "
        SELECT
            news_id,
            title,
            defunct
        FROM coding_news
        WHERE news_id = ?
        LIMIT 1
        ",
        $news_id
    );

if ($news_rows === false) {
    http_response_code(500);
    exit("IT NEWS를 확인할 수 없습니다.");
}

if (!isset($news_rows[0])) {
    http_response_code(404);
    exit("삭제할 IT NEWS를 찾을 수 없습니다.");
}

if (
    (string)$news_rows[0]["defunct"] !== "Y"
) {
    http_response_code(409);
    exit(
        "공개 중인 IT NEWS는 삭제할 수 없습니다. " .
        "먼저 비공개로 전환해 주세요."
    );
}

$delete_result =
    pdo_query(
        "
        DELETE FROM coding_news
        WHERE news_id = ?
          AND defunct = 'Y'
        ",
        $news_id
    );

if ($delete_result === false) {
    http_response_code(500);
    exit("IT NEWS를 삭제할 수 없습니다.");
}

$remaining_rows =
    pdo_query(
        "
        SELECT news_id
        FROM coding_news
        WHERE news_id = ?
        LIMIT 1
        ",
        $news_id
    );

if ($remaining_rows === false) {
    http_response_code(500);
    exit("IT NEWS 삭제 결과를 확인할 수 없습니다.");
}

if (isset($remaining_rows[0])) {
    http_response_code(500);
    exit("IT NEWS가 삭제되지 않았습니다.");
}

$redirect_params =
    array(
        "deleted" => "1",
        "page" => $return_page
    );

if ($return_keyword !== "") {
    $redirect_params["keyword"] =
        $return_keyword;
}

header(
    "Location: coding_news_list.php?" .
    http_build_query(
        $redirect_params,
        "",
        "&",
        PHP_QUERY_RFC3986
    ),
    true,
    303
);

exit;
