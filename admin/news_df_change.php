<?php

require_once(
    __DIR__ . "/admin-init.php"
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
    exit("공지사항을 관리할 권한이 없습니다.");
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
    ? (int)$_POST["return_page"]
    : 1;

$return_keyword =
    isset($_POST["return_keyword"])
    ? trim((string)$_POST["return_keyword"])
    : "";

if ($news_id <= 0) {
    http_response_code(400);
    exit("공지사항 번호가 올바르지 않습니다.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

$news_rows =
    pdo_query(
        "
        SELECT news_id, defunct
        FROM news
        WHERE news_id = ?
        LIMIT 1
        ",
        $news_id
    );

if ($news_rows === false) {
    http_response_code(500);
    exit("공지사항 상태를 확인할 수 없습니다.");
}

if (!isset($news_rows[0])) {
    http_response_code(404);
    exit("공지사항을 찾을 수 없습니다.");
}

$current_defunct =
    (string)$news_rows[0]["defunct"];

$new_defunct =
    $current_defunct === "Y"
    ? "N"
    : "Y";

$update_result =
    pdo_query(
        "
        UPDATE news
        SET defunct = ?
        WHERE news_id = ?
          AND defunct = ?
        ",
        $new_defunct,
        $news_id,
        $current_defunct
    );

if ($update_result === false) {
    http_response_code(500);
    exit("공지사항 공개 상태를 변경할 수 없습니다.");
}

$return_query =
    array(
        "updated" => "1",
        "page" => (string)$return_page
    );

if ($return_keyword !== "") {
    $return_query["keyword"] =
        $return_keyword;
}

header(
    "Location: news_list.php?" .
    http_build_query($return_query),
    true,
    303
);

exit;
