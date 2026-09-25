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
    exit('관리자 계정을 수정할 권한이 없습니다.');
}

class_share_admin_require_post_csrf();

$target_admin_id =
    isset($_POST['admin_id'])
    ? (int)$_POST['admin_id']
    : 0;

$display_name =
    isset($_POST['display_name'])
    ? trim((string)$_POST['display_name'])
    : '';

$email =
    isset($_POST['email'])
    ? trim((string)$_POST['email'])
    : '';

$status =
    isset($_POST['status'])
    ? trim((string)$_POST['status'])
    : '';

if ($target_admin_id <= 0) {
    http_response_code(400);
    exit('관리자 번호가 올바르지 않습니다.');
}

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

if (
    $text_length($display_name) < 2 ||
    $text_length($display_name) > 60
) {
    http_response_code(400);
    exit('관리자 이름은 2~60자로 입력해 주세요.');
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
    http_response_code(400);
    exit('이메일 주소 형식을 확인해 주세요.');
}

if (
    !in_array(
        $status,
        array(
            'active',
            'locked',
            'disabled'
        ),
        true
    )
) {
    http_response_code(400);
    exit('관리자 계정 상태가 올바르지 않습니다.');
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
                email,
                is_super_admin,
                status,
                failed_login_count,
                locked_until

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

    if (
        (int)$admin['id'] ===
            $target_admin_id &&
        $status !==
            (string)$target['status']
    ) {
        throw new DomainException(
            '현재 로그인한 자신의 계정 상태는 변경할 수 없습니다.'
        );
    }

    if (
        (int)$target['is_super_admin'] === 1 &&
        (string)$target['status'] === 'active' &&
        $status !== 'active'
    ) {
        $super_admin_rows =
            pdo_query(
                "
                SELECT
                    id,
                    status

                FROM class_share_admin

                WHERE is_super_admin = 1

                FOR UPDATE
                "
            );

        if ($super_admin_rows === false) {
            throw new RuntimeException(
                '최고관리자 계정 상태를 확인할 수 없습니다.'
            );
        }

        $other_active_count =
            0;

        foreach (
            $super_admin_rows as
            $super_admin
        ) {
            if (
                (int)$super_admin['id'] !==
                    $target_admin_id &&
                (string)$super_admin['status'] ===
                    'active'
            ) {
                $other_active_count++;
            }
        }

        if ($other_active_count < 1) {
            throw new DomainException(
                '마지막 활성 최고관리자 계정은 잠그거나 비활성화할 수 없습니다.'
            );
        }
    }

    $email_value =
        $email !== ''
        ? $email
        : null;

    $before_data =
        array(
            'display_name' =>
                (string)$target['display_name'],

            'email' =>
                $target['email'] === null
                ? null
                : (string)$target['email'],

            'status' =>
                (string)$target['status'],

            'failed_login_count' =>
                (int)$target[
                    'failed_login_count'
                ],

            'locked_until' =>
                $target['locked_until'] === null
                ? null
                : (string)$target['locked_until']
        );

    $after_data =
        array(
            'display_name' =>
                $display_name,

            'email' =>
                $email_value,

            'status' =>
                $status,

            'failed_login_count' =>
                0,

            'locked_until' =>
                null
        );

    $changed_fields =
        array();

    foreach (
        $after_data as
        $field_name => $after_value
    ) {
        if (
            $before_data[$field_name] !==
            $after_value
        ) {
            $changed_fields[] =
                $field_name;
        }
    }

    if (count($changed_fields) > 0) {
        $update_result =
            pdo_query(
                "
                UPDATE class_share_admin

                SET
                    display_name = ?,
                    email = ?,
                    status = ?,
                    failed_login_count = 0,
                    locked_until = NULL,
                    updated_at = NOW()

                WHERE id = ?
                ",
                $display_name,
                $email_value,
                $status,
                $target_admin_id
            );

        if ($update_result === false) {
            throw new RuntimeException(
                '관리자 계정을 저장할 수 없습니다.'
            );
        }

        $before_json =
            json_encode(
                $before_data,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        $audit_after_data =
            $after_data;

        $audit_after_data[
            'changed_fields'
        ] =
            $changed_fields;

        $after_json =
            json_encode(
                $audit_after_data,
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
                    'admin.update',
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
                '관리자 계정 감사 기록을 저장할 수 없습니다.'
            );
        }
    }

    $dbh->commit();

    $_SESSION[
        'class_share_admin_flash'
    ] =
        count($changed_fields) > 0
        ? '관리자 계정 정보를 수정했습니다.'
        : '변경된 계정 정보가 없습니다.';

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

    error_log(
        '[class-share] 관리자 계정 수정 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('관리자 계정 정보를 저장하지 못했습니다.');
}
