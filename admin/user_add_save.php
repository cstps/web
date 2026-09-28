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

if (!oj_can_create_admin_users()) {
    http_response_code(403);
    exit("사용자를 추가할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

require_once(
    __DIR__ . "/../include/my_func.inc.php"
);

$raw_user_list =
    isset($_POST["user_list"])
    ? trim((string)$_POST["user_list"])
    : "";

$flash_key =
    $OJ_NAME .
    "_admin_user_add_result";

$redirect_with_result =
    function (
        $success_ids,
        $errors
    ) use (
        $flash_key
    ) {
        $_SESSION[$flash_key] =
            array(
                "success_ids" =>
                    array_values($success_ids),
                "errors" =>
                    array_values($errors)
            );

        header(
            "Location: user_add.php?processed=1",
            true,
            303
        );

        exit;
    };

if ($raw_user_list === "") {
    $redirect_with_result(
        array(),
        array(
            "추가할 사용자 정보를 입력해 주세요."
        )
    );
}

if (strlen($raw_user_list) > 100000) {
    $redirect_with_result(
        array(),
        array(
            "입력 내용은 100,000바이트 이하로 작성해 주세요."
        )
    );
}

if (!mb_check_encoding($raw_user_list, "UTF-8")) {
    $redirect_with_result(
        array(),
        array(
            "입력 내용의 문자 인코딩이 올바르지 않습니다."
        )
    );
}

$lines =
    preg_split(
        "/\r\n|\r|\n/u",
        $raw_user_list
    );

if (!is_array($lines)) {
    $redirect_with_result(
        array(),
        array(
            "입력 내용을 줄 단위로 나눌 수 없습니다."
        )
    );
}

if (count($lines) > 500) {
    $redirect_with_result(
        array(),
        array(
            "사용자는 한 번에 500명 이하로 추가해 주세요."
        )
    );
}

$school_list_file =
    __DIR__ .
    "/../school_list.json";

if (!is_readable($school_list_file)) {
    http_response_code(500);
    exit("학교 목록 파일을 읽을 수 없습니다.");
}

$school_json =
    file_get_contents(
        $school_list_file
    );

$allowed_schools =
    json_decode(
        $school_json,
        true
    );

if (!is_array($allowed_schools)) {
    http_response_code(500);
    exit("학교 목록 파일이 올바르지 않습니다.");
}

$normalize_school =
    function ($school) {
        $normalized =
            preg_replace(
                "/[^a-zA-Z0-9가-힣]/u",
                "",
                (string)$school
            );

        if ($normalized === null) {
            return "";
        }

        return mb_strtolower(
            $normalized,
            "UTF-8"
        );
    };

$school_map =
    array();

foreach ($allowed_schools as $allowed_school) {
    if (!is_string($allowed_school)) {
        continue;
    }

    $allowed_school =
        trim($allowed_school);

    if ($allowed_school === "") {
        continue;
    }

    $school_key =
        $normalize_school(
            $allowed_school
        );

    if (
        $school_key !== "" &&
        !isset($school_map[$school_key])
    ) {
        $school_map[$school_key] =
            $allowed_school;
    }
}

$remote_ip =
    isset($_SERVER["REMOTE_ADDR"])
    ? trim((string)$_SERVER["REMOTE_ADDR"])
    : "";

if (strlen($remote_ip) > 46) {
    $remote_ip = "";
}

$actor_user_id =
    isset($_SESSION[$OJ_NAME . "_user_id"])
    ? trim(
        (string)$_SESSION[
            $OJ_NAME . "_user_id"
        ]
    )
    : "";

if (
    $actor_user_id === "" ||
    strlen($actor_user_id) > 48
) {
    http_response_code(403);
    exit("현재 관리자 계정을 확인할 수 없습니다.");
}

$success_ids =
    array();

$errors =
    array();

foreach ($lines as $index => $line) {
    $line_number =
        $index + 1;

    $line =
        preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            " ",
            (string)$line
        );

    if ($line === null) {
        $errors[] =
            $line_number .
            "번째 줄: 제어문자를 처리할 수 없습니다.";

        continue;
    }

    $line =
        trim($line);

    if ($line === "") {
        continue;
    }

    $parts =
        preg_split(
            "/\s+/u",
            $line,
            4
        );

    if (
        !is_array($parts) ||
        count($parts) < 3
    ) {
        $errors[] =
            $line_number .
            "번째 줄: 사용자 ID, 비밀번호, 별명이 필요합니다.";

        continue;
    }

    $user_id =
        trim((string)$parts[0]);

    $plain_password =
        (string)$parts[1];

    $nick =
        trim((string)$parts[2]);

    $school =
        isset($parts[3])
        ? trim((string)$parts[3])
        : "";

    if (
        strlen($user_id) < 3 ||
        strlen($user_id) > 20 ||
        !is_valid_user_name($user_id)
    ) {
        $errors[] =
            $line_number .
            "번째 줄: 사용자 ID는 3~20자의 영문자와 숫자로 입력해 주세요.";

        continue;
    }

    if (
        strlen($plain_password) < 6 ||
        strlen($plain_password) > 200
    ) {
        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 비밀번호는 6~200바이트로 입력해 주세요.";

        continue;
    }

    if (
        $nick === "" ||
        !mb_check_encoding($nick, "UTF-8") ||
        mb_strlen($nick, "UTF-8") > 20
    ) {
        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 별명은 20자 이하로 입력해 주세요.";

        continue;
    }

    if ($school !== "") {
        if (
            !mb_check_encoding($school, "UTF-8") ||
            mb_strlen($school, "UTF-8") > 20
        ) {
            $errors[] =
                $line_number .
                "번째 줄(" .
                $user_id .
                "): 학교명은 20자 이하로 입력해 주세요.";

            continue;
        }

        $school_key =
            $normalize_school(
                $school
            );

        if (
            $school_key === "" ||
            !isset($school_map[$school_key])
        ) {
            $errors[] =
                $line_number .
                "번째 줄(" .
                $user_id .
                "): 학교 목록에 없는 학교명입니다.";

            continue;
        }

        $school =
            $school_map[$school_key];
    }

    $existing_rows =
        pdo_query(
            "
            SELECT user_id
            FROM users
            WHERE user_id = ?
            LIMIT 1
            ",
            $user_id
        );

    if ($existing_rows === false) {
        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 기존 사용자 여부를 확인할 수 없습니다.";

        continue;
    }

    if (isset($existing_rows[0])) {
        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 이미 존재하는 사용자입니다.";

        continue;
    }

    $password_hash =
        pwGen($plain_password);

    $insert_result =
        pdo_query(
            "
            INSERT INTO users
            (
                user_id,
                email,
                ip,
                accesstime,
                password,
                reg_time,
                nick,
                school,
                defunct
            )
            VALUES
            (
                ?,
                '',
                ?,
                NOW(),
                ?,
                NOW(),
                ?,
                ?,
                'N'
            )
            ",
            $user_id,
            $remote_ip,
            $password_hash,
            $nick,
            $school
        );

    if ($insert_result === false) {
        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 사용자를 추가할 수 없습니다.";

        continue;
    }

    $success_ids[] =
        $user_id;

    $log_result =
        pdo_query(
            "
            INSERT INTO loginlog
            (
                user_id,
                password,
                ip,
                time
            )
            VALUES
            (
                ?,
                ?,
                ?,
                NOW()
            )
            ",
            $user_id,
            "user added by admin",
            $remote_ip
        );

    if ($log_result === false) {
        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 사용자는 추가됐지만 로그인 기록을 저장하지 못했습니다.";
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
                'create',
                ?,
                ?,
                ?
            )
            ",
            $user_id,
            $actor_user_id,
            $remote_ip,
            "관리자 사용자 추가"
        );

    if ($audit_result === false) {
        error_log(
            "[admin_user_audit] 사용자 생성 이력 저장 실패: " .
            $user_id .
            " / 생성자: " .
            $actor_user_id
        );

        $errors[] =
            $line_number .
            "번째 줄(" .
            $user_id .
            "): 사용자는 추가됐지만 생성자 기록을 저장하지 못했습니다.";
    }
}

if (
    count($success_ids) === 0 &&
    count($errors) === 0
) {
    $errors[] =
        "추가할 사용자 정보가 없습니다.";
}

$redirect_with_result(
    $success_ids,
    $errors
);
