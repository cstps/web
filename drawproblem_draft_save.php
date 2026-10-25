<?php

$draw_draft_buffer_level = ob_get_level();
ob_start();

function drawproblem_draft_json($status, array $data)
{
    global $draw_draft_buffer_level;
    while (ob_get_level() > $draw_draft_buffer_level) {
        if (!ob_end_clean()) {
            throw new RuntimeException('저장 응답 버퍼 정리 실패');
        }
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

$draw_draft_db = null;

try {
    require_once('./include/db_info.inc.php');
    require_once('./scripts/simulation_problem_draft_token.php');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        drawproblem_draft_json(405, array(
            'ok' => false, 'code' => 'method_not_allowed',
            'message' => 'POST 요청만 허용됩니다.',
        ));
    }

    $draw_draft_user = $_SESSION[$OJ_NAME . '_user_id'] ?? null;
    if (!is_string($draw_draft_user) || trim($draw_draft_user) === '') {
        drawproblem_draft_json(401, array(
            'ok' => false, 'code' => 'login_required',
            'message' => '로그인 후 코드를 저장할 수 있습니다.',
        ));
    }

    $draw_draft_user = simulation_problem_draft_user($draw_draft_user);
    $draw_draft_problem_id = simulation_problem_reader_positive_id(
        $_POST['problem_id'] ?? null
    );
    $draw_draft_version_id = simulation_problem_reader_positive_id(
        $_POST['version_id'] ?? null
    );
    $draw_draft_revision = simulation_problem_draft_revision(
        $_POST['expected_revision'] ?? null
    );
    $draw_draft_source = simulation_problem_draft_code(
        $_POST['source_code'] ?? null
    );
    $draw_draft_expected_context = $_POST['performance_context'] ?? null;

    if (!is_string($draw_draft_expected_context)) {
        throw new InvalidArgumentException('수행 기준을 확인하지 못했습니다.');
    }

    $draw_draft_token_cutoff = $draw_draft_expected_context === ''
        ? null : $draw_draft_expected_context;

    if (!simulation_problem_draft_token_valid(
        $_POST['draft_token'] ?? null,
        $draw_draft_user,
        $draw_draft_version_id,
        $draw_draft_token_cutoff
    )) {
        drawproblem_draft_json(403, array(
            'ok' => false, 'code' => 'invalid_token',
            'message' => '저장 보안 토큰이 만료됐거나 유효하지 않습니다. 코드를 복사해 보관한 뒤 문제 화면을 다시 열어 주세요.',
        ));
    }

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $draw_draft_db = $dbh;
    $draw_draft_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $draw_draft_db->beginTransaction();

    // 문제 수정과 잠금 순서를 맞추고 최신 버전·공개 설정을 확인한다.
    $statement = simulation_problem_draft_statement(
        $draw_draft_db,
        'SELECT problem_id FROM simulation_problem
         WHERE problem_id = ? FOR UPDATE',
        array($draw_draft_problem_id)
    );
    $exists = $statement->fetchColumn() !== false;
    $statement->closeCursor();

    if (!$exists) {
        throw new SimulationProblemReaderNotFoundException();
    }

    $actor = simulation_problem_current_actor($draw_draft_db);
    $problem = simulation_problem_reader_latest(
        $draw_draft_db, $actor, $draw_draft_problem_id
    );

    if ($problem['version_id'] !== $draw_draft_version_id) {
        throw new SimulationProblemDraftConflictException(
            '문제 버전이 변경됐습니다. 현재 코드를 복사해 보관한 뒤 문제 화면을 다시 열어 주세요.'
        );
    }

    if (
        (string)$problem['status'] !== '1' ||
        (string)$problem['allow_edit'] !== '1'
    ) {
        throw new UnexpectedValueException('현재 이 문제의 코드를 저장할 수 없습니다.');
    }

    $cutoff = simulation_checked_performance_context(
        $draw_draft_user,
        $draw_draft_expected_context,
        isset($_SESSION[$OJ_NAME . '_source_browser'])
    );

    $saved_revision = simulation_problem_store_draft(
        $draw_draft_db,
        $draw_draft_version_id,
        $draw_draft_user,
        $cutoff,
        $draw_draft_source,
        $draw_draft_revision
    );

    $draw_draft_db->commit();

    drawproblem_draft_json(200, array(
        'ok' => true,
        'revision' => $saved_revision,
        'message' => '코드를 저장했습니다.',
    ));

} catch (Throwable $exception) {
    if ($draw_draft_db instanceof PDO && $draw_draft_db->inTransaction()) {
        $draw_draft_db->rollBack();
    }

    if (
        $exception instanceof SimulationProblemDraftConflictException ||
        $exception instanceof SimulationPerformanceContextException
    ) {
        $status = 409;
        $code = 'draft_conflict';
        $message = $exception->getMessage();
    } elseif ($exception instanceof SimulationProblemReaderNotFoundException) {
        $status = 404;
        $code = 'not_found';
        $message = '문제를 찾을 수 없거나 접근 권한이 변경됐습니다.';
    } elseif ($exception instanceof UnexpectedValueException) {
        $status = 403;
        $code = 'access_denied';
        $message = $exception->getMessage();
    } elseif ($exception instanceof InvalidArgumentException) {
        $status = 400;
        $code = 'invalid_input';
        $message = $exception->getMessage();
    } else {
        error_log('[drawproblem_draft_save] ' . $exception->getMessage());
        $status = 500;
        $code = 'save_failed';
        $message = '코드를 저장하지 못했습니다. 현재 코드를 복사해 보관해 주세요.';
    }

    drawproblem_draft_json($status, array(
        'ok' => false, 'code' => $code, 'message' => $message,
    ));
}
