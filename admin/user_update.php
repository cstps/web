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

if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("사용자 정보를 수정할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

$user_id =
    isset($_POST["user_id"])
    ? trim((string)$_POST["user_id"])
    : "";

$nick =
    isset($_POST["nick"])
    ? trim((string)$_POST["nick"])
    : "";

$school =
    isset($_POST["school"])
    ? trim((string)$_POST["school"])
    : "";

$return_page =
    isset($_POST["return_page"])
    ? (int)$_POST["return_page"]
    : 1;

$return_keyword =
    isset($_POST["return_keyword"])
    ? trim((string)$_POST["return_keyword"])
    : "";

if (
    $user_id === "" ||
    strlen($user_id) > 48
) {
    http_response_code(400);
    exit("사용자 ID가 올바르지 않습니다.");
}

if (
    !mb_check_encoding($nick, "UTF-8") ||
    mb_strlen($nick, "UTF-8") > 20
) {
    http_response_code(400);
    exit("별명은 20자 이하로 입력해 주세요.");
}

if (
    !mb_check_encoding($school, "UTF-8") ||
    mb_strlen($school, "UTF-8") > 20
) {
    http_response_code(400);
    exit("학교명은 20자 이하로 입력해 주세요.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

$user_rows =
    pdo_query(
        "
        SELECT user_id, nick
        FROM users
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

if ($user_rows === false) {
    http_response_code(500);
    exit("사용자 정보를 확인할 수 없습니다.");
}

if (!isset($user_rows[0])) {
    http_response_code(404);
    exit("사용자를 찾을 수 없습니다.");
}

$previous_nick =
    (string)$user_rows[0]["nick"];

$update_result =
    pdo_query(
        "
        UPDATE users
        SET nick = ?,
            school = ?
        WHERE user_id = ?
        ",
        $nick,
        $school,
        $user_id
    );

if ($update_result === false) {
    http_response_code(500);
    exit("사용자 정보를 수정할 수 없습니다.");
}

if ($previous_nick !== $nick) {
    $solution_update_result =
        pdo_query(
            "
            UPDATE solution
            SET nick = ?
            WHERE user_id = ?
            ",
            $nick,
            $user_id
        );

    if ($solution_update_result === false) {
        http_response_code(500);
        exit("제출 기록의 별명을 동기화할 수 없습니다.");
    }
}

$return_query =
    array(
        "edited" => "1",
        "page" => (string)$return_page
    );

if ($return_keyword !== "") {
    $return_query["keyword"] =
        $return_keyword;
}

header(
    "Location: user_list.php?" .
    http_build_query($return_query),
    true,
    303
);

exit;
