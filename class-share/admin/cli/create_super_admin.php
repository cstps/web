<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once(
    dirname(__DIR__) .
    '/include/admin_init.php'
);


function class_share_cli_read_line(
    $prompt
) {
    echo $prompt;

    $value =
        fgets(STDIN);

    if ($value === false) {
        return null;
    }

    return rtrim(
        $value,
        "\r\n"
    );
}


function class_share_cli_read_password(
    $prompt
) {
    if (
        !function_exists('stream_isatty') ||
        !stream_isatty(STDIN)
    ) {
        fwrite(
            STDERR,
            "보안상 실제 터미널에서만 실행할 수 있습니다.\n"
        );

        exit(1);
    }

    echo $prompt;

    $exit_code = 0;

    system(
        'stty -echo',
        $exit_code
    );

    if ($exit_code !== 0) {
        fwrite(
            STDERR,
            "\n비밀번호 숨김 입력을 설정할 수 없습니다.\n"
        );

        exit(1);
    }

    try {
        $value =
            fgets(STDIN);
    } finally {
        system(
            'stty echo'
        );

        echo PHP_EOL;
    }

    if ($value === false) {
        return null;
    }

    return rtrim(
        $value,
        "\r\n"
    );
}


echo "학교 행사 최고 관리자 계정 생성\n";
echo "--------------------------------\n";

$login_id =
    class_share_cli_read_line(
        '관리자 아이디: '
    );

if ($login_id === null) {
    fwrite(
        STDERR,
        "아이디를 읽을 수 없습니다.\n"
    );

    exit(1);
}

$login_id =
    class_share_admin_normalize_login_id(
        $login_id
    );

if (
    !preg_match(
        '/^[a-z0-9._-]{4,64}$/',
        $login_id
    )
) {
    fwrite(
        STDERR,
        "아이디는 영문 소문자, 숫자, 점, " .
        "밑줄, 하이픈을 사용하여 " .
        "4~64자로 입력해야 합니다.\n"
    );

    exit(1);
}

$display_name =
    class_share_cli_read_line(
        '관리자 이름: '
    );

if (
    $display_name === null ||
    strlen(trim($display_name)) < 2 ||
    strlen(trim($display_name)) > 60
) {
    fwrite(
        STDERR,
        "관리자 이름은 2~60자로 입력해야 합니다.\n"
    );

    exit(1);
}

$display_name =
    trim(
        $display_name
    );

$password =
    class_share_cli_read_password(
        '비밀번호: '
    );

$password_confirmation =
    class_share_cli_read_password(
        '비밀번호 확인: '
    );

if (
    $password === null ||
    strlen($password) < 12 ||
    strlen($password) > 128
) {
    fwrite(
        STDERR,
        "비밀번호는 12~128자로 입력해야 합니다.\n"
    );

    exit(1);
}

if (
    !hash_equals(
        $password,
        (string)$password_confirmation
    )
) {
    fwrite(
        STDERR,
        "비밀번호 확인이 일치하지 않습니다.\n"
    );

    exit(1);
}

$existing =
    pdo_query(
        "
        SELECT id
        FROM class_share_admin
        WHERE login_id = ?
        LIMIT 1
        ",
        $login_id
    );

if ($existing === false) {
    fwrite(
        STDERR,
        "기존 관리자 계정을 확인할 수 없습니다.\n"
    );

    exit(1);
}

if (isset($existing[0])) {
    fwrite(
        STDERR,
        "이미 사용 중인 관리자 아이디입니다.\n"
    );

    exit(1);
}

$password_hash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );

$password = null;
$password_confirmation = null;

if ($password_hash === false) {
    fwrite(
        STDERR,
        "비밀번호를 안전하게 변환할 수 없습니다.\n"
    );

    exit(1);
}

$admin_id =
    pdo_query(
        "
        INSERT INTO class_share_admin
        (
            login_id,
            password_hash,
            display_name,
            is_super_admin,
            status,
            failed_login_count,
            password_changed_at,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            1,
            'active',
            0,
            NOW(),
            NOW(),
            NOW()
        )
        ",
        $login_id,
        $password_hash,
        $display_name
    );

if ($admin_id === false) {
    fwrite(
        STDERR,
        "최고 관리자 계정을 생성하지 못했습니다.\n"
    );

    exit(1);
}

echo PHP_EOL;
echo "최고 관리자 계정이 생성되었습니다.\n";
echo "관리자 번호: " . (int)$admin_id . PHP_EOL;
echo "관리자 아이디: " . $login_id . PHP_EOL;
echo "관리자 이름: " . $display_name . PHP_EOL;
