<?php

require_once(__DIR__ . '/course_simulation_python_grade_result.php');

// 서버에서 검증한 요청과 목표 PNG만 전달한다.
// 컨테이너 실행·종료는 기존 Python 실행 관리기가 담당한다.
function course_simulation_execute_python_grade(
    array $request,
    $source_code,
    $reference_png,
    $image_id
) {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI에서만 채점을 실행할 수 있습니다.');
    }

    if (
        !is_string($image_id) ||
        preg_match('/^sha256:[0-9a-f]{64}$/D', $image_id) !== 1
    ) {
        throw new RuntimeException('고정된 로컬 실행 이미지가 필요합니다.');
    }

    // 실행 전에 요청 형식과 제출 코드 해시를 검사한다.
    course_simulation_validate_python_grade_result(
        $request,
        $source_code,
        array(
            'schema_version' => 1,
            'status' => 'error',
            'code' => 'execution_error',
        )
    );

    if (
        !is_string($reference_png) ||
        strlen($reference_png) < 8 ||
        strlen($reference_png) > 1048576 ||
        substr($reference_png, 0, 8) !== "\x89PNG\r\n\x1a\n" ||
        !hash_equals(
            $request['target_png_sha256'],
            hash('sha256', $reference_png)
        )
    ) {
        throw new RuntimeException('채점할 목표 PNG의 해시가 다릅니다.');
    }

    $info = @getimagesizefromstring($reference_png);

    if (
        $info === false ||
        $info[2] !== IMAGETYPE_PNG ||
        $info[0] !== 400 ||
        $info[1] !== 400
    ) {
        throw new RuntimeException('목표 PNG의 크기 또는 형식이 다릅니다.');
    }

    if (!is_executable('/usr/bin/python3')) {
        throw new RuntimeException('Python 실행 파일이 없습니다.');
    }

    // 실행 중 파일 교체를 감지하기 위해 사용 파일의 해시를 고정한다.
    $hashes = array();

    foreach (array(
        'course_simulation_python_runtime_poc.py',
        'course_simulation_python_render_poc.py',
        'course_simulation_python_container_poc.py',
        'simulation_problem_python_grade.py',
        'simulation_problem_python_reference.py',
        'simulation_problem_compare.py',
        'course_simulation_python_compare_poc.py',
        'course_simulation_image_metrics.py',
        'course_simulation_region_metrics.py',
    ) as $name) {
        $path = __DIR__ . '/' . $name;

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('채점 실행에 필요한 파일이 없습니다.');
        }

        $hash = hash_file('sha256', $path);

        if (!is_string($hash) || strlen($hash) !== 64) {
            throw new RuntimeException('채점 실행 파일의 해시 검사에 실패했습니다.');
        }

        $hashes[$name] = $hash;
    }

    if (!hash_equals(
        $request['runtime_sha256'],
        $hashes['course_simulation_python_runtime_poc.py']
    )) {
        throw new RuntimeException('요청에 저장된 그림 함수 버전이 다릅니다.');
    }

    $directory = sys_get_temp_dir() .
        '/course-python-grade-' . bin2hex(random_bytes(16));
    $created = false;
    $files = array();
    $process = null;

    try {
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('채점 임시 디렉터리를 만들지 못했습니다.');
        }

        $created = true;

        $input = array(
            'schema_version' => 1,
            'engine' => 'python-stdlib-rgba-poc-v1',
            'runtime_sha256' => $request['runtime_sha256'],
            'source_code' => $source_code,
            'frame' => 0,
        );

        $grading = array(
            'max_score' => $request['max_score'],
            'grading_config' => $request['grading_config'],
        );

        $contents = array(
            'request.json' => json_encode(
                $input,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            'grading.json' => json_encode(
                $grading,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            'reference.png' => $reference_png,
            'output.json' => '',
            'error.txt' => '',
        );

        foreach ($contents as $name => $data) {
            $path = $directory . '/' . $name;
            $files[] = $path;

            if (
                file_put_contents($path, $data) !== strlen($data) ||
                !chmod($path, 0600)
            ) {
                throw new RuntimeException('채점 임시 파일 저장에 실패했습니다.');
            }
        }

        $command = array(
            '/usr/bin/python3',
            '-B',
            __DIR__ . '/simulation_problem_python_grade.py',
            '--input-json', $directory . '/request.json',
            '--reference-png', $directory . '/reference.png',
            '--grading-json', $directory . '/grading.json',
            '--image-id', $image_id,
        );

        $pipes = array();
        $process = proc_open(
            $command,
            array(
                0 => array('file', '/dev/null', 'r'),
                1 => array('file', $directory . '/output.json', 'w'),
                2 => array('file', $directory . '/error.txt', 'w'),
            ),
            $pipes,
            dirname(__DIR__)
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Python 채점 실행을 시작하지 못했습니다.');
        }

        // 외부에서 먼저 종료하지 않고 실행 관리기의 정리를 기다린다.
        $exit_code = proc_close($process);
        $process = null;

        foreach ($hashes as $name => $expected_hash) {
            if (hash_file('sha256', __DIR__ . '/' . $name) !== $expected_hash) {
                throw new RuntimeException('채점 중 실행 파일이 변경됐습니다.');
            }
        }

        $output_path = $directory . '/output.json';
        clearstatcache(true, $output_path);

        if (
            !is_file($output_path) ||
            is_link($output_path) ||
            filesize($output_path) < 1 ||
            filesize($output_path) > 131072
        ) {
            throw new RuntimeException('Python 채점 결과 크기가 올바르지 않습니다.');
        }

        $result = json_decode(
            file_get_contents($output_path),
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        if (
            !is_array($result) ||
            !in_array($exit_code, array(0, 1), true) ||
            ($exit_code === 0 && ($result['status'] ?? null) !== 'ok') ||
            ($exit_code === 1 && ($result['status'] ?? null) !== 'error')
        ) {
            throw new RuntimeException('Python 채점 종료 상태와 결과가 다릅니다.');
        }

        // 충족률·영역 배점·점수를 PHP에서 다시 검증한다.
        return course_simulation_validate_python_grade_result(
            $request,
            $source_code,
            $result
        );

    } finally {
        if (is_resource($process)) {
            proc_close($process);
        }

        foreach (array_reverse($files) as $path) {
            if (is_file($path) || is_link($path)) {
                if (!unlink($path)) {
                    error_log('[course_python_grade] 임시 파일 정리 실패');
                }
            }
        }

        if ($created && is_dir($directory) && !rmdir($directory)) {
            error_log('[course_python_grade] 임시 디렉터리 정리 실패');
        }
    }
}
