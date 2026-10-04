<?php

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$respond = static function (array $payload, $status = 200) {
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $respond(array(
        'ok' => false,
        'message' => 'GET 요청만 사용할 수 있습니다.'
    ), 405);
}

require_once(__DIR__ . '/include/db_info.inc.php');

// 로그인 사용자 확인
$session_key = $OJ_NAME . '_user_id';

if (
    !isset($_SESSION[$session_key]) ||
    !is_string($_SESSION[$session_key]) ||
    $_SESSION[$session_key] === ''
) {
    $respond(array(
        'ok' => false,
        'message' => '로그인이 필요합니다.'
    ), 401);
}

$user_id = $_SESSION[$session_key];

// 반복 조회가 다른 요청의 세션 처리를 막지 않도록 잠금 해제
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// 시험 실행 번호 검증
$value = $_GET['solution_id'] ?? null;

if (
    !is_string($value) ||
    !preg_match('/\A[1-9][0-9]{0,9}\z/', $value) ||
    (int)$value > 2147483647
) {
    $respond(array(
        'ok' => false,
        'message' => '시험 실행 번호가 올바르지 않습니다.'
    ), 400);
}

$solution_id = (int)$value;

// 본인의 시험 실행만 조회하고 출력 크기를 제한
$rows = pdo_query(
    "SELECT
        s.solution_id, s.result, s.time, s.memory,
        t.solution_id AS test_result_id,
        t.execution_result,
        LEFT(t.stdout, 65536) AS stdout,
        LEFT(t.stderr, 65536) AS stderr,
        t.stdout_truncated, t.stderr_truncated,
        OCTET_LENGTH(t.stdout) > 65536 AS stdout_capped,
        OCTET_LENGTH(t.stderr) > 65536 AS stderr_capped,
        c.solution_id AS compile_result_id,
        LEFT(c.error, 65536) AS compile_error,
        OCTET_LENGTH(c.error) > 65536 AS compile_error_truncated
     FROM solution s
     LEFT JOIN test_run_result t
        ON t.solution_id = s.solution_id
     LEFT JOIN compileinfo c
        ON c.solution_id = s.solution_id AND s.result = 11
     WHERE s.solution_id = ?
       AND s.user_id = ?
       AND s.problem_id = 0
     LIMIT 1",
    $solution_id,
    $user_id
);

if ($rows === false) {
    $respond(array(
        'ok' => false,
        'message' => '시험 실행 결과 조회에 실패했습니다.'
    ), 500);
}

if (!isset($rows[0])) {
    $respond(array(
        'ok' => false,
        'message' => '조회할 시험 실행을 찾을 수 없습니다.'
    ), 404);
}

$row = $rows[0];
$result = (int)$row['result'];

$completed = in_array(
    $result,
    array(4, 5, 6, 7, 8, 9, 10, 11, 13),
    true
);

$run_available =
    $completed &&
    $result !== 11 &&
    isset($row['test_result_id']);

$compile_available =
    $result === 11 &&
    isset($row['compile_result_id']);

$execution_result = $run_available
    ? (int)$row['execution_result']
    : null;

$stdout = $run_available ? (string)$row['stdout'] : '';
$stderr = $run_available ? (string)$row['stderr'] : '';
$compile_error = $compile_available
    ? (string)$row['compile_error']
    : '';

$status_labels = array(
    0 => '실행 대기',
    1 => '재실행 대기',
    2 => '컴파일 중',
    3 => '실행 중',
    4 => '실행 완료',
    5 => '출력 형식 오류',
    6 => '출력 결과 불일치',
    7 => '시간 제한 초과',
    8 => '메모리 제한 초과',
    9 => '출력 제한 초과',
    10 => '실행 오류',
    11 => '컴파일 오류',
    12 => '컴파일 완료',
    13 => '시험 실행 완료',
    14 => '채점 확인 대기'
);

$effective_result = $execution_result !== null
    ? $execution_result
    : $result;

$details_available = $run_available || $compile_available;
$message = '';

if ($completed && !$details_available) {
    $message = '상세 결과가 저장되어 있지 않습니다.';
}

$respond(array(
    'ok' => true,
    'solution_id' => $solution_id,
    'completed' => $completed,
    'result' => $result,
    'execution_result' => $execution_result,
    'status' => $status_labels[$effective_result] ?? '상태 확인 중',
    'time_ms' => (int)$row['time'],
    'memory_kb' => (int)$row['memory'],
    'stdout' => $stdout,
    'stderr' => $stderr,
    'compile_error' => $compile_error,
    'stdout_truncated' => $run_available && (
        (int)$row['stdout_truncated'] !== 0 ||
        (int)$row['stdout_capped'] !== 0
    ),
    'stderr_truncated' => $run_available && (
        (int)$row['stderr_truncated'] !== 0 ||
        (int)$row['stderr_capped'] !== 0
    ),
    'compile_error_truncated' => $compile_available &&
        (int)$row['compile_error_truncated'] !== 0,
    'stdout_invalid_utf8' => preg_match('//u', $stdout) !== 1,
    'stderr_invalid_utf8' => preg_match('//u', $stderr) !== 1,
    'compile_error_invalid_utf8' =>
        preg_match('//u', $compile_error) !== 1,
    'details_available' => $details_available,
    'message' => $message
));
