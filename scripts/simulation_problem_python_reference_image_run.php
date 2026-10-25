#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

require_once(__DIR__ . '/simulation_problem_image_request.php');
require_once(__DIR__ . '/course_simulation_region_result.php');


// 짧은 Docker 제어 명령에만 외부 시간 제한을 적용한다.
// 그림 실행 시간은 컨테이너 상태를 직접 확인해 제한한다.
function bank_image_docker(array $args, $output_name = 'docker.out')
{
    global $docker_prefix, $temp_dir;

    $output = $temp_dir . '/' . $output_name;
    $errors = $temp_dir . '/docker-error.log';

    $command = array_merge(
        array('/usr/bin/timeout', '--kill-after=2s', '10s'),
        $docker_prefix,
        $args
    );

    $shell = implode(' ', array_map('escapeshellarg', $command)) .
        ' > ' . escapeshellarg($output) .
        ' 2> ' . escapeshellarg($errors);

    $unused = array();
    $code = 0;
    exec($shell, $unused, $code);

    if ($code !== 0) {
        throw new RuntimeException('Docker 제어 명령 실패: ' . $args[0]);
    }

    $data = file_get_contents($output, false, null, 0, 65537);

    if ($data === false || strlen($data) > 65536) {
        throw new RuntimeException('Docker 응답 크기가 올바르지 않습니다.');
    }

    return $data;
}


function bank_image_load(PDO $db, $version_id)
{
    $statement = $db->prepare(
        'SELECT
            CAST(v.version_id AS CHAR) AS version_id,
            CAST(v.problem_id AS CHAR) AS problem_id,
            v.version_no, v.simulation_mode,
            v.canvas_width, v.canvas_height, v.p5_version,
            v.source_language, v.execution_engine, v.execution_version,
            p.published,
            r.source_code AS reference_source,
            r.render_config, r.grading_config, r.max_score,
            i.source_sha256, i.settings_sha256, i.request_metadata,
            i.image_status, i.claim_token
         FROM simulation_problem_version v
         INNER JOIN simulation_problem p ON p.problem_id = v.problem_id
         INNER JOIN simulation_problem_reference r
           ON r.version_id = v.version_id
         INNER JOIN simulation_problem_reference_image i
           ON i.version_id = v.version_id
         WHERE v.version_id = ?
         FOR UPDATE'
    );
    if (!$statement || !$statement->execute(array($version_id))) {
        throw new RuntimeException('문제은행 이미지 요청 조회 실패');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$row) {
        throw new RuntimeException('문제은행 정답 또는 생성 요청이 없습니다.');
    }

    return $row;
}


function bank_image_check_snapshot(array $row, $expected = null)
{
    $current = simulation_problem_image_request_metadata($row);
    $stored = json_decode(
        $row['request_metadata'],
        false,
        64,
        JSON_THROW_ON_ERROR
    );

    $canonical = simulation_problem_image_canonical_json((object)$current);

    if (
        !$stored instanceof stdClass ||
        $canonical !== simulation_problem_image_canonical_json($stored) ||
        $row['source_sha256'] !== $current['source_sha256'] ||
        $row['settings_sha256'] !== $current['settings_sha256']
    ) {
        throw new RuntimeException('등록 이후 정답 코드 또는 실행 설정이 달라졌습니다.');
    }

    if (
        $expected !== null &&
        $canonical !== simulation_problem_image_canonical_json((object)$expected)
    ) {
        throw new RuntimeException('이미지 생성 중 정답 또는 생성 요청이 변경됐습니다.');
    }

    return $current;
}


$root_dir = dirname(__DIR__);
$db = null;
$job_lock_connection = null;
$temp_dir = null;
$docker_prefix = null;
$container_name = null;
$claim_token = null;
$claimed = false;
$exit_code = 0;
$version_id = null;

