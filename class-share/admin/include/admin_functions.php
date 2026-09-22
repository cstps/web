<?php

function class_share_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function class_share_admin_client_ip()
{
    $ip =
        isset($_SERVER['REMOTE_ADDR'])
        ? trim((string)$_SERVER['REMOTE_ADDR'])
        : '';

    if (
        $ip === '' ||
        filter_var($ip, FILTER_VALIDATE_IP) === false
    ) {
        return '0.0.0.0';
    }

    return $ip;
}


// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------

function class_share_admin_csrf_token()
{
    $session_key =
        'class_share_admin_csrf_token';

    if (
        !isset($_SESSION[$session_key]) ||
        !is_string($_SESSION[$session_key]) ||
        strlen($_SESSION[$session_key]) < 64
    ) {
        try {
            $_SESSION[$session_key] =
                bin2hex(
                    random_bytes(32)
                );
        } catch (Throwable $e) {
            error_log(
                '[class-share] CSRF 토큰 생성 실패: ' .
                    $e->getMessage()
            );

            http_response_code(500);
            exit('보안키를 생성할 수 없습니다.');
        }
    }

    return $_SESSION[$session_key];
}


function class_share_admin_csrf_input()
{
    return
        '<input type="hidden" ' .
        'name="csrf_token" value="' .
        class_share_escape(
            class_share_admin_csrf_token()
        ) .
        '">';
}


function class_share_admin_require_post_csrf()
{
    if (
        !isset($_SERVER['REQUEST_METHOD']) ||
        $_SERVER['REQUEST_METHOD'] !== 'POST'
    ) {
        header(
            'Allow: POST'
        );

        http_response_code(405);
        exit('POST 요청만 허용됩니다.');
    }

    $session_token =
        isset($_SESSION['class_share_admin_csrf_token'])
        ? $_SESSION['class_share_admin_csrf_token']
        : '';

    $request_token =
        isset($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    if (
        !is_string($session_token) ||
        !is_string($request_token) ||
        $session_token === '' ||
        $request_token === '' ||
        !hash_equals(
            $session_token,
            $request_token
        )
    ) {
        http_response_code(403);
        exit('유효하지 않은 요청입니다.');
    }
}


// ------------------------------------------------------------
// 관리자 세션
// ------------------------------------------------------------

function class_share_admin_clear_session()
{
    $_SESSION = array();

    if (
        session_status() === PHP_SESSION_ACTIVE &&
        ini_get('session.use_cookies')
    ) {
        $cookie =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            array(
                'expires' => time() - 42000,
                'path' =>
                isset($cookie['path'])
                    ? $cookie['path']
                    : '/class-share/admin/',
                'domain' =>
                isset($cookie['domain'])
                    ? $cookie['domain']
                    : '',
                'secure' =>
                !empty($cookie['secure']),
                'httponly' => true,
                'samesite' => 'Strict'
            )
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


function class_share_admin_current()
{
    if (
        !isset(
            $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY]
        ) ||
        !is_array(
            $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY]
        )
    ) {
        return null;
    }

    $admin =
        $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY];

    if (
        empty($admin['id']) ||
        empty($admin['login_id']) ||
        !isset($admin['last_activity'])
    ) {
        class_share_admin_clear_session();

        return null;
    }

    $last_activity =
        (int)$admin['last_activity'];

    if (
        $last_activity <= 0 ||
        time() - $last_activity >
        CLASS_SHARE_ADMIN_IDLE_TIMEOUT
    ) {
        class_share_admin_clear_session();

        return null;
    }

    $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY]['last_activity'] =
        time();

    return
        $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY];
}


function class_share_admin_require_login()
{
    $admin =
        class_share_admin_current();

    if ($admin === null) {
        header(
            'Location: /class-share/admin/login.php',
            true,
            303
        );

        exit;
    }

    $rows =
        pdo_query(
            "
            SELECT
                login_id,
                display_name,
                is_super_admin,
                status
            FROM class_share_admin
            WHERE id = ?
            LIMIT 1
            ",
            (int)$admin['id']
        );

    if ($rows === false) {
        error_log(
            '[class-share] 관리자 계정 상태 확인 실패'
        );

        http_response_code(500);
        exit('관리자 계정을 확인할 수 없습니다.');
    }

    if (
        !isset($rows[0]) ||
        (string)$rows[0]['status'] !== 'active'
    ) {
        class_share_admin_clear_session();

        header(
            'Location: /class-share/admin/login.php',
            true,
            303
        );

        exit;
    }

    $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY]['login_id'] =
        (string)$rows[0]['login_id'];

    $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY]['display_name'] =
        (string)$rows[0]['display_name'];

    $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY]['is_super_admin'] =
        (int)$rows[0]['is_super_admin'] === 1;

    return
        $_SESSION[CLASS_SHARE_ADMIN_SESSION_KEY];
}


function class_share_admin_is_super_admin($admin = null)
{
    if ($admin === null) {
        $admin =
            class_share_admin_current();
    }

    return
        is_array($admin) &&
        !empty($admin['is_super_admin']);
}


// ------------------------------------------------------------
// 학교별 권한
// ------------------------------------------------------------

function class_share_admin_school_role(
    $admin_id,
    $school_id
) {
    $admin_id =
        (int)$admin_id;

    $school_id =
        (int)$school_id;

    if (
        $admin_id <= 0 ||
        $school_id <= 0
    ) {
        return null;
    }

    $rows =
        pdo_query(
            "
            SELECT role
            FROM class_share_admin_school
            WHERE admin_id = ?
              AND school_id = ?
              AND active = 1
            LIMIT 1
            ",
            $admin_id,
            $school_id
        );

    if (
        $rows === false ||
        !isset($rows[0]['role'])
    ) {
        return null;
    }

    return
        (string)$rows[0]['role'];
}


function class_share_admin_can_view_school(
    $school_id,
    $admin = null
) {
    if ($admin === null) {
        $admin =
            class_share_admin_current();
    }

    if (!is_array($admin)) {
        return false;
    }

    if (
        class_share_admin_is_super_admin(
            $admin
        )
    ) {
        return true;
    }

    $role =
        class_share_admin_school_role(
            $admin['id'],
            $school_id
        );

    return in_array(
        $role,
        array(
            'school_admin',
            'editor',
            'viewer'
        ),
        true
    );
}


function class_share_admin_can_edit_school(
    $school_id,
    $admin = null
) {
    if ($admin === null) {
        $admin =
            class_share_admin_current();
    }

    if (!is_array($admin)) {
        return false;
    }

    if (
        class_share_admin_is_super_admin(
            $admin
        )
    ) {
        return true;
    }

    $role =
        class_share_admin_school_role(
            $admin['id'],
            $school_id
        );

    return in_array(
        $role,
        array(
            'school_admin',
            'editor'
        ),
        true
    );
}


function class_share_admin_can_manage_school(
    $school_id,
    $admin = null
) {
    if ($admin === null) {
        $admin =
            class_share_admin_current();
    }

    if (!is_array($admin)) {
        return false;
    }

    if (
        class_share_admin_is_super_admin(
            $admin
        )
    ) {
        return true;
    }

    return
        class_share_admin_school_role(
            $admin['id'],
            $school_id
        ) === 'school_admin';
}
