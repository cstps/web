<?php

require_once(__DIR__ . '/simulation_problem_submission_input.php');
require_once(__DIR__ . '/course_simulation_performance_policy.php');

function simulation_problem_submission_token_user($user_id)
{
    if (
        !is_string($user_id) ||
        !preg_match('/^[^\x00-\x20\x7F]{1,48}$/uD', $user_id)
    ) {
        throw new InvalidArgumentException('학생 계정이 올바르지 않습니다.');
    }

    return $user_id;
}

function simulation_problem_submission_token_context($context)
{
    if (!is_string($context)) {
        throw new InvalidArgumentException('수행모드 기준이 올바르지 않습니다.');
    }

    simulation_assert_performance_context(
        $context,
        $context === '' ? null : $context
    );

    return $context;
}

function simulation_problem_submission_token_records()
{
    global $OJ_NAME;

    $key = $OJ_NAME . '_simulation_problem_submission_tokens';
    $records = $_SESSION[$key] ?? array();
    $valid = array();
    $now = time();

    if (is_array($records)) {
        foreach ($records as $hash => $record) {
            if (
                is_string($hash) &&
                preg_match('/^[0-9a-f]{64}$/D', $hash) &&
                is_array($record) &&
                isset($record['issued_at']) &&
                is_int($record['issued_at']) &&
                $record['issued_at'] <= $now &&
                $record['issued_at'] >= $now - 7200
            ) {
                $valid[$hash] = $record;
            }
        }
    }

    $_SESSION[$key] = $valid;
    return $valid;
}

// 호출자는 로그인 사용자와 서버에서 조회한 현재 수행 기준을 전달한다.
function simulation_problem_submission_token_issue(
    $user_id,
    $problem_id,
    $version_id,
    $context
) {
    global $OJ_NAME;

    $user_id = simulation_problem_submission_token_user($user_id);
    $problem_id = simulation_problem_submission_id($problem_id);
    $version_id = simulation_problem_submission_id($version_id);
    $context = simulation_problem_submission_token_context($context);

    $records = simulation_problem_submission_token_records();

    while (count($records) >= 20) {
        array_shift($records);
    }

    $token = bin2hex(random_bytes(32));
    $records[hash('sha256', $token)] = array(
        'user_id' => $user_id,
        'problem_id' => $problem_id,
        'version_id' => $version_id,
        'performance_context' => $context,
        'issued_at' => time(),
    );

    $_SESSION[$OJ_NAME . '_simulation_problem_submission_tokens'] = $records;
    return $token;
}

// 토큰은 여기서 소모하지 않는다.
// 저장 시 같은 요청 키의 기존 기록을 확인하여 재전송을 처리해야 한다.
// 현재 접근 권한과 실제 수행 기준도 저장 직전에 별도로 검사해야 한다.
function simulation_problem_submission_token_valid(
    $token,
    $user_id,
    $problem_id,
    $version_id,
    $context
) {
    if (
        !is_string($token) ||
        !preg_match('/^[0-9a-f]{64}$/D', $token)
    ) {
        return false;
    }

    $user_id = simulation_problem_submission_token_user($user_id);
    $problem_id = simulation_problem_submission_id($problem_id);
    $version_id = simulation_problem_submission_id($version_id);
    $context = simulation_problem_submission_token_context($context);

    $records = simulation_problem_submission_token_records();
    $record = $records[hash('sha256', $token)] ?? null;

    return is_array($record) &&
        ($record['user_id'] ?? null) === $user_id &&
        ($record['problem_id'] ?? null) === $problem_id &&
        ($record['version_id'] ?? null) === $version_id &&
        ($record['performance_context'] ?? null) === $context;
}
