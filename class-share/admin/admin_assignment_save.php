<?php

require_once(__DIR__ . '/include/admin_init.php');

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    http_response_code(405);
    exit('POST 요청만 허용됩니다.');
}

$admin = class_share_admin_require_login();

if (!class_share_admin_is_super_admin($admin)) {
    http_response_code(403);
    exit('관리자 권한을 배정할 권한이 없습니다.');
}

class_share_admin_require_post_csrf();

$target_admin_id = isset($_POST['admin_id'])
    ? (int)$_POST['admin_id']
    : 0;

$posted_roles = isset($_POST['roles']) &&
    is_array($_POST['roles'])
    ? $_POST['roles']
    : array();

if ($target_admin_id <= 0) {
    http_response_code(400);
    exit('관리자 번호가 올바르지 않습니다.');
}

$target_rows = pdo_query(
    "SELECT id, display_name, is_super_admin
     FROM class_share_admin
     WHERE id = ?
     LIMIT 1",
    $target_admin_id
);

if ($target_rows === false) {
    http_response_code(500);
    exit('관리자 계정을 확인할 수 없습니다.');
}

if (!isset($target_rows[0])) {
    http_response_code(404);
    exit('관리자 계정을 찾을 수 없습니다.');
}

if ((int)$target_rows[0]['is_super_admin'] === 1) {
    http_response_code(400);
    exit('최고관리자는 학교별 역할을 배정하지 않습니다.');
}

$school_rows = pdo_query(
    "SELECT id
     FROM class_share_school
     ORDER BY id"
);

if ($school_rows === false) {
    http_response_code(500);
    exit('학교 목록을 확인할 수 없습니다.');
}

$school_ids = array();

foreach ($school_rows as $school) {
    $school_ids[] = (int)$school['id'];
}

$allowed_roles = array(
    '',
    'school_admin',
    'editor',
    'viewer'
);

$desired_roles = array();

foreach ($school_ids as $school_id) {
    $role = isset($posted_roles[$school_id])
        ? trim((string)$posted_roles[$school_id])
        : '';

    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(400);
        exit('학교 역할 값이 올바르지 않습니다.');
    }

    $desired_roles[$school_id] = $role;
}

foreach ($posted_roles as $school_id => $role) {
    if (
        !preg_match(
            '/^[1-9][0-9]*$/D',
            (string)$school_id
        ) ||
        !in_array(
            (int)$school_id,
            $school_ids,
            true
        )
    ) {
        http_response_code(400);
        exit('존재하지 않는 학교가 포함되어 있습니다.');
    }
}

try {
    global $dbh;

    if (!($dbh instanceof PDO)) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $locked_rows = pdo_query(
        "SELECT id, display_name, is_super_admin
         FROM class_share_admin
         WHERE id = ?
         LIMIT 1
         FOR UPDATE",
        $target_admin_id
    );

    if (
        $locked_rows === false ||
        !isset($locked_rows[0])
    ) {
        throw new RuntimeException(
            '관리자 계정을 잠글 수 없습니다.'
        );
    }

    if ((int)$locked_rows[0]['is_super_admin'] === 1) {
        throw new DomainException(
            '최고관리자에는 학교 권한을 배정할 수 없습니다.'
        );
    }

    $current_rows = pdo_query(
        "SELECT school_id, role, active
         FROM class_share_admin_school
         WHERE admin_id = ?
         FOR UPDATE",
        $target_admin_id
    );

    if ($current_rows === false) {
        throw new RuntimeException(
            '현재 학교 권한을 잠글 수 없습니다.'
        );
    }

    $current_by_school = array();
    $before_roles = array();

    foreach ($current_rows as $assignment) {
        $school_id = (int)$assignment['school_id'];
        $current_by_school[$school_id] = $assignment;

        if ((int)$assignment['active'] === 1) {
            $before_roles[$school_id] =
                (string)$assignment['role'];
        }
    }

    $after_roles = array();

    foreach ($desired_roles as $school_id => $role) {
        if ($role !== '') {
            $after_roles[$school_id] = $role;
        }
    }

    ksort($before_roles);
    ksort($after_roles);

    if ($before_roles !== $after_roles) {
        foreach ($desired_roles as $school_id => $role) {
            if ($role === '') {
                if (
                    isset($current_by_school[$school_id]) &&
                    (int)$current_by_school[$school_id][
                        'active'
                    ] === 1
                ) {
                    $result = pdo_query(
                        "UPDATE class_share_admin_school
                         SET
                             active = 0,
                             updated_at = NOW()
                         WHERE admin_id = ?
                           AND school_id = ?",
                        $target_admin_id,
                        $school_id
                    );

                    if ($result === false) {
                        throw new RuntimeException(
                            '학교 권한을 해제할 수 없습니다.'
                        );
                    }
                }

                continue;
            }

            $result = pdo_query(
                "INSERT INTO class_share_admin_school
                 (
                     admin_id,
                     school_id,
                     role,
                     active,
                     created_at,
                     updated_at
                 )
                 VALUES
                 (
                     ?,
                     ?,
                     ?,
                     1,
                     NOW(),
                     NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                     role = VALUES(role),
                     active = 1,
                     updated_at = NOW()",
                $target_admin_id,
                $school_id,
                $role
            );

            if ($result === false) {
                throw new RuntimeException(
                    '학교 권한을 저장할 수 없습니다.'
                );
            }
        }

        $before_json = json_encode(
            array(
                'assignments' => $before_roles
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        $after_json = json_encode(
            array(
                'assignments' => $after_roles,
                'changed_fields' => array(
                    'school_assignments'
                )
            ),
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

        $audit_result = pdo_query(
            "INSERT INTO class_share_audit_log
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
                 'admin.assign',
                 'admin',
                 ?,
                 ?,
                 ?,
                 ?,
                 NOW()
             )",
            (int)$admin['id'],
            $target_admin_id,
            $before_json,
            $after_json,
            class_share_admin_client_ip()
        );

        if ($audit_result === false) {
            throw new RuntimeException(
                '학교 권한 감사 기록을 저장할 수 없습니다.'
            );
        }
    }

    $dbh->commit();

    $_SESSION['class_share_admin_flash'] =
        $before_roles === $after_roles
        ? '변경된 학교 권한이 없습니다.'
        : (string)$locked_rows[0]['display_name'] .
            ' 관리자의 학교 권한을 저장했습니다.';

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
        '[class-share] 관리자 학교 권한 저장 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('학교 권한을 저장하지 못했습니다.');
}
