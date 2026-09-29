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

if (!oj_can_manage_admin_system_settings()) {
    http_response_code(403);
    exit("시스템 설정을 관리할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

$setting_id =
    isset($_POST["setting_id"])
    ? (int)$_POST["setting_id"]
    : 0;

$exam_mode =
    isset($_POST["exam_mode"])
    ? (string)$_POST["exam_mode"]
    : "";

$register_enabled =
    isset($_POST["register"])
    ? (string)$_POST["register"]
    : "";

if ($setting_id <= 0) {
    http_response_code(400);
    exit("설정 번호가 올바르지 않습니다.");
}

if (
    !in_array(
        $exam_mode,
        array("0", "1"),
        true
    )
) {
    http_response_code(400);
    exit("수행평가 모드 값이 올바르지 않습니다.");
}

if (
    !in_array(
        $register_enabled,
        array("0", "1"),
        true
    )
) {
    http_response_code(400);
    exit("회원가입 설정값이 올바르지 않습니다.");
}

$setting_rows =
    pdo_query(
        "
        SELECT id
        FROM setting
        WHERE id = ?
        LIMIT 1
        ",
        $setting_id
    );

if ($setting_rows === false) {
    http_response_code(500);
    exit("시스템 설정을 확인할 수 없습니다.");
}

if (!isset($setting_rows[0])) {
    http_response_code(404);
    exit("시스템 설정을 찾을 수 없습니다.");
}

$update_result =
    pdo_query(
        "
        UPDATE setting
        SET exam_mode = ?,
            register = ?
        WHERE id = ?
        ",
        (int)$exam_mode,
        (int)$register_enabled,
        $setting_id
    );

if ($update_result === false) {
    http_response_code(500);
    exit("시스템 설정을 저장할 수 없습니다.");
}

header(
    "Location: setdbinfo.php?saved=1",
    true,
    303
);

exit;
