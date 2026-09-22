<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

class_share_admin_require_post_csrf();

$school_id =
    isset($_POST['school_id'])
    ? (int)$_POST['school_id']
    : 0;

if ($school_id <= 0) {
    http_response_code(400);
    exit('학교 번호가 올바르지 않습니다.');
}

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 학교의 행사를 등록할 권한이 없습니다.');
}

$academic_year =
    isset($_POST['academic_year'])
    ? (int)$_POST['academic_year']
    : 0;

$slug =
    isset($_POST['slug'])
    ? strtolower(trim((string)$_POST['slug']))
    : '';

$title =
    isset($_POST['title'])
    ? trim((string)$_POST['title'])
    : '';

$subtitle =
    isset($_POST['subtitle'])
    ? trim((string)$_POST['subtitle'])
    : '';

$event_start_input =
    isset($_POST['event_start_at'])
    ? trim((string)$_POST['event_start_at'])
    : '';

$event_end_input =
    isset($_POST['event_end_at'])
    ? trim((string)$_POST['event_end_at'])
    : '';

$application_start_input =
    isset($_POST['application_start_at'])
    ? trim((string)$_POST['application_start_at'])
    : '';

$application_end_input =
    isset($_POST['application_end_at'])
    ? trim((string)$_POST['application_end_at'])
    : '';

$privacy_policy_version =
    isset($_POST['privacy_policy_version'])
    ? trim((string)$_POST['privacy_policy_version'])
    : '';

$privacy_notice =
    isset($_POST['privacy_notice'])
    ? trim((string)$_POST['privacy_notice'])
    : '';

$retention_input =
    isset($_POST['retention_until'])
    ? trim((string)$_POST['retention_until'])
    : '';

$form_values =
    array(
        'academic_year' =>
            (string)$academic_year,

        'slug' => $slug,
        'title' => $title,
        'subtitle' => $subtitle,

        'event_start_at' =>
            $event_start_input,

        'event_end_at' =>
            $event_end_input,

        'application_start_at' =>
            $application_start_input,

        'application_end_at' =>
            $application_end_input,

        'privacy_policy_version' =>
            $privacy_policy_version,

        'privacy_notice' =>
            $privacy_notice,

        'retention_until' =>
            $retention_input
    );

$redirect_with_errors =
    function ($errors) use (
        $form_values,
        $school_id
    ) {
        $_SESSION[
            'class_share_event_form_errors'
        ] =
            array_values(
                array_unique(
                    $errors
                )
            );

        $_SESSION[
            'class_share_event_form_values'
        ] =
            $form_values;

        session_write_close();

        header(
            'Location: /class-share/admin/' .
            'event_form.php?school_id=' .
            rawurlencode(
                (string)$school_id
            ),
            true,
            303
        );

        exit;
    };

$text_length =
    function ($value) {
        if (function_exists('mb_strlen')) {
            return mb_strlen(
                $value,
                'UTF-8'
            );
        }

        return strlen(
            $value
        );
    };

$errors =
    array();

$timezone =
    new DateTimeZone(
        'Asia/Seoul'
    );

$parse_datetime =
    function (
        $value,
        $label
    ) use (
        &$errors,
        $timezone
    ) {
        if ($value === '') {
            return null;
        }

        $date =
            DateTime::createFromFormat(
                'Y-m-d\TH:i',
                $value,
                $timezone
            );

        $date_errors =
            DateTime::getLastErrors();

        if (
            $date === false ||
            (
                is_array($date_errors) &&
                (
                    $date_errors['warning_count'] > 0 ||
                    $date_errors['error_count'] > 0
                )
            ) ||
            $date->format('Y-m-d\TH:i') !== $value
        ) {
            $errors[] =
                $label .
                ' 형식이 올바르지 않습니다.';

            return null;
        }

        return
            $date->format(
                'Y-m-d H:i:s'
            );
    };

$parse_date =
    function (
        $value,
        $label
    ) use (
        &$errors,
        $timezone
    ) {
        if ($value === '') {
            return null;
        }

        $date =
            DateTime::createFromFormat(
                '!Y-m-d',
                $value,
                $timezone
            );

        $date_errors =
            DateTime::getLastErrors();

        if (
            $date === false ||
            (
                is_array($date_errors) &&
                (
                    $date_errors['warning_count'] > 0 ||
                    $date_errors['error_count'] > 0
                )
            ) ||
            $date->format('Y-m-d') !== $value
        ) {
            $errors[] =
                $label .
                ' 형식이 올바르지 않습니다.';

            return null;
        }

        return
            $date->format(
                'Y-m-d'
            );
    };

if (
    $academic_year < 2020 ||
    $academic_year > 2100
) {
    $errors[] =
        '학년도는 2020~2100 사이여야 합니다.';
}

if (
    !preg_match(
        '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
        $slug
    ) ||
    strlen($slug) < 3 ||
    strlen($slug) > 80
) {
    $errors[] =
        '행사 주소 식별자는 영문 소문자, 숫자, ' .
        '하이픈을 사용하여 3~80자로 입력해야 합니다.';
}

if (
    $text_length($title) < 2 ||
    $text_length($title) > 200
) {
    $errors[] =
        '행사명은 2~200자로 입력해야 합니다.';
}