try {
    $options = getopt('', array('version-id:'));
    $version_id = simulation_problem_image_positive_id(
        $options['version-id'] ?? null
    );

    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '1024.kr';
    require_once($root_dir . '/include/db_info.inc.php');

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $db = $dbh;
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once(__DIR__ . '/course_simulation_job_lock.php');
    $job_lock_connection = simulation_job_acquire_lock(
        'bank_image',
        $version_id
    );

    if ($job_lock_connection === null) {
        throw new RuntimeException('다른 실행기가 이 문제 버전을 처리 중입니다.');
    }

    $db->beginTransaction();
    $reference = bank_image_load($db, $version_id);

    if ($reference['image_status'] !== 'pending') {
        throw new RuntimeException('생성 대기 상태의 요청만 처리할 수 있습니다.');
    }

    // Python 실험 경로는 비공개 문제의 지정된 엔진만 허용한다.
    $execution = simulation_validate_execution_metadata($reference, true);
    if (
        $execution['source_language'] !== 'python' ||
        $execution['execution_engine'] !== 'python-stdlib-rgba-poc' ||
        $execution['execution_version'] !== '1' ||
        !in_array($reference['published'] ?? null, array(0, '0'), true)
    ) {
        throw new RuntimeException('비공개 Python 그림 문제만 처리할 수 있습니다.');
    }

    $claim_token = bin2hex(random_bytes(16));
    $statement = $db->prepare(
        "UPDATE simulation_problem_reference_image
         SET image_status = 'running',
             claim_token = ?, started_at = NOW(), error_message = NULL
         WHERE version_id = ? AND image_status = 'pending'"
    );
    $statement->execute(array($claim_token, $version_id));

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('이미지 생성 요청 확보 실패');
    }
    $statement->closeCursor();

    $db->commit();
    $claimed = true;

    $snapshot = bank_image_check_snapshot($reference);
    $grading = json_decode(
        $reference['grading_config'], true, 64, JSON_THROW_ON_ERROR
    );
    $render = json_decode(
        $reference['render_config'], true, 64, JSON_THROW_ON_ERROR
    );

    if (
        !is_array($render) ||
        ($render['renderer'] ?? null) !== 'stdlib-rgba' ||
        ($render['pixel_density'] ?? null) !== 1 ||
        ($render['background_rgb'] ?? null) !== array(255, 255, 255)
    ) {
        throw new RuntimeException('지원하지 않는 정답 렌더링 설정입니다.');
    }

    $mode = $grading['scoring_mode'] ?? null;

    if (
        !is_array($grading) ||
        !in_array($mode, array('metrics_only', 'whole_image', 'regions'), true) ||
        ($grading['metric'] ?? null) !== 'symmetric_foreground_color_coverage' ||
        ($grading['neighborhood'] ?? null) !== 'euclidean_disk' ||
        !is_int($grading['position_tolerance_px'] ?? null) ||
        $grading['position_tolerance_px'] < 0 ||
        $grading['position_tolerance_px'] > 10 ||
        ($grading['color_tolerance'] ?? null) !== 0
    ) {
        throw new RuntimeException('정답 채점 설정이 올바르지 않습니다.');
    }

    if ($mode === 'regions') {
        if (($grading['scoring_method'] ?? null) !== 'region_coverage_linear_v1') {
            throw new RuntimeException('영역별 점수 계산 방식이 올바르지 않습니다.');
        }
        $grading['regions'] = simulation_validate_region_config(
            $grading['regions'] ?? null,
            $reference['max_score'],
            400,
            400
        );
    } elseif (
        $mode === 'whole_image' &&
        ($grading['scoring_method'] ?? null) !== 'coverage_linear_v1'
    ) {
        throw new RuntimeException('전체 그림 점수 계산 방식이 올바르지 않습니다.');
    }

    $python_adapter = __DIR__ . '/simulation_problem_python_reference.py';
    $python_manager = __DIR__ . '/course_simulation_python_container_poc.py';
    $python_runner = __DIR__ . '/course_simulation_python_render_poc.py';
    $python_runtime = __DIR__ . '/course_simulation_python_runtime_poc.py';
    $python_compare = __DIR__ . '/course_simulation_python_compare_poc.py';
    $metrics = __DIR__ . '/course_simulation_image_metrics.py';
    $regions = __DIR__ . '/course_simulation_region_metrics.py';

    foreach (array(
        $python_adapter, $python_manager, $python_runner,
        $python_runtime, $python_compare, $metrics, $regions
    ) as $file) {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Python 실행·검사 파일을 읽지 못했습니다.');
        }
    }

    if (
        !is_executable('/usr/bin/python3') ||
        !function_exists('proc_open')
    ) {
        throw new RuntimeException('Python 처리기 실행 기능을 확인해 주세요.');
    }

    $runtime_hash = hash_file('sha256', $python_runtime);
    if ($runtime_hash !== ($snapshot['settings']->runtime_sha256 ?? null)) {
        throw new RuntimeException('요청에 등록된 Python 그림 함수와 현재 파일이 다릅니다.');
    }

    umask(0077);
    $temp_dir = sys_get_temp_dir() . '/bank-python-image-' .
        bin2hex(random_bytes(16));

    if (!mkdir($temp_dir, 0700) || !mkdir($temp_dir . '/output', 0700)) {
        throw new RuntimeException('Python 임시 출력 디렉터리 생성 실패');
    }

    $request_path = $temp_dir . '/request.json';
    $grading_path = $temp_dir . '/grading.json';
    $png_path = $temp_dir . '/output/target.png';
    $report_path = $temp_dir . '/report.json';
    $errors_path = $temp_dir . '/render-errors.log';

    $request_json = json_encode(array(
        'schema_version' => 1,
        'engine' => 'python-stdlib-rgba-poc-v1',
        'runtime_sha256' => $runtime_hash,
        'source_code' => $reference['reference_source'],
        'frame' => 0,
    ), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $grading_json = json_encode(array(
        'max_score' => $reference['max_score'],
        'grading_config' => $grading,
    ), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    if (
        file_put_contents($request_path, $request_json) !== strlen($request_json) ||
        file_put_contents($grading_path, $grading_json) !== strlen($grading_json)
    ) {
        throw new RuntimeException('Python 실행 요청 저장 실패');
    }

    if (fileowner($request_path) === 0) {
        throw new RuntimeException('일반 사용자 또는 서비스 계정으로 실행해 주세요.');
    }

    $docker_prefix = (
        is_readable('/var/run/docker.sock') &&
        is_writable('/var/run/docker.sock')
    ) ? array('/usr/bin/docker') : array('/usr/bin/sudo', '-n', '/usr/bin/docker');

    $image_id = trim(bank_image_docker(array(
        'image', 'inspect', '--format', '{{.Id}}',
        '1024kr/p5-render-poc:playwright-1.63.0'
    )));

    if (preg_match('/^sha256:[0-9a-f]{64}$/D', $image_id) !== 1) {
        throw new RuntimeException('로컬 Python 실행 이미지 식별자 확인 실패');
    }

    // 입력 코드는 하위 실행 관리 도구가 Docker 내부에서만 실행한다.
    // PHP에서는 정답 코드를 실행하거나 평가하지 않는다.
    $process = proc_open(
        array(
            '/usr/bin/python3', '-B', $python_adapter,
            '--input-json', $request_path,
            '--grading-json', $grading_path,
            '--image-id', $image_id,
            '--output-png', $png_path,
        ),
        array(
            0 => array('file', '/dev/null', 'r'),
            1 => array('file', $report_path, 'w'),
            2 => array('file', $errors_path, 'w'),
        ),
        $pipes,
        $root_dir
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Python 실행 관리 프로세스 시작 실패');
    }

    $python_exit_code = proc_close($process);
    if ($python_exit_code !== 0) {
        $detail = is_file($errors_path)
            ? file_get_contents($errors_path, false, null, 0, 8192)
            : false;

        if (is_string($detail) && trim($detail) !== '') {
            fwrite(STDERR, "Python 실행 상세 오류:\n" . $detail . "\n");
        }

        throw new RuntimeException('Python 목표 그림 실행 또는 영역 검사 실패');
    }

    if (
        !is_file($report_path) || is_link($report_path) ||
        filesize($report_path) > 65536
    ) {
        throw new RuntimeException('Python 검사 결과 파일 크기·형식 오류');
    }

    $metadata = json_decode(
        file_get_contents($report_path),
        true,
        64,
        JSON_THROW_ON_ERROR
    );

    foreach (array(
        'schema_version' => 1,
        'status' => 'ok',
        'engine' => 'python-stdlib-rgba-poc-v1',
        'runtime_sha256' => $runtime_hash,
        'source_sha256' => $snapshot['source_sha256'],
        'frame' => 0,
        'width' => 400,
        'height' => 400,
    ) as $key => $expected_value) {
        if (!is_array($metadata) || ($metadata[$key] ?? null) !== $expected_value) {
            throw new RuntimeException('Python 검사 결과와 요청 정보 불일치: ' . $key);
        }
    }

    if (!is_file($png_path) || is_link($png_path)) {
        throw new RuntimeException('Python 출력 PNG 파일 형식 오류');
    }

    $png = file_get_contents($png_path, false, null, 0, 1048577);
    if ($png === false || strlen($png) < 1 || strlen($png) > 1048576) {
        throw new RuntimeException('Python 출력 PNG 크기 오류');
    }

    $png_hash = hash('sha256', $png);
    $info = @getimagesizefromstring($png);
    if (
        substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n" ||
        !$info || $info[2] !== IMAGETYPE_PNG ||
        $info[0] !== 400 || $info[1] !== 400 ||
        ($metadata['png_sha256'] ?? null) !== $png_hash ||
        ($metadata['png_bytes'] ?? null) !== strlen($png)
    ) {
        throw new RuntimeException('Python PNG 형식·크기·해시 검사 실패');
    }

    $elapsed = $metadata['elapsed_seconds'] ?? null;
    if (
        (!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) || $elapsed < 0
    ) {
        throw new RuntimeException('Python 실행 시간 정보 오류');
    }

    if (hash_file('sha256', $python_runtime) !== $runtime_hash) {
        throw new RuntimeException('검사 중 Python 그림 함수가 변경됐습니다.');
    }

    $safe = array(
        'schema_version' => 1,
        'status' => 'ok',
        'request' => $snapshot,
        'png_sha256' => $png_hash,
        'png_bytes' => strlen($png),
        'elapsed_seconds' => $elapsed,
        'docker_image_id' => $image_id,
        'runtime_sha256' => $runtime_hash,
        'adapter_sha256' => hash_file('sha256', $python_adapter),
        'manager_sha256' => hash_file('sha256', $python_manager),
        'runner_sha256' => hash_file('sha256', $python_runner),
        'compare_sha256' => hash_file('sha256', $python_compare),
        'metrics_sha256' => hash_file('sha256', $metrics),
        'regions_sha256' => hash_file('sha256', $regions),
    );

    if ($mode === 'regions') {
        $validation = $metadata['region_validation'] ?? null;
        if (!is_array($validation)) {
            throw new RuntimeException('Python 정답 영역 검사 결과 누락');
        }

        $validated = simulation_validate_region_result(
            $validation['comparison'] ?? null,
            $grading['regions'],
            $reference['max_score'],
            $grading['position_tolerance_px'],
            $validation['tolerance'] ?? null
        );

        if (
            (float)$validated['final_coverage'] !== 100.0 ||
            $validated['outside_student_foreground'] !== 0
        ) {
            throw new RuntimeException('Python 정답 그림이 영역 설정을 충족하지 않습니다.');
        }

        $safe['region_validation'] = array(
            'status' => 'valid',
            'comparison' => $validated,
        );
    }

    // Python 연결 도구가 실행·비교 컨테이너를 정리한 뒤 반환한다.

    $db->beginTransaction();
    $fresh = bank_image_load($db, $version_id);
    bank_image_check_snapshot($fresh, $snapshot);
    if (!in_array($fresh['published'] ?? null, array(0, '0'), true)) {
        throw new RuntimeException('처리 중 문제 공개 상태가 변경됐습니다.');
    }

    if (
        $fresh['image_status'] !== 'running' ||
        $fresh['claim_token'] !== $claim_token
    ) {
        throw new RuntimeException('이미지 생성 요청 소유권이 달라졌습니다.');
    }

    $statement = $db->prepare(
        "UPDATE simulation_problem_reference_image
         SET image_status = 'ready',
             png_data = ?, png_sha256 = ?, render_metadata = ?,
             error_message = NULL, generated_at = NOW(),
             claim_token = NULL
         WHERE version_id = ?
           AND image_status = 'running' AND claim_token = ?"
    );
    $statement->bindValue(1, $png, PDO::PARAM_LOB);
    $statement->bindValue(2, $png_hash);
    $statement->bindValue(
        3,
        json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
    $statement->bindValue(4, $version_id);
    $statement->bindValue(5, $claim_token);
    $statement->execute();

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('이미지 완료 상태 저장 실패');
    }
    $statement->closeCursor();
    $db->commit();

    echo "문제 번호: " . $snapshot['problem_id'] . "\n";
    echo "버전 ID: " . $version_id . "\n";
    echo "문제 버전: " . $snapshot['version_no'] . "\n";
    echo "이미지 상태: ready\n";
    echo "PNG 크기: " . strlen($png) . "바이트\n";
    echo "결과: 문제은행 목표 이미지 생성·검사·저장 완료\n";

} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    if ($claimed) {
        try {
            $statement = $db->prepare(
                "UPDATE simulation_problem_reference_image
                 SET image_status = 'error',
                     error_message = '목표 이미지 생성 또는 검증에 실패했습니다.',
                     claim_token = NULL
                 WHERE version_id = ?
                   AND image_status = 'running' AND claim_token = ?"
            );
            $statement->execute(array($version_id, $claim_token));
            $statement->closeCursor();
        } catch (Throwable $save_error) {
            fwrite(STDERR, "이미지 오류 상태 저장 실패\n");
        }
    }

    fwrite(STDERR, "실패: " . $error->getMessage() . "\n");
    $exit_code = 1;

} finally {
    if ($container_name !== null && $temp_dir !== null) {
        try {
            bank_image_docker(array('rm', '-f', $container_name));
        } catch (Throwable $cleanup_error) {
            fwrite(STDERR, "검사 컨테이너 정리 확인 필요: " . $container_name . "\n");
            $exit_code = 1;
        }
    }

    if ($temp_dir !== null && is_dir($temp_dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $temp_dir,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink() || !$entry->isDir()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($temp_dir);
    }

    $job_lock_connection = null;
}

exit($exit_code);
