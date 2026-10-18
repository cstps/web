#!/usr/bin/env php
<?php

// 제출 알림을 기다리며 기존 채점 대기열을 순차 처리한다.
// 소켓으로 전달된 데이터는 채점 입력으로 사용하지 않는다.
// 실제 작업은 반드시 DB의 pending 기록에서 선택한다.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

function simulation_daemon_next_grade()
{
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    // 대기 중에 DB 연결을 유지하지 않는다.
    $db = new PDO(
        'mysql:host=' . $DB_HOST . ';dbname=' . $DB_NAME . ';charset=utf8mb4',
        $DB_USER,
        $DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
        )
    );

    $stmt = $db->query(
        "SELECT grade_id
         FROM course_simulation_grade
         WHERE grading_status = 'pending'
         ORDER BY created_at, grade_id
         LIMIT 1"
    );

    $grade_id = $stmt->fetchColumn();
    $stmt->closeCursor();

    return $grade_id === false ? null : (string)$grade_id;
}

function simulation_daemon_drain_notifications($server)
{
    // 알림 내용은 읽거나 해석하지 않고 연결만 수락·종료한다.
    // 한 번에 처리할 연결 수를 제한하여 DB 처리가 밀리지 않게 한다.
    for ($index = 0; $index < 256; $index++) {
        $client = @stream_socket_accept($server, 0);

        if ($client === false) {
            break;
        }

        fclose($client);
    }
}

$root_dir = dirname(__DIR__);
$runtime_dir = '/run/1024kr-simulation-grade';
$socket_path = $runtime_dir . '/notify.sock';
$lock_path = $runtime_dir . '/daemon.lock';

$lock_file = null;
$locked = false;
$server = null;
$socket_created = false;
$stop_requested = false;
$exit_code = 0;

try {
    if (!isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = '1024.kr';
    }

    require_once($root_dir . '/include/db_info.inc.php');

    if (!is_dir($runtime_dir) || is_link($runtime_dir)) {
        throw new RuntimeException(
            '실행 디렉터리가 준비되지 않았습니다: ' . $runtime_dir
        );
    }

    $worker = $root_dir . '/scripts/course_simulation_grade_worker.php';

    if (!is_readable($worker)) {
        throw new RuntimeException('채점 배치 실행기를 읽을 수 없습니다.');
    }

    if (!function_exists('proc_open')) {
        throw new RuntimeException('프로세스 실행 기능을 사용할 수 없습니다.');
    }

    $lock_file = fopen($lock_path, 'c');

    if ($lock_file === false) {
        throw new RuntimeException('상시 처리기 잠금 파일을 열 수 없습니다.');
    }

    if (!flock($lock_file, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('다른 상시 처리기가 실행 중입니다.');
    }

    $locked = true;

    // 이전 비정상 종료로 남은 소켓은 잠금 확보 후에만 정리한다.
    if (file_exists($socket_path) || is_link($socket_path)) {
        if (!unlink($socket_path)) {
            throw new RuntimeException('기존 알림 소켓을 정리하지 못했습니다.');
        }
    }

    $context = stream_context_create(array(
        'socket' => array('backlog' => 128),
    ));

    $socket_errno = 0;
    $socket_error = '';

    $server = stream_socket_server(
        'unix://' . $socket_path,
        $socket_errno,
        $socket_error,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        $context
    );

    if ($server === false) {
        throw new RuntimeException(
            '알림 소켓 생성 실패: ' . $socket_error
        );
    }

    $socket_created = true;

    // 서비스의 기본 그룹을 통해 웹 실행 계정에 알림 권한을 부여한다.
    if (!chmod($socket_path, 0660)) {
        throw new RuntimeException('알림 소켓 권한 설정에 실패했습니다.');
    }

    if (!stream_set_blocking($server, false)) {
        throw new RuntimeException('알림 소켓 대기 방식 설정에 실패했습니다.');
    }

    if (
        function_exists('pcntl_async_signals') &&
        function_exists('pcntl_signal')
    ) {
        pcntl_async_signals(true);

        $stop_handler = function ($signal) use (&$stop_requested) {
            $stop_requested = true;
        };

        pcntl_signal(SIGTERM, $stop_handler);
        pcntl_signal(SIGINT, $stop_handler);
    }

    echo "상시 처리기 시작: 제출 알림 대기 / 복구 확인 간격 60초\n";

    while (!$stop_requested) {
        // 시작 시와 알림 또는 대기 시간 만료 후 대기열을 처리한다.
        while (!$stop_requested) {
            simulation_daemon_drain_notifications($server);

            $before_id = simulation_daemon_next_grade();

            if ($before_id === null) {
                break;
            }

            $process = proc_open(
                array(PHP_BINARY, $worker, '--limit=1'),
                array(
                    0 => array('file', '/dev/null', 'r'),
                    1 => STDOUT,
                    2 => STDERR,
                ),
                $pipes,
                $root_dir
            );

            if (!is_resource($process)) {
                throw new RuntimeException(
                    '채점 배치 프로세스를 시작하지 못했습니다.'
                );
            }

            $worker_code = proc_close($process);

            if ($stop_requested) {
                break;
            }

            $after_id = simulation_daemon_next_grade();

            // 권한 문제 등으로 같은 작업이 pending에 남으면
            // 즉시 반복하지 않고 알림 또는 복구 확인까지 대기한다.
            if ($after_id === $before_id) {
                fwrite(
                    STDERR,
                    '대기열 처리 보류: 채점 ID ' . $before_id .
                    ', 배치 종료 코드 ' . $worker_code . "\n"
                );

                simulation_daemon_drain_notifications($server);
                break;
            }
        }

        if ($stop_requested) {
            break;
        }

        $read = array($server);
        $write = null;
        $except = null;

        // 평상시에는 소켓 알림을 기다린다.
        // 알림이 없어도 60초 후 DB 대기열을 다시 확인한다.
        $ready = @stream_select($read, $write, $except, 60);

        if ($ready === false && !$stop_requested) {
            throw new RuntimeException('알림 대기 중 오류가 발생했습니다.');
        }
    }

    echo "상시 처리기 종료\n";

} catch (Throwable $e) {
    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    $exit_code = 1;

} finally {
    if (is_resource($server)) {
        fclose($server);
    }

    if ($socket_created && file_exists($socket_path)) {
        if (!unlink($socket_path)) {
            fwrite(STDERR, "알림 소켓 삭제에 실패했습니다.\n");
            $exit_code = 1;
        }
    }

    if (is_resource($lock_file)) {
        if ($locked) {
            flock($lock_file, LOCK_UN);
        }
        fclose($lock_file);
    }
}

exit($exit_code);
