<?php

require_once(__DIR__ . '/simulation_execution_config.php');
require_once(__DIR__ . '/simulation_problem_grade_result.php');

// request는 서버가 정답 버전을 고정하여 만든 채점 설정이다.
// 브라우저에서 전송한 설정을 직접 전달해서는 안 된다.
// 학생 코드 실행과 DB 변경은 이 함수에서 수행하지 않는다.
function course_simulation_validate_python_grade_result(
    array $request,
    $source_code,
    array $result
) {
    $execution = simulation_validate_execution_metadata($request, true);

    if (
        ($request['schema_version'] ?? null) !== 1 ||
        $execution['source_language'] !== 'python' ||
        $execution['execution_engine'] !== 'python-stdlib-rgba-poc' ||
        $execution['execution_version'] !== '1' ||
        ($request['frame'] ?? null) !== 0 ||
        ($request['canvas_width'] ?? null) !== 400 ||
        ($request['canvas_height'] ?? null) !== 400
    ) {
        throw new RuntimeException(
            '수업 Python 채점 요청의 실행 설정이 올바르지 않습니다.'
        );
    }

    if (
        !is_string($source_code) ||
        trim($source_code) === '' ||
        strlen($source_code) > 200000 ||
        strpos($source_code, "\0") !== false ||
        preg_match('//u', $source_code) !== 1
    ) {
        throw new RuntimeException(
            '채점 대상 제출 코드가 올바르지 않습니다.'
        );
    }

    foreach (array(
        'runtime_sha256',
        'target_png_sha256',
        'student_source_sha256'
    ) as $key) {
        if (
            !is_string($request[$key] ?? null) ||
            preg_match('/^[0-9a-f]{64}$/D', $request[$key]) !== 1
        ) {
            throw new RuntimeException(
                '채점 요청의 파일 해시가 올바르지 않습니다.'
            );
        }
    }

    $source_hash = hash('sha256', $source_code);

    if (!hash_equals($request['student_source_sha256'], $source_hash)) {
        throw new RuntimeException(
            '제출 코드와 저장된 채점 요청의 해시가 다릅니다.'
        );
    }

    $grading = $request['grading_config'] ?? null;
    $max_score = $request['max_score'] ?? null;

    if (
        !is_array($grading) ||
        !in_array(
            $grading['scoring_mode'] ?? null,
            array('metrics_only', 'whole_image', 'regions'),
            true
        ) ||
        !is_string($max_score) ||
        preg_match(
            '/^(?:0|[1-9][0-9]{0,4})\.[0-9]{2}$/D',
            $max_score
        ) !== 1 ||
        (float)$max_score <= 0
    ) {
        throw new RuntimeException(
            '수업 Python 채점 배점 설정이 올바르지 않습니다.'
        );
    }

    if (
        ($result['schema_version'] ?? null) !== 1 ||
        !in_array($result['status'] ?? null, array('ok', 'error'), true)
    ) {
        throw new RuntimeException(
            'Python 채점 결과 형식이 올바르지 않습니다.'
        );
    }

    if ($result['status'] === 'error') {
        $messages = array(
            'syntax_error' => 'Python 코드 문법을 확인해 주세요.',
            'runtime_error' => 'Python 코드 실행 중 오류가 발생했습니다.',
            'timeout' => '코드 실행 제한 시간을 초과했습니다.',
            'memory_limit' => '코드 실행 메모리 제한을 초과했습니다.',
            'invalid_request' => '채점 실행 요청이 올바르지 않습니다.',
            'execution_error' => '코드를 실행하지 못했습니다.',
            'grading_error' => '제출 코드 채점에 실패했습니다.',
            'comparison_error' => '그림 비교에 실패했습니다.',
        );

        $code = $result['code'] ?? null;

        if (!is_string($code) || !isset($messages[$code])) {
            throw new RuntimeException(
                '지원하지 않는 Python 채점 오류 코드입니다.'
            );
        }

        // 실행기가 반환한 상세 메시지나 경로는 저장하지 않는다.
        return array(
            'grading_status' => 'error',
            'score' => null,
            'max_score' => null,
            'feedback' => array(
                'schema_version' => 1,
                'error' => array(
                    'code' => $code,
                    'message' => $messages[$code],
                ),
            ),
        );
    }

    $expected = array(
        'execution_engine' => $execution['execution_engine'],
        'execution_version' => $execution['execution_version'],
        'runtime_sha256' => $request['runtime_sha256'],
        'source_sha256' => $source_hash,
        'frame' => 0,
        'width' => 400,
        'height' => 400,
        'scoring_mode' => $grading['scoring_mode'],
        'reference_png_sha256' => $request['target_png_sha256'],
    );

    foreach ($expected as $key => $value) {
        if (($result[$key] ?? null) !== $value) {
            throw new RuntimeException(
                'Python 채점 결과와 저장된 요청이 다릅니다: ' . $key
            );
        }
    }

    $student_png_hash = $result['student_png_sha256'] ?? null;

    if (
        !is_string($student_png_hash) ||
        preg_match('/^[0-9a-f]{64}$/D', $student_png_hash) !== 1
    ) {
        throw new RuntimeException(
            '제출 그림의 파일 해시가 올바르지 않습니다.'
        );
    }

    $elapsed = $result['elapsed_seconds'] ?? null;

    if (
        (!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) ||
        $elapsed < 0
    ) {
        throw new RuntimeException(
            'Python 채점 실행 시간이 올바르지 않습니다.'
        );
    }

    // 문제은행에서 검증한 동일 함수로 충족률과 점수를 재검산한다.
    $validated = simulation_problem_validate_grade_metrics(
        $result['metrics'] ?? null,
        $grading,
        $max_score
    );

    return array(
        'grading_status' => 'graded',
        'score' => $validated['score'],
        'max_score' => $validated['max_score'],
        'feedback' => array(
            'schema_version' => 1,
            'runtime' => array_merge(
                $expected,
                array(
                    'student_png_sha256' => $student_png_hash,
                    'elapsed_seconds' => (float)$elapsed,
                )
            ),
            'result' => array(
                'status' => 'ok',
                'metrics' => $validated['metrics'],
                'elapsed_seconds' => (float)$elapsed,
            ),
            'scoring' => $validated['scoring'],
        ),
    );
}
