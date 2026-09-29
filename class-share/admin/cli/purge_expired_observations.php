<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

$bootstrap_output_level = ob_get_level();
ob_start();

require_once(
    dirname(__DIR__) .
    '/include/admin_init.php'
);

while (ob_get_level() > $bootstrap_output_level) {
    ob_end_clean();
}

$arguments = array_slice($argv, 1);
$execute = false;

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
    in_array('--dry-run', $arguments, true)
) {
    fwrite(
        STDERR,
        "--dry-run과 --execute는 함께 사용할 수 없습니다.\n"
    );
    exit(2);
}

if (!($dbh instanceof PDO)) {
    fwrite(STDERR, "DB 연결이 준비되지 않았습니다.\n");
    exit(1);
}

/*
 * retention_until은 한국 날짜로 입력됩니다.
 * MySQL 세션 시간대와 무관하게 한국 날짜를 사용합니다.
 */
$expired_condition =
    "retention_until < " .
    "DATE(UTC_TIMESTAMP() + INTERVAL 9 HOUR)";

$summary_rows = pdo_query(
    "
    SELECT
        COUNT(*) AS observation_count,
        COUNT(DISTINCT event_id) AS event_count,
        SUM(
            CASE
                WHEN archive_body IS NOT NULL
                 AND archive_reviewed_at IS NOT NULL
                THEN 1
                ELSE 0
            END
        ) AS archive_count
    FROM class_share_observation
    WHERE {$expired_condition}
    "
);

if (
    $summary_rows === false ||
    !isset($summary_rows[0])
) {
    fwrite(STDERR, "파기 대상을 조회하지 못했습니다.\n");
    exit(1);
}

$count = (int)$summary_rows[0]['observation_count'];
$event_count = (int)$summary_rows[0]['event_count'];
$archive_count = (int)$summary_rows[0]['archive_count'];

if (!$execute) {
    echo "참관록 파기 dry-run\n";
    echo "-------------------\n";
    echo "대상 행사: {$event_count}개\n";
    echo "대상 참관록: {$count}건\n";
    echo "검토 완료 보존용 내용: {$archive_count}건\n";
    echo "데이터는 변경하지 않았습니다.\n";
    exit(0);
}

if ($count === 0) {
    echo "파기할 참관록이 없습니다.\n";
    exit(0);
}

try {
    $dbh->beginTransaction();

    $archived = pdo_query(
        "
        INSERT INTO class_share_observation_archive (
            event_id,
            class_id,
            body
        )
        SELECT
            event_id,
            class_id,
            archive_body
        FROM class_share_observation
        WHERE {$expired_condition}
          AND archive_body IS NOT NULL
          AND archive_reviewed_at IS NOT NULL
        "
    );

    if ($archived === false) {
        throw new RuntimeException(
            '검토된 보존용 내용을 옮기지 못했습니다.'
        );
    }

    $deleted = pdo_query(
        "
        DELETE FROM class_share_observation
        WHERE {$expired_condition}
        "
    );

    if ($deleted === false) {
        throw new RuntimeException(
            '참관록을 삭제하지 못했습니다.'
        );
    }

    if ((int)$deleted < (int)$archived) {
        throw new RuntimeException(
            '보존 건수와 파기 건수가 일치하지 않습니다.'
        );
    }

    $dbh->commit();

    echo "보존용 내용 이동: " .
        (int)$archived .
        "건\n";
    echo "원본 참관록 파기: " .
        (int)$deleted .
        "건\n";
} catch (Throwable $exception) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    fwrite(
        STDERR,
        "참관록 파기 실패: " .
        $exception->getMessage() .
        "\n"
    );
    exit(1);
}
