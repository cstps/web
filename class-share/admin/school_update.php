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
    !class_share_admin_can_manage_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit(
        '해당 학교의 설정을 관리할 권한이 없습니다.'
    );
}

$school_code =
    isset($_POST['school_code'])
    ? trim((string)$_POST['school_code'])
    : '';

$school_name =
    isset($_POST['school_name'])
    ? trim((string)$_POST['school_name'])
    : '';

$slug =
    isset($_POST['slug'])
    ? strtolower(
        trim(
            (string)$_POST['slug']
        )
    )
    : '';

$page_title =
    isset($_POST['page_title'])
    ? trim((string)$_POST['page_title'])
    : '';

$introduction =
    isset($_POST['introduction'])
    ? trim((string)$_POST['introduction'])
    : '';

$status =
    isset($_POST['status'])
    ? trim((string)$_POST['status'])
    : '';

$form_values =
    array(
        'school_code' => $school_code,
        'school_name' => $school_name,
        'slug' => $slug,
        'page_title' => $page_title,
        'introduction' => $introduction,
        'status' => $status
    );

$redirect_with_errors =
    function ($errors) use (
        $form_values,
        $school_id
    ) {
        $_SESSION[
            'class_share_school_form_errors'
        ] =
            array_values(
                array_unique(
                    $errors
                )
            );

        $_SESSION[
            'class_share_school_form_values'
        ] =
            $form_values;

        session_write_close();

        header(
            'Location: /class-share/admin/' .
            'school_form.php?id=' .
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

if (
    $text_length($school_name) < 2 ||
    $text_length($school_name) > 100
) {
    $errors[] =
        '학교명은 2~100자로 입력해야 합니다.';
}

if (
    $school_code !== '' &&
    !preg_match(
        '/^[A-Za-z0-9_-]{2,20}$/D',
        $school_code
    )
) {
    $errors[] =
        '학교 코드는 영문, 숫자, 밑줄, ' .
        '하이픈을 사용하여 2~20자로 입력해야 합니다.';
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
        '공개 주소 식별자는 영문 소문자, 숫자, ' .
        '하이픈을 사용하여 3~80자로 입력해야 합니다.';
}

if (
    $text_length($page_title) < 2 ||
    $text_length($page_title) > 150
) {
    $errors[] =
        '공개 페이지 제목은 2~150자로 입력해야 합니다.';
}

if (
    $text_length($introduction) > 5000
) {
    $errors[] =
        '학교별 안내문은 5,000자 이하여야 합니다.';
}

if (
    !in_array(
        $status,
        array(
            'active',
            'inactive'
        ),
        true
    )
) {
    $errors[] =
        '운영 상태가 올바르지 않습니다.';
}

if (count($errors) > 0) {
    $redirect_with_errors(
        $errors
    );
}

$current_rows =
    pdo_query(
        "
        SELECT
            id,
            school_code,
            school_name,
            slug,
            page_title,
            introduction,
            status
        FROM class_share_school
        WHERE id = ?
        LIMIT 1
        ",
        $school_id
    );

if ($current_rows === false) {
    $redirect_with_errors(
        array(
            '기존 학교 정보를 확인할 수 없습니다.'
        )
    );
}

if (!isset($current_rows[0])) {
    http_response_code(404);
    exit('학교를 찾을 수 없습니다.');
}

$current =
    $current_rows[0];

$school_code_value =
    $school_code !== ''
    ? $school_code
    : null;

$duplicate_rows =
    pdo_query(
        "
        SELECT
            id,
            school_code,
            slug
        FROM class_share_school
        WHERE id <> ?
          AND (
              slug = ?
              OR (
                  school_code IS NOT NULL
                  AND school_code = ?
              )
          )
        ",
        $school_id,
        $slug,
        $school_code_value
    );

if ($duplicate_rows === false) {
    $redirect_with_errors(
        array(
            '학교 중복 여부를 확인할 수 없습니다.'
        )
    );
}

foreach ($duplicate_rows as $row) {
    if (
        strcasecmp(
            (string)$row['slug'],
            $slug
        ) === 0
    ) {
        $errors[] =
            '이미 사용 중인 공개 주소 식별자입니다.';
    }

    if (
        $school_code_value !== null &&
        $row['school_code'] !== null &&
        strcasecmp(
            (string)$row['school_code'],
            $school_code_value
        ) === 0
    ) {
        $errors[] =
            '이미 등록된 학교 코드입니다.';
    }
}

if (count($errors) > 0) {
    $redirect_with_errors(
        $errors
    );
}

$before =
    array(
        'school_code' =>
            $current['school_code'] !== null
            ? (string)$current['school_code']
            : null,

        'school_name' =>
            (string)$current['school_name'],

        'slug' =>
            (string)$current['slug'],

        'page_title' =>
            (string)$current['page_title'],

        'introduction' =>
            $current['introduction'] !== null
            ? (string)$current['introduction']
            : null,

        'status' =>
            (string)$current['status']
    );

$after =
    array(
        'school_code' =>
            $school_code_value,

        'school_name' =>
            $school_name,

        'slug' =>
            $slug,

        'page_title' =>
            $page_title,

        'introduction' =>
            $introduction !== ''
            ? $introduction
            : null,

        'status' =>
            $status
    );

if ($before === $after) {
    $_SESSION[
        'class_share_admin_flash'
    ] =
        '변경된 내용이 없습니다.';

    session_write_close();

    header(
        'Location: /class-share/admin/schools.php',
        true,
        303
    );

    exit;
}

$before_json =
    json_encode(
        $before,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

$after_json =
    json_encode(
        $after,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

if (
    $before_json === false ||
    $after_json === false
) {
    $redirect_with_errors(
        array(
            '변경 내용을 기록할 수 없습니다.'
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

    if (
        $before['slug'] !== $after['slug'] &&
        !class_share_admin_is_super_admin(
            $admin
        )
    ) {
        $event_rows =
            pdo_query(
                "
                SELECT
                    id,
                    status

                FROM class_share_event

                WHERE school_id = ?

                ORDER BY id

                FOR UPDATE
                ",
                $school_id
            );

        if ($event_rows === false) {
            throw new RuntimeException(
                '학교 행사 상태를 확인할 수 없습니다.'
            );
        }

        $has_protected_event =
            false;

        foreach ($event_rows as $event) {
            if (
                in_array(
                    (string)$event['status'],
                    array(
                        'published',
                        'closed',
                        'archived'
                    ),
                    true
                )
            ) {
                $has_protected_event =
                    true;

                break;
            }
        }

        $application_rows =
            pdo_query(
                "
                SELECT
                    COUNT(*) AS application_count

                FROM class_share_application AS application

                INNER JOIN class_share_event AS event
                    ON event.id =
                       application.event_id

                WHERE event.school_id = ?
                ",
                $school_id
            );

        if (
            $application_rows === false ||
            !isset($application_rows[0])
        ) {
            throw new RuntimeException(
                '학교 행사 신청 자료를 확인할 수 없습니다.'
            );
        }

        $application_count =
            (int)$application_rows[0][
                'application_count'
            ];

        if (
            $has_protected_event ||
            $application_count > 0
        ) {
            throw new DomainException(
                '공개·종료·보관 행사 또는 신청 자료가 있는 학교의 공개 주소는 학교 관리자가 변경할 수 없습니다. 최고관리자에게 요청해 주세요.'
            );
        }
    }

    $update_result =
        pdo_query(
            "
            UPDATE class_share_school
            SET
                school_code = ?,
                school_name = ?,
                slug = ?,
                page_title = ?,
                introduction = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
            ",
            $school_code_value,
            $school_name,
            $slug,
            $page_title,
            $introduction !== ''
            ? $introduction
            : null,
            $status,
            $school_id
        );

    if ($update_result === false) {
        throw new RuntimeException(
            '학교 수정 실패'
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
                'school.update',
                'school',
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
            ",
            $school_id,
            (int)$admin['id'],
            $school_id,
            $before_json,
            $after_json,
            class_share_admin_client_ip()
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '감사 기록 저장 실패'
        );
    }

    $dbh->commit();
} catch (DomainException $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    $redirect_with_errors(
        array(
            $e->getMessage()
        )
    );
} catch (Throwable $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        '[class-share] 학교 수정 실패: ' .
        $e->getMessage()
    );

    $redirect_with_errors(
        array(
            '학교 변경사항을 저장하지 못했습니다.'
        )
    );
}

$_SESSION[
    'class_share_admin_flash'
] =
    '학교 정보가 수정되었습니다.';

session_write_close();

header(
    'Location: /class-share/admin/schools.php',
    true,
    303
);

exit;
