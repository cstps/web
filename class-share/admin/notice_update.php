<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    __DIR__ .
    '/include/notice_functions.php'
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

$notice_id =
    isset($_POST['notice_id'])
    ? (int)$_POST['notice_id']
    : 0;

$posted_event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

$requested_status =
    isset($_POST['status'])
    ? trim(
        (string)$_POST['status']
    )
    : '';

if (
    $admin_id <= 0 ||
    $notice_id <= 0 ||
    $posted_event_id <= 0
) {
    http_response_code(400);
    exit('관리자, 행사 또는 공지 정보가 올바르지 않습니다.');
}

$edit_url =
    '/class-share/admin/notice_edit.php' .
    '?notice_id=' .
    $notice_id;

$redirect_with_errors =
    function (
        $errors,
        $form_values
    ) use (
        $edit_url,
        $notice_id
    ) {
        $_SESSION[
            'class_share_notice_edit_id'
        ] =
            $notice_id;

        $_SESSION[
            'class_share_notice_edit_errors'
        ] =
            is_array($errors)
            ? $errors
            : array(
                '입력 내용을 확인해 주세요.'
            );

        $_SESSION[
            'class_share_notice_edit_values'
        ] =
            is_array($form_values)
            ? $form_values
            : array();

        header(
            'Location: ' . $edit_url,
            true,
            303
        );

        exit;
    };

$notice_rows =
    pdo_query(
        "
        SELECT
            notice.id,
            notice.event_id,
            notice.status,
            event.school_id,
            event.status AS event_status

        FROM class_share_notice AS notice

        INNER JOIN class_share_event AS event
            ON event.id = notice.event_id

        WHERE notice.id = ?

        LIMIT 1
        ",
        $notice_id
    );

if ($notice_rows === false) {
    http_response_code(500);
    exit('공지 정보를 불러올 수 없습니다.');
}

if (!isset($notice_rows[0])) {
    http_response_code(404);
    exit('공지를 찾을 수 없습니다.');
}

$current_notice =
    $notice_rows[0];

$event_id =
    (int)$current_notice['event_id'];

$school_id =
    (int)$current_notice['school_id'];

if ($posted_event_id !== $event_id) {
    http_response_code(400);
    exit('공지와 행사 정보가 일치하지 않습니다.');
}

$list_url =
    '/class-share/admin/notices.php' .
    '?event_id=' .
    $event_id;

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 공지를 수정할 권한이 없습니다.');
}

