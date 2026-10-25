<?php
require_once(__DIR__ . '/simulation_problem_permissions.php');
require_once(__DIR__ . '/simulation_problem_image_request.php');


function simulation_problem_image_view(
    PDO $connection,
    array $actor,
    $version_id,
    $include_png = false
) {
    $version_id = simulation_problem_image_positive_id($version_id);

    $statement = $connection->prepare(
        'SELECT
            CAST(v.version_id AS CHAR) AS version_id,
            CAST(v.problem_id AS CHAR) AS problem_id,
            v.version_no, v.simulation_mode,
            v.canvas_width, v.canvas_height, v.p5_version,
            v.source_language, v.execution_engine, v.execution_version,
            p.owner_user_id, p.sharing, p.status,
            r.source_code AS reference_source,
            r.render_config, r.grading_config, r.max_score,
            i.image_status, i.source_sha256, i.settings_sha256,
            i.request_metadata, i.render_metadata,
            i.png_sha256, i.generated_at,
            OCTET_LENGTH(i.png_data) AS png_bytes, ' .
            ($include_png ? 'i.png_data' : 'NULL AS png_data') . '
         FROM simulation_problem_version v
         INNER JOIN simulation_problem p ON p.problem_id = v.problem_id
         LEFT JOIN simulation_problem_reference r ON r.version_id = v.version_id
         LEFT JOIN simulation_problem_reference_image i ON i.version_id = v.version_id
         WHERE v.version_id = ?'
    );

    if (!$statement || !$statement->execute(array($version_id))) {
        throw new RuntimeException('목표 이미지 조회 실패');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (
        !$row ||
        simulation_problem_permissions($actor, $row)['edit'] !== true
    ) {
        throw new UnexpectedValueException('목표 이미지 조회 권한이 없습니다.');
    }

    $view = array(
        'version_id' => $version_id,
        'version_no' => (int)$row['version_no'],
        'state' => $row['image_status'] ?? 'missing',
        'label' => '',
        'generated_at' => '',
        'region_label' => '',
        'png_sha256' => null,
    );

    $labels = array(
        'missing' => '이미지 생성 요청 없음',
        'pending' => '목표 이미지 생성 대기',
        'running' => '목표 이미지 생성 중',
        'ready' => '목표 이미지 생성·검사 완료',
        'error' => '목표 이미지 생성 실패',
    );

    if (!isset($labels[$view['state']])) {
        $view['state'] = 'invalid';
    }

    if ($view['state'] !== 'missing') {
        try {
            $current = simulation_problem_image_request_metadata($row);
            $stored = json_decode(
                $row['request_metadata'], false, 64, JSON_THROW_ON_ERROR
            );

            $canonical = simulation_problem_image_canonical_json((object)$current);

            if (
                !$stored instanceof stdClass ||
                $canonical !== simulation_problem_image_canonical_json($stored) ||
                $row['source_sha256'] !== $current['source_sha256'] ||
                $row['settings_sha256'] !== $current['settings_sha256']
            ) {
                throw new RuntimeException('이미지 요청과 현재 정답 설정 불일치');
            }

            if ($view['state'] === 'ready') {
                $metadata = json_decode(
                    $row['render_metadata'], false, 64, JSON_THROW_ON_ERROR
                );

                if (
                    !$metadata instanceof stdClass ||
                    ($metadata->status ?? null) !== 'ok' ||
                    !isset($metadata->request) ||
                    $canonical !== simulation_problem_image_canonical_json(
                        $metadata->request
                    ) ||
                    !is_string($row['png_sha256']) ||
                    preg_match('/^[0-9a-f]{64}$/D', $row['png_sha256']) !== 1 ||
                    ($metadata->png_sha256 ?? null) !== $row['png_sha256'] ||
                    !is_int($metadata->png_bytes ?? null) ||
                    $metadata->png_bytes !== (int)$row['png_bytes'] ||
                    $metadata->png_bytes < 1 ||
                    $metadata->png_bytes > 1048576 ||
                    !is_string($row['generated_at']) ||
                    $row['generated_at'] === ''
                ) {
                    throw new RuntimeException('저장된 이미지 검사 정보 불일치');
                }

                $grading = $current['settings']->grading_config;
                if (($grading->scoring_mode ?? null) === 'regions') {
                    if (
                        ($metadata->region_validation->status ?? null) !== 'valid'
                    ) {
                        throw new RuntimeException('정답 영역 검사 기록 누락');
                    }
                    $view['region_label'] = '영역 검사 통과';
                }

                if ($include_png) {
                    $png = $row['png_data'];
                    if (
                        !is_string($png) ||
                        strlen($png) !== $metadata->png_bytes ||
                        hash('sha256', $png) !== $row['png_sha256']
                    ) {
                        throw new RuntimeException('저장된 PNG 해시 불일치');
                    }

                    $info = @getimagesizefromstring($png);
                    if (
                        !$info || $info[2] !== IMAGETYPE_PNG ||
                        $info[0] !== 400 || $info[1] !== 400
                    ) {
                        throw new RuntimeException('저장된 PNG 형식 불일치');
                    }

                    $view['png_data'] = $png;
                }

                $view['generated_at'] = $row['generated_at'];
                $view['png_sha256'] = $row['png_sha256'];
            }
        } catch (Throwable $exception) {
            error_log('[simulation_problem_image_view] ' . $exception->getMessage());
            $view['state'] = 'invalid';
            $view['png_sha256'] = null;
            unset($view['png_data']);
        }
    }

    $view['label'] = $labels[$view['state']]
        ?? '정답과 이미지 검사 정보를 다시 확인해야 합니다.';

    return $view;
}
