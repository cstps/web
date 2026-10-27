<?php

require_once(__DIR__ . '/simulation_problem_permissions.php');
require_once(__DIR__ . '/simulation_problem_input.php');
require_once(__DIR__ . '/course_simulation_region_config.php');
require_once(__DIR__ . '/simulation_problem_image_request.php');


class SimulationProblemVersionConflict extends RuntimeException
{
}


function simulation_problem_store_statement(
    PDO $connection,
    $sql,
    array $values
) {
    $statement = $connection->prepare($sql);

    if (!$statement || !$statement->execute($values)) {
        throw new RuntimeException('문제 저장 쿼리 처리에 실패했습니다.');
    }

    return $statement;
}


// 그림 문제의 정답과 채점 설정을 검사한다.
// 자유 실습은 정답 기록을 만들지 않는다.
function simulation_problem_reference_input(array $input, array $version)
{
    if ($version['simulation_mode'] === 'free') {
        return null;
    }

    $source = simulation_problem_input_string(
        $input, 'reference_source', 200000
    );

    $mode = simulation_problem_input_choice(
        $input,
        'scoring_mode',
        array('metrics_only', 'whole_image', 'regions')
    );

    $radius = simulation_problem_input_choice(
        $input, 'position_tolerance', array('0', '1', '2')
    );

    $max_score = simulation_problem_input_string(
        $input, 'max_score', 8
    );

    $units = simulation_region_score_units($max_score);
    $normalized_score = sprintf(
        '%d.%02d',
        intdiv($units, 100),
        $units % 100
    );

    $grading = array(
        'schema_version' => 1,
        'experimental' => true,
        'metric' => 'symmetric_foreground_color_coverage',
        'position_tolerance_px' => (int)$radius,
        'neighborhood' => 'euclidean_disk',
        'color_tolerance' => 0,
        'scoring_mode' => $mode,
        'scoring_method' => null,
    );

    if ($mode === 'whole_image') {
        $grading['scoring_method'] = 'coverage_linear_v1';
    } elseif ($mode === 'regions') {
        $raw_regions = simulation_problem_input_string(
            $input, 'regions_json', 20000
        );

        $regions = json_decode(
            $raw_regions,
            true,
            32,
            JSON_THROW_ON_ERROR
        );

        $grading['regions'] = simulation_validate_region_config(
            $regions,
            $normalized_score
        );
        $grading['scoring_method'] = 'region_coverage_linear_v1';
    }

    $render = array(
        'schema_version' => 1,
        'renderer' => $version['execution_engine'] === 'p5js'
            ? 'P2D'
            : 'stdlib-rgba',
        'pixel_density' => 1,
        'background_rgb' => array(255, 255, 255),
    );

    return array(
        'source_code' => $source,
        'max_score' => $normalized_score,
        'render_config' => json_encode(
            $render,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ),
        'grading_config' => json_encode(
            $grading,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ),
    );
}


