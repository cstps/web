#!/usr/bin/env php
<?php

require_once(__DIR__ . '/simulation_execution_config.php');

// 등록된 채점 요청 하나를 처리하는 CLI.
// 정답 설정에 따라 비교 지표와 점수를 저장한다.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

function simulation_grade_statement(PDO $db, $sql, array $args = array())
{
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}

function simulation_grade_exec(array $args, $stdout, $stderr)
{
    $parts = array_map('escapeshellarg', $args);
    $command = implode(' ', $parts) .
        ' > ' . escapeshellarg($stdout) .
        ' 2> ' . escapeshellarg($stderr);

    $unused = array();
    $code = 0;
    exec($command, $unused, $code);
    return $code;
}

$options = getopt('', array('grade-id:'));
$raw_id = isset($options['grade-id'])
    ? (string)$options['grade-id']
    : '';

$grade_id = filter_var(
    $raw_id,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if (
    !preg_match('/^[1-9][0-9]*$/D', $raw_id) ||
    $grade_id === false
) {
    fwrite(
        STDERR,
        "사용법: php scripts/course_simulation_grade_run.php --grade-id=1\n"
    );
    exit(1);
}

$root_dir = dirname(__DIR__);
$db = null;
$claimed = false;
$feedback = array();
$temp_files = array();
$container_name = null;
$exit_code = 0;
$is_python_grade = false;

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
        'grade',
        $grade_id
    );

    if ($job_lock_connection === null) {
        throw new RuntimeException('다른 실행기가 이 작업을 처리 중입니다.');
    }


    foreach (array('/usr/bin/timeout', '/usr/bin/sudo', '/usr/bin/docker') as $binary) {
        if (!is_executable($binary)) {
            throw new RuntimeException('필요한 실행 파일이 없습니다: ' . $binary);
        }
    }

    $python_script = $root_dir . '/scripts/course_simulation_render_grade.py';
    $metrics_script = $root_dir . '/scripts/course_simulation_image_metrics.py';
    $region_script = $root_dir . '/scripts/course_simulation_region_metrics.py';
    $p5_file = $root_dir . '/template/syzoj/js/vendor/p5.min.js';

    foreach (array($python_script, $metrics_script, $region_script, $p5_file) as $file) {
        if (!is_readable($file)) {
            throw new RuntimeException('필요한 파일을 읽을 수 없습니다: ' . $file);
        }
    }

    // 서비스에서 소켓 접근 권한이 있으면 Docker를 직접 실행한다.
    // 일반 터미널에서는 기존 sudo 방식을 사용한다.
    $docker_socket = '/var/run/docker.sock';

    $docker_prefix = (
        is_readable($docker_socket) &&
        is_writable($docker_socket)
    )
        ? array('/usr/bin/docker')
        : array('/usr/bin/sudo', '-n', '/usr/bin/docker');

    $docker_check = simulation_grade_exec(
        array_merge(
            array('/usr/bin/timeout', '--kill-after=2s', '5s'),
            $docker_prefix,
            array('info')
        ),
        '/dev/null',
        '/dev/null'
    );

    if ($docker_check !== 0) {
        throw new RuntimeException(
            'Docker 접근에 실패했습니다. 서비스 실행 권한 또는 sudo 인증을 확인하세요.'
        );
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    $db->beginTransaction();

    $stmt = simulation_grade_statement(
        $db,
        'SELECT grade_id, submission_id, grading_no,
                grading_status, feedback_json
         FROM course_simulation_grade
         WHERE grade_id = ?
         FOR UPDATE',
        array($grade_id)
    );
    $grade = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$grade) {
        throw new RuntimeException('채점 요청을 찾을 수 없습니다.');
    }

    if ($grade['grading_status'] !== 'pending') {
        throw new RuntimeException(
            'pending 상태만 실행할 수 있습니다. 현재 상태: ' .
            $grade['grading_status']
        );
    }

    $stmt = simulation_grade_statement(
        $db,
        "UPDATE course_simulation_grade
         SET grading_status = 'running', started_at = NOW()
         WHERE grade_id = ? AND grading_status = 'pending'",
        array($grade_id)
    );

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('채점 요청을 실행 상태로 변경하지 못했습니다.');
    }
    $stmt->closeCursor();

    $db->commit();
    $claimed = true;

    $decoded = json_decode($grade['feedback_json'], true);

    if (!is_array($decoded) ||
        !isset($decoded['schema_version']) ||
        (int)$decoded['schema_version'] !== 1 ||
        !isset($decoded['request']) ||
        !is_array($decoded['request'])) {
        throw new RuntimeException('채점 요청 메타데이터가 올바르지 않습니다.');
    }

    $feedback = $decoded;
    $request = $feedback['request'];

    $stmt = simulation_grade_statement(
        $db,
        'SELECT submission_id, submission_no, activity_id, source_code
         FROM course_simulation_submission
         WHERE submission_id = ?',
        array($grade['submission_id'])
    );
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $stmt = simulation_grade_statement(
        $db,
        'SELECT reference_id, activity_id, reference_no, source_code,
                canvas_width, canvas_height, p5_version,
                source_language, execution_engine, execution_version,
                render_config, grading_config, max_score
         FROM course_simulation_reference
         WHERE reference_id = ?',
        array(isset($request['reference_id']) ? $request['reference_id'] : 0)
    );
    $reference = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$submission || !$reference) {
        throw new RuntimeException('제출 또는 등록된 정답을 찾을 수 없습니다.');
    }

    // 현재 활동 설정 대신 요청에 고정된 정답 버전을 사용한다.
    $execution = simulation_validate_execution_metadata($reference, true);
    $is_python_grade =
        $execution['execution_engine'] === 'python-stdlib-rgba-poc';

    if ($is_python_grade) {
        require_once(__DIR__ . '/course_simulation_grade_request.php');
        require_once(__DIR__ . '/course_simulation_python_grade_execute.php');

        // 객체의 키 순서만 정규화하고 값의 자료형은 유지한다.
        $normalize = null;
        $normalize = static function ($value) use (&$normalize) {
            if (!is_array($value)) {
                return $value;
            }

            foreach ($value as $key => $item) {
                $value[$key] = $normalize($item);
            }

            ksort($value);
            return $value;
        };

        // 실행 전과 저장 직전에 원본과 채점 요청을 재검사한다.
        $snapshot = static function () use (
            $db, $grade, $request, $normalize
        ) {
            if (!$db->inTransaction()) {
                throw new RuntimeException(
                    '채점 원본 검사는 트랜잭션 안에서 수행해야 합니다.'
                );
            }

            $stmt = simulation_grade_statement(
                $db,
                'SELECT submission_id, grading_no, grading_status,
                        feedback_json, started_at
                 FROM course_simulation_grade
                 WHERE grade_id = ? FOR UPDATE',
                array($grade['grade_id'])
            );
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (
                !$current ||
                $current['grading_status'] !== 'running' ||
                (string)$current['submission_id'] !==
                    (string)$grade['submission_id'] ||
                (string)$current['grading_no'] !==
                    (string)$grade['grading_no'] ||
                $current['feedback_json'] !== $grade['feedback_json'] ||
                !is_string($current['started_at']) ||
                $current['started_at'] === ''
            ) {
                throw new RuntimeException(
                    '실행 중인 수업 채점 요청이 변경됐습니다.'
                );
            }

            $stmt = simulation_grade_statement(
                $db,
                'SELECT submission_id, submission_no, activity_id, source_code
                 FROM course_simulation_submission
                 WHERE submission_id = ? FOR UPDATE',
                array($grade['submission_id'])
            );
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $stmt = simulation_grade_statement(
                $db,
                'SELECT reference_id, activity_id, reference_no, source_code,
                        canvas_width, canvas_height, p5_version,
                        source_language, execution_engine, execution_version,
                        render_config, grading_config, max_score
                 FROM course_simulation_reference
                 WHERE reference_id = ? FOR UPDATE',
                array($request['reference_id'] ?? 0)
            );
            $answer = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$student || !$answer) {
                throw new RuntimeException(
                    '제출 또는 정답 버전이 없습니다.'
                );
            }

            $stmt = simulation_grade_statement(
                $db,
                'SELECT reference_id, image_status, source_sha256,
                        png_data, png_sha256, render_metadata, generated_at
                 FROM course_simulation_reference_image
                 WHERE reference_id = ? FOR UPDATE',
                array($answer['reference_id'])
            );
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $expected_request = course_simulation_grade_request_metadata(
                $student['activity_id'],
                $answer,
                $student['source_code'],
                $image
            );

            if ($normalize($expected_request) !== $normalize($request)) {
                throw new RuntimeException(
                    '현재 채점 원본과 저장된 수업 요청이 다릅니다.'
                );
            }

            return array(
                'source_code' => $student['source_code'],
                'submission_no' => (string)$student['submission_no'],
                'png_data' => $image['png_data'],
                'started_at' => $current['started_at'],
            );
        };

        $db->beginTransaction();
        $python_start = $snapshot();
        $db->commit();

        $pipes = array();
        $inspect_process = proc_open(
            array_merge(
                array('/usr/bin/timeout', '--kill-after=2s', '5s'),
                $docker_prefix,
                array(
                    'image', 'inspect', '--format', '{{.Id}}',
                    '1024kr/p5-render-poc:playwright-1.63.0'
                )
            ),
            array(
                0 => array('file', '/dev/null', 'r'),
                1 => array('pipe', 'w'),
                2 => array('file', '/dev/null', 'w'),
            ),
            $pipes
        );

        if (!is_resource($inspect_process)) {
            throw new RuntimeException(
                '로컬 채점 이미지 확인을 시작하지 못했습니다.'
            );
        }

        $pinned_image = trim(stream_get_contents($pipes[1], 256));
        fclose($pipes[1]);
        $inspect_code = proc_close($inspect_process);

        if (
            $inspect_code !== 0 ||
            preg_match('/^sha256:[0-9a-f]{64}$/D', $pinned_image) !== 1
        ) {
            throw new RuntimeException(
                '로컬 채점 이미지에 접근하지 못했습니다.'
            );
        }

        // 실행 중에는 DB 트랜잭션과 행 잠금을 유지하지 않는다.
        $python_result = course_simulation_execute_python_grade(
            $request,
            $python_start['source_code'],
            $python_start['png_data'],
            $pinned_image
        );

        $db->beginTransaction();
        $python_finish = $snapshot();

        if (
            $python_start['started_at'] !== $python_finish['started_at'] ||
            $python_start['submission_no'] !==
                $python_finish['submission_no'] ||
            $python_start['source_code'] !== $python_finish['source_code'] ||
            $python_start['png_data'] !== $python_finish['png_data']
        ) {
            throw new RuntimeException(
                '실행 중 채점 작업 또는 원본이 변경됐습니다.'
            );
        }

        $python_feedback = $python_result['feedback'];
        $python_feedback['request'] = $request;

        $python_feedback_json = json_encode(
            $python_feedback,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        $stmt = simulation_grade_statement(
            $db,
            "UPDATE course_simulation_grade
             SET grading_status = ?, score = ?, max_score = ?,
                 feedback_json = ?, graded_at = NOW()
             WHERE grade_id = ? AND grading_status = 'running'",
            array(
                $python_result['grading_status'],
                $python_result['score'],
                $python_result['max_score'],
                $python_feedback_json,
                $grade_id,
            )
        );

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Python 채점 결과를 저장하지 못했습니다.'
            );
        }

        $stmt->closeCursor();
        $db->commit();
        $claimed = false;

        echo "채점 ID: " . $grade_id . "\n";
        echo "제출 ID: " . $grade['submission_id'] . "\n";
        echo "정답 버전: " . $request['reference_no'] . "\n";
        echo "채점 상태: " . $python_result['grading_status'] . "\n";

        if ($python_result['grading_status'] === 'error') {
            echo $python_feedback['error']['message'] . "\n";
            $exit_code = 1;
        } elseif ($python_result['score'] !== null) {
            echo "점수: " . $python_result['score'] .
                " / " . $python_result['max_score'] . "\n";
            echo "결과: Python 비교 지표·점수 저장 완료\n";
        } else {
            echo "결과: Python 비교 지표 저장 완료 / 공식 점수 미산정\n";
        }

    } else {
    // 기존 p5.js 채점의 지표 검사와 점수 계산은 그대로 유지한다.
    simulation_validate_execution_metadata($reference);

    $expected = array(
        'activity_id' => (int)$submission['activity_id'],
        'reference_id' => (int)$reference['reference_id'],
        'reference_no' => (int)$reference['reference_no'],
        'canvas_width' => (int)$reference['canvas_width'],
        'canvas_height' => (int)$reference['canvas_height'],
        'p5_version' => (string)$reference['p5_version'],
        'reference_max_score' => (string)$reference['max_score'],
        'reference_source_sha256' => hash('sha256', $reference['source_code']),
        'student_source_sha256' => hash('sha256', $submission['source_code']),
        'render_config_sha256' => hash('sha256', $reference['render_config']),
        'grading_config_sha256' => hash('sha256', $reference['grading_config']),
    );

    if ((int)$reference['activity_id'] !== (int)$submission['activity_id']) {
        throw new RuntimeException('제출과 정답의 활동 ID가 다릅니다.');
    }

    foreach ($expected as $key => $value) {
        if (!array_key_exists($key, $request) ||
            (string)$request[$key] !== (string)$value) {
            throw new RuntimeException('등록 이후 채점 입력이 달라졌습니다: ' . $key);
        }
    }

    if ($expected['canvas_width'] !== 400 ||
        $expected['canvas_height'] !== 400 ||
        $expected['p5_version'] !== '2.3.4') {
        throw new RuntimeException('현재 비교기는 400×400, p5.js 2.3.4만 지원합니다.');
    }

    // 요청에 기록된 해시와 일치하는 정답 버전의 설정만 사용한다.
    $grading_config = json_decode($reference['grading_config'], true);

    if (
        !is_array($grading_config) ||
        ($grading_config['metric'] ?? '') !==
            'symmetric_foreground_color_coverage' ||
        ($grading_config['neighborhood'] ?? '') !== 'euclidean_disk' ||
        !isset($grading_config['position_tolerance_px']) ||
        !is_int($grading_config['position_tolerance_px']) ||
        $grading_config['position_tolerance_px'] < 0 ||
        $grading_config['position_tolerance_px'] > 10 ||
        ($grading_config['color_tolerance'] ?? null) !== 0
    ) {
        throw new RuntimeException('지원하지 않거나 올바르지 않은 채점 설정입니다.');
    }

    $scoring_mode = $grading_config['scoring_mode'] ?? 'metrics_only';

    if (
        !is_string($scoring_mode) ||
        !in_array($scoring_mode, array('metrics_only', 'whole_image', 'regions'), true)
    ) {
        throw new RuntimeException('지원하지 않는 점수 산정 방식입니다.');
    }

    if ($scoring_mode === 'whole_image') {
        if (
            ($grading_config['scoring_method'] ?? '') !==
            'coverage_linear_v1'
        ) {
            throw new RuntimeException('지원하지 않는 점수 계산 방식입니다.');
        }

        require_once(
            $root_dir . '/include/course_simulation_scoring.inc.php'
        );
    }

    if ($scoring_mode === 'regions') {
        if (
            ($grading_config['scoring_method'] ?? '') !==
            'region_coverage_linear_v1'
        ) {
            throw new RuntimeException(
                '지원하지 않는 영역별 점수 계산 방식입니다.'
            );
        }

        require_once(
            $root_dir . '/scripts/course_simulation_region_result.php'
        );
        require_once(
            $root_dir . '/include/course_simulation_scoring.inc.php'
        );

        // 해시 검사를 마친 정답 버전의 설정을 검증하고 정규화한다.
        $grading_config['regions'] = simulation_validate_region_config(
            $grading_config['regions'] ?? null,
            (string)$reference['max_score'],
            400,
            400
        );
    }

    $payload = array(
        'activity_id' => (int)$submission['activity_id'],
        'reference_no' => (int)$reference['reference_no'],
        'reference_code' => (string)$reference['source_code'],
        'submission_id' => (int)$submission['submission_id'],
        'submission_no' => (int)$submission['submission_no'],
        'student_code' => (string)$submission['source_code'],
        'canvas_width' => 400,
        'canvas_height' => 400,
        'grading_config' => $grading_config,
        'reference_max_score' => (string)$reference['max_score'],
    );

    foreach (array('input', 'output', 'error') as $label) {
        $file = tempnam(sys_get_temp_dir(), 'simulation-' . $label . '-');
        if ($file === false) {
            throw new RuntimeException('임시 파일 생성에 실패했습니다.');
        }
        $temp_files[$label] = $file;
        if (!chmod($file, 0600)) {
            throw new RuntimeException('임시 파일 권한 설정에 실패했습니다.');
        }
    }

    $input_json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($input_json === false ||
        file_put_contents($temp_files['input'], $input_json) !== strlen($input_json)) {
        throw new RuntimeException('렌더링 입력 저장에 실패했습니다.');
    }

    $container_name = 'simulation-grade-' . $grade_id . '-' . bin2hex(random_bytes(8));
    $image = '1024kr/p5-render-poc:playwright-1.63.0';

    // 기존 렌더 조건에서 정답 버전의 위치 허용 오차를 추가 비교한다.
    $feedback['runtime'] = array(
        'comparison_mode' => 'static-image-tolerance-v1',
        'docker_image' => $image,
        'python_script_sha256' => hash_file('sha256', $python_script),
        'metrics_script_sha256' => hash_file('sha256', $metrics_script),
        'p5_file_sha256' => hash_file('sha256', $p5_file),
    );

    $command = array_merge(
        array(
            '/usr/bin/timeout',
            '--signal=TERM',
            '--kill-after=5s',
            '40s'
        ),
        $docker_prefix,
        array(
        'run', '--name', $container_name,
        '--network=none',
        '--user=' . fileowner($temp_files['input']) . ':' . filegroup($temp_files['input']),
        '--cap-drop=ALL',
        '--security-opt=no-new-privileges',
        '--cpus=1',
        '--memory=512m',
        '--memory-swap=512m',
        '--pids-limit=128',
        '--tmpfs', '/tmp:rw,nosuid,nodev,size=256m',
        '-v', $python_script . ':/work/course_simulation_render_grade.py:ro',
        '-v', $metrics_script . ':/work/course_simulation_image_metrics.py:ro',
        '-v', $region_script . ':/work/course_simulation_region_metrics.py:ro',
        '-v', $p5_file . ':/work/p5.min.js:ro',
        '-v', $temp_files['input'] . ':/work/input.json:ro',
        '--entrypoint', 'python',
        $image,
        '/work/course_simulation_render_grade.py',
        '--input-json', '/work/input.json',
        '--p5', '/work/p5.min.js',
        '--json',
        )
    );

    $render_code = simulation_grade_exec(
        $command,
        $temp_files['output'],
        $temp_files['error']
    );

    if ($render_code !== 0) {
        throw new RuntimeException(
            '렌더링 처리 실패. 종료 코드: ' . $render_code
        );
    }

    clearstatcache(true, $temp_files['output']);
    if (filesize($temp_files['output']) > 1048576) {
        throw new RuntimeException('렌더링 결과가 허용 크기를 초과했습니다.');
    }

    $result = json_decode(file_get_contents($temp_files['output']), true);

    if (!is_array($result) || !isset($result['status']) ||
        $result['status'] !== 'ok') {
        throw new RuntimeException('비교 결과 JSON이 올바르지 않습니다.');
    }

    foreach (array('activity_id', 'reference_no', 'submission_id', 'submission_no') as $key) {
        if (!isset($result[$key]) || $result[$key] !== $payload[$key]) {
            throw new RuntimeException('비교 결과 식별자가 다릅니다: ' . $key);
        }
    }

    $metrics = array();
    foreach (array(
        'reference_foreground', 'student_foreground', 'different_pixels',
        'full_match', 'foreground_match', 'foreground_overlap',
        'foreground_color_error'
    ) as $key) {
        $value = isset($result['metrics'][$key]) ? $result['metrics'][$key] : null;

        if ((!is_int($value) && !is_float($value)) ||
            !is_finite((float)$value) || $value < 0) {
            throw new RuntimeException('비교 지표가 올바르지 않습니다: ' . $key);
        }

        if (in_array($key, array('full_match', 'foreground_match', 'foreground_overlap'), true) &&
            $value > 100) {
            throw new RuntimeException('백분율 범위를 초과했습니다: ' . $key);
        }

        if (in_array($key, array('reference_foreground', 'student_foreground', 'different_pixels'), true) &&
            ($value > 160000 || floor((float)$value) !== (float)$value)) {
            throw new RuntimeException('픽셀 수 범위가 올바르지 않습니다: ' . $key);
        }

        $metrics[$key] = $value;
    }

    // 허용 오차 결과도 필요한 필드만 검증하여 저장한다.
    $tolerance = $result['metrics']['tolerance'] ?? null;

    if (!is_array($tolerance)) {
        throw new RuntimeException('허용 오차 비교 결과가 없습니다.');
    }

    $validated_tolerance = array();

    foreach (array(
        'metric' => 'symmetric_foreground_color_coverage',
        'aggregation' => 'harmonic_mean',
        'neighborhood' => 'euclidean_disk',
        'position_tolerance_px' => $grading_config['position_tolerance_px'],
        'color_tolerance' => 0,
    ) as $key => $expected_value) {
        if (($tolerance[$key] ?? null) !== $expected_value) {
            throw new RuntimeException('허용 오차 결과의 설정이 다릅니다: ' . $key);
        }
        $validated_tolerance[$key] = $expected_value;
    }

    foreach (array(
        'reference_foreground', 'student_foreground',
        'reference_matched', 'student_matched'
    ) as $key) {
        $value = $tolerance[$key] ?? null;

        if (!is_int($value) || $value < 0 || $value > 160000) {
            throw new RuntimeException('허용 오차 결과의 픽셀 수가 잘못됐습니다: ' . $key);
        }
        $validated_tolerance[$key] = $value;
    }

    $reference_count = $validated_tolerance['reference_foreground'];
    $student_count = $validated_tolerance['student_foreground'];

    if (
        $reference_count === 0 ||
        (float)$reference_count !== (float)$metrics['reference_foreground'] ||
        (float)$student_count !== (float)$metrics['student_foreground'] ||
        $validated_tolerance['reference_matched'] > $reference_count ||
        $validated_tolerance['student_matched'] > $student_count
    ) {
        throw new RuntimeException('허용 오차 결과의 전경 픽셀 수가 일치하지 않습니다.');
    }

    $reference_ratio =
        $validated_tolerance['reference_matched'] / $reference_count;

    $student_ratio = $student_count > 0
        ? $validated_tolerance['student_matched'] / $student_count
        : 0.0;

    $symmetric_ratio = ($reference_ratio + $student_ratio) > 0
        ? 2.0 * $reference_ratio * $student_ratio /
            ($reference_ratio + $student_ratio)
        : 0.0;

    foreach (array(
        'reference_coverage' => 100.0 * $reference_ratio,
        'student_coverage' => 100.0 * $student_ratio,
        'symmetric_coverage' => 100.0 * $symmetric_ratio,
    ) as $key => $expected_value) {
        $value = $tolerance[$key] ?? null;

        if (
            (!is_int($value) && !is_float($value)) ||
            !is_finite((float)$value) ||
            $value < 0 || $value > 100 ||
            abs((float)$value - $expected_value) > 0.000001
        ) {
            throw new RuntimeException('허용 오차 결과의 비율이 잘못됐습니다: ' . $key);
        }
        $validated_tolerance[$key] = $value;
    }

    $metrics['tolerance'] = $validated_tolerance;

    if ($scoring_mode === 'regions') {
        $metrics['region_comparison'] = simulation_validate_region_result(
            $result['metrics']['region_comparison'] ?? null,
            $grading_config['regions'],
            (string)$reference['max_score'],
            $grading_config['position_tolerance_px'],
            $validated_tolerance
        );
    }

    $elapsed = isset($result['elapsed_seconds']) ? $result['elapsed_seconds'] : null;
    if ((!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) || $elapsed < 0) {
        throw new RuntimeException('실행 시간이 올바르지 않습니다.');
    }

    // 정답 버전에 명시된 방식으로만 점수를 계산한다.
    $score_value = null;
    $max_score_value = null;

    $feedback['scoring'] = array(
        'mode' => $scoring_mode,
        'method' => null,
    );

    if ($scoring_mode === 'whole_image') {
        $calculated_score = course_simulation_calculate_score(
            $metrics['tolerance']['symmetric_coverage'],
            (string)$reference['max_score']
        );

        $score_value = $calculated_score['score'];
        $max_score_value = $calculated_score['max_score'];

        $feedback['scoring'] = array(
            'mode' => 'whole_image',
            'method' => $calculated_score['scoring_method'],
            'coverage_used' => $metrics['tolerance']['symmetric_coverage'],
            'score' => $score_value,
            'max_score' => $max_score_value,
            'scoring_script_sha256' => hash_file(
                'sha256',
                $root_dir . '/include/course_simulation_scoring.inc.php'
            ),
        );
    }

    if ($scoring_mode === 'regions') {
        $region_coverage =
            $metrics['region_comparison']['final_coverage'];

        $calculated_score = course_simulation_calculate_score(
            $region_coverage,
            (string)$reference['max_score']
        );

        $score_value = $calculated_score['score'];
        $max_score_value = $calculated_score['max_score'];

        $feedback['scoring'] = array(
            'mode' => 'regions',
            'method' => 'region_coverage_linear_v1',
            'coverage_used' => $region_coverage,
            'score' => $score_value,
            'max_score' => $max_score_value,
            'scoring_script_sha256' => hash_file(
                'sha256',
                $root_dir . '/include/course_simulation_scoring.inc.php'
            ),
            'region_metrics_script_sha256' => hash_file(
                'sha256',
                $region_script
            ),
            'region_config_script_sha256' => hash_file(
                'sha256',
                $root_dir . '/scripts/course_simulation_region_config.php'
            ),
            'region_result_script_sha256' => hash_file(
                'sha256',
                $root_dir . '/scripts/course_simulation_region_result.php'
            ),
        );
    }

    $feedback['result'] = array(
        'status' => 'ok',
        'metrics' => $metrics,
        'elapsed_seconds' => $elapsed,
    );

    $feedback_json = json_encode(
        $feedback,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($feedback_json === false) {
        throw new RuntimeException('결과 JSON 생성에 실패했습니다.');
    }

    $stmt = simulation_grade_statement(
        $db,
        "UPDATE course_simulation_grade
         SET grading_status = 'graded',
             score = ?, max_score = ?,
             feedback_json = ?, graded_at = NOW()
         WHERE grade_id = ? AND grading_status = 'running'",
        array(
            $score_value,
            $max_score_value,
            $feedback_json,
            $grade_id
        )
    );
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('채점 완료 상태를 저장하지 못했습니다.');
    }
    $stmt->closeCursor();

    echo "채점 ID: " . $grade_id . "\n";
    echo "제출 ID: " . $payload['submission_id'] . "\n";
    echo "정답 버전: " . $payload['reference_no'] . "\n";
    echo "채점 상태: graded\n";
    printf("전경 일치율: %.3f%%\n", $metrics['foreground_match']);
    printf("전경 겹침률: %.3f%%\n", $metrics['foreground_overlap']);
    printf(
        "허용 오차 반영 충족률: %.3f%%\n",
        $metrics['tolerance']['symmetric_coverage']
    );
    if ($score_value !== null) {
        echo "점수: " . $score_value .
            " / " . $max_score_value . "\n";
        echo "결과: 비교 지표·점수 저장 완료\n";
    } else {
        echo "결과: 비교 지표 저장 완료 / 공식 점수 미산정\n";
    }

    }

} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    if ($claimed) {
        try {
            if ($is_python_grade) {
                // 내부 실행 경로나 상세 오류는 학생용 피드백에 남기지 않는다.
                $feedback = array(
                    'schema_version' => 1,
                    'request' => $request,
                    'error' => array(
                        'code' => 'grading_error',
                        'message' => '수업 Python 채점 처리에 실패했습니다. 교사 확인이 필요합니다.',
                    ),
                );
            } else {
                $feedback['error'] = array('message' => $e->getMessage());
            }
            $error_json = json_encode(
                $feedback,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            if ($error_json === false) {
                $error_json = '{"error":{"message":"채점 처리 실패"}}';
            }

            $stmt = simulation_grade_statement(
                $db,
                "UPDATE course_simulation_grade
                 SET grading_status = 'error',
                     score = NULL, max_score = NULL,
                     feedback_json = ?, graded_at = NOW()
                 WHERE grade_id = ? AND grading_status = 'running'" .
                ($is_python_grade
                    ? ' AND feedback_json = CAST(? AS JSON)'
                    : ''),
                $is_python_grade
                    ? array($error_json, $grade_id, $grade['feedback_json'])
                    : array($error_json, $grade_id)
            );
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
        $cleanup_code = simulation_grade_exec(
            array_merge(
                array('/usr/bin/timeout', '--kill-after=2s', '10s'),
                $docker_prefix,
                array('rm', '-f', $container_name)
            ),
            '/dev/null',
            '/dev/null'
        );

        if ($cleanup_code !== 0) {
            fwrite(STDERR, "컨테이너 정리 상태를 확인하세요: " . $container_name . "\n");
        }
    }

    foreach ($temp_files as $file) {
        if (is_file($file) && !unlink($file)) {
            fwrite(STDERR, "임시 파일 삭제 실패: " . $file . "\n");
        }
    }
}

exit($exit_code);
