<?php

function simulation_problem_csrf_key()
{
    global $OJ_NAME;

    if (!is_string($OJ_NAME) || $OJ_NAME === '') {
        throw new RuntimeException('사이트 설정을 확인하지 못했습니다.');
    }

    return $OJ_NAME . '_simulation_problem_form_tokens';
}


function simulation_problem_csrf_user()
{
    global $OJ_NAME;

    $user = $_SESSION[$OJ_NAME.'_user_id'] ?? null;

    if (!is_string($user) || trim($user) === '') {
        throw new RuntimeException('로그인 사용자를 확인하지 못했습니다.');
    }

    return $user;
}


function simulation_problem_csrf_context($context)
{
    if (
        !is_string($context) ||
        $context === '' ||
        strlen($context) > 128 ||
        strpos($context, "\0") !== false
    ) {
        throw new InvalidArgumentException('편집 대상을 확인하지 못했습니다.');
    }

    return $context;
}


function simulation_problem_csrf_issue($context)
{
    $context = simulation_problem_csrf_context($context);
    $key = simulation_problem_csrf_key();
    $user = simulation_problem_csrf_user();
    $now = time();

    $tokens = $_SESSION[$key] ?? array();

    if (!is_array($tokens)) {
        $tokens = array();
    }

    foreach ($tokens as $hash => $record) {
        if (
            !is_array($record) ||
            !is_int($record['issued_at'] ?? null) ||
            $record['issued_at'] > $now ||
            $now - $record['issued_at'] > 1800 ||
            ($record['user_id'] ?? null) !== $user
        ) {
            unset($tokens[$hash]);
        }
    }

    while (count($tokens) >= 10) {
        array_shift($tokens);
    }

    $token = bin2hex(random_bytes(32));
    $tokens[hash('sha256', $token)] = array(
        'user_id' => $user,
        'context' => $context,
        'issued_at' => $now,
    );

    $_SESSION[$key] = $tokens;

    return $token;
}


// 검증에 성공한 토큰은 즉시 소모한다.
// 다른 문제나 다른 기준 버전에 발급한 토큰은 사용할 수 없다.
function simulation_problem_csrf_consume($token, $context)
{
    $context = simulation_problem_csrf_context($context);
    $key = simulation_problem_csrf_key();
    $user = simulation_problem_csrf_user();

    if (
        !is_string($token) ||
        preg_match('/^[0-9a-f]{64}$/D', $token) !== 1
    ) {
        return false;
    }

    $tokens = $_SESSION[$key] ?? null;
    if (!is_array($tokens)) {
        return false;
    }

    $hash = hash('sha256', $token);
    $record = $tokens[$hash] ?? null;
    $now = time();

    if (
        !is_array($record) ||
        !is_int($record['issued_at'] ?? null) ||
        $record['issued_at'] > $now ||
        $now - $record['issued_at'] > 1800 ||
        ($record['user_id'] ?? null) !== $user ||
        ($record['context'] ?? null) !== $context
    ) {
        return false;
    }

    unset($_SESSION[$key][$hash]);
    return true;
}
