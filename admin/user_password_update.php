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

if (!oj_can_view_admin_users()) {
    http_response_code(403);
    exit("사용자 비밀번호를 변경할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

require_once(
    __DIR__ . "/../include/my_func.inc.php"
);

$user_id =
    isset($_POST["user_id"])
    ? trim((string)$_POST["user_id"])
    : "";

$password =
    isset($_POST["password"])
    ? (string)$_POST["password"]
    : "";

$password_confirm =
    isset($_POST["password_confirm"])
    ? (string)$_POST["password_confirm"]
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
    $password === "" ||
    strlen($password) > 200
) {
    http_response_code(400);
    exit("새 비밀번호를 200바이트 이하로 입력해 주세요.");
}

if ($password !== $password_confirm) {
    http_response_code(400);
    exit("새 비밀번호와 확인 값이 일치하지 않습니다.");
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
        SELECT user_id, password
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

$is_admin =
    oj_is_admin();

if (!$is_admin) {
    $protected_rows =
        pdo_query(
            "
            SELECT user_id
            FROM privilege
            WHERE user_id = ?
              AND rightstr IN (
                  'administrator',
                  'contest_creator',
                  'problem_editor',
                  'system_config_manager'
              )
            LIMIT 1
            ",
            $user_id
        );

    if ($protected_rows === false) {
        http_response_code(500);
        exit("사용자 권한을 확인할 수 없습니다.");
    }

    if (isset($protected_rows[0])) {
        http_response_code(403);
        exit(
            "보호 권한 사용자의 비밀번호는 " .
            "최고관리자만 변경할 수 있습니다."
        );
    }
}

$password_hash =
    pwGen($password);

$current_password_hash =
    (string)$user_rows[0]["password"];

if ($current_password_hash !== $password_hash) {
    if ($is_admin) {
        $update_result =
            pdo_query(
                "
                UPDATE users
                SET password = ?
                WHERE user_id = ?
                ",
                $password_hash,
                $user_id
            );
    } else {
        $update_result =
            pdo_query(
                "
                UPDATE users
                SET password = ?
                WHERE user_id = ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM privilege
                      WHERE privilege.user_id = users.user_id
                        AND privilege.rightstr IN (
                            'administrator',
                            'contest_creator',
                            'problem_editor',
                            'system_config_manager'
                        )
                  )
                ",
                $password_hash,
                $user_id
            );
    }

    if ($update_result === false) {
        http_response_code(500);
        exit("사용자 비밀번호를 변경할 수 없습니다.");
    }

    if (!$is_admin && (int)$update_result !== 1) {
        http_response_code(403);
        exit("이 사용자의 비밀번호를 변경할 수 없습니다.");
    }
}

$return_query =
    array(
        "password_updated" => "1",
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
