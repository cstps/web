#!/usr/bin/env php
<?php

// 서버에서 생성·검증한 목표 PNG를 정답 버전에 연결하여 저장한다.
// 웹에서는 실행할 수 없다.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

$options = getopt('', array('reference-id:', 'png:', 'metadata:'));

$reference_id = filter_var(
    $options['reference-id'] ?? '',
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if ($reference_id === false ||
    !isset($options['png'], $options['metadata'])) {
    fwrite(
        STDERR,
        "사용법: php scripts/course_simulation_reference_image_import.php " .
        "--reference-id=2 --png=경로 --metadata=경로\n"
    );
    exit(1);
}

$root_dir = dirname(__DIR__);
$db = null;
$exit_code = 0;

try {
    // 읽는 크기를 제한한다.
    $png = file_get_contents(
        (string)$options['png'], false, null, 0, 1048577
    );
    $metadata_text = file_get_contents(
        (string)$options['metadata'], false, null, 0, 65537
    );

    if ($png === false || strlen($png) === 0 ||
        strlen($png) > 1048576) {
        throw new RuntimeException('PNG 파일 크기가 올바르지 않습니다.');
    }

    if ($metadata_text === false ||
        strlen($metadata_text) > 65536) {
        throw new RuntimeException('생성 결과 파일을 읽을 수 없거나 너무 큽니다.');
    }

    $metadata = json_decode($metadata_text, true);

    if (!is_array($metadata) ||
        ($metadata['status'] ?? '') !== 'ok' ||
        ($metadata['comparison_mode'] ?? '') !== 'static-image-poc-v1') {
        throw new RuntimeException('생성 결과 JSON이 올바르지 않습니다.');
    }

    $image_info = @getimagesizefromstring($png);

    if ($image_info === false || $image_info[2] !== IMAGETYPE_PNG) {
        throw new RuntimeException('PNG 이미지가 아닙니다.');
    }

    $png_sha256 = hash('sha256', $png);

    if (($metadata['png_sha256'] ?? '') !== $png_sha256 ||
        ($metadata['png_bytes'] ?? null) !== strlen($png)) {
        throw new RuntimeException('PNG 해시 또는 파일 크기가 다릅니다.');
    }

    $p5_path = $root_dir . '/template/syzoj/js/vendor/p5.min.js';
    if (!is_readable($p5_path) ||
        ($metadata['p5_file_sha256'] ?? '') !== hash_file('sha256', $p5_path)) {
        throw new RuntimeException('현재 p5.js 파일과 생성 당시 파일이 다릅니다.');
    }

    if (!isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = '1024.kr';
    }

    require_once($root_dir . '/include/db_info.inc.php');
    if (!function_exists('pdo_query')) {
        require_once($root_dir . '/include/pdo.php');
    }

    if (pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('DB 연결에 실패했습니다.');
    }

    $db = $dbh;

    if ($db->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    $db->beginTransaction();

    $stmt = $db->prepare(
        'SELECT reference_id, activity_id, reference_no, source_code,
                canvas_width, canvas_height, p5_version
         FROM course_simulation_reference
         WHERE reference_id = ?
         FOR UPDATE'
    );
    $stmt->execute(array($reference_id));
    $reference = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$reference) {
        throw new RuntimeException('정답을 찾을 수 없습니다.');
    }

    foreach (array(
        'reference_id', 'activity_id', 'reference_no',
        'canvas_width', 'canvas_height'
    ) as $key) {
        if (($metadata[$key] ?? null) !== (int)$reference[$key]) {
            throw new RuntimeException('정답 정보와 생성 결과가 다릅니다: ' . $key);
        }
    }

    $source_sha256 = hash('sha256', (string)$reference['source_code']);

    if (($metadata['reference_source_sha256'] ?? '') !== $source_sha256 ||
        ($metadata['p5_version'] ?? '') !== (string)$reference['p5_version']) {
        throw new RuntimeException('정답 코드 또는 p5.js 버전이 다릅니다.');
    }

    if ($image_info[0] !== (int)$reference['canvas_width'] ||
        $image_info[1] !== (int)$reference['canvas_height']) {
        throw new RuntimeException('PNG와 정답의 캔버스 크기가 다릅니다.');
    }

    $elapsed = $metadata['elapsed_seconds'] ?? null;
    if ((!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) || $elapsed < 0) {
        throw new RuntimeException('생성 시간이 올바르지 않습니다.');
    }

    // 필요한 생성 정보만 저장한다.
    $safe_metadata = array();
    foreach (array(
        'status', 'comparison_mode', 'activity_id', 'reference_id',
        'reference_no', 'canvas_width', 'canvas_height', 'p5_version',
        'reference_source_sha256', 'p5_file_sha256',
        'png_sha256', 'png_bytes', 'elapsed_seconds'
    ) as $key) {
        $safe_metadata[$key] = $metadata[$key];
    }

    $metadata_json = json_encode(
        $safe_metadata,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($metadata_json === false) {
        throw new RuntimeException('저장용 JSON 생성에 실패했습니다.');
    }

    $stmt = $db->prepare(
        'SELECT image_status, source_sha256, png_sha256
         FROM course_simulation_reference_image
         WHERE reference_id = ?
         FOR UPDATE'
    );
    $stmt->execute(array($reference_id));
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $already_ready = false;

    if ($existing) {
        if ($existing['source_sha256'] !== $source_sha256) {
            throw new RuntimeException('기존 이미지 요청과 정답 코드 해시가 다릅니다.');
        }

        if ($existing['image_status'] === 'running') {
            throw new RuntimeException('이미지 생성 작업이 진행 중입니다.');
        }

        if ($existing['image_status'] === 'ready') {
            if ($existing['png_sha256'] !== $png_sha256) {
                throw new RuntimeException(
                    '다른 이미지가 이미 저장돼 있습니다. 덮어쓰지 않습니다.'
                );
            }
            $already_ready = true;
        }
    }

    if (!$already_ready) {
        if ($existing) {
            $stmt = $db->prepare(
                "UPDATE course_simulation_reference_image
                 SET image_status = 'ready',
                     png_data = ?, png_sha256 = ?,
                     render_metadata = ?, error_message = NULL,
                     generated_at = NOW()
                 WHERE reference_id = ?"
            );
            $stmt->bindValue(1, $png, PDO::PARAM_LOB);
            $stmt->bindValue(2, $png_sha256);
            $stmt->bindValue(3, $metadata_json);
            $stmt->bindValue(4, $reference_id, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO course_simulation_reference_image
                    (png_data, png_sha256, render_metadata,
                     reference_id, source_sha256, image_status, generated_at)
                 VALUES (?, ?, ?, ?, ?, 'ready', NOW())"
            );
            $stmt->bindValue(1, $png, PDO::PARAM_LOB);
            $stmt->bindValue(2, $png_sha256);
            $stmt->bindValue(3, $metadata_json);
            $stmt->bindValue(4, $reference_id, PDO::PARAM_INT);
            $stmt->bindValue(5, $source_sha256);
        }

        $stmt->execute();
        $stmt->closeCursor();
    }

    $db->commit();

    echo "정답 ID: " . $reference_id . "\n";
    echo "정답 버전: " . $reference['reference_no'] . "\n";
    echo "이미지 상태: ready\n";
    echo "PNG 크기: " . strlen($png) . "바이트\n";
    echo $already_ready
        ? "결과: 동일 이미지가 이미 저장돼 있습니다.\n"
        : "결과: 목표 PNG 저장 완료\n";

} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    $exit_code = 1;
}

exit($exit_code);
