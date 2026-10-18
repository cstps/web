#!/usr/bin/env php
<?php

// 등록된 채점 요청 하나를 처리하는 CLI.
// 현재는 비교 지표만 저장하고 공식 점수는 계산하지 않는다.

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

    foreach (array('/usr/bin/timeout', '/usr/bin/sudo', '/usr/bin/docker') as $binary) {
        if (!is_executable($binary)) {
            throw new RuntimeException('필요한 실행 파일이 없습니다: ' . $binary);
        }
    }

    $python_script = $root_dir . '/scripts/course_simulation_render_grade.py';
    $p5_file = $root_dir . '/template/syzoj/js/vendor/p5.min.js';

    foreach (array($python_script, $p5_file) as $file) {
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
         SET grading_status = 'running'
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

    $payload = array(
        'activity_id' => (int)$submission['activity_id'],
        'reference_no' => (int)$reference['reference_no'],
        'reference_code' => (string)$reference['source_code'],
        'submission_id' => (int)$submission['submission_id'],
        'submission_no' => (int)$submission['submission_no'],
        'student_code' => (string)$submission['source_code'],
        'canvas_width' => 400,
        'canvas_height' => 400,
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

    // 현재 비교기는 고정된 PoC 렌더 조건을 사용한다.
    // 정답의 render_config/grading_config를 해석하는 기능은 아직 없다.
    $feedback['runtime'] = array(
        'comparison_mode' => 'static-image-poc-v1',
        'docker_image' => $image,
        'python_script_sha256' => hash_file('sha256', $python_script),
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

    $elapsed = isset($result['elapsed_seconds']) ? $result['elapsed_seconds'] : null;
    if ((!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) || $elapsed < 0) {
        throw new RuntimeException('실행 시간이 올바르지 않습니다.');
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
             score = NULL, max_score = NULL,
             feedback_json = ?, graded_at = NOW()
         WHERE grade_id = ? AND grading_status = 'running'",
        array($feedback_json, $grade_id)
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
    echo "결과: 비교 지표 저장 완료 / 공식 점수 미산정\n";

} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    if ($claimed) {
        try {
            $feedback['error'] = array('message' => $e->getMessage());
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
                 WHERE grade_id = ? AND grading_status = 'running'",
                array($error_json, $grade_id)
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
