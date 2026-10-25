<?php

$draw_status_buffer_level = ob_get_level();
ob_start();

function drawproblem_grade_status_reply(array $data, $status)
{
    global $draw_status_buffer_level;

    while (ob_get_level() > $draw_status_buffer_level) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_THROW_ON_ERROR
    );
    exit;
}

try {
    require_once(__DIR__ . '/include/db_info.inc.php');
    require_once(
        __DIR__ . '/scripts/simulation_problem_submission_history_view.php'
    );

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');

        drawproblem_grade_status_reply(array(
            'ok' => false,
            'message' => 'GET 요청만 허용됩니다.',
        ), 405);
    }

    $user_id = $_SESSION[$OJ_NAME . '_user_id'] ?? null;

    if (!is_string($user_id) || trim($user_id) === '') {
        drawproblem_grade_status_reply(array(
            'ok' => false,
            'message' => '로그인 후 제출 기록을 확인할 수 있습니다.',
        ), 401);
    }

    $context = $_GET['performance_context'] ?? null;

    if (!is_string($context)) {
        throw new InvalidArgumentException('화면의 수행 기준을 확인하지 못했습니다.');
    }

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('제출 기록 조회용 DB 연결 실패');
    }

    $history = simulation_problem_submission_history(
        $dbh,
        $_GET['problem_id'] ?? null,
        $context
    );

    $has_pending = false;

    foreach ($history['submissions'] as $submission) {
        if (in_array(
            $submission['grading_status'],
            array('pending', 'running'),
            true
        )) {
            $has_pending = true;
            break;
        }
    }

    drawproblem_grade_status_reply(array(
        'ok' => true,
        'problem_id' => $history['problem_id'],
        'performance_context' => $history['performance_context'],
        'has_pending' => $has_pending,
        'html' => simulation_problem_render_submission_history($history),
    ), 200);

} catch (SimulationPerformanceContextException $exception) {
    drawproblem_grade_status_reply(array(
        'ok' => false,
        'message' => $exception->getMessage(),
    ), 409);

} catch (SimulationProblemReaderNotFoundException $exception) {
    drawproblem_grade_status_reply(array(
        'ok' => false,
        'message' => '문제를 찾을 수 없거나 공개되지 않은 문제입니다.',
    ), 404);

} catch (UnexpectedValueException $exception) {
    drawproblem_grade_status_reply(array(
        'ok' => false,
        'message' => '제출 기록 조회 권한이 없습니다.',
    ), 403);

} catch (InvalidArgumentException $exception) {
    drawproblem_grade_status_reply(array(
        'ok' => false,
        'message' => $exception->getMessage(),
    ), 400);

} catch (Throwable $exception) {
    error_log('[drawproblem_grade_status] ' . $exception->getMessage());

    drawproblem_grade_status_reply(array(
        'ok' => false,
        'message' => '제출 기록을 불러오지 못했습니다.',
    ), 500);
}
