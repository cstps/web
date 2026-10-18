#!/usr/bin/env php
<?php

// 대기 중인 시뮬레이션 채점을 제한된 건수만큼 순차 처리한다.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

$options = getopt('', array('limit:'));
$raw_limit = isset($options['limit'])
    ? (string)$options['limit']
    : '1';

$limit = filter_var(
    $raw_limit,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1, 'max_range' => 10))
);

if ($limit === false) {
    fwrite(
        STDERR,
        "사용법: php scripts/course_simulation_grade_worker.php --limit=1\n" .
        "처리 건수는 1~10 사이여야 합니다.\n"
    );
    exit(1);
}

$root_dir = dirname(__DIR__);
$lock = null;
$locked = false;
$exit_code = 0;

try {
    // 같은 DB의 동시 배치 실행을 막기 위해 MySQL 연결 잠금을 사용한다.
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

    // 연결 종료 시 잠금이 해제되도록 비영속 전용 연결을 사용한다.
    $lock = new PDO(
        'mysql:host=' . $DB_HOST . ';dbname=' . $DB_NAME . ';charset=utf8mb4',
        $DB_USER,
        $DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
        )
    );

    $lock_name = '1024kr_simulation_grade_worker';
    $stmt = $lock->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute(array($lock_name));
    $lock_result = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ((string)$lock_result === '0') {
        echo "다른 채점 배치 실행기가 처리 중입니다.\n";
    } elseif ((string)$lock_result !== '1') {
        throw new RuntimeException('배치 실행 잠금을 얻지 못했습니다.');
    } else {
        $locked = true;
        $runner = $root_dir . '/scripts/course_simulation_grade_run.php';

        if (!is_readable($runner)) {
            throw new RuntimeException('채점 실행기를 읽을 수 없습니다.');
        }

        $success = 0;
        $failed = 0;

        for ($index = 0; $index < $limit; $index++) {
            $stmt = $lock->query(
                "SELECT grade_id
                 FROM course_simulation_grade
                 WHERE grading_status = 'pending'
                 ORDER BY created_at, grade_id
                 LIMIT 1"
            );
            $grade_id = $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($grade_id === false) {
                break;
            }

            if (!preg_match('/^[1-9][0-9]*$/D', (string)$grade_id)) {
                throw new RuntimeException('채점 ID가 올바르지 않습니다.');
            }

            echo "처리 시작: 채점 ID " . $grade_id . "\n";

            $process = proc_open(
                array(
                    PHP_BINARY,
                    $runner,
                    '--grade-id=' . $grade_id,
                ),
                array(
                    0 => array('file', '/dev/null', 'r'),
                    1 => STDOUT,
                    2 => STDERR,
                ),
                $pipes,
                $root_dir
            );

            if (!is_resource($process)) {
                throw new RuntimeException('채점 실행 프로세스를 시작하지 못했습니다.');
            }

            $code = proc_close($process);

            if ($code === 0) {
                $success++;
            } else {
                $failed++;

                // 실행 전 실패로 pending이 유지되면 같은 요청을 반복하지 않는다.
                $stmt = $lock->prepare(
                    'SELECT grading_status
                     FROM course_simulation_grade
                     WHERE grade_id = ?'
                );
                $stmt->execute(array($grade_id));
                $status = $stmt->fetchColumn();
                $stmt->closeCursor();

                if ($status === 'pending' || $status === 'running') {
                    fwrite(
                        STDERR,
                        "배치 중단: 채점 ID " . $grade_id .
                        "의 상태를 확인하세요: " . $status . "\n"
                    );
                    break;
                }
            }
        }

        echo "배치 결과: 성공 " . $success .
            "건 / 실패 " . $failed . "건\n";

        if ($failed > 0) {
            $exit_code = 1;
        }
    }

} catch (Throwable $e) {
    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    $exit_code = 1;

} finally {
    if ($locked && $lock instanceof PDO) {
        try {
            $stmt = $lock->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute(array($lock_name));
            $stmt->closeCursor();
        } catch (Throwable $e) {
            fwrite(STDERR, "배치 잠금 해제 실패: " . $e->getMessage() . "\n");
            $exit_code = 1;
        }
    }
    $lock = null;
}

exit($exit_code);
