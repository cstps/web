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
    exit("사용자 권한을 관리할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

$user_id =
    isset($_POST["user_id"])
    ? trim((string)$_POST["user_id"])
    : "";

$rightstr =
    isset($_POST["rightstr"])
    ? trim((string)$_POST["rightstr"])
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
    $rightstr === "" ||
    strlen($rightstr) > 64
) {
    http_response_code(400);
    exit("권한 코드가 올바르지 않습니다.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

$special_rights =
    array(
        "administrator",
        "problem_editor",
        "source_browser",
        "contest_creator",
        "password_setter",
        "system_config_manager",
        "printer",
        "balloon"
    );

$is_special_right =
    in_array(
        $rightstr,
        $special_rights,
        true
    );

$is_contest_right =
    preg_match(
        "/^c[1-9][0-9]*$/",
        $rightstr
    ) === 1;

$is_source_right =
    preg_match(
        "/^s[1-9][0-9]*$/",
        $rightstr
    ) === 1;

if (
    !$is_special_right &&
    !$is_contest_right &&
    !$is_source_right
) {
    http_response_code(400);
    exit("이 화면에서 제거할 수 없는 권한입니다.");
}

$user_rows =
    pdo_query(
        "
        SELECT user_id
        FROM users
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

if ($user_rows === false) {
    http_response_code(500);
    exit("사용자를 확인할 수 없습니다.");
}

if (!isset($user_rows[0])) {
    http_response_code(404);
    exit("사용자를 찾을 수 없습니다.");
}

$current_user_id =
    isset(
        $_SESSION[
            $OJ_NAME . "_user_id"
        ]
    )
    ? (string)$_SESSION[
        $OJ_NAME . "_user_id"
    ]
    : "";

if (
    $rightstr === "administrator" &&
    $user_id === $current_user_id
) {
    http_response_code(400);
    exit("현재 로그인한 계정의 최고관리자 권한은 제거할 수 없습니다.");
}

if ($rightstr === "administrator") {
    $administrator_rows =
        pdo_query(
            "
            SELECT
                COUNT(
                    DISTINCT privilege.user_id
                ) AS total
            FROM privilege
            INNER JOIN users
                ON users.user_id =
                    privilege.user_id
            WHERE privilege.rightstr =
                    'administrator'
              AND privilege.defunct = 'N'
              AND users.defunct = 'N'
            "
        );

    if (
        $administrator_rows === false ||
        !isset($administrator_rows[0])
    ) {
        http_response_code(500);
        exit("최고관리자 현황을 확인할 수 없습니다.");
    }

    $administrator_count =
        (int)$administrator_rows[0]["total"];

    if ($administrator_count <= 1) {
        http_response_code(400);
        exit("마지막 최고관리자 권한은 제거할 수 없습니다.");
    }
}

$privilege_rows =
    pdo_query(
        "
        SELECT rightstr
        FROM privilege
        WHERE user_id = ?
          AND rightstr = ?
          AND defunct = 'N'
        LIMIT 1
        ",
        $user_id,
        $rightstr
    );

if ($privilege_rows === false) {
    http_response_code(500);
    exit("사용자 권한을 확인할 수 없습니다.");
}

if (!isset($privilege_rows[0])) {
    http_response_code(404);
    exit("활성 권한을 찾을 수 없습니다.");
}

$update_result =
    pdo_query(
        "
        UPDATE privilege
        SET defunct = 'Y'
        WHERE user_id = ?
          AND rightstr = ?
          AND defunct = 'N'
        ",
        $user_id,
        $rightstr
    );

if ($update_result === false) {
    http_response_code(500);
    exit("사용자 권한을 제거할 수 없습니다.");
}

$return_query =
    array(
        "uid" =>
            $user_id,
        "privilege_removed" =>
            "1",
        "return_page" =>
            (string)$return_page
    );

if ($return_keyword !== "") {
    $return_query["return_keyword"] =
        $return_keyword;
}

header(
    "Location: user_privilege_manage.php?" .
    http_build_query($return_query),
    true,
    303
);

exit;
