<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    __DIR__ .
    '/include/class_functions.php'
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

$class_id =
    isset($_POST['class_id'])
    ? (int)$_POST['class_id']
    : 0;

$posted_event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

if (
    $admin_id <= 0 ||
    $class_id <= 0 ||
    $posted_event_id <= 0
) {
    http_response_code(400);
    exit('관리자, 행사 또는 수업 정보가 올바르지 않습니다.');
}

$edit_url =
    '/class-share/admin/class_edit.php' .
    '?class_id=' .
    $class_id;

$redirect_with_errors =
    function (
        $errors,
        $form_values
    ) use (
        $edit_url,
        $class_id
    ) {
        $_SESSION[
            'class_share_class_edit_id'
        ] =
            $class_id;

        $_SESSION[
            'class_share_class_edit_errors'
        ] =
            is_array($errors)
            ? $errors
            : array(
                '입력 내용을 확인해 주세요.'
            );

        $_SESSION[
            'class_share_class_edit_values'
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

$class_rows =
    pdo_query(
        "
        SELECT
            class_item.id,
            class_item.event_id,
            class_item.status,
            event.school_id,
            event.status AS event_status,
            event.event_start_at,
            event.event_end_at,
            event.application_start_at,
            event.application_end_at

        FROM class_share_class AS class_item

        INNER JOIN class_share_event AS event
            ON event.id = class_item.event_id

        WHERE class_item.id = ?

        LIMIT 1
        ",
        $class_id
    );

if ($class_rows === false) {
    http_response_code(500);
    exit('수업 정보를 불러올 수 없습니다.');
}

if (!isset($class_rows[0])) {
    http_response_code(404);
    exit('수업을 찾을 수 없습니다.');
}

$current_class =
    $class_rows[0];

$event_id =
    (int)$current_class['event_id'];

$school_id =
    (int)$current_class['school_id'];

if ($posted_event_id !== $event_id) {
    http_response_code(400);
    exit('수업과 행사 정보가 일치하지 않습니다.');
}

$list_url =
    '/class-share/admin/classes.php' .
    '?event_id=' .
    $event_id;

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 수업을 수정할 권한이 없습니다.');
}

if (
    in_array(
        (string)$current_class['event_status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    ) ||
    in_array(
        (string)$current_class['status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    $redirect_with_errors(
        array(
            '취소되거나 보관된 행사 또는 수업은 수정할 수 없습니다.'
        ),
        $_POST
    );
}

$validation =
    class_share_class_validate_input(
        $_POST,
        $current_class
    );

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

    /*
     * 다른 수업 등록·수정 작업과 순서를 맞추기 위해
     * 행사를 먼저 잠급니다.
     */
    $locked_event_rows =
        pdo_query(
            "
            SELECT
                id,
                school_id,
                status,
                event_start_at,
                event_end_at,
                application_start_at,
                application_end_at

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
            '취소되거나 보관된 행사의 수업은 수정할 수 없습니다.'
        );
    }

    $locked_class_rows =
        pdo_query(
            "
            SELECT
                id,
                event_id,
                public_id,
                subject,
                title,
                teacher_name,
                target,
                class_start_at,
                class_end_at,
                place,
                application_deadline,
                capacity,
                description,
                sort_order,
                status,
                created_by,
                updated_by

            FROM class_share_class

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $class_id
        );

    if (
        $locked_class_rows === false ||
        !isset($locked_class_rows[0])
    ) {
        throw new RuntimeException(
            '수업을 다시 확인할 수 없습니다.'
        );
    }

    $locked_class =
        $locked_class_rows[0];

    if (
        (int)$locked_class['event_id'] !==
        $event_id
    ) {
        throw new DomainException(
            '수업의 소속 행사가 변경되었습니다.'
        );
    }

    if (
        in_array(
            (string)$locked_class['status'],
            array(
                'cancelled',
                'archived'
            ),
            true
        )
    ) {
        throw new DomainException(
            '취소되거나 보관된 수업은 수정할 수 없습니다.'
        );
    }

    /*
     * 행사 시간이 바뀌었을 수 있으므로
     * 잠근 행사 정보로 다시 검증합니다.
     */
    $locked_validation =
        class_share_class_validate_input(
            $_POST,
            $locked_event
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

    $data =
        $locked_validation['data'];

    $application_count_rows =
        pdo_query(
            "
            SELECT
                COUNT(*) AS active_count

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

    if (
        $application_count_rows === false ||
        !isset($application_count_rows[0])
    ) {
        throw new RuntimeException(
            '현재 신청자 수를 확인할 수 없습니다.'
        );
    }

    $active_application_count =
        (int)$application_count_rows[0][
            'active_count'
        ];

    if (
        $data['capacity'] <
        $active_application_count
    ) {
        throw new DomainException(
            '정원은 현재 유효 신청자 ' .
            $active_application_count .
            '명보다 작게 설정할 수 없습니다.'
        );
    }

    $duplicate_rows =
        pdo_query(
            "
            SELECT
                id

            FROM class_share_class

            WHERE event_id = ?
              AND id <> ?
              AND title = ?
              AND teacher_name = ?
              AND class_start_at = ?
              AND place = ?
              AND status NOT IN (
                  'cancelled',
                  'archived'
              )

            LIMIT 1
            ",
            $event_id,
            $class_id,
            $data['title'],
            $data['teacher_name'],
            $data['class_start_at'],
            $data['place']
        );

    if ($duplicate_rows === false) {
        throw new RuntimeException(
            '중복 수업을 확인할 수 없습니다.'
        );
    }

    if (isset($duplicate_rows[0])) {
        throw new DomainException(
            '같은 수업명, 교사명, 시작일시와 장소를 가진 수업이 이미 등록되어 있습니다.'
        );
    }

    $before_data =
        array(
            'subject' =>
                (string)$locked_class['subject'],

            'title' =>
                (string)$locked_class['title'],

            'teacher_name' =>
                (string)$locked_class[
                    'teacher_name'
                ],

            'target' =>
                (string)$locked_class['target'],

            'class_start_at' =>
                (string)$locked_class[
                    'class_start_at'
                ],

            'class_end_at' =>
                $locked_class['class_end_at'] ===
                    null
                ? null
                : (string)$locked_class[
                    'class_end_at'
                ],

            'place' =>
                (string)$locked_class['place'],

            'application_deadline' =>
                (string)$locked_class[
                    'application_deadline'
                ],

            'capacity' =>
                (int)$locked_class['capacity'],

            'description' =>
                (string)$locked_class[
                    'description'
                ],

            'sort_order' =>
                (int)$locked_class['sort_order'],

            'status' =>
                (string)$locked_class['status']
        );

    $after_data =
        array(
            'subject' =>
                $data['subject'],

            'title' =>
                $data['title'],

            'teacher_name' =>
                $data['teacher_name'],

            'target' =>
                $data['target'],

            'class_start_at' =>
                $data['class_start_at'],

            'class_end_at' =>
                $data['class_end_at'],

            'place' =>
                $data['place'],

            'application_deadline' =>
                $data[
                    'application_deadline'
                ],

            'capacity' =>
                $data['capacity'],

            'description' =>
                $data['description'],

            'sort_order' =>
                $data['sort_order'],

            'status' =>
                (string)$locked_class['status']
        );

    $changed_fields =
        array();

    foreach (
        $after_data as
        $field_name => $after_value
    ) {
        $before_value =
            isset($before_data[$field_name]) ||
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
                UPDATE class_share_class

                SET
                    subject = ?,
                    title = ?,
                    teacher_name = ?,
                    target = ?,
                    class_start_at = ?,
                    class_end_at = ?,
                    place = ?,
                    application_deadline = ?,
                    capacity = ?,
                    description = ?,
                    sort_order = ?,
                    updated_by = ?

                WHERE id = ?
                  AND event_id = ?
                ",
                $data['subject'],
                $data['title'],
                $data['teacher_name'],
                $data['target'],
                $data['class_start_at'],
                $data['class_end_at'],
                $data['place'],
                $data[
                    'application_deadline'
                ],
                $data['capacity'],
                $data['description'],
                $data['sort_order'],
                $admin_id,
                $class_id,
                $event_id
            );

        if (
            $update_result === false ||
            (int)$update_result !== 1
        ) {
            throw new RuntimeException(
                '수업 변경 내용을 저장할 수 없습니다.'
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
                    'class.update',
                    'class',
                    ?,
                    ?,
                    ?,
                    ?
                )
                ",
                $school_id,
                $admin_id,
                $class_id,
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
        '[class-share] 수업 수정 실패: ' .
        $exception->getMessage()
    );

    $redirect_with_errors(
        array(
            '수업 변경 내용을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $form_values
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    $was_updated
    ? '수업 정보를 수정했습니다.'
    : '변경된 내용이 없습니다.';

header(
    'Location: ' . $list_url,
    true,
    303
);

exit;
