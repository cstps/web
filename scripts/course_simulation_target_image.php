<?php

require_once(__DIR__ . '/course_simulation_grade_request.php');

// 호출자는 수업 열람 권한을 먼저 검사해야 한다.
// 정답 코드와 검사 기록은 함수 내부에서만 사용하고 반환하지 않는다.
function course_simulation_target_image(
    PDO $connection,
    $activity_id,
    array $activity
) {
    $activity_id = course_simulation_grade_request_id($activity_id);

    if (
        $activity_id > 2147483647 ||
        ($activity['simulation_mode'] ?? null) !== 'drawing'
    ) {
        throw new InvalidArgumentException(
            '목표 그림 조회 대상이 올바르지 않습니다.'
        );
    }

    $activity_execution = simulation_validate_execution_metadata(
        $activity, true
    );

    $statement = $connection->prepare(
        'SELECT r.reference_id, r.activity_id, r.reference_no,
                r.source_code, r.canvas_width, r.canvas_height, r.p5_version,
                r.source_language, r.execution_engine, r.execution_version,
                r.render_config, r.grading_config, r.max_score,
                i.image_status, i.source_sha256, i.png_data,
                i.png_sha256, i.render_metadata, i.generated_at
         FROM course_simulation_reference r
         LEFT JOIN course_simulation_reference_image i
           ON i.reference_id = r.reference_id
         WHERE r.activity_id = ?
         ORDER BY r.reference_no DESC
         LIMIT 1'
    );

    if (!$statement || !$statement->execute(array($activity_id))) {
        throw new RuntimeException('목표 그림 조회에 실패했습니다.');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$row || ($row['image_status'] ?? null) !== 'ready') {
        return null;
    }

    $execution = simulation_validate_execution_metadata($row, true);

    if (
        $execution !== $activity_execution ||
        (int)$row['canvas_width'] !== (int)$activity['canvas_width'] ||
        (int)$row['canvas_height'] !== (int)$activity['canvas_height'] ||
        (int)$row['canvas_width'] !== 400 ||
        (int)$row['canvas_height'] !== 400
    ) {
        throw new RuntimeException(
            '활동과 목표 그림의 실행 설정이 다릅니다.'
        );
    }

    $png = $row['png_data'];
    $png_hash = $row['png_sha256'];
    $source_hash = hash('sha256', $row['source_code']);

    if (
        !is_string($png) ||
        strlen($png) < 8 ||
        strlen($png) > 1048576 ||
        substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n" ||
        !is_string($png_hash) ||
        preg_match('/^[0-9a-f]{64}$/D', $png_hash) !== 1 ||
        !hash_equals($png_hash, hash('sha256', $png)) ||
        !is_string($row['source_sha256']) ||
        !hash_equals($source_hash, $row['source_sha256'])
    ) {
        throw new RuntimeException(
            '목표 그림의 파일 또는 정답 해시가 다릅니다.'
        );
    }

    $info = @getimagesizefromstring($png);

    if (
        $info === false ||
        $info[2] !== IMAGETYPE_PNG ||
        $info[0] !== 400 ||
        $info[1] !== 400
    ) {
        throw new RuntimeException(
            '목표 PNG의 크기 또는 형식이 다릅니다.'
        );
    }

    $raw_metadata = $row['render_metadata'];

    if (!is_string($raw_metadata) || strlen($raw_metadata) > 131072) {
        throw new RuntimeException(
            '목표 그림 검사 기록이 올바르지 않습니다.'
        );
    }

    $metadata = json_decode(
        $raw_metadata, true, 64, JSON_THROW_ON_ERROR
    );

    if (!is_array($metadata)) {
        throw new RuntimeException('목표 그림 검사 기록이 없습니다.');
    }

    foreach (array(
        'status' => 'ok',
        'reference_source_sha256' => $source_hash,
        'grading_config_sha256' => hash(
            'sha256', $row['grading_config']
        ),
        'png_sha256' => $png_hash,
        'png_bytes' => strlen($png),
        'canvas_width' => 400,
        'canvas_height' => 400,
    ) as $key => $expected) {
        if (($metadata[$key] ?? null) !== $expected) {
            throw new RuntimeException(
                '현재 정답과 목표 그림 검사 기록이 다릅니다.'
            );
        }
    }

    if (
        !is_string($row['generated_at']) ||
        trim($row['generated_at']) === ''
    ) {
        throw new RuntimeException(
            '목표 그림 생성 완료 시각이 없습니다.'
        );
    }

    // 공통 검사로 Python 실행 파일 해시와 영역 검사 기록도 확인한다.
    // 정답 코드를 검사 인자로만 사용하며 코드를 실행하지 않는다.
    course_simulation_grade_request_metadata(
        $activity_id, $row, $row['source_code'], $row
    );

    if ($execution['execution_engine'] === 'p5js') {
        foreach (array(
            'activity_id' => $activity_id,
            'reference_id' => course_simulation_grade_request_id(
                $row['reference_id']
            ),
            'reference_no' => course_simulation_grade_request_id(
                $row['reference_no']
            ),
            'p5_version' => '2.3.4',
        ) as $key => $expected) {
            if (($metadata[$key] ?? null) !== $expected) {
                throw new RuntimeException(
                    'p5.js 목표 그림의 정답 버전이 다릅니다.'
                );
            }
        }
    }

    return array(
        'canvas_width' => 400,
        'canvas_height' => 400,
        'png_data' => $png,
        'png_sha256' => $png_hash,
    );
}
