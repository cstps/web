<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    dirname(__DIR__) .
    '/include/application_functions.php'
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

class_share_admin_require_post_csrf();

$admin_id =
    isset($admin['id'])
    ? (int)$admin['id']
    : 0;

$application_id =
    isset($_POST['application_id'])
    ? (int)$_POST['application_id']
    : 0;

$posted_event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

$requested_status =
    isset($_POST['status'])
    ? trim((string)$_POST['status'])
    : '';

$admin_note =
    isset($_POST['admin_note'])
    ? trim(
        str_replace(
            array("\r\n", "\r"),
            "\n",
            (string)$_POST['admin_note']
        )
    )
    : '';

$allowed_statuses =
    array(
        'applied',
        'approved',
        'waiting',
        'rejected',
        'attended',
        'absent',
        'cancelled'
    );

if (
    $admin_id <= 0 ||
    $application_id <= 0 ||
    $posted_event_id <= 0
) {
    http_response_code(400);
    exit('관리자, 행사 또는 신청 정보가 올바르지 않습니다.');
}

$edit_url =
    '/class-share/admin/application_edit.php' .
    '?application_id=' .
    rawurlencode(
        (string)$application_id
    );

$redirect_with_message =
    function ($message) use ($edit_url) {
        $_SESSION[
            'class_share_admin_flash'
        ] =
            (string)$message;

        session_write_close();

        header(
            'Location: ' .
            $edit_url,
            true,
            303
        );

        exit;
    };

if (
    !in_array(
        $requested_status,
        $allowed_statuses,
        true
    )
) {
    $redirect_with_message(
        '신청 상태가 올바르지 않습니다.'
    );
}

$note_length =
    function_exists('mb_strlen')
    ? mb_strlen(
        $admin_note,
        'UTF-8'
    )
    : strlen($admin_note);

if ($note_length > 5000) {
    $redirect_with_message(
        '관리자 메모는 5,000자 이하로 입력해 주세요.'
    );
}

$current_rows =
    pdo_query(
        "
        SELECT
            application.id,
            application.event_id,
            application.privacy_destroyed_at,
            event.school_id

        FROM class_share_application AS application

        INNER JOIN class_share_event AS event
            ON event.id =
               application.event_id

        WHERE application.id = ?

        LIMIT 1
        ",
        $application_id
    );

if (
    $current_rows === false ||
    !isset($current_rows[0])
) {
    http_response_code(404);
    exit('신청 정보를 찾을 수 없습니다.');
}

$school_id =
    (int)$current_rows[0]['school_id'];

$event_id =
    (int)$current_rows[0]['event_id'];

if ($posted_event_id !== $event_id) {
    http_response_code(400);
    exit('행사와 신청 정보가 일치하지 않습니다.');
}

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 신청 정보를 변경할 권한이 없습니다.');
}

if (
    $current_rows[0]['privacy_destroyed_at'] !== null
) {
    http_response_code(410);
    exit('개인정보가 파기된 신청은 변경할 수 없습니다.');
}

if (!($dbh instanceof PDO)) {
    http_response_code(500);
    exit('DB 연결이 준비되지 않았습니다.');
}

$ip_address =
    isset($_SERVER['REMOTE_ADDR'])
    ? trim((string)$_SERVER['REMOTE_ADDR'])
    : '';

if (
    $ip_address === '' ||
    strlen($ip_address) > 45
) {
    $ip_address =
        null;
}

