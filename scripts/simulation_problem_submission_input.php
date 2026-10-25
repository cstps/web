<?php

// 입력 형식만 검사한다.
// 로그인 권한, 토큰 발급 여부, 현재 문제 버전과 수행 기준은
// 저장 처리에서 서버 정보를 기준으로 다시 확인해야 한다.

function simulation_problem_submission_id($value)
{
    if (
        !is_string($value) ||
        !preg_match('/^[1-9][0-9]{0,18}$/D', $value) ||
        (
            strlen($value) === 19 &&
            strcmp($value, '9223372036854775807') > 0
        )
    ) {
        throw new InvalidArgumentException('문제 또는 버전 번호가 올바르지 않습니다.');
    }

    return $value;
}

function simulation_problem_submission_input(array $input)
{
    $problem_id = simulation_problem_submission_id(
        $input['problem_id'] ?? null
    );
    $version_id = simulation_problem_submission_id(
        $input['version_id'] ?? null
    );

    $source = $input['source_code'] ?? null;

    if (
        !is_string($source) ||
        strlen($source) > 200000 ||
        strpos($source, "\0") !== false ||
        preg_match('//u', $source) !== 1 ||
        preg_match('/\S/u', $source) !== 1
    ) {
        throw new InvalidArgumentException(
            '제출 코드는 비어 있지 않은 UTF-8 문자열이며 200,000바이트 이하여야 합니다.'
        );
    }

    $token = $input['submission_token'] ?? null;

    if (
        !is_string($token) ||
        !preg_match('/^[0-9a-f]{64}$/D', $token)
    ) {
        throw new InvalidArgumentException('제출 요청 토큰이 올바르지 않습니다.');
    }

    $context = $input['performance_context'] ?? null;

    if (!is_string($context)) {
        throw new InvalidArgumentException('수행모드 기준을 확인하지 못했습니다.');
    }

    if ($context !== '') {
        if (!preg_match(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',
            $context
        )) {
            throw new InvalidArgumentException('수행모드 기준 형식이 올바르지 않습니다.');
        }

        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $context
        );

        if (
            !$parsed ||
            $parsed->format('Y-m-d H:i:s') !== $context ||
            substr($context, 0, 4) === '0000'
        ) {
            throw new InvalidArgumentException('수행모드 기준 날짜가 올바르지 않습니다.');
        }
    }

    return array(
        'problem_id' => $problem_id,
        'version_id' => $version_id,
        'source_code' => $source,
        'submission_token' => $token,
        'request_key' => hash('sha256', $token),
        'performance_context' => $context,
    );
}
