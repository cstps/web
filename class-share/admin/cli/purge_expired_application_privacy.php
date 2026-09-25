<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] =
        'localhost';
}

$bootstrap_output_level =
    ob_get_level();
ob_start();

require_once(
    dirname(__DIR__) .
    '/include/admin_init.php'
);

while (ob_get_level() > $bootstrap_output_level) {
    ob_end_clean();
}

$arguments =
    array_slice(
        $argv,
        1
    );

$execute =
    false;

foreach ($arguments as $argument) {
    if ($argument === '--execute') {
        $execute = true;
        continue;
    }

    if ($argument === '--dry-run') {
        continue;
    }

    fwrite(
        STDERR,
        "사용법: php " .
        basename(__FILE__) .
        " [--dry-run|--execute]\n"
    );

    exit(2);
}

if (
    $execute &&
    in_array(
        '--dry-run',
        $arguments,
        true
    )
) {
    fwrite(
        STDERR,
        "--dry-run과 --execute는 함께 사용할 수 없습니다.\n"
    );

    exit(2);
}

$summary_rows =
    pdo_query(
        "
        SELECT
            COUNT(
                DISTINCT event.id
            ) AS event_count,
            COUNT(
                application.id
            ) AS application_count

        FROM class_share_event AS event

        INNER JOIN class_share_application
            AS application
            ON application.event_id =
               event.id

        WHERE event.retention_until
              IS NOT NULL
          AND event.retention_until <
              CURDATE()
          AND application.privacy_destroyed_at
              IS NULL
        "
    );

if (
    $summary_rows === false ||
    !isset($summary_rows[0])
) {
    fwrite(
        STDERR,
        "개인정보 파기 대상을 확인할 수 없습니다.\n"
    );

    exit(1);
}

$event_count =
    (int)$summary_rows[0]['event_count'];

$application_count =
    (int)$summary_rows[0]['application_count'];

if (!$execute) {
    echo "개인정보 파기 dry-run\n";
    echo "----------------------\n";
    echo "대상 행사: {$event_count}개\n";
    echo "대상 신청: {$application_count}건\n";
    echo "데이터는 변경하지 않았습니다.\n";
    exit(0);
}

echo "개인정보 파기 실행\n";
echo "-----------------\n";

if ($application_count === 0) {
    echo "파기할 개인정보가 없습니다.\n";
    exit(0);
}

if (!($dbh instanceof PDO)) {
    fwrite(
        STDERR,
        "DB 연결이 준비되지 않았습니다.\n"
    );
    exit(1);
}

try {
    $dbh->beginTransaction();

    $target_rows =
        pdo_query(
            "
            SELECT
                application.id,
                application.event_id
            FROM class_share_application
                AS application
            INNER JOIN class_share_event
                AS event
                ON event.id =
                   application.event_id
            WHERE event.retention_until
                  IS NOT NULL
              AND event.retention_until <
                  CURDATE()
              AND application.privacy_destroyed_at
                  IS NULL
            ORDER BY
                application.event_id,
                application.id
            FOR UPDATE
            "
        );

    if ($target_rows === false) {
        throw new RuntimeException(
            '개인정보 파기 대상을 잠글 수 없습니다.'
        );
    }

    $locked_application_count =
        count($target_rows);

    if ($locked_application_count === 0) {
        $dbh->commit();
        echo "파기할 개인정보가 없습니다.\n";
        exit(0);
    }

    $target_event_ids =
        array();

    foreach ($target_rows as $target_row) {
        $target_event_ids[
            (int)$target_row['event_id']
        ] =
            true;
    }

    $locked_event_count =
        count($target_event_ids);

    $audit_update_result =
        pdo_query(
            "
            UPDATE class_share_audit_log
                AS audit_log
            INNER JOIN class_share_application
                AS application
                ON application.id =
                   audit_log.target_id
            INNER JOIN class_share_event
                AS event
                ON event.id =
                   application.event_id
            SET
                audit_log.before_data = NULL,
                audit_log.after_data = NULL,
                audit_log.ip_address = NULL
            WHERE audit_log.target_type =
                  'application'
              AND event.retention_until
                  IS NOT NULL
              AND event.retention_until <
                  CURDATE()
              AND application.privacy_destroyed_at
                  IS NULL
            "
        );

    if ($audit_update_result === false) {
        throw new RuntimeException(
            '신청 감사 자료를 비식별화할 수 없습니다.'
        );
    }

    $application_update_result =
        pdo_query(
            "
            UPDATE class_share_application
                AS application
            INNER JOIN class_share_event
                AS event
                ON event.id =
                   application.event_id
            SET
                application.application_code = NULL,
                application.applicant_name = NULL,
                application.applicant_school = NULL,
                application.phone_ciphertext = NULL,
                application.phone_lookup_hash = NULL,
                application.phone_last4 = NULL,
                application.password_hash = NULL,
                application.admin_note = NULL,
                application.privacy_destroyed_at = NOW(),
                application.updated_at = NOW()
            WHERE event.retention_until
                  IS NOT NULL
              AND event.retention_until <
                  CURDATE()
              AND application.privacy_destroyed_at
                  IS NULL
            "
        );

    if (
        $application_update_result === false ||
        (int)$application_update_result !==
            $locked_application_count
    ) {
        throw new RuntimeException(
            '신청 개인정보 파기 건수가 일치하지 않습니다.'
        );
    }

    $after_data =
        json_encode(
            array(
                'event_count' =>
                    $locked_event_count,
                'application_count' =>
                    $locked_application_count,
                'audit_log_count' =>
                    (int)$audit_update_result
            ),
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($after_data === false) {
        throw new RuntimeException(
            '파기 감사 자료를 만들 수 없습니다.'
        );
    }

    $audit_insert_result =
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
                NULL,
                NULL,
                'system',
                'application.privacy_destroy',
                'privacy_retention',
                NULL,
                NULL,
                ?,
                NULL,
                NOW()
            )
            ",
            $after_data
        );

    if ($audit_insert_result === false) {
        throw new RuntimeException(
            '파기 감사 기록을 저장할 수 없습니다.'
        );
    }

    $dbh->commit();

    echo "파기 행사: {$locked_event_count}개\n";
    echo "파기 신청: {$locked_application_count}건\n";
    echo "비식별 감사 로그: " .
        (int)$audit_update_result .
        "건\n";
    echo "개인정보 파기가 완료되었습니다.\n";
} catch (Throwable $exception) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    fwrite(
        STDERR,
        "개인정보 파기 실패: " .
        $exception->getMessage() .
        "\n"
    );

    exit(1);
}
