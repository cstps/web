<?php

// 초기화 파일의 출력이 PNG 응답에 섞이지 않도록 버퍼링한다.
$image_buffer_level = ob_get_level();
ob_start();

function simulation_reference_image_clear_output()
{
    global $image_buffer_level;

    while (ob_get_level() > $image_buffer_level) {
        ob_end_clean();
    }
}

function simulation_reference_image_fail($message, $status)
{
    simulation_reference_image_clear_output();

    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo $message . "\n";
    }
    exit;
}

try {
    require_once(__DIR__ . '/include/db_info.inc.php');
    require_once(__DIR__ . '/include/const.inc.php');
    require_once(__DIR__ . '/include/memcache.php');
    require_once(__DIR__ . '/include/setlang.php');
    require_once(__DIR__ . '/include/course_functions.inc.php');

    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if (!in_array($method, array('GET', 'HEAD'), true)) {
        header('Allow: GET, HEAD');
        simulation_reference_image_fail('지원하지 않는 요청 방식입니다.', 405);
    }

    if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
        simulation_reference_image_fail('로그인이 필요합니다.', 401);
    }

    $raw_id = (
        isset($_GET['activity_id']) &&
        is_string($_GET['activity_id'])
    ) ? $_GET['activity_id'] : '';

    if (
        !preg_match('/^[1-9][0-9]*$/D', $raw_id) ||
        strlen($raw_id) > 10 ||
        (float)$raw_id > 2147483647
    ) {
        simulation_reference_image_fail('잘못된 활동 번호입니다.', 400);
    }

    $activity_id = (int)$raw_id;

    $rows = pdo_query(
        "SELECT
            ca.course_id,
            ca.status AS activity_status,
            ca.visible AS activity_visible,
            cl.status AS lesson_status,
            cl.visible AS lesson_visible,
            c.status AS course_status,
            s.simulation_mode,
            s.canvas_width, s.canvas_height,
            s.source_language, s.execution_engine, s.execution_version
         FROM course_activity ca
         INNER JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         INNER JOIN course c
           ON c.course_id = ca.course_id
         INNER JOIN course_simulation s
           ON s.activity_id = ca.activity_id
         WHERE ca.activity_id = ?
           AND ca.activity_type = 'simulation'
         LIMIT 1",
        $activity_id
    );

    if ($rows === false) {
        throw new RuntimeException('활동 조회에 실패했습니다.');
    }

    if (!isset($rows[0])) {
        simulation_reference_image_fail('활동을 찾을 수 없습니다.', 404);
    }

    $activity = $rows[0];
    $course_id = (int)$activity['course_id'];

    $is_manager = (
        course_can_access($course_id) &&
        course_can_manage_contests($course_id)
    );
    $is_student = course_is_active_student($course_id);

    if (!$is_manager && !$is_student) {
        simulation_reference_image_fail('활동 열람 권한이 없습니다.', 403);
    }

    if (
        (int)$activity['activity_status'] !== 1 ||
        (int)$activity['lesson_status'] !== 1
    ) {
        simulation_reference_image_fail('열람할 수 없는 활동입니다.', 403);
    }

    if (
        !$is_manager &&
        (
            (int)$activity['course_status'] !== 1 ||
            (int)$activity['lesson_visible'] !== 1 ||
            (int)$activity['activity_visible'] !== 1
        )
    ) {
        simulation_reference_image_fail('공개되지 않은 활동입니다.', 403);
    }

    if ($activity['simulation_mode'] !== 'drawing') {
        simulation_reference_image_fail('목표 그림이 없는 활동입니다.', 404);
    }

    // 권한 확인을 마쳤으므로 세션 잠금을 해제한다.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    require_once(__DIR__ . '/scripts/course_simulation_target_image.php');

    $image = course_simulation_target_image(
        $dbh, $activity_id, $activity
    );

    if ($image === null) {
        simulation_reference_image_fail(
            '최신 정답의 목표 그림이 아직 준비되지 않았습니다.',
            404
        );
    }

    $png = $image['png_data'];

    if (
        !is_string($png) ||
        strlen($png) === 0 ||
        strlen($png) > 1048576 ||
        !hash_equals((string)$image['png_sha256'], hash('sha256', $png))
    ) {
        throw new RuntimeException('저장된 목표 PNG 검증에 실패했습니다.');
    }

    $info = @getimagesizefromstring($png);

    if (
        $info === false ||
        $info[2] !== IMAGETYPE_PNG ||
        $info[0] !== (int)$image['canvas_width'] ||
        $info[1] !== (int)$image['canvas_height']
    ) {
        throw new RuntimeException('저장된 목표 PNG 형식 또는 크기가 다릅니다.');
    }

    simulation_reference_image_clear_output();

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header(
        'Content-Disposition: inline; filename="simulation-target-' .
        $activity_id . '.png"'
    );
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');

    if ($method !== 'HEAD') {
        echo $png;
    }
    exit;

} catch (Throwable $e) {
    error_log('[course_simulation_reference_image] ' . $e->getMessage());
    simulation_reference_image_fail('목표 그림을 제공하지 못했습니다.', 500);
}
