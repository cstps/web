<?php

require_once(__DIR__ . '/simulation_execution_config.php');
require_once(__DIR__ . '/course_simulation_region_config.php');


// JSON 객체의 키 순서에 따라 해시가 달라지지 않도록 정렬한다.
function simulation_problem_image_canonical_value($value)
{
    if ($value instanceof stdClass) {
        $properties = get_object_vars($value);
        ksort($properties, SORT_STRING);
        $result = new stdClass();

        foreach ($properties as $key => $item) {
            $result->{$key} = simulation_problem_image_canonical_value($item);
        }

        return $result;
    }

    if (is_array($value)) {
        $result = array();
        foreach ($value as $item) {
            $result[] = simulation_problem_image_canonical_value($item);
        }
        return $result;
    }

    return $value;
}


function simulation_problem_image_canonical_json($value)
{
    return json_encode(
        simulation_problem_image_canonical_value($value),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRESERVE_ZERO_FRACTION |
        JSON_THROW_ON_ERROR
    );
}


function simulation_problem_image_positive_id($value)
{
    if (
        !is_string($value) ||
        preg_match('/^[1-9][0-9]{0,18}$/D', $value) !== 1 ||
        (
            strlen($value) === 19 &&
            strcmp($value, '9223372036854775807') > 0
        )
    ) {
        throw new InvalidArgumentException('문제 또는 버전 번호가 올바르지 않습니다.');
    }

    return $value;
}


// DB에서 조회한 정답 버전으로만 호출한다.
// 정답 코드는 실행하지 않고 해시만 기록한다.
function simulation_problem_image_request_metadata(array $row)
{
    $version_id = simulation_problem_image_positive_id(
        $row['version_id'] ?? null
    );
    $problem_id = simulation_problem_image_positive_id(
        $row['problem_id'] ?? null
    );

    $version_no = $row['version_no'] ?? null;
    if (is_int($version_no)) {
        $version_no = (string)$version_no;
    }

    if (
        !is_string($version_no) ||
        preg_match('/^[1-9][0-9]{0,9}$/D', $version_no) !== 1 ||
        (float)$version_no > 2147483647
    ) {
        throw new InvalidArgumentException('문제 버전 회차가 올바르지 않습니다.');
    }

    if (
        ($row['simulation_mode'] ?? null) !== 'drawing' ||
        !in_array($row['canvas_width'] ?? null, array(400, '400'), true) ||
        !in_array($row['canvas_height'] ?? null, array(400, '400'), true)
    ) {
        throw new InvalidArgumentException(
            '목표 이미지 생성은 400×400 그림 문제만 지원합니다.'
        );
    }

    $execution = simulation_validate_execution_metadata($row, true);
    $source = $row['reference_source'] ?? null;

    if (
        !is_string($source) ||
        trim($source) === '' ||
        strlen($source) > 200000 ||
        strpos($source, "\0") !== false ||
        preg_match('//u', $source) !== 1
    ) {
        throw new InvalidArgumentException('정답 코드가 올바르지 않습니다.');
    }

    $configs = array();

    foreach (array('render_config', 'grading_config') as $key) {
        if (!is_string($row[$key] ?? null)) {
            throw new InvalidArgumentException('정답 설정을 확인하지 못했습니다.');
        }

        $decoded = json_decode(
            $row[$key],
            false,
            64,
            JSON_THROW_ON_ERROR
        );

        if (!$decoded instanceof stdClass) {
            throw new InvalidArgumentException('정답 설정은 JSON 객체여야 합니다.');
        }

        $configs[$key] = $decoded;
    }

    $units = simulation_region_score_units($row['max_score'] ?? null);
    $max_score = sprintf(
        '%d.%02d', intdiv($units, 100), $units % 100
    );

    $settings = (object)array_merge($execution, array(
        'schema_version' => 1,
        'canvas_width' => 400,
        'canvas_height' => 400,
        'frame' => 0,
        'max_score' => $max_score,
        'render_config' => $configs['render_config'],
        'grading_config' => $configs['grading_config'],
    ));

    if ($execution['execution_engine'] === 'p5js') {
        $artifact = dirname(__DIR__) .
            '/template/syzoj/js/vendor/p5.min.js';
        $artifact_key = 'p5_file_sha256';
    } else {
        $artifact = __DIR__ . '/course_simulation_python_runtime_poc.py';
        $artifact_key = 'runtime_sha256';
    }

    if (!is_file($artifact) || !is_readable($artifact)) {
        throw new RuntimeException('그림 실행 파일을 읽지 못했습니다.');
    }

    $artifact_hash = hash_file('sha256', $artifact);
    if (!is_string($artifact_hash)) {
        throw new RuntimeException('그림 실행 파일 해시 생성에 실패했습니다.');
    }

    $settings->{$artifact_key} = $artifact_hash;

    return array(
        'schema_version' => 1,
        'problem_id' => $problem_id,
        'version_id' => $version_id,
        'version_no' => (int)$version_no,
        'source_sha256' => hash('sha256', $source),
        'settings_sha256' => hash(
            'sha256',
            simulation_problem_image_canonical_json($settings)
        ),
        'settings' => $settings,
    );
}


function simulation_enqueue_problem_reference_image(
    PDO $connection,
    $version_id
) {
    if (!$connection->inTransaction()) {
        throw new RuntimeException('이미지 요청 저장에는 트랜잭션이 필요합니다.');
    }

    $version_id = simulation_problem_image_positive_id($version_id);

    $statement = $connection->prepare(
        'SELECT
            CAST(v.version_id AS CHAR) AS version_id,
            CAST(v.problem_id AS CHAR) AS problem_id,
            v.version_no, v.simulation_mode,
            v.canvas_width, v.canvas_height, v.p5_version,
            v.source_language, v.execution_engine, v.execution_version,
            r.source_code AS reference_source,
            r.render_config, r.grading_config, r.max_score
         FROM simulation_problem_version v
         INNER JOIN simulation_problem_reference r
           ON r.version_id = v.version_id
         WHERE v.version_id = ?
         FOR UPDATE'
    );

    if (!$statement || !$statement->execute(array($version_id))) {
        throw new RuntimeException('이미지 생성 대상 조회에 실패했습니다.');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$row) {
        throw new RuntimeException('이미지 생성 대상 정답을 찾지 못했습니다.');
    }

    $metadata = simulation_problem_image_request_metadata($row);

    $statement = $connection->prepare(
        "INSERT INTO simulation_problem_reference_image
            (
                version_id, source_sha256, settings_sha256,
                request_metadata, image_status
            )
         VALUES (?, ?, ?, ?, 'pending')"
    );

    if (!$statement || !$statement->execute(array(
        $version_id,
        $metadata['source_sha256'],
        $metadata['settings_sha256'],
        simulation_problem_image_canonical_json((object)$metadata),
    ))) {
        throw new RuntimeException('이미지 생성 요청 저장에 실패했습니다.');
    }

    $statement->closeCursor();
    return $metadata;
}
