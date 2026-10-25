<?php

require_once(__DIR__ . '/simulation_problem_submission_input.php');
require_once(__DIR__ . '/simulation_execution_config.php');
require_once(__DIR__ . '/simulation_problem_grade_result.php');

class SimulationProblemGradeClaimException extends RuntimeException
{
}

function simulation_problem_grade_job_statement(
    PDO $connection,
    $sql,
    array $arguments
) {
    $statement = $connection->prepare($sql);

    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('채점 작업 기록 처리에 실패했습니다.');
    }

    return $statement;
}

// 확보와 완료 저장은 각각 짧은 자체 트랜잭션으로 수행한다.
// 컨테이너 실행 중에는 DB 트랜잭션을 유지하지 않는다.
function simulation_problem_grade_job_claim(PDO $connection, $grade_id)
{
    $grade_id = simulation_problem_submission_id($grade_id);

    if ($connection->inTransaction()) {
        throw new RuntimeException('채점 작업 확보는 별도 트랜잭션으로 호출해 주세요.');
    }

    if (!$connection->beginTransaction()) {
        throw new RuntimeException('채점 작업 트랜잭션을 시작하지 못했습니다.');
    }

    try {
        $statement = simulation_problem_grade_job_statement(
            $connection,
            'SELECT
                CAST(g.grade_id AS CHAR) AS grade_id,
                CAST(g.submission_id AS CHAR) AS submission_id,
                g.grading_status, g.request_metadata,
                CAST(s.version_id AS CHAR) AS version_id,
                s.source_code
             FROM simulation_problem_grade g
             INNER JOIN simulation_problem_submission s
               ON s.submission_id = g.submission_id
             WHERE g.grade_id = ?
             FOR UPDATE',
            array($grade_id)
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (!$row || $row['grading_status'] !== 'pending') {
            if (!$connection->commit()) {
                throw new RuntimeException('채점 작업 확인을 완료하지 못했습니다.');
            }
            return null;
        }

        $token = bin2hex(random_bytes(16));

        $statement = simulation_problem_grade_job_statement(
            $connection,
            "UPDATE simulation_problem_grade
             SET grading_status = 'running',
                 claim_token = ?, started_at = NOW(),
                 graded_at = NULL, score = NULL, max_score = NULL,
                 feedback_json = NULL, error_message = NULL
             WHERE grade_id = ? AND grading_status = 'pending'",
            array($token, $grade_id)
        );
        $changed = $statement->rowCount();
        $statement->closeCursor();

        if ($changed !== 1) {
            throw new SimulationProblemGradeClaimException(
                '채점 작업을 확보하지 못했습니다.'
            );
        }

        if (!$connection->commit()) {
            throw new RuntimeException('채점 작업 확보를 저장하지 못했습니다.');
        }

        $row['claim_token'] = $token;
        return $row;

    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

// 호출자는 컨테이너 실행 전 정답 이미지와 요청 스냅샷도 검사해야 한다.
function simulation_problem_grade_job_result(array $claim, array $result)
{
    if (
        ($result['schema_version'] ?? null) !== 1 ||
        !in_array($result['status'] ?? null, array('ok', 'error'), true)
    ) {
        throw new RuntimeException('채점 실행 결과 형식이 올바르지 않습니다.');
    }

    if ($result['status'] === 'error') {
        $messages = array(
            'syntax_error' => '코드 문법을 확인해 주세요.',
            'runtime_error' => '코드 실행 중 오류가 발생했습니다.',
            'render_error' => '코드 실행 또는 그림 생성에 실패했습니다.',
            'timeout' => '코드 실행 제한 시간을 초과했습니다.',
            'memory_limit' => '코드 실행 메모리 제한을 초과했습니다.',
            'invalid_request' => '채점 실행 요청이 올바르지 않습니다.',
            'execution_error' => '코드를 실행하지 못했습니다.',
            'grading_error' => '제출 코드 채점에 실패했습니다.',
            'comparison_error' => '그림 비교에 실패했습니다.',
        );

        $error_code = $result['code'] ?? null;

        if (!is_string($error_code) || !isset($messages[$error_code])) {
            throw new RuntimeException('지원하지 않는 채점 오류 코드입니다.');
        }

        return array(
            'status' => 'error',
            'score' => null,
            'max_score' => null,
            'error_message' => $messages[$error_code],
            'feedback' => array(
                'schema_version' => 1,
                'error' => array(
                    'code' => $error_code,
                    'message' => $messages[$error_code],
                ),
            ),
        );
    }

    $request = json_decode(
        $claim['request_metadata'],
        true,
        64,
        JSON_THROW_ON_ERROR
    );
    $reference = $request['reference_request'] ?? null;
    $settings = is_array($reference) ? ($reference['settings'] ?? null) : null;

    if (
        !is_array($request) ||
        ($request['schema_version'] ?? null) !== 1 ||
        ($request['submission_id'] ?? null) !== $claim['submission_id'] ||
        !is_array($reference) ||
        ($reference['version_id'] ?? null) !== $claim['version_id'] ||
        !is_array($settings) ||
        !is_array($settings['grading_config'] ?? null)
    ) {
        throw new RuntimeException('저장된 채점 요청 정보가 올바르지 않습니다.');
    }

    $execution = simulation_validate_execution_metadata($settings, true);
    $source_hash = hash('sha256', $claim['source_code']);

    if (($request['student_source_sha256'] ?? null) !== $source_hash) {
        throw new RuntimeException('제출 코드와 채점 요청 해시가 다릅니다.');
    }

    $expected = array(
        'execution_engine' => $execution['execution_engine'],
        'execution_version' => $execution['execution_version'],
        'source_sha256' => $source_hash,
        'frame' => 0,
        'width' => 400,
        'height' => 400,
        'scoring_mode' => $settings['grading_config']['scoring_mode'] ?? null,
        'reference_png_sha256' => $request['target_png_sha256'] ?? null,
    );

    $artifact_key = $execution['execution_engine'] === 'p5js'
        ? 'p5_file_sha256'
        : 'runtime_sha256';

    foreach (array(
        $artifact_key => $settings[$artifact_key] ?? null,
        'reference_png_sha256' => $expected['reference_png_sha256'],
        'student_png_sha256' => $result['student_png_sha256'] ?? null,
    ) as $key => $hash) {
        if (
            !is_string($hash) ||
            preg_match('/^[0-9a-f]{64}$/D', $hash) !== 1
        ) {
            throw new RuntimeException('채점 결과의 파일 해시가 올바르지 않습니다.');
        }
        $expected[$key] = $hash;
    }

    foreach ($expected as $key => $value) {
        if (($result[$key] ?? null) !== $value) {
            throw new RuntimeException('채점 결과와 요청 정보가 다릅니다: ' . $key);
        }
    }

    $elapsed = $result['elapsed_seconds'] ?? null;
    if (
        (!is_int($elapsed) && !is_float($elapsed)) ||
        !is_finite((float)$elapsed) ||
        $elapsed < 0
    ) {
        throw new RuntimeException('채점 실행 시간이 올바르지 않습니다.');
    }

    $validated = simulation_problem_validate_grade_metrics(
        $result['metrics'] ?? null,
        $settings['grading_config'],
        $settings['max_score'] ?? null
    );

    return array(
        'status' => 'graded',
        'score' => $validated['score'],
        'max_score' => $validated['max_score'],
        'error_message' => null,
        'feedback' => array(
            'schema_version' => 1,
            'runtime' => array_merge($expected, array(
                'elapsed_seconds' => (float)$elapsed,
            )),
            'result' => array('metrics' => $validated['metrics']),
            'scoring' => $validated['scoring'],
        ),
    );
}

function simulation_problem_grade_job_finish(
    PDO $connection,
    array $claim,
    array $result
) {
    $grade_id = simulation_problem_submission_id($claim['grade_id'] ?? null);
    $token = $claim['claim_token'] ?? null;

    if (
        !is_string($token) ||
        preg_match('/^[0-9a-f]{32}$/D', $token) !== 1
    ) {
        throw new SimulationProblemGradeClaimException('채점 작업 토큰이 올바르지 않습니다.');
    }

    $validated = simulation_problem_grade_job_result($claim, $result);
    $feedback_json = json_encode(
        $validated['feedback'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    if ($connection->inTransaction()) {
        throw new RuntimeException('채점 완료 저장은 별도 트랜잭션으로 호출해 주세요.');
    }

    if (!$connection->beginTransaction()) {
        throw new RuntimeException('채점 완료 저장을 시작하지 못했습니다.');
    }

    try {
        $statement = simulation_problem_grade_job_statement(
            $connection,
            'SELECT
                g.grading_status, g.claim_token, g.request_metadata,
                CAST(g.submission_id AS CHAR) AS submission_id,
                CAST(s.version_id AS CHAR) AS version_id,
                s.source_code
             FROM simulation_problem_grade g
             INNER JOIN simulation_problem_submission s
               ON s.submission_id = g.submission_id
             WHERE g.grade_id = ?
             FOR UPDATE',
            array($grade_id)
        );
        $current = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (
            !$current ||
            $current['grading_status'] !== 'running' ||
            $current['claim_token'] !== $token ||
            $current['request_metadata'] !== $claim['request_metadata'] ||
            $current['submission_id'] !== $claim['submission_id'] ||
            $current['version_id'] !== $claim['version_id'] ||
            $current['source_code'] !== $claim['source_code']
        ) {
            throw new SimulationProblemGradeClaimException(
                '채점 작업 상태 또는 원본 정보가 변경되어 결과를 저장하지 않았습니다.'
            );
        }

        $statement = simulation_problem_grade_job_statement(
            $connection,
            'UPDATE simulation_problem_grade
             SET grading_status = ?, score = ?, max_score = ?,
                 feedback_json = ?, error_message = ?,
                 graded_at = NOW(), claim_token = NULL
             WHERE grade_id = ?
               AND grading_status = ?
               AND claim_token = ?',
            array(
                $validated['status'],
                $validated['score'],
                $validated['max_score'],
                $feedback_json,
                $validated['error_message'],
                $grade_id,
                'running',
                $token,
            )
        );
        $changed = $statement->rowCount();
        $statement->closeCursor();

        if ($changed !== 1) {
            throw new SimulationProblemGradeClaimException('채점 결과 저장 상태가 변경됐습니다.');
        }

        if (!$connection->commit()) {
            throw new RuntimeException('채점 결과 저장을 완료하지 못했습니다.');
        }

        return array(
            'grade_id' => $grade_id,
            'grading_status' => $validated['status'],
            'score' => $validated['score'],
            'max_score' => $validated['max_score'],
        );

    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}
