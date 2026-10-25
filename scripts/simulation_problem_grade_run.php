<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once(__DIR__ . '/simulation_problem_grade_job.php');
require_once(__DIR__ . '/simulation_problem_grade_request.php');

function simulation_problem_grade_read_reference(PDO $connection, array $claim)
{
    $statement = simulation_problem_grade_job_statement(
        $connection,
        'SELECT
            CAST(v.version_id AS CHAR) AS version_id,
            CAST(v.problem_id AS CHAR) AS problem_id,
            v.version_no, v.simulation_mode,
            v.canvas_width, v.canvas_height, v.p5_version,
            v.source_language, v.execution_engine, v.execution_version,
            r.source_code AS reference_source,
            r.render_config, r.grading_config, r.max_score,
            i.image_status, i.source_sha256, i.settings_sha256,
            i.request_metadata, i.render_metadata,
            i.png_sha256, i.generated_at,
            OCTET_LENGTH(i.png_data) AS png_bytes,
            i.png_data
         FROM simulation_problem_version v
         LEFT JOIN simulation_problem_reference r
           ON r.version_id = v.version_id
         LEFT JOIN simulation_problem_reference_image i
           ON i.version_id = v.version_id
         WHERE v.version_id = ?',
        array($claim['version_id'])
    );
    $reference = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$reference) {
        throw new RuntimeException('채점 대상 정답 버전을 찾지 못했습니다.');
    }

    $expected = simulation_problem_grade_request_metadata(
        $reference,
        $claim['source_code']
    );
    $expected['submission_id'] = $claim['submission_id'];

    $stored = json_decode(
        $claim['request_metadata'],
        false,
        64,
        JSON_THROW_ON_ERROR
    );

    if (
        !$stored instanceof stdClass ||
        simulation_problem_image_canonical_json($stored) !==
            simulation_problem_image_canonical_json((object)$expected)
    ) {
        throw new RuntimeException('현재 정답과 저장된 채점 요청이 다릅니다.');
    }

    return $reference;
}

function simulation_problem_grade_write_file($path, $contents)
{
    if (file_put_contents($path, $contents) !== strlen($contents)) {
        throw new RuntimeException('채점 임시 파일 저장에 실패했습니다.');
    }

    if (!chmod($path, 0600)) {
        throw new RuntimeException('채점 임시 파일 권한 설정에 실패했습니다.');
    }
}

// 실행 인수는 배열로 전달하며 셸 명령으로 조합하지 않는다.
// 각 Python 실행기가 컨테이너 제한 시간과 강제 정리를 담당한다.
function simulation_problem_grade_child(array $command, $directory)
{
    $stdout = $directory . '/stdout.json';
    $stderr = $directory . '/stderr.log';

    $process = proc_open(
        $command,
        array(
            0 => array('file', '/dev/null', 'r'),
            1 => array('file', $stdout, 'w'),
            2 => array('file', $stderr, 'w'),
        ),
        $pipes,
        dirname(__DIR__)
    );

    if (!is_resource($process)) {
        throw new RuntimeException('채점 실행기를 시작하지 못했습니다.');
    }

    $exit_code = proc_close($process);

    clearstatcache(true, $stdout);
    $size = is_file($stdout) ? filesize($stdout) : false;

    if ($size === false || $size < 1 || $size > 262144) {
        throw new RuntimeException('채점 실행기의 응답 크기가 올바르지 않습니다.');
    }

    $output = file_get_contents($stdout);

    if (!is_string($output)) {
        throw new RuntimeException('채점 실행기의 응답을 읽지 못했습니다.');
    }

    return array($exit_code, $output);
}

