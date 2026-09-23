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

$event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

if (
    $admin_id <= 0 ||
    $event_id <= 0
) {
    http_response_code(400);
    exit('관리자 또는 행사 정보가 올바르지 않습니다.');
}

$form_url =
    '/class-share/admin/notice_form.php' .
    '?event_id=' .
    $event_id;

$list_url =
    '/class-share/admin/notices.php' .
    '?event_id=' .
    $event_id;

$redirect_with_errors =
    function (
        $errors,
        $form_values
    ) use ($form_url) {
        $_SESSION[
            'class_share_notice_form_errors'
        ] =
            is_array($errors)
            ? $errors
            : array(
                '입력 내용을 확인해 주세요.'
            );

        $_SESSION[
            'class_share_notice_form_values'
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
            id,
            school_id,
            status

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
    exit('해당 행사의 공지를 작성할 권한이 없습니다.');
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
            '취소되거나 보관된 행사에는 공지를 추가할 수 없습니다.'
        ),
        $_POST
    );
}

$validation =
    class_share_notice_validate_input(
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

$data =
    $validation['data'];

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
            '취소되거나 보관된 행사에는 공지를 추가할 수 없습니다.'
        );
    }

    $insert_result =
        pdo_query(
            "
            INSERT INTO class_share_notice
            (
                event_id,
                title,
                content,
                important,
                status,
                published_at,
                sort_order,
                created_by,
                updated_by
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                'draft',
                NULL,
                ?,
                ?,
                ?
            )
            ",
            $event_id,
            $data['title'],
            $data['content'],
            $data['important'],
            $data['sort_order'],
            $admin_id,
            $admin_id
        );

    $notice_id =
        (int)$insert_result;

    if (
        $insert_result === false ||
        $notice_id <= 0
    ) {
        throw new RuntimeException(
            '공지를 저장할 수 없습니다.'
        );
    }

    $after_data =
        json_encode(
            array(
                'event_id' =>
                    $event_id,

                'title' =>
                    $data['title'],

                'content' =>
                    $data['content'],

                'important' =>
                    $data['important'],

                'status' =>
                    'draft',

                'published_at' =>
                    null,

                'sort_order' =>
                    $data['sort_order']
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
                'notice.create',
                'notice',
                ?,
                NULL,
                ?,
                ?
            )
            ",
            $school_id,
            $admin_id,
            $notice_id,
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
        '[class-share] 공지 등록 실패: ' .
        $exception->getMessage()
    );

    $redirect_with_errors(
        array(
            '공지를 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.'
        ),
        $validation['form_values']
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    '공지를 작성 중 상태로 저장했습니다.';

header(
    'Location: ' . $list_url,
    true,
    303
);

exit;
