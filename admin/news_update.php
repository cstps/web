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
    exit("공지사항을 수정할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

$news_id =
    isset($_POST["news_id"])
    ? (int)$_POST["news_id"]
    : 0;

$title =
    isset($_POST["title"])
    ? trim(
        strip_tags(
            (string)$_POST["title"]
        )
    )
    : "";

$content =
    isset($_POST["content"])
    ? trim((string)$_POST["content"])
    : "";

if ($news_id <= 0) {
    http_response_code(400);
    exit("공지사항 번호가 올바르지 않습니다.");
}

if ($title === "") {
    http_response_code(422);
    exit("공지사항 제목을 입력해 주세요.");
}

$title_length =
    function_exists("mb_strlen")
    ? mb_strlen(
        $title,
        "UTF-8"
    )
    : strlen($title);

if ($title_length > 200) {
    http_response_code(422);
    exit("공지사항 제목은 200자 이하로 입력해 주세요.");
}

if ($content === "") {
    http_response_code(422);
    exit("공지사항 내용을 입력해 주세요.");
}

if (strlen($content) > 65000) {
    http_response_code(422);
    exit("공지사항 내용이 너무 깁니다.");
}

$existing_rows =
    pdo_query(
        "
        SELECT news_id
        FROM news
        WHERE news_id = ?
        LIMIT 1
        ",
        $news_id
    );

if ($existing_rows === false) {
    http_response_code(500);
    exit("공지사항을 확인할 수 없습니다.");
}

if (!isset($existing_rows[0])) {
    http_response_code(404);
    exit("공지사항을 찾을 수 없습니다.");
}

$title =
    RemoveXSS(
        $title
    );

$content =
    RemoveXSS(
        $content
    );

$user_id =
    isset(
        $_SESSION[
            $OJ_NAME . "_user_id"
        ]
    )
    ? (string)$_SESSION[
        $OJ_NAME . "_user_id"
    ]
    : "";

if ($user_id === "") {
    http_response_code(403);
    exit("관리자 계정을 확인할 수 없습니다.");
}

$update_result =
    pdo_query(
        "
        UPDATE news
        SET
            title = ?,
            content = ?,
            user_id = ?,
            time = NOW()
        WHERE news_id = ?
        ",
        $title,
        $content,
        $user_id,
        $news_id
    );

if ($update_result === false) {
    http_response_code(500);
    exit("공지사항을 수정할 수 없습니다.");
}

header(
    "Location: news_edit.php?id=" .
    rawurlencode(
        (string)$news_id
    ) .
    "&saved=1",
    true,
    303
);

exit;
