<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    http_response_code(405);
    exit('POST 요청만 허용됩니다.');
}

$admin =
    class_share_admin_require_login();

if (
    !class_share_admin_is_super_admin(
        $admin
    )
) {
    http_response_code(403);
    exit('관리자 계정을 생성할 권한이 없습니다.');
}

class_share_admin_require_post_csrf();

$login_id =
    isset($_POST['login_id'])
    ? class_share_admin_normalize_login_id(
        $_POST['login_id']
    )
    : '';

$display_name =
    isset($_POST['display_name'])
    ? trim(
        (string)$_POST['display_name']
    )
    : '';

$email =
    isset($_POST['email'])
    ? trim(
        (string)$_POST['email']
    )
    : '';

$password =
    isset($_POST['password'])
    ? (string)$_POST['password']
    : '';

$password_confirmation =
    isset($_POST['password_confirmation'])
    ? (string)$_POST[
        'password_confirmation'
    ]
    : '';

$form_values =
    array(
        'login_id' => $login_id,
        'display_name' => $display_name,
        'email' => $email
    );

$redirect_with_errors =
    function ($errors) use (
        $form_values
    ) {
        $_SESSION[
            'class_share_admin_form_errors'
        ] =
            array_values(
                array_unique(
                    is_array($errors)
                    ? $errors
                    : array(
                        '입력 내용을 확인해 주세요.'
                    )
                )
            );

        $_SESSION[
            'class_share_admin_form_values'
        ] =
            $form_values;

        session_write_close();

        header(
            'Location: /class-share/admin/' .
            'admin_form.php',
            true,
            303
        );

        exit;
    };

$text_length =
    function ($value) {
        if (function_exists('mb_strlen')) {
            return mb_strlen(
                $value,
                'UTF-8'
            );
        }

        return strlen($value);
    };

$errors =
    array();

if (
    !preg_match(
        '/^[a-z0-9._-]{4,64}$/D',
        $login_id
    )
) {
    $errors[] =
        '관리자 아이디는 영문 소문자, 숫자, 점, ' .
        '밑줄, 하이픈을 사용하여 4~64자로 입력해 주세요.';
}

if (
    $text_length($display_name) < 2 ||
    $text_length($display_name) > 60
) {
    $errors[] =
        '관리자 이름은 2~60자로 입력해 주세요.';
}

if (
    $email !== '' &&
    (
        strlen($email) > 255 ||
        filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) === false
    )
) {
    $errors[] =
        '이메일 주소 형식을 확인해 주세요.';
}

if (
    strlen($password) < 12 ||
    strlen($password) > 128
) {
    $errors[] =
        '초기 비밀번호는 12~128자로 입력해 주세요.';
}

if (
    !hash_equals(
        $password,
        $password_confirmation
    )
) {
    $errors[] =
        '비밀번호 확인이 일치하지 않습니다.';
}

if (count($errors) > 0) {
    $password = null;
    $password_confirmation = null;

    $redirect_with_errors(
        $errors
    );
}

$existing_rows =
    pdo_query(
        "
        SELECT id
        FROM class_share_admin
        WHERE login_id = ?
        LIMIT 1
        ",
        $login_id
    );

if ($existing_rows === false) {
    $redirect_with_errors(
        array(
            '관리자 아이디 중복 여부를 확인할 수 없습니다.'
        )
    );
}

if (isset($existing_rows[0])) {
    $redirect_with_errors(
        array(
            '이미 사용 중인 관리자 아이디입니다.'
        )
    );
}

$password_hash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );

$password = null;
$password_confirmation = null;

if ($password_hash === false) {
    $redirect_with_errors(
        array(
            '비밀번호를 안전하게 저장할 수 없습니다.'
        )
    );
}

try {
    global $dbh;

    if (!($dbh instanceof PDO)) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $admin_id =
        pdo_query(
            "
            INSERT INTO class_share_admin
            (
                login_id,
                password_hash,
                display_name,
                email,
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
                ?,
                0,
                'active',
                0,
                NOW(),
                NOW(),
                NOW()
            )
            ",
            $login_id,
            $password_hash,
            $display_name,
            $email !== ''
            ? $email
            : null
        );

    if ($admin_id === false) {
        throw new RuntimeException(
            '관리자 계정을 저장할 수 없습니다.'
        );
    }

    $after_json =
        json_encode(
            array(
                'login_id' =>
                    $login_id,

                'display_name' =>
                    $display_name,

                'email' =>
                    $email !== ''
                    ? $email
                    : null,

                'is_super_admin' =>
                    false,

                'status' =>
                    'active'
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($after_json === false) {
        throw new RuntimeException(
            '감사 로그 자료를 만들 수 없습니다.'
        );
    }

    $audit_result =
        pdo_query(
            "
            INSERT INTO class_share_audit_log
            (
                school_id,
                admin_id,
                actor_type,
                action,
                target_type,
                target_id,
                before_data,
                after_data,
                ip_address,
                created_at
            )
            VALUES
            (
                NULL,
                ?,
                'admin',
                'admin.create',
                'admin',
                ?,
                NULL,
                ?,
                ?,
                NOW()
            )
            ",
            (int)$admin['id'],
            (int)$admin_id,
            $after_json,
            class_share_admin_client_ip()
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '관리자 생성 감사 기록을 저장할 수 없습니다.'
        );
    }

    $dbh->commit();

    $_SESSION[
        'class_share_admin_flash'
    ] =
        $display_name .
        ' 관리자 계정을 생성했습니다. ' .
        '이제 학교와 역할을 배정해 주세요.';

    session_write_close();

    header(
        'Location: /class-share/admin/admins.php',
        true,
        303
    );

    exit;
} catch (Throwable $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        '[class-share] 관리자 계정 생성 실패: ' .
        $e->getMessage()
    );

    $redirect_with_errors(
        array(
            '관리자 계정을 생성하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        )
    );
}
