<?php

require_once(__DIR__ . '/course_simulation_problem_import_context.php');
require_once(__DIR__ . '/simulation_problem_csrf.php');

// 요청에서는 대상 번호와 보안 토큰만 받는다.
// 문제 내용·실행 설정·정답·배점·생성자는 서버에서 결정한다.
function course_simulation_store_problem_import(PDO $connection, array $input)
{
    if ($connection->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    $required = array('course_id', 'lesson_id', 'version_id', 'import_token');

    if (
        count($input) !== count($required) ||
        array_diff(array_keys($input), $required)
    ) {
        throw new InvalidArgumentException(
            '가져오기 요청 항목이 올바르지 않습니다.'
        );
    }

    $course_id = course_simulation_problem_import_id(
        $input['course_id'], true
    );
    $lesson_id = course_simulation_problem_import_id(
        $input['lesson_id'], true
    );
    $version_id = course_simulation_problem_import_id(
        $input['version_id']
    );

    $token_context = course_simulation_problem_import_token_context(
        $course_id, $lesson_id, $version_id
    );

    // 같은 확인 화면의 중복 요청은 저장을 시작하기 전에 차단한다.
    if (!simulation_problem_csrf_consume(
        $input['import_token'], $token_context
    )) {
        throw new UnexpectedValueException(
            '가져오기 보안 토큰이 없거나 만료됐습니다. 확인 화면을 다시 열어 주세요.'
        );
    }

    $transaction_started = false;

    try {
        if (!$connection->beginTransaction()) {
            throw new RuntimeException(
                '가져오기 저장 트랜잭션을 시작하지 못했습니다.'
            );
        }
        $transaction_started = true;

        $context = course_simulation_problem_import_context(
            $connection, $course_id, $lesson_id, true
        );
        $source = course_simulation_problem_import_source(
            $connection, $context['actor'], $version_id, true
        );

        $content = $source['content'];
        $creator = $context['actor']['user_id'];

        // 차시 행을 잠근 상태에서 새 활동의 마지막 순서를 정한다.
        $statement = $connection->prepare(
            'SELECT COALESCE(MAX(sort_order), 0)
             FROM course_activity
             WHERE course_id = ? AND lesson_id = ?'
        );

        if (!$statement || !$statement->execute(array(
            $course_id, $lesson_id
        ))) {
            throw new RuntimeException(
                '학습 활동 순서 조회에 실패했습니다.'
            );
        }

        $maximum_order = $statement->fetchColumn();
        $statement->closeCursor();

        if (
            !is_scalar($maximum_order) ||
            preg_match('/^[0-9]{1,10}$/D', (string)$maximum_order) !== 1 ||
            (int)$maximum_order >= 4294967295
        ) {
            throw new RuntimeException(
                '학습 활동 순서의 허용 범위를 초과했습니다.'
            );
        }

        $sort_order = (int)$maximum_order + 1;

        $statement = $connection->prepare(
            "INSERT INTO course_activity
                (course_id, lesson_id, activity_type, title, description,
                 sort_order, visible, status, created_by)
             VALUES (?, ?, 'simulation', ?, ?, ?, 0, 1, ?)"
        );

        if (!$statement || !$statement->execute(array(
            $course_id,
            $lesson_id,
            $content['title'],
            $content['description'],
            $sort_order,
            $creator,
        ))) {
            throw new RuntimeException(
                '가져온 학습 활동 저장에 실패했습니다.'
            );
        }

        $statement->closeCursor();
        $activity_id = course_simulation_problem_import_id(
            $connection->lastInsertId(), true
        );

        $statement = $connection->prepare(
            'INSERT INTO course_simulation
                (activity_id, simulation_mode, starter_code,
                 canvas_width, canvas_height, allow_edit, allow_submit,
                 grading_config, source_language, execution_engine,
                 execution_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)'
        );

        if (!$statement || !$statement->execute(array(
            $activity_id,
            $content['simulation_mode'],
            $content['starter_code'],
            $content['canvas_width'],
            $content['canvas_height'],
            $content['allow_edit'],
            $content['allow_submit'],
            $content['source_language'],
            $content['execution_engine'],
            $content['execution_version'],
        ))) {
            throw new RuntimeException(
                '가져온 실행 설정 저장에 실패했습니다.'
            );
        }

        $statement->closeCursor();
        $reference_id = null;

        if ($content['simulation_mode'] === 'drawing') {
            $reference = $source['reference'];

            if (
                !is_array($reference) ||
                ($source['target']['state'] ?? null) !== 'ready'
            ) {
                throw new RuntimeException(
                    '검사 완료된 정답을 확인하지 못했습니다.'
                );
            }

            $statement = $connection->prepare(
                'INSERT INTO course_simulation_reference
                    (activity_id, reference_no, source_code,
                     canvas_width, canvas_height, p5_version,
                     source_language, execution_engine, execution_version,
                     render_config, grading_config, max_score, created_by)
                 VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$statement || !$statement->execute(array(
                $activity_id,
                $reference['source_code'],
                $content['canvas_width'],
                $content['canvas_height'],
                $source['p5_version'],
                $content['source_language'],
                $content['execution_engine'],
                $content['execution_version'],
                $reference['render_config'],
                $reference['grading_config'],
                $reference['max_score'],
                $creator,
            ))) {
                throw new RuntimeException(
                    '가져온 정답 버전 저장에 실패했습니다.'
                );
            }

            $statement->closeCursor();
            $reference_id = course_simulation_problem_import_id(
                $connection->lastInsertId()
            );

            // 수업 정답 번호에 맞는 이미지와 검사 기록을 새로 생성한다.
            $statement = $connection->prepare(
                "INSERT INTO course_simulation_reference_image
                    (reference_id, source_sha256, image_status)
                 VALUES (?, ?, 'pending')"
            );

            if (!$statement || !$statement->execute(array(
                $reference_id,
                hash('sha256', $reference['source_code']),
            ))) {
                throw new RuntimeException(
                    '수업 목표 이미지 생성 요청 저장에 실패했습니다.'
                );
            }

            $statement->closeCursor();
        }

        $statement = $connection->prepare(
            'INSERT INTO course_simulation_problem_import
                (activity_id, version_id, imported_by)
             VALUES (?, ?, ?)'
        );

        if (!$statement || !$statement->execute(array(
            $activity_id, $version_id, $creator,
        ))) {
            throw new RuntimeException(
                '원본 문제 버전 연결 저장에 실패했습니다.'
            );
        }

        $statement->closeCursor();

        if (!$connection->commit()) {
            throw new RuntimeException(
                '가져오기 저장을 완료하지 못했습니다.'
            );
        }
        $transaction_started = false;

        // 코드와 정답·배점 원문은 응답용 결과에 포함하지 않는다.
        return array(
            'course_id' => (int)$course_id,
            'lesson_id' => (int)$lesson_id,
            'activity_id' => $activity_id,
            'problem_id' => $source['problem_id'],
            'version_id' => $version_id,
            'version_no' => $source['version_no'],
            'reference_id' => $reference_id,
            'image_queued' => $reference_id !== null,
        );

    } catch (Throwable $error) {
        if ($transaction_started && $connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}
