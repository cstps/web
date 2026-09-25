<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    dirname(__DIR__) .
    '/include/privacy_crypto.php'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Cache-Control: no-store, max-age=0'
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);

header(
    'Referrer-Policy: same-origin'
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

$application_id =
    isset($_POST['application_id'])
    ? (int)$_POST['application_id']
    : 0;

if ($application_id <= 0) {
    http_response_code(400);
    exit('신청 번호가 올바르지 않습니다.');
}

$application_rows =
    pdo_query(
        "
        SELECT
            application.id,
            application.event_id,
            application.applicant_name,
            application.applicant_school,
            application.phone_ciphertext,

            event.school_id,
            event.title AS event_title,

            school.school_name

        FROM class_share_application AS application

        INNER JOIN class_share_event AS event
            ON event.id =
               application.event_id

        INNER JOIN class_share_school AS school
            ON school.id =
               event.school_id

        WHERE application.id = ?

        LIMIT 1
        ",
        $application_id
    );

if ($application_rows === false) {
    http_response_code(500);
    exit('신청 정보를 불러올 수 없습니다.');
}

if (!isset($application_rows[0])) {
    http_response_code(404);
    exit('신청 정보를 찾을 수 없습니다.');
}

$application =
    $application_rows[0];

$school_id =
    (int)$application['school_id'];

if (
    !class_share_admin_can_view_sensitive_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('전체 연락처를 확인할 권한이 없습니다.');
}

try {
    $phone =
        class_share_decrypt_phone(
            $application[
                'phone_ciphertext'
            ]
        );
} catch (Throwable $e) {
    error_log(
        '[class-share] 신청자 연락처 복호화 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('연락처를 확인할 수 없습니다.');
}

if (strlen($phone) === 11) {
    $formatted_phone =
        substr($phone, 0, 3) .
        '-' .
        substr($phone, 3, 4) .
        '-' .
        substr($phone, 7, 4);
} elseif (strlen($phone) === 10) {
    $formatted_phone =
        substr($phone, 0, 3) .
        '-' .
        substr($phone, 3, 3) .
        '-' .
        substr($phone, 6, 4);
} else {
    $formatted_phone =
        $phone;
}

try {
    global $dbh;

    if (!($dbh instanceof PDO)) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $after_json =
        json_encode(
            array(
                'application_id' =>
                    $application_id,

                'event_id' =>
                    (int)$application[
                        'event_id'
                    ],

                'school_id' =>
                    $school_id
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($after_json === false) {
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
                'application.phone_view',
                'application',
                ?,
                NULL,
                ?,
                ?,
                NOW()
            )
            ",
            $school_id,
            (int)$admin['id'],
            $application_id,
            $after_json,
            class_share_admin_client_ip()
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '연락처 열람 감사 기록을 저장할 수 없습니다.'
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

    $phone = null;
    $formatted_phone = null;

    error_log(
        '[class-share] 연락처 열람 기록 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('연락처 열람 기록을 저장할 수 없습니다.');
}

$page_title =
    '신청자 연락처 확인';

$active_menu =
    'schools';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <?php
            echo class_share_escape(
                $application['school_name']
            );
            ?>
            ·
            <?php
            echo class_share_escape(
                $application['event_title']
            );
            ?>
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/application_edit.php?application_id=<?php
            echo $application_id;
            ?>">
            ← 신청 상세
        </a>
    </div>
</div>

<section class="admin-panel">
    <h2>전체 연락처</h2>

    <dl>
        <dt>신청자</dt>
        <dd>
            <?php
            echo class_share_escape(
                $application['applicant_name']
            );
            ?>
        </dd>

        <dt>소속</dt>
        <dd>
            <?php
            echo class_share_escape(
                $application[
                    'applicant_school'
                ]
            );
            ?>
        </dd>

        <dt>연락처</dt>
        <dd>
            <strong>
                <?php
                echo class_share_escape(
                    $formatted_phone
                );
                ?>
            </strong>
        </dd>
    </dl>

    <p class="admin-muted">
        개인정보 보호를 위해 필요한 업무에만 사용해 주세요.
        이 열람은 감사 로그에 기록되었습니다.
    </p>
</section>

<?php

$phone = null;
$formatted_phone = null;

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
