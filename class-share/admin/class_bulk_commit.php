<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    __DIR__ .
    '/include/class_functions.php'
);

require_once(
    __DIR__ .
    '/include/class_bulk_functions.php'
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

$bulk_token =
    isset($_POST['bulk_token'])
    ? trim(
        (string)$_POST['bulk_token']
    )
    : '';

if (
    $admin_id <= 0 ||
    $event_id <= 0
) {
    http_response_code(400);
    exit('관리자 또는 행사 정보가 올바르지 않습니다.');
}

$upload_url =
    '/class-share/admin/class_bulk_upload.php' .
    '?event_id=' .
    $event_id;

$list_url =
    '/class-share/admin/classes.php' .
    '?event_id=' .
    $event_id;

$redirect_upload_error =
    function ($message) use ($upload_url) {
        $_SESSION[
            'class_share_bulk_upload_errors'
        ] =
            array(
                (string)$message
            );

        header(
            'Location: ' . $upload_url,
            true,
            303
        );

        exit;
    };

if (
    !preg_match(
        '/^[a-f0-9]{64}$/D',
        $bulk_token
    )
) {
    $redirect_upload_error(
        '일괄 등록 확인 정보가 올바르지 않습니다. CSV 파일을 다시 업로드해 주세요.'
    );
}

$preview =
    isset(
        $_SESSION[
            'class_share_bulk_preview'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_bulk_preview'
        ]
    )
    ? $_SESSION[
        'class_share_bulk_preview'
    ]
    : null;

if ($preview === null) {
    $redirect_upload_error(
        '일괄 등록 미리보기 정보가 없습니다. CSV 파일을 다시 업로드해 주세요.'
    );
}

$stored_token_hash =
    isset($preview['token_hash'])
    ? (string)$preview['token_hash']
    : '';

$submitted_token_hash =
    hash(
        'sha256',
        $bulk_token
    );

if (
    $stored_token_hash === '' ||
    !hash_equals(
        $stored_token_hash,
        $submitted_token_hash
    )
) {
    $redirect_upload_error(
        '일괄 등록 확인 정보가 일치하지 않습니다. CSV 파일을 다시 업로드해 주세요.'
    );
}

if (
    !isset($preview['expires_at']) ||
    (int)$preview['expires_at'] < time()
) {
    unset(
        $_SESSION[
            'class_share_bulk_preview'
        ]
    );

    $redirect_upload_error(
        '미리보기 유효시간 20분이 지났습니다. CSV 파일을 다시 업로드해 주세요.'
    );
}

if (
    !isset($preview['event_id']) ||
    (int)$preview['event_id'] !==
        $event_id ||
    !isset($preview['admin_id']) ||
    (int)$preview['admin_id'] !==
        $admin_id
) {
    unset(
        $_SESSION[
            'class_share_bulk_preview'
        ]
    );

    $redirect_upload_error(
        '미리보기의 관리자 또는 행사 정보가 일치하지 않습니다.'
    );
}

$preview_rows =
    isset($preview['rows']) &&
    is_array($preview['rows'])
    ? $preview['rows']
    : array();

if (
    count($preview_rows) < 1 ||
    count($preview_rows) > 500
) {
    unset(
        $_SESSION[
            'class_share_bulk_preview'
        ]
    );

    $redirect_upload_error(
        '일괄 등록할 수업 개수가 올바르지 않습니다.'
    );
}

$event_rows =
    pdo_query(
        "
        SELECT
            id,
            school_id,
            title,
            status,
            event_start_at,
            event_end_at,
            application_start_at,
            application_end_at

        FROM class_share_event

        WHERE id = ?

        LIMIT 1
        ",
        $event_id
    );

if ($event_rows === false) {
    http_response_code(500);
    exit('행사 정보를 불러올 수 없습니다.');
}

if (!isset($event_rows[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

$event =
    $event_rows[0];

$school_id =
    (int)$event['school_id'];

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 행사의 수업을 일괄 등록할 권한이 없습니다.');
}

if (
    isset($preview['school_id']) &&
    (int)$preview['school_id'] !==
        $school_id
) {
    unset(
        $_SESSION[
            'class_share_bulk_preview'
        ]
    );

    $redirect_upload_error(
        '미리보기 이후 행사의 소속 학교가 변경되었습니다.'
    );
}

/*
 * 이 토큰은 이후 요청에서 다시 사용할 수 없도록
 * DB 처리 전에 세션에서 제거합니다.
 */
unset(
    $_SESSION[
        'class_share_bulk_preview'
    ]
);

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

$created_class_ids =
    array();

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
                title,
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
            '취소되거나 보관된 행사에는 수업을 추가할 수 없습니다.'
        );
    }

    foreach ($preview_rows as $preview_row) {
        $row_number =
            isset($preview_row['row_number'])
            ? (int)$preview_row['row_number']
            : 0;

        $source_data =
            isset($preview_row['data']) &&
            is_array($preview_row['data'])
            ? $preview_row['data']
            : array();

        $validation =
            class_share_class_validate_input(
                $source_data,
                $locked_event
            );

        if (
            count(
                $validation['errors']
            ) > 0
        ) {
            $first_error =
                (string)$validation[
                    'errors'
                ][0];

            throw new DomainException(
                'CSV ' .
                $row_number .
                '행: ' .
                $first_error
            );
        }

        $data =
            $validation['data'];

        $duplicate_rows =
            pdo_query(
                "
                SELECT
                    id

                FROM class_share_class

                WHERE event_id = ?
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
                $data['title'],
                $data['teacher_name'],
                $data['class_start_at'],
                $data['place']
            );

        if ($duplicate_rows === false) {
            throw new RuntimeException(
                '기존 수업 중복 여부를 확인할 수 없습니다.'
            );
        }

        if (isset($duplicate_rows[0])) {
            throw new DomainException(
                'CSV ' .
                $row_number .
                '행의 수업이 이미 등록되어 있습니다.'
            );
        }

        try {
            $public_id =
                bin2hex(
                    random_bytes(16)
                );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '수업 공개 식별자를 만들 수 없습니다.'
            );
        }

        $insert_result =
            pdo_query(
                "
                INSERT INTO class_share_class
                (
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
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'draft',
                    ?,
                    ?
                )
                ",
                $event_id,
                $public_id,
                $data['subject'],
                $data['title'],
                $data['teacher_name'],
                $data['target'],
                $data['class_start_at'],
                $data['class_end_at'],
                $data['place'],
                $data['application_deadline'],
                $data['capacity'],
                $data['description'],
                $data['sort_order'],
                $admin_id,
                $admin_id
            );

        $class_id =
            (int)$insert_result;

        if (
            $insert_result === false ||
            $class_id <= 0
        ) {
            throw new RuntimeException(
                'CSV ' .
                $row_number .
                '행의 수업을 저장할 수 없습니다.'
            );
        }

        $after_data =
            json_encode(
                array(
                    'source' =>
                        'csv',

                    'csv_row' =>
                        $row_number,

                    'event_id' =>
                        $event_id,

                    'public_id' =>
                        $public_id,

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

                    'sort_order' =>
                        $data['sort_order'],

                    'status' =>
                        'draft'
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        if ($after_data === false) {
            throw new RuntimeException(
                '수업 감사 로그 데이터를 만들 수 없습니다.'
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
                    'class.create',
                    'class',
                    ?,
                    NULL,
                    ?,
                    ?
                )
                ",
                $school_id,
                $admin_id,
                $class_id,
                $after_data,
                $ip_address
            );

        if ($audit_result === false) {
            throw new RuntimeException(
                '수업 감사 로그를 저장할 수 없습니다.'
            );
        }

        $created_class_ids[] =
            $class_id;
    }

    $batch_after_data =
        json_encode(
            array(
                'source' =>
                    'csv',

                'event_id' =>
                    $event_id,

                'created_count' =>
                    count(
                        $created_class_ids
                    ),

                'class_ids' =>
                    $created_class_ids
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($batch_after_data === false) {
        throw new RuntimeException(
            '일괄 등록 감사 로그 데이터를 만들 수 없습니다.'
        );
    }

    $batch_audit_result =
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
                'class.bulk_create',
                'event',
                ?,
                NULL,
                ?,
                ?
            )
            ",
            $school_id,
            $admin_id,
            $event_id,
            $batch_after_data,
            $ip_address
        );

    if ($batch_audit_result === false) {
        throw new RuntimeException(
            '일괄 등록 감사 로그를 저장할 수 없습니다.'
        );
    }

    $dbh->commit();
} catch (DomainException $exception) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    $redirect_upload_error(
        $exception->getMessage() .
        ' 전체 등록을 취소했습니다. CSV 파일을 다시 확인해 주세요.'
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
        '[class-share] CSV 수업 일괄 등록 실패: ' .
        $exception->getMessage()
    );

    $redirect_upload_error(
        '수업 일괄 등록에 실패하여 전체 등록을 취소했습니다. 잠시 후 다시 시도해 주세요.'
    );
}

$created_count =
    count(
        $created_class_ids
    );

$_SESSION[
    'class_share_admin_flash'
] =
    $created_count .
    '개의 수업을 작성 중 상태로 일괄 등록했습니다.';

header(
    'Location: ' . $list_url,
    true,
    303
);

exit;
