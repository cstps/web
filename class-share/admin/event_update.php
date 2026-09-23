<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    __DIR__ .
    '/include/event_functions.php'
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

$event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

$posted_school_id =
    isset($_POST['school_id'])
    ? (int)$_POST['school_id']
    : 0;

if (
    $admin_id <= 0 ||
    $event_id <= 0 ||
    $posted_school_id <= 0
) {
    http_response_code(400);
    exit('관리자, 학교 또는 행사 정보가 올바르지 않습니다.');
}

$edit_url =
    '/class-share/admin/event_edit.php' .
    '?event_id=' .
    $event_id;

$redirect_with_errors =
    function (
        $errors,
        $form_values
    ) use (
        $edit_url,
        $event_id
    ) {
        $_SESSION[
            'class_share_event_edit_id'
        ] =
            $event_id;

        $_SESSION[
            'class_share_event_edit_errors'
        ] =
            is_array($errors)
            ? $errors
            : array(
                '입력 내용을 확인해 주세요.'
            );

        $_SESSION[
            'class_share_event_edit_values'
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

$current_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id,
            event.slug,
            school.status AS school_status

        FROM class_share_event AS event

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE event.id = ?

        LIMIT 1
        ",
        $event_id
    );

if ($current_rows === false) {
    http_response_code(500);
    exit('행사 정보를 불러올 수 없습니다.');
}

if (!isset($current_rows[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

$current_event =
    $current_rows[0];

$school_id =
    (int)$current_event['school_id'];

if ($posted_school_id !== $school_id) {
    http_response_code(400);
    exit('행사와 학교 정보가 일치하지 않습니다.');
}

$list_url =
    '/class-share/admin/events.php' .
    '?school_id=' .
    $school_id;

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 행사를 수정할 권한이 없습니다.');
}

$validation =
    class_share_event_validate_input(
        $_POST
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

    $locked_event_rows =
        pdo_query(
            "
            SELECT
                id,
                school_id,
                academic_year,
                slug,
                title,
                subtitle,
                event_start_at,
                event_end_at,
                application_start_at,
                application_end_at,
                privacy_policy_version,
                privacy_notice,
                retention_until,
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

    $locked_school_rows =
        pdo_query(
            "
            SELECT
                id,
                status

            FROM class_share_school

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
            ",
            $school_id
        );

    if (
        $locked_school_rows === false ||
        !isset($locked_school_rows[0])
    ) {
        throw new RuntimeException(
            '학교 상태를 다시 확인할 수 없습니다.'
        );
    }

    $school_status =
        (string)$locked_school_rows[0][
            'status'
        ];

    $locked_validation =
        class_share_event_validate_input(
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

    $data =
        $locked_validation['data'];

    $duplicate_rows =
        pdo_query(
            "
            SELECT
                id

            FROM class_share_event

            WHERE school_id = ?
              AND slug = ?
              AND id <> ?

            LIMIT 1
            ",
            $school_id,
            $data['slug'],
            $event_id
        );

    if ($duplicate_rows === false) {
        throw new RuntimeException(
            '행사 주소 중복 여부를 확인할 수 없습니다.'
        );
    }

    if (isset($duplicate_rows[0])) {
        throw new DomainException(
            '같은 학교에서 이미 사용 중인 행사 주소 식별자입니다.'
        );
    }

    $application_count_rows =
        pdo_query(
            "
            SELECT
                COUNT(*) AS application_count

            FROM class_share_application AS application

            INNER JOIN class_share_class AS class_item
                ON class_item.id = application.class_id

            WHERE class_item.event_id = ?
            ",
            $event_id
        );

    if (
        $application_count_rows === false ||
        !isset($application_count_rows[0])
    ) {
        throw new RuntimeException(
            '행사 신청 기록을 확인할 수 없습니다.'
        );
    }

    $application_count =
        (int)$application_count_rows[0][
            'application_count'
        ];

    if (
        $data['slug'] !==
            (string)$locked_event['slug'] &&
        $application_count > 0
    ) {
        throw new DomainException(
            '신청 기록이 있는 행사의 공개 주소 식별자는 변경할 수 없습니다.'
        );
    }

    /*
     * 새 행사·신청 기간에서 벗어나는 기존 수업이
     * 있는지 확인합니다.
     */
    $invalid_class_rows =
        pdo_query(
            "
            SELECT
                COUNT(*) AS invalid_count

            FROM class_share_class

            WHERE event_id = ?
              AND
              (
                  class_start_at < ?
                  OR class_start_at > ?
                  OR
                  (
                      class_end_at IS NOT NULL
                      AND class_end_at > ?
                  )
                  OR application_deadline < ?
                  OR application_deadline > ?
                  OR application_deadline >
                     class_start_at
              )
            ",
            $event_id,
            $data['event_start_at'],
            $data['event_end_at'],
            $data['event_end_at'],
            $data['application_start_at'],
            $data['application_end_at']
        );

    if (
        $invalid_class_rows === false ||
        !isset($invalid_class_rows[0])
    ) {
        throw new RuntimeException(
            '기존 수업의 날짜 범위를 확인할 수 없습니다.'
        );
    }

    $invalid_class_count =
        (int)$invalid_class_rows[0][
            'invalid_count'
        ];

    if ($invalid_class_count > 0) {
        throw new DomainException(
            '변경하려는 행사 또는 신청 기간을 벗어나는 기존 수업이 ' .
            $invalid_class_count .
            '개 있습니다. 먼저 해당 수업의 일시와 신청 마감일을 수정해 주세요.'
        );
    }

    $published_class_rows =
        pdo_query(
            "
            SELECT
                COUNT(*) AS published_count

            FROM class_share_class

            WHERE event_id = ?
              AND status = 'published'
            ",
            $event_id
        );

    if (
        $published_class_rows === false ||
        !isset($published_class_rows[0])
    ) {
        throw new RuntimeException(
            '공개 수업 수를 확인할 수 없습니다.'
        );
    }

    $published_class_count =
        (int)$published_class_rows[0][
            'published_count'
        ];

    if ($data['status'] === 'published') {
        if ($school_status !== 'active') {
            throw new DomainException(
                '학교가 활성 상태일 때만 행사를 공개할 수 있습니다.'
            );
        }

        if ($published_class_count < 1) {
            throw new DomainException(
                '공개 상태의 수업이 최소 1개 있어야 행사를 공개할 수 있습니다.'
            );
        }
    }

    $before_data =
        array(
            'academic_year' =>
                (int)$locked_event[
                    'academic_year'
                ],

            'slug' =>
                (string)$locked_event['slug'],

            'title' =>
                (string)$locked_event['title'],

            'subtitle' =>
                (string)$locked_event['subtitle'],

            'event_start_at' =>
                (string)$locked_event[
                    'event_start_at'
                ],

            'event_end_at' =>
                (string)$locked_event[
                    'event_end_at'
                ],

            'application_start_at' =>
                (string)$locked_event[
                    'application_start_at'
                ],

            'application_end_at' =>
                (string)$locked_event[
                    'application_end_at'
                ],

            'privacy_policy_version' =>
                (string)$locked_event[
                    'privacy_policy_version'
                ],

            'privacy_notice' =>
                (string)$locked_event[
                    'privacy_notice'
                ],

            'retention_until' =>
                $locked_event[
                    'retention_until'
                ] === null
                ? null
                : (string)$locked_event[
                    'retention_until'
                ],

            'status' =>
                (string)$locked_event['status']
        );

    $after_data =
        array(
            'academic_year' =>
                $data['academic_year'],

            'slug' =>
                $data['slug'],

            'title' =>
                $data['title'],

            'subtitle' =>
                $data['subtitle'],

            'event_start_at' =>
                $data['event_start_at'],

            'event_end_at' =>
                $data['event_end_at'],

            'application_start_at' =>
                $data[
                    'application_start_at'
                ],

            'application_end_at' =>
                $data[
                    'application_end_at'
                ],

            'privacy_policy_version' =>
                $data[
                    'privacy_policy_version'
                ],

            'privacy_notice' =>
                $data['privacy_notice'],

            'retention_until' =>
                $data['retention_until'],

            'status' =>
                $data['status']
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
                UPDATE class_share_event

                SET
                    academic_year = ?,
                    slug = ?,
                    title = ?,
                    subtitle = ?,
                    event_start_at = ?,
                    event_end_at = ?,
                    application_start_at = ?,
                    application_end_at = ?,
                    privacy_policy_version = ?,
                    privacy_notice = ?,
                    retention_until = ?,
                    status = ?,
                    updated_by = ?

                WHERE id = ?
                  AND school_id = ?
                ",
                $data['academic_year'],
                $data['slug'],
                $data['title'],
                $data['subtitle'],
                $data['event_start_at'],
                $data['event_end_at'],
                $data[
                    'application_start_at'
                ],
                $data[
                    'application_end_at'
                ],
                $data[
                    'privacy_policy_version'
                ],
                $data['privacy_notice'],
                $data['retention_until'],
                $data['status'],
                $admin_id,
                $event_id,
                $school_id
            );

        if (
            $update_result === false ||
            (int)$update_result !== 1
        ) {
            throw new RuntimeException(
                '행사 변경 내용을 저장할 수 없습니다.'
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
                    'event.update',
                    'event',
                    ?,
                    ?,
                    ?,
                    ?
                )
                ",
                $school_id,
                $admin_id,
                $event_id,
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
        '[class-share] 행사 수정 실패: ' .
        $exception->getMessage()
    );

    $redirect_with_errors(
        array(
            '행사 변경 내용을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $form_values
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    $was_updated
    ? '행사 정보를 수정했습니다.'
    : '변경된 내용이 없습니다.';

header(
    'Location: ' . $list_url,
    true,
    303
);

exit;
