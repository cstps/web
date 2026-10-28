<?php

require_once(__DIR__ . '/course_simulation_grade_request.php');
require_once(__DIR__ . '/course_simulation_region_result.php');

// CLI 처리기에서만 호출한다.
// 컨테이너 실행·비교·정리는 기존 Python 실행 관리 도구가 담당한다.
// 이 함수는 DB를 변경하지 않고 검증된 PNG와 저장용 검사 기록을 반환한다.
function course_simulation_render_python_reference(
    array $reference,
    $image_id
) {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('목표 이미지 생성은 CLI에서 수행해야 합니다.');
    }

    if (
        !is_string($image_id) ||
        preg_match('/^sha256:[0-9a-f]{64}$/D', $image_id) !== 1
    ) {
        throw new InvalidArgumentException('고정된 로컬 실행 이미지 ID가 필요합니다.');
    }

    $execution = simulation_validate_execution_metadata($reference, true);

    if (
        $execution['source_language'] !== 'python' ||
        $execution['execution_engine'] !== 'python-stdlib-rgba-poc' ||
        $execution['execution_version'] !== '1' ||
        !in_array($reference['canvas_width'] ?? null, array(400, '400'), true) ||
        !in_array($reference['canvas_height'] ?? null, array(400, '400'), true)
    ) {
        throw new RuntimeException('지원하지 않는 Python 목표 이미지 설정입니다.');
    }

    $source_code = $reference['source_code'] ?? null;

    if (
        !is_string($source_code) ||
        trim($source_code) === '' ||
        strlen($source_code) > 200000 ||
        strpos($source_code, "\0") !== false ||
        preg_match('//u', $source_code) !== 1 ||
        !is_string($reference['render_config'] ?? null) ||
        !is_string($reference['grading_config'] ?? null)
    ) {
        throw new RuntimeException('정답 코드 또는 저장된 설정이 올바르지 않습니다.');
    }

    $grading = simulation_reference_readiness_config($reference);
    $grading['scoring_mode'] = $grading['scoring_mode'] ?? 'metrics_only';

    $adapter = __DIR__ . '/simulation_problem_python_reference.py';
    $runtime = __DIR__ . '/course_simulation_python_runtime_poc.py';

    foreach (array($adapter, $runtime) as $file) {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Python 목표 이미지 실행 파일이 없습니다.');
        }
    }

    if (filesize($runtime) < 1 || filesize($runtime) > 100000) {
        throw new RuntimeException('Python 그림 함수 파일 크기가 올바르지 않습니다.');
    }

    $runtime_hash = hash_file('sha256', $runtime);
    $adapter_hash = hash_file('sha256', $adapter);
    $source_hash = hash('sha256', $source_code);

    $directory = sys_get_temp_dir() .
        '/course-python-reference-' . bin2hex(random_bytes(16));

    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('목표 이미지 임시 디렉터리 생성 실패');
    }

    $files = array(
        'request' => $directory . '/request.json',
        'grading' => $directory . '/grading.json',
        'png' => $directory . '/target.png',
        'report' => $directory . '/report.json',
        'error' => $directory . '/error.log',
    );

    try {
        $inputs = array(
            'request' => array(
                'schema_version' => 1,
                'engine' => 'python-stdlib-rgba-poc-v1',
                'runtime_sha256' => $runtime_hash,
                'source_code' => $source_code,
                'frame' => 0,
            ),
            'grading' => array(
                'max_score' => (string)$reference['max_score'],
                'grading_config' => $grading,
            ),
        );

        foreach ($inputs as $key => $input) {
            $json = json_encode(
                $input,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            );

            if (file_put_contents($files[$key], $json) !== strlen($json)) {
                throw new RuntimeException('목표 이미지 실행 요청 저장 실패');
            }

            if (!chmod($files[$key], 0600)) {
                throw new RuntimeException('실행 요청 파일 권한 설정 실패');
            }
        }

        // root 소유 파일을 컨테이너 입력으로 사용하지 않는다.
        if (fileowner($files['request']) === 0) {
            throw new RuntimeException('일반 사용자 또는 서비스 계정으로 실행해 주세요.');
        }

        $process = proc_open(
            array(
                '/usr/bin/python3',
                '-B',
                $adapter,
                '--input-json', $files['request'],
                '--grading-json', $files['grading'],
                '--image-id', $image_id,
                '--output-png', $files['png'],
            ),
            array(
                0 => array('file', '/dev/null', 'r'),
                1 => array('file', $files['report'], 'w'),
                2 => array('file', $files['error'], 'w'),
            ),
            $pipes,
            dirname(__DIR__)
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Python 목표 이미지 생성기를 시작하지 못했습니다.');
        }

        $exit_code = proc_close($process);

        if ($exit_code !== 0) {
            throw new RuntimeException('Python 목표 이미지 생성 또는 영역 검사에 실패했습니다.');
        }

        clearstatcache();

        if (
            !is_file($files['report']) ||
            is_link($files['report']) ||
            filesize($files['report']) < 1 ||
            filesize($files['report']) > 65536
        ) {
            throw new RuntimeException('목표 이미지 검사 결과 파일이 올바르지 않습니다.');
        }

        try {
            $report = json_decode(
                file_get_contents($files['report']),
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                '목표 이미지 검사 결과 JSON이 올바르지 않습니다.',
                0,
                $exception
            );
        }

        foreach (array(
            'schema_version' => 1,
            'status' => 'ok',
            'engine' => 'python-stdlib-rgba-poc-v1',
            'runtime_sha256' => $runtime_hash,
            'source_sha256' => $source_hash,
            'frame' => 0,
            'width' => 400,
            'height' => 400,
        ) as $key => $expected) {
            if (!is_array($report) || ($report[$key] ?? null) !== $expected) {
                throw new RuntimeException(
                    '목표 이미지 실행 결과의 설정이 다릅니다: ' . $key
                );
            }
        }

        if (!is_file($files['png']) || is_link($files['png'])) {
            throw new RuntimeException('목표 PNG 파일 형식이 올바르지 않습니다.');
        }

        $png = file_get_contents($files['png'], false, null, 0, 1048577);

        if (
            !is_string($png) ||
            strlen($png) < 8 ||
            strlen($png) > 1048576 ||
            substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n"
        ) {
            throw new RuntimeException('목표 PNG 파일 크기 또는 서명이 올바르지 않습니다.');
        }

        $png_hash = hash('sha256', $png);
        $info = @getimagesizefromstring($png);

        if (
            $info === false ||
            $info[2] !== IMAGETYPE_PNG ||
            $info[0] !== 400 ||
            $info[1] !== 400 ||
            ($report['png_sha256'] ?? null) !== $png_hash ||
            ($report['png_bytes'] ?? null) !== strlen($png)
        ) {
            throw new RuntimeException('목표 PNG의 크기·형식·해시 검사가 실패했습니다.');
        }

        $elapsed = $report['elapsed_seconds'] ?? null;

        if (
            (!is_int($elapsed) && !is_float($elapsed)) ||
            !is_finite((float)$elapsed) ||
            $elapsed < 0
        ) {
            throw new RuntimeException('목표 이미지 실행 시간이 올바르지 않습니다.');
        }

        if (
            hash_file('sha256', $runtime) !== $runtime_hash ||
            hash_file('sha256', $adapter) !== $adapter_hash
        ) {
            throw new RuntimeException('목표 이미지 생성 중 실행 파일이 변경됐습니다.');
        }

        $metadata = array(
            'schema_version' => 1,
            'status' => 'ok',
            'comparison_mode' => 'python-static-image-v1',
            'source_language' => $execution['source_language'],
            'execution_engine' => $execution['execution_engine'],
            'execution_version' => $execution['execution_version'],
            'runtime_sha256' => $runtime_hash,
            'adapter_sha256' => $adapter_hash,
            'reference_source_sha256' => $source_hash,
            'render_config_sha256' => hash('sha256', $reference['render_config']),
            'grading_config_sha256' => hash('sha256', $reference['grading_config']),
            'png_sha256' => $png_hash,
            'png_bytes' => strlen($png),
            'canvas_width' => 400,
            'canvas_height' => 400,
            'elapsed_seconds' => (float)$elapsed,
        );

        if ($grading['scoring_mode'] === 'regions') {
            $validation = $report['region_validation'] ?? null;

            if (!is_array($validation)) {
                throw new RuntimeException('Python 정답의 영역 검사 결과가 없습니다.');
            }

            $comparison = simulation_validate_region_result(
                $validation['comparison'] ?? null,
                $grading['regions'],
                (string)$reference['max_score'],
                $grading['position_tolerance_px'],
                $validation['tolerance'] ?? null
            );

            if (
                (float)$comparison['final_coverage'] !== 100.0 ||
                $comparison['outside_student_foreground'] !== 0
            ) {
                throw new RuntimeException('정답 그림이 채점 영역을 충족하지 않습니다.');
            }

            $metadata['region_validation'] = array(
                'status' => 'valid',
                'comparison' => $comparison,
            );
        }

        // 새로 만든 검사 기록이 공통 채점 요청 조건도 충족하는지 확인한다.
        course_simulation_grade_request_metadata(
            $reference['activity_id'],
            $reference,
            $source_code,
            array(
                'reference_id' => $reference['reference_id'],
                'image_status' => 'ready',
                'source_sha256' => $source_hash,
                'png_data' => $png,
                'png_sha256' => $png_hash,
                'render_metadata' => json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
                'generated_at' => date('Y-m-d H:i:s'),
            )
        );

        return array(
            'png_data' => $png,
            'png_sha256' => $png_hash,
            'render_metadata' => $metadata,
        );

    } finally {
        foreach ($files as $file) {
            if (is_file($file) || is_link($file)) {
                if (!unlink($file)) {
                    error_log('[course_python_reference] 임시 파일 정리 실패');
                }
            }
        }

        if (!rmdir($directory)) {
            error_log('[course_python_reference] 임시 디렉터리 정리 실패');
        }
    }
}
