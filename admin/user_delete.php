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

$is_admin_user_manager =
    oj_can_manage_admin_users();

$can_create_users =
    oj_can_create_admin_users();

if (
    !$is_admin_user_manager &&
    !$can_create_users
) {
    http_response_code(403);
    exit("사용자를 삭제할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

require_once(
    __DIR__ . "/user_delete_functions.php"
);

$user_id =
    isset($_POST["user_id"])
    ? trim((string)$_POST["user_id"])
    : "";

$confirm_user_id =
    isset($_POST["confirm_user_id"])
    ? trim((string)$_POST["confirm_user_id"])
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

if ($confirm_user_id !== $user_id) {
    http_response_code(400);
    exit("삭제 확인용 사용자 ID가 일치하지 않습니다.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

$current_user_id =
    isset(
        $_SESSION[
            $OJ_NAME . "_user_id"
        ]
    )
    ? trim(
        (string)$_SESSION[
            $OJ_NAME . "_user_id"
        ]
    )
    : "";

if (
    $current_user_id === "" ||
    strlen($current_user_id) > 48
) {
    http_response_code(403);
    exit("현재 관리자 계정을 확인할 수 없습니다.");
}

$actor_ip =
    isset($_SERVER["REMOTE_ADDR"])
    ? trim((string)$_SERVER["REMOTE_ADDR"])
    : "";

if (strlen($actor_ip) > 46) {
    $actor_ip = "";
}

if ($user_id === $current_user_id) {
    http_response_code(400);
    exit("현재 로그인한 자기 계정은 삭제할 수 없습니다.");
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

$has_administrator_right =
    oj_admin_user_has_administrator_right(
        $user_id
    );

if ($has_administrator_right === null) {
    http_response_code(500);
    exit("사용자 권한을 확인할 수 없습니다.");
}

if ($has_administrator_right) {
    http_response_code(400);
    exit("활성 최고관리자 권한이 있는 계정은 삭제할 수 없습니다.");
}

if (!$is_admin_user_manager) {
    $creation_actor_id =
        oj_admin_user_creation_actor(
            $user_id
        );

    if ($creation_actor_id === null) {
        http_response_code(500);
        exit("사용자 생성 이력을 확인할 수 없습니다.");
    }

    if ($creation_actor_id !== $current_user_id) {
        http_response_code(403);
        exit(
            "자신이 사용자 추가 화면에서 직접 추가한 " .
            "계정만 삭제할 수 있습니다."
        );
    }

    $has_protected_delete_right =
        oj_admin_user_has_protected_delete_right(
            $user_id
        );

    if ($has_protected_delete_right === null) {
        http_response_code(500);
        exit("사용자 특별권한을 확인할 수 없습니다.");
    }

    if ($has_protected_delete_right) {
        http_response_code(403);
        exit(
            "활성 특별권한이 있는 계정은 " .
            "비밀번호 관리자가 삭제할 수 없습니다."
        );
    }
}

$blockers =
    oj_admin_user_delete_blockers(
        $user_id
    );

if ($blockers === false) {
    http_response_code(500);
    exit("사용자 활동 기록을 확인할 수 없습니다.");
}

if (count($blockers) > 0) {
    http_response_code(409);
    exit(
        "활동 기록이 있는 사용자는 영구 삭제할 수 없습니다. " .
        "사용 중지 상태로 변경해 주세요."
    );
}

$delete_result =
    pdo_query(
        "
        DELETE
            users,
            privilege,
            loginlog
        FROM users
        LEFT JOIN privilege
            ON privilege.user_id =
                users.user_id
        LEFT JOIN loginlog
            ON loginlog.user_id =
                users.user_id
        WHERE users.user_id = ?
        ",
        $user_id
    );

if ($delete_result === false) {
    http_response_code(500);
    exit("사용자를 삭제할 수 없습니다.");
}

$remaining_user_rows =
    pdo_query(
        "
        SELECT user_id
        FROM users
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

$remaining_privilege_rows =
    pdo_query(
        "
        SELECT 1 AS found
        FROM privilege
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

$remaining_loginlog_rows =
    pdo_query(
        "
        SELECT 1 AS found
        FROM loginlog
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

if (
    $remaining_user_rows === false ||
    $remaining_privilege_rows === false ||
    $remaining_loginlog_rows === false
) {
    error_log(
        "사용자 삭제 후 확인 실패: " .
        $user_id
    );

    http_response_code(500);
    exit("사용자 삭제 결과를 확인할 수 없습니다.");
}

if (
    isset($remaining_user_rows[0]) ||
    isset($remaining_privilege_rows[0]) ||
    isset($remaining_loginlog_rows[0])
) {
    error_log(
        "사용자 삭제 후 잔여 자료 발견: " .
        $user_id
    );

    http_response_code(500);
    exit("사용자 삭제 후 일부 자료가 남아 있습니다.");
}

$audit_result =
    pdo_query(
        "
        INSERT INTO admin_user_audit
        (
            user_id,
            action,
            actor_user_id,
            actor_ip,
            details
        )
        VALUES
        (
            ?,
            'delete',
            ?,
            ?,
            ?
        )
        ",
        $user_id,
        $current_user_id,
        $actor_ip,
        "관리자 사용자 삭제"
    );

if ($audit_result === false) {
    error_log(
        "[admin_user_audit] 사용자 삭제 이력 저장 실패: " .
        $user_id .
        " / 삭제자: " .
        $current_user_id
    );

    http_response_code(500);
    exit(
        "사용자는 삭제됐지만 삭제 이력을 저장하지 못했습니다. " .
        "서버 오류 기록을 확인해 주세요."
    );
}

$return_query =
    array(
        "user_deleted" =>
            "1",
        "page" =>
            (string)$return_page
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
