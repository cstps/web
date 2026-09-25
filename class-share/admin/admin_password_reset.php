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
    exit('관리자 비밀번호를 재설정할 권한이 없습니다.');
}

class_share_admin_require_post_csrf();

$target_admin_id =
    isset($_POST['admin_id'])
    ? (int)$_POST['admin_id']
    : 0;

$new_password =
    isset($_POST['new_password'])
    ? (string)$_POST['new_password']
    : '';

$new_password_confirmation =
    isset($_POST['new_password_confirmation'])
    ? (string)$_POST[
        'new_password_confirmation'
    ]
    : '';

if ($target_admin_id <= 0) {
    http_response_code(400);
    exit('관리자 번호가 올바르지 않습니다.');
}

if (
    strlen($new_password) < 12 ||
    strlen($new_password) > 128
) {
    http_response_code(400);
    exit('새 비밀번호는 12~128자로 입력해 주세요.');
}

if (
    !hash_equals(
        $new_password,
        $new_password_confirmation
    )
) {
    http_response_code(400);
    exit('새 비밀번호 확인이 일치하지 않습니다.');
}

$password_hash =
    password_hash(
        $new_password,
        PASSWORD_DEFAULT
    );

$new_password = null;
$new_password_confirmation = null;

if ($password_hash === false) {
    http_response_code(500);
    exit('비밀번호를 안전하게 저장할 수 없습니다.');
}

try {
    global $dbh;

    if (!($dbh instanceof PDO)) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $target_rows =
        pdo_query(
            "
            SELECT
                id,
                login_id,
                display_name,
                status,
                failed_login_count,
                locked_until,
                password_changed_at

            FROM class_share_admin

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $target_admin_id
        );

    if (
        $target_rows === false ||
        !isset($target_rows[0])
    ) {
        throw new DomainException(
            '관리자 계정을 찾을 수 없습니다.'
        );
    }

    $target =
        $target_rows[0];

    $before_status =
        (string)$target['status'];

    $after_status =
        $before_status === 'locked'
        ? 'active'
        : $before_status;

    $update_result =
        pdo_query(
            "
            UPDATE class_share_admin

            SET
                password_hash = ?,

                password_changed_at =
                    CASE
                        WHEN password_changed_at >= NOW()
                        THEN DATE_ADD(
                            password_changed_at,
                            INTERVAL 1 SECOND
                        )
                        ELSE NOW()
                    END,

                status = ?,
                failed_login_count = 0,
                locked_until = NULL,
                updated_at = NOW()

            WHERE id = ?
            ",
            $password_hash,
            $after_status,
            $target_admin_id
        );

    $password_hash = null;

    if ($update_result === false) {
        throw new RuntimeException(
            '관리자 비밀번호를 저장할 수 없습니다.'
        );
    }

    $changed_rows =
        pdo_query(
            "
            SELECT
                status,
                password_changed_at

            FROM class_share_admin

            WHERE id = ?

            LIMIT 1
            ",
            $target_admin_id
        );

    if (
        $changed_rows === false ||
        !isset($changed_rows[0])
    ) {
        throw new RuntimeException(
            '변경된 관리자 계정을 확인할 수 없습니다.'
        );
    }

    $before_data =
        array(
            'status' =>
                $before_status,

            'failed_login_count' =>
                (int)$target[
                    'failed_login_count'
                ],

            'locked_until' =>
                $target['locked_until'] === null
                ? null
                : (string)$target['locked_until'],

            'password_changed_at' =>
                (string)$target[
                    'password_changed_at'
                ]
        );

    $after_data =
        array(
            'status' =>
                (string)$changed_rows[0]['status'],

            'failed_login_count' =>
                0,

            'locked_until' =>
                null,

            'password_changed_at' =>
                (string)$changed_rows[0][
                    'password_changed_at'
                ],

            'changed_fields' =>
                array(
                    'password',
                    'password_changed_at',
                    'failed_login_count',
                    'locked_until'
                )
        );

    if ($before_status !== $after_status) {
        $after_data[
            'changed_fields'
        ][] =
            'status';
    }

    $before_json =
        json_encode(
            $before_data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    $after_json =
        json_encode(
            $after_data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if (
        $before_json === false ||
        $after_json === false
    ) {
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
                'admin.password_reset',
                'admin',
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
            ",
            (int)$admin['id'],
            $target_admin_id,
            $before_json,
            $after_json,
            class_share_admin_client_ip()
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '비밀번호 재설정 감사 기록을 저장할 수 없습니다.'
        );
    }

    $dbh->commit();

    $_SESSION[
        'class_share_admin_flash'
    ] =
        (string)$target['display_name'] .
        ' 관리자의 비밀번호를 재설정했습니다.';

    session_write_close();

    header(
        'Location: /class-share/admin/admins.php',
        true,
        303
    );

    exit;
} catch (DomainException $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    http_response_code(400);
    exit($e->getMessage());
} catch (Throwable $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    $password_hash = null;

    error_log(
        '[class-share] 관리자 비밀번호 재설정 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('관리자 비밀번호를 재설정하지 못했습니다.');
}
