<?php

$OJ_CACHE_SHARE = false;
$cache_time = 0;
header('Cache-Control: private, no-store');

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./scripts/simulation_problem_reader.php');
require_once('./scripts/simulation_problem_draft_token.php');
require_once('./scripts/simulation_execution_config.php');
require_once('./include/course_content_sanitizer.inc.php');

$view_title = '그림 문제';
$show_title = $view_title;

function drawproblem_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function drawproblem_error($message, $status)
{
    global $view_errors, $OJ_TEMPLATE, $view_title, $show_title;

    http_response_code($status);
    $view_title = '그림 문제';
    $show_title = $view_title;
    $view_errors = '<h2>' . drawproblem_escape($message) . '</h2>' .
        '<p><a href="/drawproblemset.php">그림 문제 목록으로 돌아가기</a></p>';
    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

try {
    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $draw_reader_actor = simulation_problem_current_actor($dbh);
    $draw_reader_problem = simulation_problem_reader_latest(
        $dbh,
        $draw_reader_actor,
        $_GET['problem_id'] ?? null
    );

    simulation_validate_execution_metadata($draw_reader_problem, true);

    $draw_reader_description = course_content_sanitize_html(
        $draw_reader_problem['description']
    );
    $draw_reader_logged_in = $draw_reader_actor['user_id'] !== '';

    $draw_reader_source = $draw_reader_problem['starter_code'];
    $draw_reader_revision = '0';
    $draw_reader_cutoff = null;
    $draw_reader_draft_token = '';
    $draw_reader_has_draft = false;

    if (
        $draw_reader_logged_in &&
        (string)$draw_reader_problem['status'] === '1' &&
        (string)$draw_reader_problem['allow_edit'] === '1'
    ) {
        $draw_reader_cutoff = simulation_performance_history_cutoff(
            $draw_reader_actor['user_id'],
            isset($_SESSION[$OJ_NAME . '_source_browser'])
        );

        $draw_reader_draft = simulation_problem_load_draft(
            $dbh,
            $draw_reader_problem['version_id'],
            $draw_reader_actor['user_id'],
            $draw_reader_cutoff
        );

        if ($draw_reader_draft !== null) {
            $draw_reader_source = $draw_reader_draft['source_code'];
            $draw_reader_revision = $draw_reader_draft['revision'];
            $draw_reader_has_draft = true;
        }

        $draw_reader_draft_token = simulation_problem_draft_token_issue(
            $draw_reader_actor['user_id'],
            $draw_reader_problem['version_id'],
            $draw_reader_cutoff
        );
    }


    $view_title = $draw_reader_problem['title'] . ' - 그림 문제';
    $show_title = $view_title;

} catch (InvalidArgumentException $exception) {
    drawproblem_error('그림 문제 번호 또는 실행 설정이 올바르지 않습니다.', 400);
} catch (SimulationProblemReaderNotFoundException $exception) {
    drawproblem_error('문제를 찾을 수 없거나 공개되지 않은 문제입니다.', 404);
} catch (Throwable $exception) {
    error_log('[drawproblem] ' . $exception->getMessage());
    drawproblem_error('그림 문제를 불러오지 못했습니다.', 500);
}

require('template/' . $OJ_TEMPLATE . '/drawproblem.php');
