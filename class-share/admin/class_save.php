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
    header(
        'Allow: POST'
    );

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

if ($admin_id <= 0) {
    http_response_code(500);
    exit('관리자 정보를 확인할 수 없습니다.');
}

$event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$form_url =
    '/class-share/admin/class_form.php' .
    '?event_id=' .
    $event_id;

$list_url =
    '/class-share/admin/classes.php' .
    '?event_id=' .
    $event_id;

$redirect_with_errors =
    function (
        $errors,
        $form_values
    ) use ($form_url) {
        $_SESSION[
            'class_share_class_form_errors'
        ] =
            is_array($errors)
            ? $errors
            : array(
                '입력 내용을 확인해 주세요.'
            );

        $_SESSION[
            'class_share_class_form_values'
        ] =
            is_array($form_values)
            ? $form_values
            : array();

        header(
            'Location: ' . $form_url,
            true,
            303
        );

        exit;
    };

$event_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id,
            event.title,
            event.status,
            event.event_start_at,
            event.event_end_at,
            event.application_start_at,
            event.application_end_at,
            school.school_name

        FROM class_share_event AS event

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE event.id = ?

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
    exit('해당 행사의 수업을 등록할 권한이 없습니다.');
}

if (
    in_array(
        (string)$event['status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    $redirect_with_errors(
        array(
            '취소되거나 보관된 행사에는 수업을 추가할 수 없습니다.'
        ),
        $_POST
    );
}

$validation =
    class_share_class_validate_input(
        $_POST,
        $event
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
    http_response_code(500);
    exit('중복 수업을 확인할 수 없습니다.');
}

if (isset($duplicate_rows[0])) {
    $redirect_with_errors(
        array(
            '같은 수업명, 교사명, 시작일시와 장소를 가진 수업이 이미 등록되어 있습니다.'
        ),
        $validation['form_values']
    );
}

try {
    $public_id =
        bin2hex(
            random_bytes(16)
        );
} catch (Throwable $exception) {
    error_log(
        '[class-share] 수업 공개 식별자 생성 실패: ' .
        $exception->getMessage()
    );

    $redirect_with_errors(
        array(
            '수업 식별자를 생성할 수 없습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $validation['form_values']
    );
}

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

    if (
        (int)$locked_event_rows[0]['school_id'] !==
        $school_id
    ) {
        throw new RuntimeException(
            '행사 소속 학교가 변경되었습니다.'
        );
    }

    if (
        in_array(
            (string)$locked_event_rows[0]['status'],
            array(
                'cancelled',
                'archived'
            ),
            true
        )
    ) {
        throw new RuntimeException(
            '취소되거나 보관된 행사입니다.'
        );
    }

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

            FOR UPDATE
            ",
            $event_id,
            $data['title'],
            $data['teacher_name'],
            $data['class_start_at'],
            $data['place']
        );

    if ($duplicate_rows === false) {
        throw new RuntimeException(
            '중복 수업을 다시 확인할 수 없습니다.'
        );
    }

    if (isset($duplicate_rows[0])) {
        throw new DomainException(
            '같은 수업이 이미 등록되어 있습니다.'
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
            '수업을 저장할 수 없습니다.'
        );
    }

    $after_data =
        json_encode(
            array(
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
            '감사 로그를 저장할 수 없습니다.'
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

    $redirect_with_errors(
        array(
            $exception->getMessage()
        ),
        $validation['form_values']
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
        '[class-share] 수업 등록 실패: ' .
        $exception->getMessage()
    );

    $redirect_with_errors(
        array(
            '수업을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $validation['form_values']
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    '수업이 등록되었습니다. 공개 전까지 작성 중 상태로 유지됩니다.';

header(
    'Location: ' . $list_url,
    true,
    303
);

exit;