try {
    $dbh->beginTransaction();

    $event_rows =
        pdo_query(
            "
            SELECT
                id,
                school_id,
                application_capacity

            FROM class_share_event

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $event_id
        );

    if (
        $event_rows === false ||
        !isset($event_rows[0]) ||
        (int)$event_rows[0]['school_id'] !==
            $school_id
    ) {
        throw new RuntimeException(
            '행사 정보를 다시 확인할 수 없습니다.'
        );
    }

    $locked_rows =
        pdo_query(
            "
            SELECT
                id,
                event_id,
                application_scope,
                class_id,
                phone_lookup_hash,
                status,
                admin_note,
                privacy_destroyed_at

            FROM class_share_application

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $application_id
        );

    if (
        $locked_rows === false ||
        !isset($locked_rows[0])
    ) {
        throw new RuntimeException(
            '신청 정보를 다시 확인할 수 없습니다.'
        );
    }

    $current =
        $locked_rows[0];

    if (
        (int)$current['event_id'] !==
        $event_id
    ) {
        throw new RuntimeException(
            '행사와 신청 정보가 일치하지 않습니다.'
        );
    }

    if (
        $current['privacy_destroyed_at'] !== null
    ) {
        throw new DomainException(
            '개인정보가 파기된 신청은 변경할 수 없습니다.'
        );
    }

    $current_status =
        (string)$current['status'];

    $current_note =
        $current['admin_note'] === null
        ? ''
        : (string)$current['admin_note'];

    $active_statuses =
        class_share_application_active_statuses();

    $was_active =
        in_array(
            $current_status,
            $active_statuses,
            true
        );

    $will_be_active =
        in_array(
            $requested_status,
            $active_statuses,
            true
        );

    if (
        !$was_active &&
        $will_be_active
    ) {
        if (
            (string)$current[
                'application_scope'
            ] === 'event'
        ) {
            $duplicate_rows =
                pdo_query(
                    "
                    SELECT COUNT(*) AS duplicate_count
                    FROM class_share_application
                    WHERE event_id = ?
                      AND application_scope = 'event'
                      AND phone_lookup_hash = ?
                      AND id <> ?
                      AND status IN (
                          'applied',
                          'approved',
                          'waiting'
                      )
                    ",
                    $event_id,
                    (string)$current[
                        'phone_lookup_hash'
                    ],
                    $application_id
                );

            $count_rows =
                pdo_query(
                    "
                    SELECT COUNT(*) AS active_count
                    FROM class_share_application
                    WHERE event_id = ?
                      AND application_scope = 'event'
                      AND status IN (
                          'applied',
                          'approved',
                          'waiting'
                      )
                    ",
                    $event_id
                );

            $capacity =
                $event_rows[0][
                    'application_capacity'
                ] === null
                ? null
                : (int)$event_rows[0][
                    'application_capacity'
                ];
        } else {
            $class_id =
                (int)$current['class_id'];

            $class_rows =
                pdo_query(
                    "
                    SELECT capacity
                    FROM class_share_class
                    WHERE id = ?
                      AND event_id = ?
                    LIMIT 1
                    FOR UPDATE
                    ",
                    $class_id,
                    $event_id
                );

            if (
                $class_rows === false ||
                !isset($class_rows[0])
            ) {
                throw new RuntimeException(
                    '프로그램 정보를 확인할 수 없습니다.'
                );
            }

            $duplicate_rows =
                pdo_query(
                    "
                    SELECT COUNT(*) AS duplicate_count
                    FROM class_share_application
                    WHERE class_id = ?
                      AND phone_lookup_hash = ?
                      AND id <> ?
                      AND status IN (
                          'applied',
                          'approved',
                          'waiting'
                      )
                    ",
                    $class_id,
                    (string)$current[
                        'phone_lookup_hash'
                    ],
                    $application_id
                );

            $count_rows =
                pdo_query(
                    "
                    SELECT COUNT(*) AS active_count
                    FROM class_share_application
                    WHERE class_id = ?
                      AND status IN (
                          'applied',
                          'approved',
                          'waiting'
                      )
                    ",
                    $class_id
                );

            $capacity =
                (int)$class_rows[0][
                    'capacity'
                ];
        }

        if (
            $duplicate_rows === false ||
            !isset($duplicate_rows[0]) ||
            $count_rows === false ||
            !isset($count_rows[0])
        ) {
            throw new RuntimeException(
                '신청 중복 또는 정원을 확인할 수 없습니다.'
            );
        }

        if (
            (int)$duplicate_rows[0][
                'duplicate_count'
            ] > 0
        ) {
            throw new DomainException(
                '같은 연락처의 활성 신청이 이미 존재합니다.'
            );
        }

        if (
            $capacity !== null &&
            (int)$count_rows[0][
                'active_count'
            ] >= $capacity
        ) {
            throw new DomainException(
                '현재 정원이 가득 차 있어 활성 상태로 변경할 수 없습니다.'
            );
        }
    }

    $changed_fields =
        array();

    if ($current_status !== $requested_status) {
        $changed_fields[] =
            'status';
    }

    if ($current_note !== $admin_note) {
        $changed_fields[] =
            'admin_note';
    }

    if (count($changed_fields) === 0) {
        $dbh->commit();

        $redirect_with_message(
            '변경된 내용이 없습니다.'
        );
    }

    $update_result =
        pdo_query(
            "
            UPDATE class_share_application

            SET
                status = ?,
                cancelled_at =
                    CASE
                        WHEN ? = 'cancelled'
                        THEN COALESCE(
                            cancelled_at,
                            NOW()
                        )
                        ELSE NULL
                    END,
                processed_by = ?,
                admin_note = ?,
                updated_at = NOW()

            WHERE id = ?
              AND event_id = ?
            ",
            $requested_status,
            $requested_status,
            $admin_id,
            $admin_note === ''
            ? null
            : $admin_note,
            $application_id,
            $event_id
        );

    if (
        $update_result === false ||
        (int)$update_result !== 1
    ) {
        throw new RuntimeException(
            '신청 변경 내용을 저장할 수 없습니다.'
        );
    }

    $before_json =
        json_encode(
            array(
                'event_id' =>
                    $event_id,

                'status' =>
                    $current_status,

                'admin_note_present' =>
                    $current_note !== ''
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    $after_json =
        json_encode(
            array(
                'event_id' =>
                    $event_id,

                'status' =>
                    $requested_status,

                'admin_note_present' =>
                    $admin_note !== '',

                'changed_fields' =>
                    $changed_fields
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
                ?,
                ?,
                'admin',
                'application.update',
                'application',
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
            ",
            $school_id,
            $admin_id,
            $application_id,
            $before_json,
            $after_json,
            $ip_address
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '신청 변경 감사 기록을 저장할 수 없습니다.'
        );
    }

    $dbh->commit();

    $redirect_with_message(
        '신청 처리 상태를 저장했습니다.'
    );
} catch (DomainException $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    $redirect_with_message(
        $e->getMessage()
    );
} catch (Throwable $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    error_log(
        '[class-share] 신청 상태 변경 실패: ' .
        $e->getMessage()
    );

    $redirect_with_message(
        '신청 정보를 저장하는 중 오류가 발생했습니다.'
    );
}
