<?php

require_once(__DIR__ . '/simulation_problem_draft_store.php');

// 저장 요청에 반복 사용할 수 있는 페이지별 토큰이다.
// 사용자·문제 버전·수행 기준에 묶고 두 시간 후 만료한다.
function simulation_problem_draft_token_records()
{
    global $OJ_NAME;
    $key = $OJ_NAME . '_simulation_problem_draft_tokens';
    $records = $_SESSION[$key] ?? array();
    $valid = array();
    $now = time();

    if (is_array($records)) {
        foreach ($records as $hash => $record) {
            if (
                is_string($hash) &&
                preg_match('/^[0-9a-f]{64}$/D', $hash) === 1 &&
                is_array($record) &&
                is_int($record['issued_at'] ?? null) &&
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

function simulation_problem_draft_token_issue($user_id, $version_id, $cutoff)
{
    global $OJ_NAME;
    $user_id = simulation_problem_draft_user($user_id);
    $version_id = simulation_problem_reader_positive_id($version_id);
    $scope = simulation_problem_draft_scope($cutoff);
    $records = simulation_problem_draft_token_records();

    while (count($records) >= 20) {
        array_shift($records);
    }

    $token = bin2hex(random_bytes(32));
    $records[hash('sha256', $token)] = array(
        'user_id' => $user_id,
        'version_id' => $version_id,
        'scope' => $scope,
        'issued_at' => time(),
    );
    $_SESSION[$OJ_NAME . '_simulation_problem_draft_tokens'] = $records;
    return $token;
}

function simulation_problem_draft_token_valid(
    $token,
    $user_id,
    $version_id,
    $cutoff
) {
    if (
        !is_string($token) ||
        preg_match('/^[0-9a-f]{64}$/D', $token) !== 1
    ) {
        return false;
    }

    $scope = simulation_problem_draft_scope($cutoff);
    $records = simulation_problem_draft_token_records();
    $record = $records[hash('sha256', $token)] ?? null;

    return (
        is_array($record) &&
        ($record['user_id'] ?? null) === $user_id &&
        ($record['version_id'] ?? null) === $version_id &&
        ($record['scope'] ?? null) === $scope
    );
}
