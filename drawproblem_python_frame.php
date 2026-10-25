<?php
// 초기화 출력이 격리 프레임의 응답 헤더에 영향을 주지 않도록 한다.
$draw_frame_buffer_level = ob_get_level();
ob_start();

function drawproblem_frame_error($status, $message)
{
    global $draw_frame_buffer_level;
    while (ob_get_level() > $draw_frame_buffer_level) {
        if (!ob_end_clean()) {
            break;
        }
    }
    http_response_code($status);
    header('Cache-Control: private, no-store');
    header('Content-Type: text/plain; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors https://1024.kr");
    exit($message);
}

try {
    require_once(__DIR__ . '/include/db_info.inc.php');
    require_once(__DIR__ . '/scripts/simulation_problem_reader.php');
    require_once(__DIR__ . '/scripts/simulation_execution_config.php');

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $draw_frame_actor = simulation_problem_current_actor($dbh);
    if ($draw_frame_actor['user_id'] === '') {
        drawproblem_frame_error(403, '로그인 후 코드를 실행할 수 있습니다.');
    }

    $draw_frame_problem = simulation_problem_reader_latest(
        $dbh,
        $draw_frame_actor,
        $_GET['problem_id'] ?? null
    );
    $draw_frame_version = simulation_problem_reader_positive_id(
        $_GET['version_id'] ?? null
    );

    if ($draw_frame_problem['version_id'] !== $draw_frame_version) {
        drawproblem_frame_error(409, '문제 버전이 변경됐습니다. 문제 화면을 다시 열어 주세요.');
    }

    $draw_frame_execution = simulation_validate_execution_metadata(
        $draw_frame_problem,
        true
    );

    if (
        $draw_frame_execution['source_language'] !== 'python' ||
        $draw_frame_execution['execution_engine'] !== 'python-stdlib-rgba-poc' ||
        $draw_frame_execution['execution_version'] !== '1' ||
        (int)$draw_frame_problem['canvas_width'] !== 400 ||
        (int)$draw_frame_problem['canvas_height'] !== 400
    ) {
        drawproblem_frame_error(400, '지원하지 않는 Python 실행 설정입니다.');
    }

} catch (SimulationProblemReaderNotFoundException $exception) {
    drawproblem_frame_error(404, '문제를 찾을 수 없거나 공개되지 않았습니다.');
} catch (InvalidArgumentException $exception) {
    drawproblem_frame_error(400, '실행 요청이 올바르지 않습니다.');
} catch (Throwable $exception) {
    error_log('[drawproblem_python_frame] ' . $exception->getMessage());
    drawproblem_frame_error(500, 'Python 실행 화면을 준비하지 못했습니다.');
}

// 권한 확인 과정에서 발생한 출력만 제거한다.
while (ob_get_level() > $draw_frame_buffer_level) {
    if (!ob_end_clean()) {
        throw new RuntimeException('격리 프레임 출력 버퍼 정리 실패');
    }
}

$channel = $_GET['channel'] ?? null;
if (
    !is_string($channel) ||
    preg_match('/^[a-f0-9]{64}$/D', $channel) !== 1
) {
    http_response_code(400);
    exit('실행 식별자가 올바르지 않습니다.');
}

header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: sandbox allow-scripts; default-src 'none'; " .
    "script-src 'wasm-unsafe-eval' " .
    "https://1024.kr/template/syzoj/js/simulation-problem-preview-frame.js " .
    "https://1024.kr/template/syzoj/js/vendor/pyodide-314.0.7/; " .
    "worker-src data:; " .
    "connect-src https://1024.kr/template/syzoj/js/vendor/pyodide-314.0.7/; " .
    "frame-src 'none'; object-src 'none'; base-uri 'none'; " .
    "form-action 'none'; frame-ancestors https://1024.kr"
);
?>
<!doctype html>
<html lang="ko">
<head><meta charset="utf-8"><title>Python 코드 격리 실행</title></head>
<body>
<script src="https://1024.kr/template/syzoj/js/simulation-problem-preview-frame.js?v=1"></script>
</body>
</html>
