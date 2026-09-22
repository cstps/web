<?php

function class_share_admin_normalize_login_id(
    $login_id
) {
    $login_id =
        strtolower(
            trim(
                (string)$login_id
            )
        );

    return $login_id;
}


function class_share_admin_record_login_attempt(
    $admin_id,
    $login_id,
    $was_successful,
    $failure_reason = null
) {
    $user_agent =
        isset($_SERVER['HTTP_USER_AGENT'])
        ? substr(
            (string)$_SERVER['HTTP_USER_AGENT'],
            0,
            255
        )
        : null;

    $result =
        pdo_query(
            "
            INSERT INTO class_share_admin_login_attempt
            (
                admin_id,
                login_id,
                ip_address,
                was_successful,
                failure_reason,
                user_agent,
                attempted_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
            ",
            $admin_id,
            $login_id,
            class_share_admin_client_ip(),
            $was_successful ? 1 : 0,
            $failure_reason,
            $user_agent
        );

    return $result !== false;
}


function class_share_admin_rate_limit_status(
    $login_id
) {
    $ip_address =
        class_share_admin_client_ip();

    $rows =
        pdo_query(
            "
            SELECT
                SUM(
                    CASE
                        WHEN login_id = ?
                        THEN 1
                        ELSE 0
                    END
                ) AS login_failures,

                SUM(
                    CASE
                        WHEN ip_address = ?
                        THEN 1
                        ELSE 0
                    END
                ) AS ip_failures

            FROM class_share_admin_login_attempt

            WHERE was_successful = 0
              AND attempted_at >=
                  DATE_SUB(
                      NOW(),
                      INTERVAL 15 MINUTE
                  )
              AND (
                  login_id = ?
                  OR ip_address = ?
              )
            ",
            $login_id,
            $ip_address,
            $login_id,
            $ip_address
        );

    if (
        $rows === false ||
        !isset($rows[0])
    ) {
        return null;
    }

    $login_failures =
        isset($rows[0]['login_failures'])
        ? (int)$rows[0]['login_failures']
        : 0;

    $ip_failures =
        isset($rows[0]['ip_failures'])
        ? (int)$rows[0]['ip_failures']
        : 0;

    return array(
        'blocked' =>
            $login_failures >= 10 ||
            $ip_failures >= 30,

        'login_failures' =>
            $login_failures,

        'ip_failures' =>
            $ip_failures
    );
}


function class_share_admin_authenticate(
    $login_id,
    $password
) {
    $login_id =
        class_share_admin_normalize_login_id(
            $login_id
        );

    $password =
        (string)$password;

    if (
        !preg_match(
            '/^[a-z0-9._-]{4,64}$/',
            $login_id
        ) ||
        $password === '' ||
        strlen($password) > 1024
    ) {
        class_share_admin_record_login_attempt(
            null,
            substr($login_id, 0, 64),
            false,
            'invalid_input'
        );

        return array(
            'ok' => false,
            'message' =>
                '아이디 또는 비밀번호가 올바르지 않습니다.'
        );
    }

    $rate_limit =
        class_share_admin_rate_limit_status(
            $login_id
        );

    if ($rate_limit === null) {
        return array(
            'ok' => false,
            'message' =>
                '로그인을 처리할 수 없습니다.'
        );
    }

    if ($rate_limit['blocked']) {
        return array(
            'ok' => false,
            'message' =>
                '로그인 시도가 너무 많습니다. ' .
                '15분 후 다시 시도해 주세요.'
        );
    }

    $rows =
        pdo_query(
            "
            SELECT
                id,
                login_id,
                password_hash,
                display_name,
                is_super_admin,
                status,
                failed_login_count,
                locked_until

            FROM class_share_admin

            WHERE login_id = ?

            LIMIT 1
            ",
            $login_id
        );

    if ($rows === false) {
        return array(
            'ok' => false,
            'message' =>
                '로그인을 처리할 수 없습니다.'
        );
    }

    if (!isset($rows[0])) {
        password_verify(
            $password,
            '$2y$10$92IXUNpkjO0rOQ5byMi.' .
            'Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.'
        );

        class_share_admin_record_login_attempt(
            null,
            $login_id,
            false,
            'invalid_credentials'
        );

        return array(
            'ok' => false,
            'message' =>
                '아이디 또는 비밀번호가 올바르지 않습니다.'
        );
    }

    $admin =
        $rows[0];

    $admin_id =
        (int)$admin['id'];

    $status =
        (string)$admin['status'];

    $locked_until_timestamp =
        !empty($admin['locked_until'])
        ? strtotime(
            (string)$admin['locked_until']
        )
        : false;

    if ($status === 'disabled') {
        class_share_admin_record_login_attempt(
            $admin_id,
            $login_id,
            false,
            'disabled'
        );

        return array(
            'ok' => false,
            'message' =>
                '아이디 또는 비밀번호가 올바르지 않습니다.'
        );
    }

    if (
        (
            $status === 'locked' &&
            $locked_until_timestamp === false
        ) ||
        (
            $locked_until_timestamp !== false &&
            $locked_until_timestamp > time()
        )
    ) {
        class_share_admin_record_login_attempt(
            $admin_id,
            $login_id,
            false,
            'locked'
        );

        return array(
            'ok' => false,
            'message' =>
                '로그인 시도가 너무 많습니다. ' .
                '잠시 후 다시 시도해 주세요.'
        );
    }

    if (
        $status === 'locked' &&
        $locked_until_timestamp !== false &&
        $locked_until_timestamp <= time()
    ) {
        $unlock_result =
            pdo_query(
                "
                UPDATE class_share_admin
                SET
                    status = 'active',
                    failed_login_count = 0,
                    locked_until = NULL
                WHERE id = ?
                  AND status = 'locked'
                ",
                $admin_id
            );

        if ($unlock_result === false) {
            return array(
                'ok' => false,
                'message' =>
                    '로그인을 처리할 수 없습니다.'
            );
        }

        $admin['status'] =
            'active';

        $admin['failed_login_count'] =
            0;
    }

    if (
        !password_verify(
            $password,
            (string)$admin['password_hash']
        )
    ) {
        $failure_result =
            pdo_query(
                "
                UPDATE class_share_admin
                SET
                    failed_login_count =
                        failed_login_count + 1,

                    status =
                        CASE
                            WHEN failed_login_count + 1 >= 5
                            THEN 'locked'
                            ELSE status
                        END,

                    locked_until =
                        CASE
                            WHEN failed_login_count + 1 >= 5
                            THEN DATE_ADD(
                                NOW(),
                                INTERVAL 15 MINUTE
                            )
                            ELSE locked_until
                        END

                WHERE id = ?
                  AND status <> 'disabled'
                ",
                $admin_id
            );

        if ($failure_result === false) {
            return array(
                'ok' => false,
                'message' =>
                    '로그인을 처리할 수 없습니다.'
            );
        }

        class_share_admin_record_login_attempt(
            $admin_id,
            $login_id,
            false,
            'invalid_credentials'
        );

        return array(
            'ok' => false,
            'message' =>
                '아이디 또는 비밀번호가 올바르지 않습니다.'
        );
    }

    if (
        password_needs_rehash(
            (string)$admin['password_hash'],
            PASSWORD_DEFAULT
        )
    ) {
        $new_password_hash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        if ($new_password_hash !== false) {
            pdo_query(
                "
                UPDATE class_share_admin
                SET
                    password_hash = ?,
                    password_changed_at = NOW()
                WHERE id = ?
                ",
                $new_password_hash,
                $admin_id
            );
        }
    }

    $success_result =
        pdo_query(
            "
            UPDATE class_share_admin
            SET
                status = 'active',
                failed_login_count = 0,
                locked_until = NULL,
                last_login_at = NOW()
            WHERE id = ?
            ",
            $admin_id
        );

    if ($success_result === false) {
        return array(
            'ok' => false,
            'message' =>
                '로그인을 처리할 수 없습니다.'
        );
    }

    session_regenerate_id(
        true
    );

    unset(
        $_SESSION[
            'class_share_admin_csrf_token'
        ]
    );

    $_SESSION[
        CLASS_SHARE_ADMIN_SESSION_KEY
    ] = array(
        'id' =>
            $admin_id,

        'login_id' =>
            (string)$admin['login_id'],

        'display_name' =>
            (string)$admin['display_name'],

        'is_super_admin' =>
            (int)$admin['is_super_admin'] === 1,

        'logged_in_at' =>
            time(),

        'last_activity' =>
            time()
    );

    class_share_admin_csrf_token();

    class_share_admin_record_login_attempt(
        $admin_id,
        $login_id,
        true,
        null
    );

    return array(
        'ok' => true,
        'message' => '로그인되었습니다.'
    );
}


function class_share_admin_logout()
{
    class_share_admin_clear_session();
}
