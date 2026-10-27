<?php
require_once(__DIR__ . '/simulation_problem_store.php');

// 기본 정보만 변경하고 코드와 실행·채점 설정은 잠근 기존 버전에서 복사한다.
function simulation_store_problem_basic(
    PDO $connection,
    array $input,
    $id,
    $expected
) {
    if ($connection->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    if (
        !is_string($id) ||
        !preg_match('/^[1-9][0-9]{0,18}$/D', $id) ||
        (strlen($id) === 19 && strcmp($id, '9223372036854775807') > 0) ||
        !is_string($expected) ||
        !preg_match('/^[1-9][0-9]{0,9}$/D', $expected) ||
        (float)$expected > 2147483647
    ) {
        throw new InvalidArgumentException(
            '문제 번호 또는 기준 버전이 올바르지 않습니다.'
        );
    }

    $expected_published = simulation_problem_input_choice(
        $input, 'expected_published', array('0', '1')
    );
    $expected_sharing = simulation_problem_input_choice(
        $input, 'expected_sharing', array('private', 'shared')
    );

    $connection->beginTransaction();

    try {
        $actor = simulation_problem_current_actor($connection);

        $stmt = simulation_problem_store_statement(
            $connection,
            'SELECT problem_id, owner_user_id, sharing, published, status
             FROM simulation_problem
             WHERE problem_id = ?
             FOR UPDATE',
            array($id)
        );
        $problem = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (
            !$problem ||
            simulation_problem_permissions($actor, $problem)['edit'] !== true
        ) {
            throw new UnexpectedValueException(
                '기본 정보를 변경할 권한이 없습니다.'
            );
        }

        $stmt = simulation_problem_store_statement(
            $connection,
            'SELECT *
             FROM simulation_problem_version
             WHERE problem_id = ?
             ORDER BY version_no DESC
             LIMIT 1
             FOR UPDATE',
            array($id)
        );
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$version || (string)$version['version_no'] !== $expected ||
            (string)$problem['published'] !== $expected_published ||
            $problem['sharing'] !== $expected_sharing) {
            throw new SimulationProblemVersionConflict(
                '다른 창에서 새 버전이 저장됐습니다. 입력 내용을 보관한 뒤 다시 열어 주세요.'
            );
        }

        if ((int)$version['version_no'] >= 2147483647) {
            throw new RuntimeException('문제 버전이 허용 범위에 도달했습니다.');
        }

        $execution = simulation_validate_execution_metadata($version, true);

        // 기본 정보 이외의 값은 요청값 대신 기존 저장본으로 검사한다.
        $basic = simulation_validate_problem_input(array(
            'title' => $input['title'] ?? null,
            'description' => $input['description'] ?? null,
            'allow_edit' => $input['allow_edit'] ?? null,
            'allow_submit' => $input['allow_submit'] ?? null,
            'starter_code' => $version['starter_code'],
            'simulation_mode' => $version['simulation_mode'],
            'canvas_width' => (string)$version['canvas_width'],
            'canvas_height' => (string)$version['canvas_height'],
            'sharing' => $input['sharing'] ?? null,
            'published' => $input['published'] ?? null,
            'execution_profile' => $execution['source_language'] === 'python'
                ? 'python-draw-poc-v1'
                : 'p5js-2.3.4',
        ));

        if ($basic['published'] === 1 && (string)$problem['status'] !== '1') {
            throw new InvalidArgumentException(
                '비활성 문제는 학생에게 공개할 수 없습니다.'
            );
        }

        $policy_changed = (
            (string)$basic['published'] !== (string)$problem['published'] ||
            $basic['sharing'] !== $problem['sharing']
        );

        $content_changed = (
            $basic['title'] !== $version['title'] ||
            $basic['description'] !== $version['description'] ||
            (int)$basic['allow_edit'] !== (int)$version['allow_edit'] ||
            (int)$basic['allow_submit'] !== (int)$version['allow_submit']
        );

        // 공개 정책만 바뀌면 문제 버전과 목표 이미지 요청은 만들지 않는다.
        if (!$content_changed) {
            if ($policy_changed) {
                $stmt = simulation_problem_store_statement(
                    $connection,
                    'UPDATE simulation_problem
                     SET published = ?, sharing = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE problem_id = ?',
                    array($basic['published'], $basic['sharing'], $id)
                );
                $stmt->closeCursor();
            }

            $connection->commit();

            return array(
                'problem_id' => $id,
                'version_id' => (string)$version['version_id'],
                'version_no' => (int)$version['version_no'],
                'change' => $policy_changed ? 'policy' : 'unchanged',
            );
        }

        $next = (int)$version['version_no'] + 1;

        $stmt = simulation_problem_store_statement(
            $connection,
            'INSERT INTO simulation_problem_version (
                problem_id, version_no, title, description,
                simulation_mode, starter_code,
                canvas_width, canvas_height,
                allow_edit, allow_submit, created_by,
                source_language, execution_engine, execution_version,
                p5_version
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $id,
                $next,
                $basic['title'],
                $basic['description'],
                $version['simulation_mode'],
                $version['starter_code'],
                $version['canvas_width'],
                $version['canvas_height'],
                $basic['allow_edit'],
                $basic['allow_submit'],
                $actor['user_id'],
                $version['source_language'],
                $version['execution_engine'],
                $version['execution_version'],
                $version['p5_version'],
            )
        );
        $inserted = $stmt->rowCount();
        $new_id = (string)$connection->lastInsertId();
        $stmt->closeCursor();

        if ($inserted !== 1) {
            throw new RuntimeException('기존 문제 버전을 복사하지 못했습니다.');
        }

        if ($version['simulation_mode'] === 'drawing') {
            $stmt = simulation_problem_store_statement(
                $connection,
                'SELECT source_code, render_config, grading_config, max_score
                 FROM simulation_problem_reference
                 WHERE version_id = ?
                 FOR UPDATE',
                array((string)$version['version_id'])
            );
            $reference = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$reference) {
                throw new RuntimeException('기존 정답을 찾지 못했습니다.');
            }

            $stmt = simulation_problem_store_statement(
                $connection,
                'INSERT INTO simulation_problem_reference (
                    version_id, source_code,
                    render_config, grading_config, max_score
                 ) VALUES (?, ?, ?, ?, ?)',
                array(
                    $new_id,
                    $reference['source_code'],
                    $reference['render_config'],
                    $reference['grading_config'],
                    $reference['max_score'],
                )
            );
            $copied = $stmt->rowCount();
            $stmt->closeCursor();

            if ($copied !== 1) {
                throw new RuntimeException('기존 정답을 복사하지 못했습니다.');
            }

            simulation_enqueue_problem_reference_image(
                $connection,
                $new_id
            );
        }

        $stmt = simulation_problem_store_statement(
            $connection,
            'UPDATE simulation_problem
             SET published = ?, sharing = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE problem_id = ?',
            array($basic['published'], $basic['sharing'], $id)
        );
        $stmt->closeCursor();

        $connection->commit();

        return array(
            'problem_id' => $id,
            'version_id' => $new_id,
            'version_no' => $next, 'change' => 'version',
        );

    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}
