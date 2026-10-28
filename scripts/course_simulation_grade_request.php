<?php

require_once(__DIR__ . '/simulation_execution_config.php');
require_once(__DIR__ . '/course_simulation_reference_readiness.php');

// DB에서 조회한 번호를 손실 없이 정수로 정규화한다.
function course_simulation_grade_request_id($value)
{
    if (
        (!is_int($value) && !is_string($value)) ||
        preg_match('/^[1-9][0-9]*$/D', (string)$value) !== 1
    ) {
        throw new InvalidArgumentException('채점 대상 번호가 올바르지 않습니다.');
    }

    $number = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        array('options' => array('min_range' => 1))
    );

    if ($number === false) {
        throw new InvalidArgumentException('채점 대상 번호의 범위를 초과했습니다.');
    }

    return $number;
}

// DB 변경 없이 저장할 요청 내용을 만든다.
// reference와 image는 서버가 같은 정답 버전에서 조회한 행이어야 한다.
function course_simulation_grade_request_metadata(
    $activity_id,
    array $reference,
    $source_code,
    $image = null
) {
    $activity_id = course_simulation_grade_request_id($activity_id);
    $reference_id = course_simulation_grade_request_id(
        $reference['reference_id'] ?? null
    );
    $reference_activity = course_simulation_grade_request_id(
        $reference['activity_id'] ?? null
    );
    $reference_no = course_simulation_grade_request_id(
        $reference['reference_no'] ?? null
    );

    if (
        $activity_id > 2147483647 ||
        $reference_no > 2147483647 ||
        $activity_id !== $reference_activity
    ) {
        throw new RuntimeException('활동과 정답 버전의 소속이 다릅니다.');
    }

    if (
        !is_string($source_code) ||
        trim($source_code) === '' ||
        strlen($source_code) > 200000 ||
        strpos($source_code, "\0") !== false ||
        preg_match('//u', $source_code) !== 1
    ) {
        throw new InvalidArgumentException('채점 대상 제출 코드가 올바르지 않습니다.');
    }

    foreach (array('source_code', 'render_config', 'grading_config') as $key) {
        if (!is_string($reference[$key] ?? null)) {
            throw new RuntimeException('정답 버전의 저장 정보가 올바르지 않습니다.');
        }
    }

    $execution = simulation_validate_execution_metadata($reference, true);
    $config = simulation_reference_readiness_config($reference);

    // 기존 영역별 정답의 준비 검사도 그대로 적용한다.
    simulation_validate_reference_readiness($reference, $image);

    $request = array(
        'activity_id' => $activity_id,
        'reference_id' => $reference_id,
        'reference_no' => $reference_no,
        'canvas_width' => (int)$reference['canvas_width'],
        'canvas_height' => (int)$reference['canvas_height'],
        'p5_version' => (string)$reference['p5_version'],
        'reference_max_score' => (string)$reference['max_score'],
        'reference_source_sha256' => hash(
            'sha256', $reference['source_code']
        ),
        'student_source_sha256' => hash('sha256', $source_code),
        'render_config_sha256' => hash(
            'sha256', $reference['render_config']
        ),
        'grading_config_sha256' => hash(
            'sha256', $reference['grading_config']
        ),
    );

    // 기존 p5.js 처리기와 동일한 요청 형식을 유지한다.
    if ($execution['execution_engine'] === 'p5js') {
        return $request;
    }

    if (
        $request['canvas_width'] !== 400 ||
        $request['canvas_height'] !== 400 ||
        !is_array($image) ||
        ($image['image_status'] ?? null) !== 'ready'
    ) {
        throw new RuntimeException('Python 정답 목표 이미지가 준비되지 않았습니다.');
    }

    if (
        course_simulation_grade_request_id(
            $image['reference_id'] ?? null
        ) !== $reference_id
    ) {
        throw new RuntimeException('목표 이미지의 정답 버전이 다릅니다.');
    }

    $png = $image['png_data'] ?? null;
    $png_hash = $image['png_sha256'] ?? null;

    if (
        !is_string($png) ||
        strlen($png) < 8 ||
        strlen($png) > 1048576 ||
        substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n" ||
        !is_string($png_hash) ||
        preg_match('/^[0-9a-f]{64}$/D', $png_hash) !== 1 ||
        !hash_equals($png_hash, hash('sha256', $png))
    ) {
        throw new RuntimeException('Python 목표 PNG의 파일 검사가 실패했습니다.');
    }

    $info = @getimagesizefromstring($png);

    if (
        $info === false ||
        $info[2] !== IMAGETYPE_PNG ||
        $info[0] !== 400 ||
        $info[1] !== 400
    ) {
        throw new RuntimeException('Python 목표 PNG의 크기 또는 형식이 다릅니다.');
    }

    $source_hash = $image['source_sha256'] ?? null;

    if (
        !is_string($source_hash) ||
        !hash_equals($request['reference_source_sha256'], $source_hash)
    ) {
        throw new RuntimeException('정답 코드와 목표 이미지의 해시가 다릅니다.');
    }

    $raw_metadata = $image['render_metadata'] ?? null;

    if (!is_string($raw_metadata) || strlen($raw_metadata) > 131072) {
        throw new RuntimeException('Python 목표 이미지 검사 기록이 올바르지 않습니다.');
    }

    try {
        $metadata = json_decode(
            $raw_metadata, true, 64, JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        throw new RuntimeException(
            'Python 목표 이미지 검사 기록의 JSON이 올바르지 않습니다.',
            0,
            $exception
        );
    }

    if (!is_array($metadata)) {
        throw new RuntimeException('Python 목표 이미지 검사 기록이 없습니다.');
    }

    $runtime_path = __DIR__ . '/course_simulation_python_runtime_poc.py';

    if (
        !is_file($runtime_path) ||
        !is_readable($runtime_path) ||
        filesize($runtime_path) < 1 ||
        filesize($runtime_path) > 100000
    ) {
        throw new RuntimeException('Python 그림 함수 파일을 확인하지 못했습니다.');
    }

    $runtime_hash = hash_file('sha256', $runtime_path);

    if (
        !is_string($runtime_hash) ||
        preg_match('/^[0-9a-f]{64}$/D', $runtime_hash) !== 1
    ) {
        throw new RuntimeException('Python 그림 함수의 해시를 확인하지 못했습니다.');
    }

    foreach (array(
        'status' => 'ok',
        'execution_engine' => $execution['execution_engine'],
        'execution_version' => $execution['execution_version'],
        'runtime_sha256' => $runtime_hash,
        'reference_source_sha256' => $request['reference_source_sha256'],
        'render_config_sha256' => $request['render_config_sha256'],
        'grading_config_sha256' => $request['grading_config_sha256'],
        'png_sha256' => $png_hash,
        'png_bytes' => strlen($png),
        'canvas_width' => 400,
        'canvas_height' => 400,
    ) as $key => $expected) {
        if (($metadata[$key] ?? null) !== $expected) {
            throw new RuntimeException(
                'Python 목표 이미지의 검사 기록이 다릅니다: ' . $key
            );
        }
    }

    if (
        !is_string($image['generated_at'] ?? null) ||
        trim($image['generated_at']) === ''
    ) {
        throw new RuntimeException('목표 이미지 생성 완료 시각이 없습니다.');
    }

    $config['scoring_mode'] = $config['scoring_mode'] ?? 'metrics_only';

    return array_merge($request, $execution, array(
        'schema_version' => 1,
        'frame' => 0,
        'runtime_sha256' => $runtime_hash,
        'target_png_sha256' => $png_hash,
        'grading_config' => $config,
        'max_score' => (string)$reference['max_score'],
    ));
}

// 호출자는 정답 버전을 잠근 트랜잭션 안에서 사용한다.
function course_simulation_build_grade_request(
    PDO $connection,
    $activity_id,
    array $reference,
    $source_code
) {
    if (!$connection->inTransaction()) {
        throw new RuntimeException(
            '채점 요청 생성은 제출 저장 트랜잭션 안에서 수행해야 합니다.'
        );
    }

    $execution = simulation_validate_execution_metadata($reference, true);
    $config = simulation_reference_readiness_config($reference);
    $image = null;

    if (
        $execution['source_language'] === 'python' ||
        ($config['scoring_mode'] ?? 'metrics_only') === 'regions'
    ) {
        $statement = $connection->prepare(
            'SELECT
                reference_id, image_status, source_sha256,
                png_data, png_sha256, render_metadata, generated_at
             FROM course_simulation_reference_image
             WHERE reference_id = ?
             FOR UPDATE'
        );

        if (
            !$statement ||
            !$statement->execute(array($reference['reference_id']))
        ) {
            throw new RuntimeException('정답 목표 이미지를 조회하지 못했습니다.');
        }

        $image = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
    }

    return course_simulation_grade_request_metadata(
        $activity_id, $reference, $source_code, $image
    );
}