// 호출하는 처리기에서 로그인과 보안 토큰을 검사한다.
// 이 함수에서도 현재 권한을 다시 조회하며 소유자는 요청값으로 받지 않는다.
function simulation_store_problem_version(
    PDO $connection,
    array $input,
    $problem_id,
    $expected_version,
    $content_only = false
) {
    if ($connection->inTransaction()) {
        throw new RuntimeException('이미 진행 중인 트랜잭션이 있습니다.');
    }

    if (
        !is_string($expected_version) ||
        preg_match('/^(0|[1-9][0-9]{0,9})$/D', $expected_version) !== 1 ||
        (float)$expected_version > 2147483647
    ) {
        throw new InvalidArgumentException('문제 기준 버전이 올바르지 않습니다.');
    }

    $is_new = $problem_id === null;

    if (!$is_new) {
        if (
            !is_string($problem_id) ||
            preg_match('/^[1-9][0-9]{0,18}$/D', $problem_id) !== 1 ||
            (
                strlen($problem_id) === 19 &&
                strcmp($problem_id, '9223372036854775807') > 0
            )
        ) {
            throw new InvalidArgumentException('문제 번호가 올바르지 않습니다.');
        }
    }

    if ($is_new && $expected_version !== '0') {
        throw new InvalidArgumentException('새 문제의 기준 버전은 0이어야 합니다.');
    }

    // 기존 문제의 공개 정책은 별도 저장 경로에서만 변경한다.
    if (!$is_new) {
        $input['sharing'] = 'private';
        $input['published'] = '0';

        if ($content_only === true) {
            // 실행 설정 저장 시 기본 정보는 서버의 기존 저장본을 이용한다.
            $input['title'] = '저장된 문제';
            $input['description'] = '';
            $input['allow_edit'] = '1';
            $input['allow_submit'] = '1';
        }
    }

    $version = simulation_validate_problem_input($input);
    $reference = simulation_problem_reference_input($input, $version);

    $connection->beginTransaction();

    try {
        $actor = simulation_problem_current_actor($connection);

        if (!simulation_problem_can_create($actor)) {
            throw new RuntimeException('문제를 저장할 권한이 없습니다.');
        }

        if ($is_new) {
            $statement = simulation_problem_store_statement(
                $connection,
                'INSERT INTO simulation_problem
                    (owner_user_id, sharing, published, status)
                 VALUES (?, ?, ?, 1)',
                array(
                    $actor['user_id'],
                    $version['sharing'],
                    $version['published'],
                )
            );

            $problem_id = (string)$connection->lastInsertId();
            $statement->closeCursor();
            $latest_no = 0;
        } else {
            $statement = simulation_problem_store_statement(
                $connection,
                'SELECT problem_id, owner_user_id, sharing, published, status
                 FROM simulation_problem
                 WHERE problem_id = ?
                 FOR UPDATE',
                array($problem_id)
            );

            $problem = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            if (!$problem) {
                throw new RuntimeException('수정할 문제를 찾지 못했습니다.');
            }

            $permissions = simulation_problem_permissions($actor, $problem);

            if ($permissions['edit'] !== true) {
                throw new RuntimeException('이 문제를 수정할 권한이 없습니다.');
            }

            // 다른 창에서 저장한 공개 정책을 이전 화면의 값으로 덮어쓰지 않는다.
            $version['sharing'] = $problem['sharing'];
            $version['published'] = (int)$problem['published'];


            $statement = simulation_problem_store_statement(
                $connection,
                'SELECT version_no, title, description, allow_edit, allow_submit
                 FROM simulation_problem_version
                 WHERE problem_id = ?
                 ORDER BY version_no DESC
                 LIMIT 1
                 FOR UPDATE',
                array($problem_id)
            );

            $latest = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            if (!$latest) {
                throw new RuntimeException('기존 문제 버전을 찾지 못했습니다.');
            }

            if ($content_only === true) {
                // 잠근 최신 버전의 기본 정보를 그대로 유지한다.
                foreach (array(
                    'title', 'description', 'allow_edit', 'allow_submit'
                ) as $key) {
                    $version[$key] = $latest[$key];
                }
            }

            $latest_no = (int)$latest['version_no'];
        }

        if ((string)$latest_no !== $expected_version) {
            throw new SimulationProblemVersionConflict(
                '다른 창에서 새 버전이 저장됐습니다. 입력 내용을 보관한 뒤 다시 열어 주세요.'
            );
        }

        if ($latest_no >= 2147483647) {
            throw new RuntimeException('문제 버전이 허용 범위에 도달했습니다.');
        }

        $next_no = $latest_no + 1;

        // 이전 버전은 변경하지 않고 새 버전을 추가한다.
        // 기존 p5_version 열은 기본값을 유지하며 Python 엔진에서는 사용하지 않는다.
        $statement = simulation_problem_store_statement(
            $connection,
            'INSERT INTO simulation_problem_version
                (
                    problem_id, version_no, title, description,
                    simulation_mode, starter_code,
                    canvas_width, canvas_height,
                    allow_edit, allow_submit, created_by,
                    source_language, execution_engine, execution_version
                )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                $problem_id,
                $next_no,
                $version['title'],
                $version['description'],
                $version['simulation_mode'],
                $version['starter_code'],
                $version['canvas_width'],
                $version['canvas_height'],
                $version['allow_edit'],
                $version['allow_submit'],
                $actor['user_id'],
                $version['source_language'],
                $version['execution_engine'],
                $version['execution_version'],
            )
        );

        $version_id = (string)$connection->lastInsertId();
        $statement->closeCursor();

        if ($reference !== null) {
            $statement = simulation_problem_store_statement(
                $connection,
                'INSERT INTO simulation_problem_reference
                    (
                        version_id, source_code,
                        render_config, grading_config, max_score
                    )
                 VALUES (?, ?, ?, ?, ?)',
                array(
                    $version_id,
                    $reference['source_code'],
                    $reference['render_config'],
                    $reference['grading_config'],
                    $reference['max_score'],
                )
            );
            $statement->closeCursor();

            // 새 정답과 이미지 생성 요청을 같은 트랜잭션에 저장한다.
            simulation_enqueue_problem_reference_image(
                $connection,
                $version_id
            );
        }

        $statement = simulation_problem_store_statement(
            $connection,
            'UPDATE simulation_problem
             SET sharing = ?, published = ?, updated_at = CURRENT_TIMESTAMP
             WHERE problem_id = ?',
            array(
                $version['sharing'],
                $version['published'],
                $problem_id,
            )
        );
        $statement->closeCursor();

        $connection->commit();

        return array(
            'problem_id' => $problem_id,
            'version_id' => $version_id,
            'version_no' => $next_no,
        );
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }
}
