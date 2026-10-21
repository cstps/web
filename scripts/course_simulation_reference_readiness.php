<?php

// DB 변경 없이 영역별 정답의 준비 상태를 검사한다.
function simulation_reference_readiness_config(array $reference)
{
    $raw = $reference['grading_config'] ?? null;

    if (!is_string($raw)) {
        throw new RuntimeException('정답 채점 설정이 올바르지 않습니다.');
    }

    $config = json_decode($raw, true);
    if (!is_array($config)) {
        throw new RuntimeException('정답 채점 설정을 해석하지 못했습니다.');
    }

    $mode = $config['scoring_mode'] ?? 'metrics_only';
    if (
        !is_string($mode) ||
        !in_array($mode, array('metrics_only', 'whole_image', 'regions'), true)
    ) {
        throw new RuntimeException('지원하지 않는 점수 산정 방식입니다.');
    }

    if ($mode === 'regions') {
        require_once(__DIR__ . '/course_simulation_region_config.php');

        if (
            ($config['metric'] ?? '') !==
                'symmetric_foreground_color_coverage' ||
            ($config['neighborhood'] ?? '') !== 'euclidean_disk' ||
            ($config['scoring_method'] ?? '') !==
                'region_coverage_linear_v1' ||
            !is_int($config['position_tolerance_px'] ?? null) ||
            $config['position_tolerance_px'] < 0 ||
            $config['position_tolerance_px'] > 10 ||
            ($config['color_tolerance'] ?? null) !== 0
        ) {
            throw new RuntimeException('영역별 정답 설정이 올바르지 않습니다.');
        }

        $config['regions'] = simulation_validate_region_config(
            $config['regions'] ?? null,
            (string)($reference['max_score'] ?? ''),
            400,
            400
        );
    }

    return $config;
}

function simulation_validate_reference_readiness(
    array $reference,
    $image
) {
    $config = simulation_reference_readiness_config($reference);

    if (($config['scoring_mode'] ?? 'metrics_only') !== 'regions') {
        return;
    }

    if (!is_array($image)) {
        throw new RuntimeException(
            '정답의 목표 이미지 생성 요청이 없습니다. 교사 확인이 필요합니다.'
        );
    }

    $state = $image['image_status'] ?? '';

    if ($state === 'pending' || $state === 'running') {
        throw new RuntimeException(
            '정답의 목표 이미지 생성 및 영역 검사가 진행 중입니다.'
        );
    }

    if ($state !== 'ready') {
        throw new RuntimeException(
            '정답의 목표 이미지 생성 또는 영역 검사가 완료되지 않았습니다.'
        );
    }

    $source = $reference['source_code'] ?? null;
    if (!is_string($source)) {
        throw new RuntimeException('정답 코드를 확인하지 못했습니다.');
    }

    $metadata_raw = $image['render_metadata'] ?? null;
    $metadata = is_string($metadata_raw)
        ? json_decode($metadata_raw, true)
        : null;

    if (!is_array($metadata)) {
        throw new RuntimeException('정답의 이미지 검사 기록이 올바르지 않습니다.');
    }

    $source_hash = hash('sha256', $source);
    $config_hash = hash('sha256', $reference['grading_config']);

    foreach (array(
        $image['source_sha256'] ?? null,
        $metadata['reference_source_sha256'] ?? null,
    ) as $stored_hash) {
        if (
            !is_string($stored_hash) ||
            !hash_equals($source_hash, $stored_hash)
        ) {
            throw new RuntimeException(
                '정답 코드와 목표 이미지의 검사 기록이 일치하지 않습니다.'
            );
        }
    }

    $stored_config_hash = $metadata['grading_config_sha256'] ?? null;
    if (
        !is_string($stored_config_hash) ||
        !hash_equals($config_hash, $stored_config_hash)
    ) {
        throw new RuntimeException(
            '정답 설정과 영역 검사 기록이 일치하지 않습니다.'
        );
    }

    $validation = $metadata['region_validation'] ?? null;
    if (
        !is_array($validation) ||
        ($validation['status'] ?? '') !== 'valid'
    ) {
        throw new RuntimeException(
            '정답의 영역 검사 통과 기록이 없습니다. 교사 확인이 필요합니다.'
        );
    }

    $comparison = $validation['comparison'] ?? null;
    if (
        !is_array($comparison) ||
        ($comparison['metric'] ?? '') !==
            'weighted_region_color_coverage_v1' ||
        !is_numeric($comparison['final_coverage'] ?? null) ||
        (float)$comparison['final_coverage'] !== 100.0 ||
        ($comparison['outside_student_foreground'] ?? null) !== 0 ||
        !is_array($comparison['regions'] ?? null) ||
        count($comparison['regions']) !== count($config['regions'])
    ) {
        throw new RuntimeException(
            '정답의 영역 검사 결과를 확인하지 못했습니다.'
        );
    }
}

// 호출자는 정답 버전을 잠근 트랜잭션 안에서 사용한다.
function simulation_assert_reference_ready(
    PDO $connection,
    array $reference
) {
    $config = simulation_reference_readiness_config($reference);

    if (($config['scoring_mode'] ?? 'metrics_only') !== 'regions') {
        return;
    }

    if (!$connection->inTransaction()) {
        throw new RuntimeException(
            '정답 준비 상태 검사는 트랜잭션 안에서 수행해야 합니다.'
        );
    }

    $stmt = $connection->prepare(
        'SELECT image_status, source_sha256, render_metadata
         FROM course_simulation_reference_image
         WHERE reference_id = ?
         FOR UPDATE'
    );
    $stmt->execute(array($reference['reference_id']));
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    simulation_validate_reference_readiness($reference, $image);
}
