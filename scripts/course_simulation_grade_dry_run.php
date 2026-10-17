#!/usr/bin/env php
<?php

// Course 시뮬레이션 정적 그림 채점 dry-run CLI 래퍼.
// 운영 DB의 course_simulation_grade 테이블은 수정하지 않는다.

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "이 스크립트는 CLI에서만 실행할 수 있습니다.\n");
    exit(1);
}

$root_dir = dirname(__DIR__);

if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = '1024.kr';
}

require_once($root_dir . '/include/db_info.inc.php');

if (!function_exists('pdo_query')) {
    require_once($root_dir . '/include/pdo.php');
}

$options = getopt('', array(
    'submission-id:',
    'json',
));

$submission_id_raw = isset($options['submission-id'])
    ? (string)$options['submission-id']
    : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $submission_id_raw) ||
    strlen($submission_id_raw) > 20
) {
    fwrite(
        STDERR,
        "사용법: php scripts/course_simulation_grade_dry_run.php " .
        "--submission-id=6 [--json]\n"
    );
    exit(1);
}

$submission_id = (int)$submission_id_raw;

$rows = pdo_query(
    "SELECT
        s.submission_id,
        s.submission_no,
        s.activity_id,
        s.source_code AS student_code,
        r.reference_no,
        r.source_code AS reference_code,
        r.canvas_width,
        r.canvas_height
     FROM course_simulation_submission s
     INNER JOIN course_simulation_reference r
       ON r.activity_id = s.activity_id
     WHERE s.submission_id = ?
       AND r.reference_no = (
           SELECT MAX(r2.reference_no)
           FROM course_simulation_reference r2
           WHERE r2.activity_id = s.activity_id
       )
     LIMIT 1",
    $submission_id
);

if ($rows === false) {
    fwrite(STDERR, "DB 조회에 실패했습니다.\n");
    exit(1);
}

if (!isset($rows[0])) {
    fwrite(
        STDERR,
        "제출 또는 해당 활동의 정답 버전을 찾을 수 없습니다.\n"
    );
    exit(1);
}

$row = $rows[0];

$payload = array(
    'activity_id' => (int)$row['activity_id'],
    'reference_no' => (int)$row['reference_no'],
    'reference_code' => (string)$row['reference_code'],
    'submission_id' => (int)$row['submission_id'],
    'submission_no' => (int)$row['submission_no'],
    'student_code' => (string)$row['student_code'],
    'canvas_width' => (int)$row['canvas_width'],
    'canvas_height' => (int)$row['canvas_height'],
);

$json = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    fwrite(STDERR, "입력 JSON 생성에 실패했습니다.\n");
    exit(1);
}

$temp_file = tempnam(sys_get_temp_dir(), 'simulation-grade-');

if ($temp_file === false) {
    fwrite(STDERR, "임시 파일 생성에 실패했습니다.\n");
    exit(1);
}

try {
    if (file_put_contents($temp_file, $json) === false) {
        throw new RuntimeException('임시 JSON 저장 실패');
    }

    chmod($temp_file, 0600);

    $docker_image = '1024kr/p5-render-poc:playwright-1.63.0';
    $python_script = $root_dir . '/scripts/course_simulation_render_grade.py';
    $p5_file = $root_dir . '/template/syzoj/js/vendor/p5.min.js';

    if (!is_file($python_script)) {
        throw new RuntimeException('Python dry-run 스크립트를 찾을 수 없습니다.');
    }

    if (!is_file($p5_file)) {
        throw new RuntimeException('p5.min.js 파일을 찾을 수 없습니다.');
    }

    $command = array(
        'sudo docker run --rm -i',
        '--network=none',
        '--user=1000:1000',
        '--cap-drop=ALL',
        '--security-opt=no-new-privileges',
        '--cpus=1',
        '--memory=512m',
        '--memory-swap=512m',
        '--pids-limit=128',
        '--tmpfs /tmp:rw,nosuid,nodev,size=256m',
        '-v ' . escapeshellarg($python_script . ':/work/course_simulation_render_grade.py:ro'),
        '-v ' . escapeshellarg($p5_file . ':/work/p5.min.js:ro'),
        '-v ' . escapeshellarg($temp_file . ':/work/input.json:ro'),
        '--entrypoint python',
        escapeshellarg($docker_image),
        '/work/course_simulation_render_grade.py',
        '--input-json /work/input.json',
        '--p5 /work/p5.min.js',
    );

    if (isset($options['json'])) {
        $command[] = '--json';
    }

    $shell_command = implode(' ', $command) . ' 2>&1';

    $output = array();
    $exit_code = 0;

    exec($shell_command, $output, $exit_code);

    foreach ($output as $line) {
        echo $line . "\n";
    }

    if ($exit_code !== 0) {
        fwrite(
            STDERR,
            "dry-run 렌더링 비교가 실패했습니다. 종료 코드: " .
            $exit_code .
            "\n"
        );
        exit($exit_code);
    }

} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    if (is_file($temp_file)) {
        unlink($temp_file);
    }
}
