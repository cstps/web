<?php

// 초기화 과정의 공백·줄바꿈이 PNG 응답에 섞이지 않도록 한다.
$draw_image_buffer_level = ob_get_level();
ob_start();

$draw_image_status = 200;
$draw_image_type = 'image/png';
$draw_image_body = '';

try {
    require_once('./include/db_info.inc.php');
    require_once('./scripts/simulation_problem_reader.php');
    require_once('./scripts/simulation_problem_image_view.php');

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $draw_image_actor = simulation_problem_current_actor($dbh);
    $draw_image_problem = simulation_problem_reader_latest(
        $dbh,
        $draw_image_actor,
        $_GET['problem_id'] ?? null
    );
    $draw_image_version = simulation_problem_reader_positive_id(
        $_GET['version_id'] ?? null
    );

    if (
        $draw_image_problem['version_id'] !== $draw_image_version ||
        $draw_image_problem['simulation_mode'] !== 'drawing'
    ) {
        throw new SimulationProblemReaderNotFoundException();
    }

    $draw_image_statement = $dbh->prepare(
        'SELECT
            CAST(v.version_id AS CHAR) AS version_id,
            CAST(v.problem_id AS CHAR) AS problem_id,
            v.version_no, v.simulation_mode,
            v.canvas_width, v.canvas_height, v.p5_version,
            v.source_language, v.execution_engine, v.execution_version,
            p.owner_user_id, p.sharing, p.status, p.published,
            r.source_code AS reference_source,
            r.render_config, r.grading_config, r.max_score,
            i.image_status, i.source_sha256, i.settings_sha256,
            i.request_metadata, i.render_metadata,
            i.png_sha256, i.generated_at,
            OCTET_LENGTH(i.png_data) AS png_bytes,
            i.png_data
         FROM simulation_problem_version v
         INNER JOIN simulation_problem p ON p.problem_id = v.problem_id
         INNER JOIN simulation_problem_reference r ON r.version_id = v.version_id
         LEFT JOIN simulation_problem_reference_image i ON i.version_id = v.version_id
         WHERE v.problem_id = ? AND v.version_id = ?
           AND v.version_id = (
               SELECT v2.version_id
               FROM simulation_problem_version v2
               WHERE v2.problem_id = v.problem_id
               ORDER BY v2.version_no DESC
               LIMIT 1
           )'
    );

    if (!$draw_image_statement || !$draw_image_statement->execute(array(
        $draw_image_problem['problem_id'],
        $draw_image_version,
    ))) {
        throw new RuntimeException('목표 그림 조회 실패');
    }

    $draw_image_row = $draw_image_statement->fetch(PDO::FETCH_ASSOC);
    $draw_image_statement->closeCursor();

    // 문제 조회 이후 공개 상태가 변경된 경우에도 권한을 다시 확인한다.
    if (
        !$draw_image_row ||
        !simulation_problem_reader_can_view($draw_image_actor, $draw_image_row)
    ) {
        throw new SimulationProblemReaderNotFoundException();
    }

    $draw_image_result = simulation_problem_image_validated_view(
        $draw_image_row,
        true
    );

    if (
        $draw_image_result['state'] !== 'ready' ||
        !isset($draw_image_result['png_data'])
    ) {
        $draw_image_status = 409;
        $draw_image_type = 'text/plain; charset=utf-8';
        $draw_image_body = '목표 그림을 준비 중이거나 다시 확인해야 합니다.';
    } else {
        $draw_image_body = $draw_image_result['png_data'];
    }

} catch (InvalidArgumentException $exception) {
    $draw_image_status = 400;
    $draw_image_type = 'text/plain; charset=utf-8';
    $draw_image_body = '문제 또는 버전 번호가 올바르지 않습니다.';
} catch (SimulationProblemReaderNotFoundException $exception) {
    $draw_image_status = 404;
    $draw_image_type = 'text/plain; charset=utf-8';
    $draw_image_body = '목표 그림을 찾을 수 없거나 공개되지 않았습니다.';
} catch (Throwable $exception) {
    error_log('[drawproblem_image] ' . $exception->getMessage());
    $draw_image_status = 500;
    $draw_image_type = 'text/plain; charset=utf-8';
    $draw_image_body = '목표 그림을 불러오지 못했습니다.';
}

while (ob_get_level() > $draw_image_buffer_level) {
    if (!ob_end_clean()) {
        throw new RuntimeException('목표 그림 출력 버퍼 정리 실패');
    }
}

http_response_code($draw_image_status);
header('Content-Type: ' . $draw_image_type);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($draw_image_body));

echo $draw_image_body;
exit;