function simulation_problem_process_grade(
    PDO $connection,
    $grade_id,
    $image_id
) {
    if (
        !is_string($image_id) ||
        preg_match('/^sha256:[0-9a-f]{64}$/D', $image_id) !== 1
    ) {
        throw new InvalidArgumentException('전체 로컬 이미지 ID가 필요합니다.');
    }

    $claim = simulation_problem_grade_job_claim($connection, $grade_id);

    if ($claim === null) {
        return null;
    }

    $directory = null;

    try {
        $reference = simulation_problem_grade_read_reference(
            $connection,
            $claim
        );

        $execution = simulation_validate_execution_metadata($reference, true);
        $settings = simulation_problem_image_request_metadata(
            $reference
        )['settings'];

        $directory = sys_get_temp_dir() .
            '/bank-grade-' . bin2hex(random_bytes(16));

        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('채점 임시 디렉터리를 만들지 못했습니다.');
        }

        if ($execution['execution_engine'] === 'p5js') {
            $script = __DIR__ . '/simulation_problem_p5_grade.py';
            $input = array(
                'schema_version' => 1,
                'execution_engine' => 'p5js',
                'execution_version' => $execution['execution_version'],
                'p5_file_sha256' => $settings->p5_file_sha256,
                'source_code' => $claim['source_code'],
                'frame' => 0,
            );
        } else {
            $script = __DIR__ . '/simulation_problem_python_grade.py';
            $input = array(
                'schema_version' => 1,
                'engine' => 'python-stdlib-rgba-poc-v1',
                'runtime_sha256' => $settings->runtime_sha256,
                'source_code' => $claim['source_code'],
                'frame' => 0,
            );
        }

        if (!is_file($script) || !is_readable($script)) {
            throw new RuntimeException('언어별 채점 실행 파일을 읽지 못했습니다.');
        }

        simulation_problem_grade_write_file(
            $directory . '/request.json',
            json_encode(
                $input,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            )
        );

        simulation_problem_grade_write_file(
            $directory . '/grading.json',
            json_encode(
                array(
                    'max_score' => $settings->max_score,
                    'grading_config' => $settings->grading_config,
                ),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );

        simulation_problem_grade_write_file(
            $directory . '/reference.png',
            $reference['png_data']
        );

        list($exit_code, $output) = simulation_problem_grade_child(
            array(
                '/usr/bin/python3',
                '-B',
                $script,
                '--input-json', $directory . '/request.json',
                '--reference-png', $directory . '/reference.png',
                '--grading-json', $directory . '/grading.json',
                '--image-id', $image_id,
            ),
            $directory
        );

        $result = json_decode($output, true, 64, JSON_THROW_ON_ERROR);

        if (
            !is_array($result) ||
            !in_array($exit_code, array(0, 1), true) ||
            ($result['status'] ?? null) !==
                ($exit_code === 0 ? 'ok' : 'error')
        ) {
            throw new RuntimeException('채점 실행기의 종료 상태와 응답이 다릅니다.');
        }

        // 실행 중 정답·이미지·실행 파일 설정이 바뀌었는지도 확인한다.
        if ($result['status'] === 'ok') {
            simulation_problem_grade_read_reference($connection, $claim);
        }

        return simulation_problem_grade_job_finish(
            $connection,
            $claim,
            $result
        );

    } catch (Throwable $exception) {
        error_log(
            '[simulation_problem_grade_run] ' .
            $exception->getMessage()
        );

        // 작업 토큰이나 원본이 바뀌었다면 이 저장도 거절되어 덮어쓰지 않는다.
        return simulation_problem_grade_job_finish(
            $connection,
            $claim,
            array(
                'schema_version' => 1,
                'status' => 'error',
                'code' => 'grading_error',
            )
        );

    } finally {
        if ($directory !== null && is_dir($directory)) {
            foreach (array(
                'request.json', 'grading.json', 'reference.png',
                'stdout.json', 'stderr.log',
            ) as $name) {
                $file = $directory . '/' . $name;
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }
}

// 다른 CLI 검사에서 함수를 불러올 때는 자동으로 실행하지 않는다.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $options = getopt('', array('grade-id:', 'image-id:'));
        $grade_id = simulation_problem_submission_id(
            $options['grade-id'] ?? null
        );
        $image_id = $options['image-id'] ?? null;

        if (
            !is_string($image_id) ||
            preg_match('/^sha256:[0-9a-f]{64}$/D', $image_id) !== 1
        ) {
            throw new InvalidArgumentException(
                '--grade-id 및 --image-id' .
                ' 인수를 확인해 주세요.'
            );
        }

        $root = dirname(__DIR__);
        chdir($root);
        $_SERVER['HTTP_HOST'] = '1024.kr';

        require_once($root . '/include/db_info.inc.php');

        if (
            pdo_query('SELECT 1') === false ||
            !isset($dbh) ||
            !($dbh instanceof PDO)
        ) {
            throw new RuntimeException('DB 연결을 확인하지 못했습니다.');
        }

        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $saved = simulation_problem_process_grade(
            $dbh,
            $grade_id,
            $image_id
        );

        if ($saved === null) {
            echo "처리할 대기 작업이 없거나 이미 처리 중입니다.\n";
            exit(0);
        }

        echo '채점 ID: ' . $saved['grade_id'] . "\n";
        echo '채점 상태: ' . $saved['grading_status'] . "\n";

        if ($saved['score'] !== null) {
            echo '점수: ' . $saved['score'] .
                ' / ' . $saved['max_score'] . "\n";
        }

        exit($saved['grading_status'] === 'graded' ? 0 : 1);

    } catch (Throwable $exception) {
        file_put_contents(
            'php://stderr',
            '채점 처리 실패: ' . $exception->getMessage() . "\n"
        );
        exit(1);
    }
}
