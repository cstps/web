<?php

require_once(__DIR__ . '/simulation_problem_reader.php');
require_once(__DIR__ . '/simulation_problem_submission_token.php');
require_once(__DIR__ . '/simulation_problem_grade_request.php');

class SimulationProblemSubmissionConflictException extends RuntimeException
{
}

function simulation_problem_submission_statement(
    PDO $connection,
    $sql,
    array $arguments
) {
    $statement = $connection->prepare($sql);

    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('제출 기록 처리에 실패했습니다.');
    }

    return $statement;
}

// 자체 트랜잭션으로 제출과 첫 채점 요청을 함께 저장한다.
// 사용자와 권한은 세션 및 서버 DB에서 결정한다.
// 코드 실행과 채점 처리기 알림은 이 함수에서 수행하지 않는다.
function simulation_problem_store_submission(
    PDO $connection,
    array $input
) {
    global $OJ_NAME;

    if ($connection->inTransaction()) {
        throw new RuntimeException('제출 저장은 별도 트랜잭션으로 호출해 주세요.');
    }

    $request = simulation_problem_submission_input($input);
    $actor = simulation_problem_current_actor($connection);
    $user_id = simulation_problem_submission_token_user(
        $actor['user_id'] ?? null
    );

    if (!simulation_problem_submission_token_valid(
        $request['submission_token'],
        $user_id,
        $request['problem_id'],
        $request['version_id'],
        $request['performance_context']
    )) {
        throw new UnexpectedValueException(
            '제출 요청 토큰이 만료되었거나 올바르지 않습니다. 코드를 보관한 뒤 화면을 다시 열어 주세요.'
        );
    }

    if (!$connection->beginTransaction()) {
        throw new RuntimeException('제출 저장 트랜잭션을 시작하지 못했습니다.');
    }

    try {
        // 문제 수정과 제출 번호 부여를 같은 부모 행 잠금으로 직렬화한다.
        $statement = simulation_problem_submission_statement(
            $connection,
            'SELECT problem_id
             FROM simulation_problem
             WHERE problem_id = ?
             FOR UPDATE',
            array($request['problem_id'])
        );
        $parent = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (!$parent) {
            throw new SimulationProblemReaderNotFoundException(
                '문제를 찾을 수 없거나 공개되지 않은 문제입니다.'
            );
        }

        // 잠금을 얻은 뒤 현재 권한과 최신 버전을 다시 조회한다.
        $actor = simulation_problem_current_actor($connection);

        if (($actor['user_id'] ?? null) !== $user_id) {
            throw new UnexpectedValueException('로그인 계정이 변경되었습니다.');
        }

        $problem = simulation_problem_reader_latest(
            $connection,
            $actor,
            $request['problem_id']
        );

        if ($problem['version_id'] !== $request['version_id']) {
            throw new SimulationProblemSubmissionConflictException(
                '문제 버전이 변경되었습니다. 현재 코드를 보관한 뒤 문제 화면을 다시 열어 주세요.'
            );
        }

        if (
            !in_array($problem['status'], array(1, '1'), true) ||
            !in_array($problem['allow_submit'], array(1, '1'), true)
        ) {
            throw new UnexpectedValueException('현재 제출할 수 없는 문제입니다.');
        }

        if (!in_array($problem['allow_edit'], array(0, 1, '0', '1'), true)) {
            throw new RuntimeException('학생 코드 편집 설정이 올바르지 않습니다.');
        }

        if (
            (string)$problem['allow_edit'] === '0' &&
            $request['source_code'] !== $problem['starter_code']
        ) {
            throw new UnexpectedValueException(
                '코드 편집이 허용되지 않은 문제입니다.'
            );
        }

        $execution = simulation_validate_execution_metadata($problem, true);

        if (
            $execution['execution_engine'] === 'python-stdlib-rgba-poc' &&
            (string)$problem['published'] !== '0'
        ) {
            throw new UnexpectedValueException(
                'Python 실험 문제는 비공개 상태에서만 제출할 수 있습니다.'
            );
        }

        simulation_checked_performance_context(
            $user_id,
            $request['performance_context'],
            isset($_SESSION[$OJ_NAME . '_source_browser'])
        );

        if (!in_array(
            $problem['simulation_mode'],
            array('free', 'drawing'),
            true
        )) {
            throw new RuntimeException('지원하지 않는 문제 유형입니다.');
        }

        // 이미 저장된 요청은 코드를 변경하지 않고 기존 제출을 반환한다.
        $statement = simulation_problem_submission_statement(
            $connection,
            'SELECT
                CAST(submission_id AS CHAR) AS submission_id,
                CAST(version_id AS CHAR) AS version_id,
                submission_no, performance_context, source_code
             FROM simulation_problem_submission
             WHERE user_id = ? AND request_key = ?
             FOR UPDATE',
            array($user_id, $request['request_key'])
        );
        $existing = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if ($existing) {
            if (
                $existing['version_id'] !== $request['version_id'] ||
                $existing['performance_context'] !==
                    $request['performance_context'] ||
                $existing['source_code'] !== $request['source_code']
            ) {
                throw new SimulationProblemSubmissionConflictException(
                    '이미 사용한 제출 요청입니다. 코드를 보관한 뒤 문제 화면을 다시 열어 새로 제출해 주세요.'
                );
            }

            $statement = simulation_problem_submission_statement(
                $connection,
                'SELECT CAST(grade_id AS CHAR) AS grade_id
                 FROM simulation_problem_grade
                 WHERE submission_id = ? AND grading_no = 1',
                array($existing['submission_id'])
            );
            $grade = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            if ($problem['simulation_mode'] === 'drawing' && !$grade) {
                throw new RuntimeException('기존 제출의 채점 요청을 확인하지 못했습니다.');
            }

            if (!$connection->commit()) {
                throw new RuntimeException('기존 제출 확인을 완료하지 못했습니다.');
            }

            return array(
                'submission_id' => $existing['submission_id'],
                'submission_no' => (int)$existing['submission_no'],
                'grade_id' => $grade ? $grade['grade_id'] : null,
                'reused' => true,
            );
        }

        $grade_request = null;

        if ($problem['simulation_mode'] === 'drawing') {
            $statement = simulation_problem_submission_statement(
                $connection,
                'SELECT
                    CAST(v.version_id AS CHAR) AS version_id,
                    CAST(v.problem_id AS CHAR) AS problem_id,
                    v.version_no, v.simulation_mode,
                    v.canvas_width, v.canvas_height, v.p5_version,
                    v.source_language, v.execution_engine, v.execution_version,
                    r.source_code AS reference_source,
                    r.render_config, r.grading_config, r.max_score,
                    i.image_status, i.source_sha256, i.settings_sha256,
                    i.request_metadata, i.render_metadata,
                    i.png_sha256, i.generated_at,
                    OCTET_LENGTH(i.png_data) AS png_bytes,
                    i.png_data
                 FROM simulation_problem_version v
                 LEFT JOIN simulation_problem_reference r
                   ON r.version_id = v.version_id
                 LEFT JOIN simulation_problem_reference_image i
                   ON i.version_id = v.version_id
                 WHERE v.version_id = ? AND v.problem_id = ?
                 FOR UPDATE',
                array($request['version_id'], $request['problem_id'])
            );
            $reference = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            if (!$reference) {
                throw new RuntimeException('제출 대상 정답 버전을 찾지 못했습니다.');
            }

            $grade_request = simulation_problem_grade_request_metadata(
                $reference,
                $request['source_code']
            );
        }

        $statement = simulation_problem_submission_statement(
            $connection,
            'SELECT submission_no
             FROM simulation_problem_submission
             WHERE version_id = ? AND user_id = ?
             ORDER BY submission_no DESC
             LIMIT 1
             FOR UPDATE',
            array($request['version_id'], $user_id)
        );
        $last = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $last_no = $last ? (int)$last['submission_no'] : 0;

        if ($last_no < 0 || $last_no >= 2147483647) {
            throw new RuntimeException('제출 횟수가 허용 범위를 초과했습니다.');
        }

        $submission_no = $last_no + 1;

        $statement = simulation_problem_submission_statement(
            $connection,
            'INSERT INTO simulation_problem_submission
                (version_id, user_id, submission_no,
                 performance_context, request_key, source_code)
             VALUES (?, ?, ?, ?, ?, ?)',
            array(
                $request['version_id'],
                $user_id,
                $submission_no,
                $request['performance_context'],
                $request['request_key'],
                $request['source_code'],
            )
        );
        $statement->closeCursor();

        $submission_id = simulation_problem_submission_id(
            (string)$connection->lastInsertId()
        );
        $grade_id = null;

        if ($grade_request !== null) {
            $grade_request['submission_id'] = $submission_id;

            $statement = simulation_problem_submission_statement(
                $connection,
                "INSERT INTO simulation_problem_grade
                    (submission_id, grading_no, grading_status, request_metadata)
                 VALUES (?, 1, 'pending', ?)",
                array(
                    $submission_id,
                    simulation_problem_image_canonical_json(
                        (object)$grade_request
                    ),
                )
            );
            $statement->closeCursor();

            $grade_id = simulation_problem_submission_id(
                (string)$connection->lastInsertId()
            );
        }

        if (!$connection->commit()) {
            throw new RuntimeException('제출 기록 저장을 완료하지 못했습니다.');
        }

        return array(
            'submission_id' => $submission_id,
            'submission_no' => $submission_no,
            'grade_id' => $grade_id,
            'reused' => false,
        );

    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}
