<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

if (
    !class_share_admin_is_super_admin(
        $admin
    )
) {
    http_response_code(403);
    exit('학교 관리 권한이 없습니다.');
}

class_share_admin_require_post_csrf();

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

if (count($errors) === 0) {
    $duplicate_rows =
        pdo_query(
            "
            SELECT
                id,
                school_code,
                slug
            FROM class_share_school
            WHERE slug = ?
               OR (
                   school_code IS NOT NULL
                   AND school_code = ?
               )
            ",
            $slug,
            $school_code !== ''
                ? $school_code
                : null
        );

    if ($duplicate_rows === false) {
        $errors[] =
            '학교 중복 여부를 확인할 수 없습니다.';
    } else {
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
                $school_code !== '' &&
                $row['school_code'] !== null &&
                strcasecmp(
                    (string)$row['school_code'],
                    $school_code
                ) === 0
            ) {
                $errors[] =
                    '이미 등록된 학교 코드입니다.';
            }
        }
    }
}

if (count($errors) > 0) {
    $_SESSION['class_share_school_form_errors'] =
        array_values(
            array_unique(
                $errors
            )
        );

    $_SESSION['class_share_school_form_values'] =
        $form_values;

    session_write_close();

    header(
        'Location: /class-share/admin/school_form.php',
        true,
        303
    );

    exit;
}

$school_code_value =
    $school_code !== ''
    ? $school_code
    : null;

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

    $school_id =
        pdo_query(
            "
            INSERT INTO class_share_school
            (
                school_code,
                school_name,
                slug,
                page_title,
                introduction,
                status,
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
                NOW(),
                NOW()
            )
            ",
            $school_code_value,
            $school_name,
            $slug,
            $page_title,
            $introduction !== ''
                ? $introduction
                : null,
            $status
        );

    if ($school_id === false) {
        throw new RuntimeException(
            '학교 저장 실패'
        );
    }

    $after_data =
        json_encode(
            array(
                'school_code' =>
                $school_code_value,

                'school_name' =>
                $school_name,

                'slug' =>
                $slug,

                'page_title' =>
                $page_title,

                'status' =>
                $status
            ),
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
        );

    if ($after_data === false) {
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
                'school.create',
                'school',
                ?,
                NULL,
                ?,
                ?,
                NOW()
            )
            ",
            (int)$school_id,
            (int)$admin['id'],
            (int)$school_id,
            $after_data,
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
        '[class-share] 학교 생성 실패: ' .
            $e->getMessage()
    );

    $_SESSION['class_share_school_form_errors'] =
        array(
            '학교를 저장하지 못했습니다.'
        );

    $_SESSION['class_share_school_form_values'] =
        $form_values;

    session_write_close();

    header(
        'Location: /class-share/admin/school_form.php',
        true,
        303
    );

    exit;
}

$_SESSION['class_share_admin_flash'] =
    '학교가 등록되었습니다.';

session_write_close();

header(
    'Location: /class-share/admin/schools.php',
    true,
    303
);

exit;
