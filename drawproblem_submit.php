<?php

// 초기화 파일의 공백 등이 JSON 응답에 섞이지 않도록 한다.
$draw_submit_buffer_level = ob_get_level();
ob_start();

function drawproblem_submit_reply(array $data, $status)
{
    global $draw_submit_buffer_level;

    while (ob_get_level() > $draw_submit_buffer_level) {
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
    require_once(__DIR__ . '/scripts/simulation_problem_submission_store.php');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        drawproblem_submit_reply(array(
            'ok' => false,
            'code' => 'method_not_allowed',
            'message' => 'POST 요청만 허용됩니다.',
        ), 405);
    }

    $user_id = $_SESSION[$OJ_NAME . '_user_id'] ?? null;

    if (!is_string($user_id) || trim($user_id) === '') {
        drawproblem_submit_reply(array(
            'ok' => false,
            'code' => 'login_required',
            'message' => '로그인 후 제출할 수 있습니다.',
        ), 401);
    }

    $input = simulation_problem_submission_input($_POST);

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('제출 저장용 DB 연결을 확인하지 못했습니다.');
    }

    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 이 함수 안에서 권한·최신 버전·수행 기준·정답 준비 상태를 검사하고
    // 제출과 첫 채점 요청을 같은 트랜잭션으로 커밋한다.
    $saved = simulation_problem_store_submission($dbh, $input);

    // 다음 제출용 토큰 발급 실패도 이미 저장된 제출을 실패로 바꾸지 않는다.
    $next_token = null;

    try {
        $next_token = simulation_problem_submission_token_issue(
            $user_id,
            $input['problem_id'],
            $input['version_id'],
            $input['performance_context']
        );
    } catch (Throwable $exception) {
        error_log(
            '[drawproblem_submit_next_token] ' . $exception->getMessage()
        );
    }

    // DB 커밋이 완료된 뒤에만 알린다.
    // 알림 실패 시에도 서비스의 주기적 대기열 조회로 처리할 수 있다.
    if ($saved['grade_id'] !== null) {
        try {
            $socket_errno = 0;
            $socket_error = '';

            $socket = @stream_socket_client(
                'unix:///run/1024kr-simulation-grade/notify.sock',
                $socket_errno,
                $socket_error,
                0.05,
                STREAM_CLIENT_CONNECT
            );

            if (is_resource($socket)) {
                fclose($socket);
            } else {
                error_log('[drawproblem_submit_notify] 채점 알림 연결 실패');
            }
        } catch (Throwable $exception) {
            error_log(
                '[drawproblem_submit_notify] ' . $exception->getMessage()
            );
        }
    }

    drawproblem_submit_reply(array(
        'ok' => true,
        'submission_id' => $saved['submission_id'],
        'submission_no' => $saved['submission_no'],
        'grade_id' => $saved['grade_id'],
        'reused' => $saved['reused'],
        'next_submission_token' => $next_token,
        'message' => $saved['grade_id'] === null
            ? '코드를 정식 제출했습니다.'
            : '코드를 정식 제출했습니다. 채점 결과를 기다려 주세요.',
    ), 200);

} catch (SimulationProblemSubmissionConflictException $exception) {
    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'submission_conflict',
        'message' => $exception->getMessage(),
    ), 409);

} catch (SimulationPerformanceContextException $exception) {
    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'performance_changed',
        'message' => $exception->getMessage(),
    ), 409);

} catch (SimulationProblemGradeNotReadyException $exception) {
    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'reference_not_ready',
        'message' => $exception->getMessage(),
    ), 409);

} catch (SimulationProblemReaderNotFoundException $exception) {
    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'problem_not_found',
        'message' => '문제를 찾을 수 없거나 공개되지 않은 문제입니다.',
    ), 404);

} catch (UnexpectedValueException $exception) {
    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'submission_forbidden',
        'message' => $exception->getMessage(),
    ), 403);

} catch (InvalidArgumentException $exception) {
    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'invalid_request',
        'message' => $exception->getMessage(),
    ), 400);

} catch (Throwable $exception) {
    error_log('[drawproblem_submit] ' . $exception->getMessage());

    drawproblem_submit_reply(array(
        'ok' => false,
        'code' => 'submission_failed',
        'message' => '제출 결과를 확인하지 못했습니다. 현재 코드를 보관하고 같은 요청으로 다시 확인해 주세요.',
    ), 500);
}
