<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

class_share_admin_require_post_csrf();

$current_admin =
    class_share_admin_current();

if ($current_admin !== null) {
    header(
        'Location: /class-share/admin/dashboard.php',
        true,
        303
    );

    exit;
}

$login_id =
    isset($_POST['login_id'])
    ? $_POST['login_id']
    : '';

$password =
    isset($_POST['password'])
    ? $_POST['password']
    : '';

$result =
    class_share_admin_authenticate(
        $login_id,
        $password
    );

$password = null;

if (
    !isset($result['ok']) ||
    $result['ok'] !== true
) {
    $_SESSION[
        'class_share_admin_login_error'
    ] =
        isset($result['message'])
        ? (string)$result['message']
        : '로그인할 수 없습니다.';

    session_write_close();

    header(
        'Location: /class-share/admin/login.php',
        true,
        303
    );

    exit;
}

session_write_close();

header(
    'Location: /class-share/admin/dashboard.php',
    true,
    303
);

exit;