if (
    in_array(
        (string)$current_notice['event_status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    $redirect_with_errors(
        array(
            '취소되거나 보관된 행사의 공지는 수정할 수 없습니다.'
        ),
        $_POST
    );
}

$allowed_statuses =
    array(
        'draft',
        'published',
        'archived'
    );

$validation =
    class_share_notice_validate_input(
        $_POST
    );

$validation['form_values']['status'] =
    $requested_status;

if (
    !in_array(
        $requested_status,
        $allowed_statuses,
        true
    )
) {
    $validation['errors'][] =
        '공지 공개 상태가 올바르지 않습니다.';
}

if (
    count(
        $validation['errors']
    ) > 0
) {
    $redirect_with_errors(
        $validation['errors'],
        $validation['form_values']
    );
}

$form_values =
    $validation['form_values'];

$ip_address =
    isset($_SERVER['REMOTE_ADDR'])
    ? trim(
        (string)$_SERVER['REMOTE_ADDR']
    )
    : '';

if (
    $ip_address === '' ||
    filter_var(
        $ip_address,
        FILTER_VALIDATE_IP
    ) === false
) {
    $ip_address =
        null;
} else {
    $ip_address =
        substr(
            $ip_address,
            0,
            45
        );
}

$was_updated =
    false;

try {
    if (
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException(
            '데이터베이스 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $locked_event_rows =
        pdo_query(
            "
            SELECT
                id,
                school_id,
                status

            FROM class_share_event

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $event_id
        );

    if (
        $locked_event_rows === false ||
        !isset($locked_event_rows[0])
    ) {
        throw new RuntimeException(
            '행사를 다시 확인할 수 없습니다.'
        );
    }

    $locked_event =
        $locked_event_rows[0];

    if (
        (int)$locked_event['school_id'] !==
        $school_id
    ) {
        throw new DomainException(
            '행사의 소속 학교가 변경되었습니다.'
        );
    }

    if (
        in_array(
            (string)$locked_event['status'],
            array(
                'cancelled',
                'archived'
            ),
            true
        )
    ) {
        throw new DomainException(
            '취소되거나 보관된 행사의 공지는 수정할 수 없습니다.'
        );
    }

    $locked_notice_rows =
        pdo_query(
            "
            SELECT
                id,
                event_id,
                title,
                content,
                important,
                status,
                published_at,
                sort_order

            FROM class_share_notice

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $notice_id
        );

    if (
        $locked_notice_rows === false ||
        !isset($locked_notice_rows[0])
    ) {
        throw new RuntimeException(
            '공지를 다시 확인할 수 없습니다.'
        );
    }

    $locked_notice =
        $locked_notice_rows[0];

    if (
        (int)$locked_notice['event_id'] !==
        $event_id
    ) {
        throw new DomainException(
            '공지의 소속 행사가 변경되었습니다.'
        );
    }

    $locked_validation =
        class_share_notice_validate_input(
            $_POST
        );

    if (
        count(
            $locked_validation['errors']
        ) > 0
    ) {
        throw new DomainException(
            (string)$locked_validation[
                'errors'
            ][0]
        );
    }

    if (
        !in_array(
            $requested_status,
            $allowed_statuses,
            true
        )
    ) {
        throw new DomainException(
            '공지 공개 상태가 올바르지 않습니다.'
        );
    }

    $data =
        $locked_validation['data'];

    $published_at =
        $locked_notice['published_at'] ===
            null
        ? null
        : (string)$locked_notice[
            'published_at'
        ];

    /*
     * 처음 공개할 때만 게시일을 생성합니다.
     * 이후 작성 중·보관으로 전환해도 최초 게시일은 보존합니다.
     */
    if (
        $requested_status === 'published' &&
        $published_at === null
    ) {
        $published_at =
            (
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
                        'Asia/Seoul'
                    )
                )
            )->format(
                'Y-m-d H:i:s'
            );
    }

    $before_data =
        array(
            'title' =>
                (string)$locked_notice['title'],

            'content' =>
                (string)$locked_notice['content'],

            'important' =>
                (int)$locked_notice['important'],

            'status' =>
                (string)$locked_notice['status'],

            'published_at' =>
                $locked_notice['published_at'] ===
                    null
                ? null
                : (string)$locked_notice[
                    'published_at'
                ],

            'sort_order' =>
                (int)$locked_notice['sort_order']
        );

    $after_data =
        array(
            'title' =>
                $data['title'],

            'content' =>
                $data['content'],

            'important' =>
                $data['important'],

            'status' =>
                $requested_status,

            'published_at' =>
                $published_at,

            'sort_order' =>
                $data['sort_order']
        );

    $changed_fields =
        array();

    foreach (
        $after_data as
        $field_name => $after_value
    ) {
        $before_value =
            array_key_exists(
                $field_name,
                $before_data
            )
            ? $before_data[$field_name]
            : null;

        if ($before_value !== $after_value) {
            $changed_fields[] =
                $field_name;
        }
    }

    if (count($changed_fields) === 0) {
        $dbh->commit();
    } else {
        $update_result =
            pdo_query(
                "
                UPDATE class_share_notice

                SET
                    title = ?,
                    content = ?,
                    important = ?,
                    status = ?,
                    published_at = ?,
                    sort_order = ?,
                    updated_by = ?

                WHERE id = ?
                  AND event_id = ?
                ",
                $data['title'],
                $data['content'],
                $data['important'],
                $requested_status,
                $published_at,
                $data['sort_order'],
                $admin_id,
                $notice_id,
                $event_id
            );

        if (
            $update_result === false ||
            (int)$update_result !== 1
        ) {
            throw new RuntimeException(
                '공지 변경 내용을 저장할 수 없습니다.'
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
                '감사 로그 데이터를 만들 수 없습니다.'
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
                    ip_address
                )
                VALUES
                (
                    ?,
                    ?,
                    'admin',
                    'notice.update',
                    'notice',
                    ?,
                    ?,
                    ?,
                    ?
                )
                ",
                $school_id,
                $admin_id,
                $notice_id,
                $before_json,
                $after_json,
                $ip_address
            );

        if ($audit_result === false) {
            throw new RuntimeException(
                '감사 로그를 저장할 수 없습니다.'
            );
        }

        $dbh->commit();

        $was_updated =
            true;
    }
} catch (DomainException $exception) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    $redirect_with_errors(
        array(
            $exception->getMessage()
        ),
        $form_values
    );
} catch (Throwable $exception) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        '[class-share] 공지 수정 실패: ' .
        $exception->getMessage()
    );

    $redirect_with_errors(
        array(
            '공지 변경 내용을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $form_values
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    $was_updated
    ? '공지 정보를 수정했습니다.'
    : '변경된 내용이 없습니다.';

header(
    'Location: ' . $list_url,
    true,
    303
);

exit;
