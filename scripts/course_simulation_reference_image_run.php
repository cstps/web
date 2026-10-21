#!/usr/bin/env php
<?php

// 목표 이미지 생성 대기 요청 하나를 처리하는 CLI.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

function reference_image_exec(array $args, $stdout, $stderr)
{
    $command = implode(' ', array_map('escapeshellarg', $args)) .
        ' > ' . escapeshellarg($stdout) .
        ' 2> ' . escapeshellarg($stderr);

    $unused = array();
    $code = 0;
    exec($command, $unused, $code);
    return $code;
}

$options = getopt('', array('reference-id:'));
$reference_id = filter_var(
    $options['reference-id'] ?? '',
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if ($reference_id === false) {
    fwrite(
        STDERR,
        "사용법: php scripts/course_simulation_reference_image_run.php " .
        "--reference-id=정답ID\n"
    );
    exit(1);
}

$root_dir = dirname(__DIR__);
$db = null;
$claimed = false;
$claim_token = null;
$temp_dir = null;
$container_name = null;
$docker_prefix = null;
$exit_code = 0;

try {
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

    require_once($root_dir . '/scripts/course_simulation_job_lock.php');

    // 실행 종료까지 연결을 유지해 작업 잠금을 보유한다.
    $job_lock_connection = simulation_job_acquire_lock(
        'image',
        $reference_id
    );

    if ($job_lock_connection === null) {
        throw new RuntimeException('다른 실행기가 이 작업을 처리 중입니다.');
    }


    $exporter = $root_dir . '/scripts/course_simulation_render_reference.py';
    $renderer = $root_dir . '/scripts/course_simulation_render_grade.py';
    $metrics_script = $root_dir . '/scripts/course_simulation_image_metrics.py';
    $region_script = $root_dir . '/scripts/course_simulation_region_metrics.py';
    $p5_file = $root_dir . '/template/syzoj/js/vendor/p5.min.js';

    foreach (array($exporter, $renderer, $metrics_script, $region_script, $p5_file) as $file) {
        if (!is_readable($file)) {
            throw new RuntimeException('필요한 파일을 읽을 수 없습니다: ' . $file);
        }
    }

    foreach (array('/usr/bin/timeout', '/usr/bin/docker') as $binary) {
        if (!is_executable($binary)) {
            throw new RuntimeException('필요한 실행 파일이 없습니다: ' . $binary);
        }
    }

    $docker_prefix = (
        is_readable('/var/run/docker.sock') &&
        is_writable('/var/run/docker.sock')
    )
        ? array('/usr/bin/docker')
        : array('/usr/bin/sudo', '-n', '/usr/bin/docker');

    $check_code = reference_image_exec(
        array_merge(
            array('/usr/bin/timeout', '--kill-after=2s', '5s'),
            $docker_prefix,
            array('info')
        ),
        '/dev/null',
        '/dev/null'
    );

    if ($check_code !== 0) {
        throw new RuntimeException(
            'Docker 접근에 실패했습니다. 서비스 권한 또는 sudo 인증을 확인하세요.'
        );
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    $db->beginTransaction();

    $stmt = $db->prepare(
        'SELECT
            r.reference_id, r.activity_id, r.reference_no,
            r.source_code, r.canvas_width, r.canvas_height, r.p5_version,
            r.grading_config, r.max_score,
            i.image_status, i.source_sha256
         FROM course_simulation_reference r
         INNER JOIN course_simulation_reference_image i
           ON i.reference_id = r.reference_id
         WHERE r.reference_id = ?
         FOR UPDATE'
    );
    $stmt->execute(array($reference_id));
    $reference = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$reference) {
        throw new RuntimeException('정답 또는 이미지 생성 요청을 찾을 수 없습니다.');
    }

    if ($reference['image_status'] !== 'pending') {
        throw new RuntimeException(
            'pending 상태만 실행할 수 있습니다. 현재 상태: ' .
            $reference['image_status']
        );
    }

    $claim_token = bin2hex(random_bytes(16));

    $stmt = $db->prepare(
        "UPDATE course_simulation_reference_image
         SET image_status = 'running',
             started_at = NOW(),
             render_metadata = JSON_OBJECT('claim_token', ?),
             error_message = NULL
         WHERE reference_id = ? AND image_status = 'pending'"
    );
    $stmt->execute(array($claim_token, $reference_id));

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('이미지 생성 요청을 실행 상태로 변경하지 못했습니다.');
    }
    $stmt->closeCursor();

    $db->commit();
    $claimed = true;

    $source_sha256 = hash('sha256', (string)$reference['source_code']);

    if ($source_sha256 !== $reference['source_sha256']) {
        throw new RuntimeException('생성 요청과 정답 코드의 해시가 다릅니다.');
    }

    if ((int)$reference['canvas_width'] !== 400 ||
        (int)$reference['canvas_height'] !== 400 ||
        $reference['p5_version'] !== '2.3.4') {
        throw new RuntimeException('현재 400×400, p5.js 2.3.4만 지원합니다.');
    }

    $grading_config = json_decode(
        $reference['grading_config'],
        true
    );

    if (!is_array($grading_config)) {
        throw new RuntimeException('정답 채점 설정이 올바르지 않습니다.');
    }

    $image_scoring_mode =
        $grading_config['scoring_mode'] ?? 'metrics_only';

    if (
        !is_string($image_scoring_mode) ||
        !in_array(
            $image_scoring_mode,
            array('metrics_only', 'whole_image', 'regions'),
            true
        )
    ) {
        throw new RuntimeException('지원하지 않는 점수 산정 방식입니다.');
    }

    if ($image_scoring_mode === 'regions') {
        require_once(
            $root_dir . '/scripts/course_simulation_region_result.php'
        );

        if (
            ($grading_config['metric'] ?? '') !==
                'symmetric_foreground_color_coverage' ||
            ($grading_config['neighborhood'] ?? '') !== 'euclidean_disk' ||
            ($grading_config['scoring_method'] ?? '') !==
                'region_coverage_linear_v1' ||
            !is_int($grading_config['position_tolerance_px'] ?? null) ||
            $grading_config['position_tolerance_px'] < 0 ||
            $grading_config['position_tolerance_px'] > 10 ||
            ($grading_config['color_tolerance'] ?? null) !== 0
        ) {
            throw new RuntimeException('영역별 채점 설정이 올바르지 않습니다.');
        }

        $grading_config['regions'] = simulation_validate_region_config(
            $grading_config['regions'] ?? null,
            (string)$reference['max_score'],
            400,
            400
        );
    }

    $payload = array(
        'activity_id' => (int)$reference['activity_id'],
        'reference_id' => (int)$reference['reference_id'],
        'reference_no' => (int)$reference['reference_no'],
        'reference_code' => (string)$reference['source_code'],
        'grading_config' => $grading_config,
        'reference_max_score' => (string)$reference['max_score'],
        'canvas_width' => 400,
        'canvas_height' => 400,
        'p5_version' => '2.3.4',
    );

    $input_json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($input_json === false) {
        throw new RuntimeException('렌더링 입력 JSON 생성에 실패했습니다.');
    }

    umask(0077);
    $candidate = sys_get_temp_dir() .
        '/simulation-reference-' . bin2hex(random_bytes(16));

    if (!mkdir($candidate, 0700)) {
        throw new RuntimeException('임시 디렉터리 생성에 실패했습니다.');
    }
    $temp_dir = $candidate;

    if (!mkdir($temp_dir . '/output', 0700)) {
        throw new RuntimeException('이미지 출력 디렉터리 생성에 실패했습니다.');
    }

    $input_file = $temp_dir . '/input.json';
    $result_file = $temp_dir . '/result.json';
    $error_file = $temp_dir . '/error.log';
    $png_file = $temp_dir . '/output/target.png';

    if (file_put_contents($input_file, $input_json) !== strlen($input_json)) {
        throw new RuntimeException('렌더링 입력 저장에 실패했습니다.');
    }

    $container_name = 'simulation-reference-' .
        $reference_id . '-' . bin2hex(random_bytes(8));
    $image_name = '1024kr/p5-render-poc:playwright-1.63.0';

    $command = array_merge(
        array(
            '/usr/bin/timeout', '--signal=TERM',
            '--kill-after=5s', '40s'
        ),
        $docker_prefix,
        array(
            'run', '--name', $container_name,
            '--network=none',
            '--user=' . fileowner($input_file) . ':' . filegroup($input_file),
            '--cap-drop=ALL',
            '--security-opt=no-new-privileges',
            '--cpus=1',
            '--memory=512m',
            '--memory-swap=512m',
            '--pids-limit=128',
            '--tmpfs', '/tmp:rw,nosuid,nodev,size=256m',
            '-v', $exporter . ':/work/course_simulation_render_reference.py:ro',
            '-v', $renderer . ':/work/course_simulation_render_grade.py:ro',
            '-v', $metrics_script . ':/work/course_simulation_image_metrics.py:ro',
            '-v', $region_script . ':/work/course_simulation_region_metrics.py:ro',
            '-v', $p5_file . ':/work/p5.min.js:ro',
            '-v', $input_file . ':/work/input.json:ro',
            '-v', $temp_dir . '/output:/output:rw',
            '--entrypoint', 'python',
            $image_name,
            '/work/course_simulation_render_reference.py',
            '--input-json', '/work/input.json',
            '--p5', '/work/p5.min.js',
            '--output-png', '/output/target.png',
        )
    );

    $render_code = reference_image_exec($command, $result_file, $error_file);

    if ($render_code !== 0) {
        throw new RuntimeException(
            '목표 그림 렌더링 실패. 종료 코드: ' . $render_code
        );
    }

    $metadata_text = file_get_contents($result_file, false, null, 0, 65537);

    if ($metadata_text === false || strlen($metadata_text) > 65536) {
        throw new RuntimeException('렌더링 결과 크기가 올바르지 않습니다.');
    }

    $metadata = json_decode($metadata_text, true);

    if (!is_array($metadata) ||
        ($metadata['status'] ?? '') !== 'ok' ||
        ($metadata['comparison_mode'] ?? '') !== 'static-image-poc-v1') {
        throw new RuntimeException('렌더링 결과 JSON이 올바르지 않습니다.');
    }

    foreach (array(
        'activity_id', 'reference_id', 'reference_no',
        'canvas_width', 'canvas_height', 'p5_version'
    ) as $key) {
        if (($metadata[$key] ?? null) !== $payload[$key]) {
            throw new RuntimeException('렌더링 결과 식별 정보가 다릅니다: ' . $key);
        }
    }

    if (($metadata['reference_source_sha256'] ?? '') !== $source_sha256 ||
        ($metadata['p5_file_sha256'] ?? '') !== hash_file('sha256', $p5_file)) {
        throw new RuntimeException('정답 또는 p5.js 해시가 다릅니다.');
    }

    if (!is_file($png_file) || is_link($png_file)) {
        throw new RuntimeException('출력 PNG 파일이 올바르지 않습니다.');
    }

    $png = file_get_contents($png_file, false, null, 0, 1048577);
    if ($png === false || strlen($png) === 0 || strlen($png) > 1048576) {
        throw new RuntimeException('출력 PNG 크기가 올바르지 않습니다.');
    }

    $png_sha256 = hash('sha256', $png);
    $info = @getimagesizefromstring($png);

    if ($info === false ||
        $info[2] !== IMAGETYPE_PNG ||
        $info[0] !== 400 || $info[1] !== 400 ||
        ($metadata['png_sha256'] ?? '') !== $png_sha256 ||
        ($metadata['png_bytes'] ?? null) !== strlen($png)) {
        throw new RuntimeException('출력 PNG 형식·크기·해시 검증에 실패했습니다.');
    }

    $elapsed = $metadata['elapsed_seconds'] ?? null;
    if ((!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) || $elapsed < 0) {
        throw new RuntimeException('렌더링 시간이 올바르지 않습니다.');
    }

    $safe_metadata = array();
    foreach (array(
        'status', 'comparison_mode', 'activity_id', 'reference_id',
        'reference_no', 'canvas_width', 'canvas_height', 'p5_version',
        'reference_source_sha256', 'p5_file_sha256',
        'png_sha256', 'png_bytes', 'elapsed_seconds'
    ) as $key) {
        $safe_metadata[$key] = $metadata[$key];
    }
    $safe_metadata['docker_image'] = $image_name;
    $safe_metadata['grading_config_sha256'] = hash(
        'sha256',
        $reference['grading_config']
    );

    if ($image_scoring_mode === 'regions') {
        $validation = $metadata['region_validation'] ?? null;

        if (!is_array($validation)) {
            throw new RuntimeException(
                '정답 그림의 영역 검사 결과가 없습니다.'
            );
        }

        $validated_regions = simulation_validate_region_result(
            $validation['comparison'] ?? null,
            $grading_config['regions'],
            (string)$reference['max_score'],
            $grading_config['position_tolerance_px'],
            $validation['tolerance'] ?? null
        );

        if (
            (float)$validated_regions['final_coverage'] !== 100.0 ||
            $validated_regions['outside_student_foreground'] !== 0
        ) {
            throw new RuntimeException(
                '정답 그림이 영역 설정을 충족하지 않습니다.'
            );
        }

        $safe_metadata['region_validation'] = array(
            'status' => 'valid',
            'comparison' => $validated_regions,
        );

        $safe_metadata['region_validation_scripts'] = array(
            'exporter_sha256' => hash_file('sha256', $exporter),
            'metrics_sha256' => hash_file('sha256', $metrics_script),
            'regions_sha256' => hash_file('sha256', $region_script),
            'config_validator_sha256' => hash_file(
                'sha256',
                $root_dir . '/scripts/course_simulation_region_config.php'
            ),
            'result_validator_sha256' => hash_file(
                'sha256',
                $root_dir . '/scripts/course_simulation_region_result.php'
            ),
        );
    }

    $metadata_json = json_encode(
        $safe_metadata,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($metadata_json === false) {
        throw new RuntimeException('저장용 이미지 정보 생성에 실패했습니다.');
    }

    // 실행 중인 요청의 소유 토큰과 현재 정답 해시를 재확인한다.
    $stmt = $db->prepare(
        "UPDATE course_simulation_reference_image i
         INNER JOIN course_simulation_reference r
           ON r.reference_id = i.reference_id
         SET i.image_status = 'ready',
             i.png_data = ?, i.png_sha256 = ?,
             i.render_metadata = ?, i.error_message = NULL,
             i.generated_at = NOW()
         WHERE i.reference_id = ?
           AND i.image_status = 'running'
           AND JSON_UNQUOTE(
               JSON_EXTRACT(i.render_metadata, '$.claim_token')
           ) = ?
           AND i.source_sha256 = LOWER(SHA2(r.source_code, 256))"
    );
    $stmt->bindValue(1, $png, PDO::PARAM_LOB);
    $stmt->bindValue(2, $png_sha256);
    $stmt->bindValue(3, $metadata_json);
    $stmt->bindValue(4, $reference_id, PDO::PARAM_INT);
    $stmt->bindValue(5, $claim_token);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('목표 이미지 완료 상태를 저장하지 못했습니다.');
    }
    $stmt->closeCursor();

    echo "정답 ID: " . $reference_id . "\n";
    echo "정답 버전: " . $reference['reference_no'] . "\n";
    echo "이미지 상태: ready\n";
    echo "PNG 크기: " . strlen($png) . "바이트\n";
    echo "결과: 목표 이미지 생성·저장 완료\n";

} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    if ($claimed) {
        try {
            $stmt = $db->prepare(
                "UPDATE course_simulation_reference_image
                 SET image_status = 'error',
                     error_message = '목표 이미지 생성 처리에 실패했습니다.'
                 WHERE reference_id = ?
                   AND image_status = 'running'
                   AND JSON_UNQUOTE(
                       JSON_EXTRACT(render_metadata, '$.claim_token')
                   ) = ?"
            );
            $stmt->execute(array($reference_id, $claim_token));

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('오류 상태 저장 대상이 없습니다.');
            }
            $stmt->closeCursor();
        } catch (Throwable $save_error) {
            fwrite(STDERR, "오류 상태 저장 실패: " . $save_error->getMessage() . "\n");
        }
    }

    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    $exit_code = 1;

} finally {
    if ($container_name !== null) {
        $cleanup_code = reference_image_exec(
            array_merge(
                array('/usr/bin/timeout', '--kill-after=2s', '10s'),
                $docker_prefix,
                array('rm', '-f', $container_name)
            ),
            '/dev/null',
            '/dev/null'
        );

        if ($cleanup_code !== 0) {
            fwrite(STDERR, "컨테이너 정리 상태 확인 필요: " . $container_name . "\n");
        }
    }

    if ($temp_dir !== null) {
        foreach (array(
            '/input.json', '/result.json', '/error.log', '/output/target.png'
        ) as $suffix) {
            $file = $temp_dir . $suffix;
            if (is_file($file) || is_link($file)) {
                if (!unlink($file)) {
                    fwrite(STDERR, "임시 파일 정리 실패: " . $file . "\n");
                }
            }
        }

        if (is_dir($temp_dir . '/output') &&
            !@rmdir($temp_dir . '/output')) {
            fwrite(STDERR, "임시 출력 디렉터리 정리 확인 필요\n");
        }

        if (!@rmdir($temp_dir)) {
            fwrite(STDERR, "임시 디렉터리 정리 확인 필요: " . $temp_dir . "\n");
        }
    }
}

exit($exit_code);
