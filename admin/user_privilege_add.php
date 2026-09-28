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

$privilege_type =
    isset($_POST["privilege_type"])
    ? trim((string)$_POST["privilege_type"])
    : "";

$special_right =
    isset($_POST["special_right"])
    ? trim((string)$_POST["special_right"])
    : "";

$special_value =
    isset($_POST["special_value"])
    ? trim((string)$_POST["special_value"])
    : "";

$target_id_raw =
    isset($_POST["target_id"])
    ? trim((string)$_POST["target_id"])
    : "";

$return_page =
    isset($_POST["return_page"])
    ? (int)$_POST["return_page"]
    : 1;

$return_keyword =
    isset($_POST["return_keyword"])
    ? trim((string)$_POST["return_keyword"])
    : "";

$return_target =
    isset($_POST["return_target"])
    ? trim((string)$_POST["return_target"])
    : "list";

if (
    $user_id === "" ||
    strlen($user_id) > 48
) {
    http_response_code(400);
    exit("사용자 ID가 올바르지 않습니다.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

if (
    $return_target !== "list" &&
    $return_target !== "manage"
) {
    http_response_code(400);
    exit("복귀 화면 값이 올바르지 않습니다.");
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

$rightstr = "";
$valuestr = "true";

if ($privilege_type === "special") {
    if (
        !in_array(
            $special_right,
            $special_rights,
            true
        )
    ) {
        http_response_code(400);
        exit("특별 권한 종류가 올바르지 않습니다.");
    }

    $rightstr =
        $special_right;
} elseif (
    $privilege_type === "contest" ||
    $privilege_type === "source"
) {
    if (
        $target_id_raw === "" ||
        strlen($target_id_raw) > 10 ||
        !ctype_digit($target_id_raw)
    ) {
        http_response_code(400);
        exit("대상 번호가 올바르지 않습니다.");
    }

    $target_id =
        (int)$target_id_raw;

    if ($target_id <= 0) {
        http_response_code(400);
        exit("대상 번호가 올바르지 않습니다.");
    }

    if ($privilege_type === "contest") {
        $target_rows =
            pdo_query(
                "
                SELECT contest_id
                FROM contest
                WHERE contest_id = ?
                LIMIT 1
                ",
                $target_id
            );

        if ($target_rows === false) {
            http_response_code(500);
            exit("대회를 확인할 수 없습니다.");
        }

        if (!isset($target_rows[0])) {
            http_response_code(404);
            exit("대회를 찾을 수 없습니다.");
        }

        $rightstr =
            "c" . $target_id;
    } else {
        $target_rows =
            pdo_query(
                "
                SELECT problem_id
                FROM problem
                WHERE problem_id = ?
                LIMIT 1
                ",
                $target_id
            );

        if ($target_rows === false) {
            http_response_code(500);
            exit("문제를 확인할 수 없습니다.");
        }

        if (!isset($target_rows[0])) {
            http_response_code(404);
            exit("문제를 찾을 수 없습니다.");
        }

        $rightstr =
            "s" . $target_id;
    }
} else {
    http_response_code(400);
    exit("권한 분류가 올바르지 않습니다.");
}

$privilege_rows =
    pdo_query(
        "
        SELECT defunct
        FROM privilege
        WHERE user_id = ?
          AND rightstr = ?
        ",
        $user_id,
        $rightstr
    );

if ($privilege_rows === false) {
    http_response_code(500);
    exit("기존 권한을 확인할 수 없습니다.");
}

if (count($privilege_rows) > 0) {
    $save_result =
        pdo_query(
            "
            UPDATE privilege
            SET valuestr = ?,
                defunct = 'N'
            WHERE user_id = ?
              AND rightstr = ?
            ",
            $valuestr,
            $user_id,
            $rightstr
        );
} else {
    $save_result =
        pdo_query(
            "
            INSERT INTO privilege
            (
                user_id,
                rightstr,
                valuestr,
                defunct
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'N'
            )
            ",
            $user_id,
            $rightstr,
            $valuestr
        );
}

if ($save_result === false) {
    http_response_code(500);
    exit("사용자 권한을 저장할 수 없습니다.");
}

if ($return_target === "manage") {
    $return_query =
        array(
            "uid" =>
                $user_id,
            "privilege_added" =>
                "1",
            "return_page" =>
                (string)$return_page
        );

    if ($return_keyword !== "") {
        $return_query["return_keyword"] =
            $return_keyword;
    }

    $return_location =
        "user_privilege_manage.php";
} else {
    $return_query =
        array(
            "privilege_added" =>
                "1",
            "page" =>
                (string)$return_page
        );

    if ($return_keyword !== "") {
        $return_query["keyword"] =
            $return_keyword;
    }

    $return_location =
        "user_list.php";
}

header(
    "Location: " .
    $return_location .
    "?" .
    http_build_query($return_query),
    true,
    303
);

exit;