if (
    $text_length($subtitle) > 255
) {
    $errors[] =
        '행사 부제는 255자 이하여야 합니다.';
}

if (
    $text_length($privacy_policy_version) < 1 ||
    $text_length($privacy_policy_version) > 50
) {
    $errors[] =
        '개인정보 처리 문구 버전은 1~50자여야 합니다.';
}

if (
    $text_length($privacy_notice) < 20 ||
    $text_length($privacy_notice) > 5000
) {
    $errors[] =
        '개인정보 수집·이용 안내는 20~5,000자로 입력해야 합니다.';
}

$event_start_at =
    $parse_datetime(
        $event_start_input,
        '행사 시작일시'
    );

$event_end_at =
    $parse_datetime(
        $event_end_input,
        '행사 종료일시'
    );

$application_start_at =
    $parse_datetime(
        $application_start_input,
        '신청 시작일시'
    );

$application_end_at =
    $parse_datetime(
        $application_end_input,
        '신청 종료일시'
    );

$retention_until =
    $parse_date(
        $retention_input,
        '개인정보 보관 기한'
    );

if (
    $event_start_at !== null &&
    $event_end_at !== null &&
    strtotime($event_end_at) <=
        strtotime($event_start_at)
) {
    $errors[] =
        '행사 종료일시는 시작일시보다 늦어야 합니다.';
}

if (
    $application_start_at !== null &&
    $application_end_at !== null &&
    strtotime($application_end_at) <=
        strtotime($application_start_at)
) {
    $errors[] =
        '신청 종료일시는 시작일시보다 늦어야 합니다.';
}

if (
    $retention_until !== null &&
    $event_end_at !== null &&
    $retention_until <
        substr(
            $event_end_at,
            0,
            10
        )
) {
    $errors[] =
        '개인정보 보관 기한은 행사 종료일보다 ' .
        '빠를 수 없습니다.';
}

if (count($errors) > 0) {
    $redirect_with_errors(
        $errors
    );
}

$school_rows =
    pdo_query(
        "
        SELECT
            id,
            school_name,
            status
        FROM class_share_school
        WHERE id = ?
        LIMIT 1
        ",
        $school_id
    );

if (
    $school_rows === false ||
    !isset($school_rows[0])
) {
    $redirect_with_errors(
        array(
            '학교 정보를 확인할 수 없습니다.'
        )
    );
}

$duplicate_rows =
    pdo_query(
        "
        SELECT id
        FROM class_share_event
        WHERE school_id = ?
          AND slug = ?
        LIMIT 1
        ",
        $school_id,
        $slug
    );

if ($duplicate_rows === false) {
    $redirect_with_errors(
        array(
            '행사 주소 중복 여부를 확인할 수 없습니다.'
        )
    );
}

if (isset($duplicate_rows[0])) {
    $redirect_with_errors(
        array(
            '해당 학교에서 이미 사용 중인 ' .
            '행사 주소 식별자입니다.'
        )
    );
}

try {
    if (
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $event_id =
        pdo_query(
            "
            INSERT INTO class_share_event
            (
                school_id,
                slug,
                title,
                subtitle,
                academic_year,
                event_start_at,
                event_end_at,
                application_start_at,
                application_end_at,
                privacy_policy_version,
                privacy_notice,
                retention_until,
                status,
                created_by,
                updated_by,
                created_at,
                updated_at
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
                'draft',
                ?,
                ?,
                NOW(),
                NOW()
            )
            ",
            $school_id,
            $slug,
            $title,
            $subtitle !== ''
            ? $subtitle
            : null,
            $academic_year,
            $event_start_at,
            $event_end_at,
            $application_start_at,
            $application_end_at,
            $privacy_policy_version,
            $privacy_notice,
            $retention_until,
            (int)$admin['id'],
            (int)$admin['id']
        );

    if ($event_id === false) {
        throw new RuntimeException(
            '행사 저장 실패'
        );
    }

    $after_json =
        json_encode(
            array(
                'school_id' =>
                    $school_id,

                'slug' =>
                    $slug,

                'title' =>
                    $title,

                'academic_year' =>
                    $academic_year,

                'event_start_at' =>
                    $event_start_at,

                'event_end_at' =>
                    $event_end_at,

                'application_start_at' =>
                    $application_start_at,

                'application_end_at' =>
                    $application_end_at,

                'privacy_policy_version' =>
                    $privacy_policy_version,

                'retention_until' =>
                    $retention_until,

                'status' =>
                    'draft'
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($after_json === false) {
        throw new RuntimeException(
            '감사 기록 변환 실패'
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
                'event.create',
                'event',
                ?,
                NULL,
                ?,
                ?,
                NOW()
            )
            ",
            $school_id,
            (int)$admin['id'],
            (int)$event_id,
            $after_json,
            class_share_admin_client_ip()
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '감사 기록 저장 실패'
        );
    }

    $dbh->commit();
} catch (Throwable $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        '[class-share] 행사 생성 실패: ' .
        $e->getMessage()
    );

    $redirect_with_errors(
        array(
            '행사를 저장하지 못했습니다.'
        )
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    '행사가 작성 중 상태로 등록되었습니다.';

session_write_close();

header(
    'Location: /class-share/admin/events.php?' .
    'school_id=' .
    rawurlencode(
        (string)$school_id
    ),
    true,
    303
);

exit;
