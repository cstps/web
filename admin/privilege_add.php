<?php

require_once(__DIR__ . "/admin-init.php");

if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("사용자 권한을 관리할 권한이 없습니다.");
}

$method = isset($_SERVER["REQUEST_METHOD"])
    ? (string)$_SERVER["REQUEST_METHOD"]
    : "";

if ($method === "POST") {
    http_response_code(410);
    exit("기존 권한 추가 주소는 사용할 수 없습니다. 사용자별 권한 관리 화면을 이용해 주세요.");
}

if ($method !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    exit("GET 요청만 허용됩니다.");
}

if (!isset($_GET["uid"])) {
    header("Location: user_list.php", true, 302);
    exit;
}

if (!is_string($_GET["uid"])) {
    http_response_code(400);
    exit("사용자 ID가 올바르지 않습니다.");
}

$user_id = trim($_GET["uid"]);

if (
    $user_id === "" ||
    strlen($user_id) > 48 ||
    preg_match('/[\\x00-\\x1F\\x7F]/', $user_id) === 1
) {
    http_response_code(400);
    exit("사용자 ID가 올바르지 않습니다.");
}

header(
    "Location: user_privilege_manage.php?uid=" . rawurlencode($user_id),
    true,
    302
);
exit;
