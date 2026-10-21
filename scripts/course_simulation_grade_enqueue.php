#!/usr/bin/env php
<?php

// Course 시뮬레이션 채점 대기 등록 전용 CLI.
// 렌더링 실행이나 점수 계산은 수행하지 않는다.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI에서만 실행할 수 있습니다.\n");
}

$options = getopt('', array('submission-id:'));
$raw_id = isset($options['submission-id'])
    ? (string)$options['submission-id']
    : '';

$submission_id = filter_var(
    $raw_id,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
);

if (
    !preg_match('/^[1-9][0-9]*$/D', $raw_id) ||
    $submission_id === false
) {
    fwrite(
        STDERR,
        "사용법: php scripts/course_simulation_grade_enqueue.php " .
        "--submission-id=6\n"
    );
    exit(1);
}

$root_dir = dirname(__DIR__);
$connection = null;
$exit_code = 0;

try {
    if (!isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = '1024.kr';
    }

    require_once($root_dir . '/include/db_info.inc.php');

    if (!function_exists('pdo_query')) {
        require_once($root_dir . '/include/pdo.php');
    }

    // 기존 DB 설정과 연결 방식을 사용한다.
    if (pdo_query('SELECT 1') === false) {
        throw new RuntimeException('DB 연결에 실패했습니다.');
    }

    if (!isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('PDO 연결을 찾을 수 없습니다.');
    }

    $connection = $dbh;

    if ($connection->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    $connection->beginTransaction();

    // 동일 제출의 등록 요청을 직렬화한다.
    $stmt = $connection->prepare(
        'SELECT submission_id, activity_id, source_code
         FROM course_simulation_submission
         WHERE submission_id = ?
         FOR UPDATE'
    );
    $stmt->execute(array($submission_id));
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$submission) {
        throw new RuntimeException('제출을 찾을 수 없습니다.');
    }

    $stmt = $connection->prepare(
        "SELECT grade_id, grading_status
         FROM course_simulation_grade
         WHERE submission_id = ?
           AND grading_status IN ('pending', 'running')
         ORDER BY grading_no DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(array($submission_id));
    $active = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($active) {
        throw new RuntimeException(
            '이미 대기 또는 실행 중인 채점이 있습니다. 채점 ID: ' .
            $active['grade_id'] .
            ', 상태: ' .
            $active['grading_status']
        );
    }

    $stmt = $connection->prepare(
        'SELECT grading_no
         FROM course_simulation_grade
         WHERE submission_id = ?
         ORDER BY grading_no DESC
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute(array($submission_id));
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $last_no = $last ? (int)$last['grading_no'] : 0;

    if ($last_no >= 2147483647) {
        throw new RuntimeException('채점 회차의 허용 범위를 초과했습니다.');
    }

    $grading_no = $last_no + 1;

    // 등록 시점의 최신 정답 버전을 선택한다.
    $stmt = $connection->prepare(
        'SELECT reference_id, reference_no, source_code,
                canvas_width, canvas_height, p5_version,
                render_config, grading_config, max_score
         FROM course_simulation_reference
         WHERE activity_id = ?
         ORDER BY reference_no DESC
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute(array((int)$submission['activity_id']));
    $reference = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$reference) {
        throw new RuntimeException('해당 활동의 정답을 찾을 수 없습니다.');
    }

    // 선택된 정답 버전의 목표 이미지와 영역 검사 기록을 확인한다.
    require_once(__DIR__ . '/course_simulation_reference_readiness.php');
    simulation_assert_reference_ready($connection, $reference);

    $feedback = array(
        'schema_version' => 1,
        'request' => array(
            'activity_id' => (int)$submission['activity_id'],
            'reference_id' => (int)$reference['reference_id'],
            'reference_no' => (int)$reference['reference_no'],
            'canvas_width' => (int)$reference['canvas_width'],
            'canvas_height' => (int)$reference['canvas_height'],
            'p5_version' => (string)$reference['p5_version'],
            'reference_max_score' => (string)$reference['max_score'],
            'reference_source_sha256' => hash(
                'sha256',
                (string)$reference['source_code']
            ),
            'student_source_sha256' => hash(
                'sha256',
                (string)$submission['source_code']
            ),
            'render_config_sha256' => hash(
                'sha256',
                (string)$reference['render_config']
            ),
            'grading_config_sha256' => hash(
                'sha256',
                (string)$reference['grading_config']
            ),
        ),
    );

    $feedback_json = json_encode(
        $feedback,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($feedback_json === false) {
        throw new RuntimeException('채점 요청 JSON 생성에 실패했습니다.');
    }

    $stmt = $connection->prepare(
        "INSERT INTO course_simulation_grade
            (submission_id, grading_no, grading_status,
             score, max_score, feedback_json, graded_at)
         VALUES (?, ?, 'pending', NULL, NULL, ?, NULL)"
    );
    $stmt->execute(array(
        $submission_id,
        $grading_no,
        $feedback_json,
    ));

    $grade_id = $connection->lastInsertId();
    $stmt->closeCursor();

    $connection->commit();

    echo "제출 ID: " . $submission_id . "\n";
    echo "채점 ID: " . $grade_id . "\n";
    echo "채점 회차: " . $grading_no . "\n";
    echo "정답 버전: " . $reference['reference_no'] . "\n";
    echo "채점 상태: pending\n";
    echo "결과: 채점 대기 등록 완료\n";

} catch (Throwable $e) {
    if (
        $connection instanceof PDO &&
        $connection->inTransaction()
    ) {
        $connection->rollBack();
    }

    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    $exit_code = 1;
}

exit($exit_code);
