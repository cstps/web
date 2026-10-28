<?php

// 초기화 과정의 출력이 격리 프레임 응답에 섞이지 않도록 한다.
$course_python_buffer_level = ob_get_level();
ob_start();

function course_python_frame_clear_output()
{
    global $course_python_buffer_level;

    while (ob_get_level() > $course_python_buffer_level) {
        if (!ob_end_clean()) {
            throw new RuntimeException('실행 화면 출력 정리 실패');
        }
    }
}

function course_python_frame_error($status, $message)
{
    course_python_frame_clear_output();

    http_response_code($status);
    header('Cache-Control: private, no-store');
    header('Vary: Cookie', false);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header(
        "Content-Security-Policy: default-src 'none'; " .
        "frame-ancestors https://1024.kr"
    );

    exit($message);
}

try {
    require_once(__DIR__ . '/include/db_info.inc.php');
    require_once(
        __DIR__ . '/scripts/course_simulation_execution_context.php'
    );

    if (
        !in_array(
            $_SERVER['REQUEST_METHOD'] ?? '',
            array('GET', 'HEAD'),
            true
        )
    ) {
        header('Allow: GET, HEAD');
        course_python_frame_error(405, 'GET 요청만 허용됩니다.');
    }

    $course_python_user = $_SESSION[$OJ_NAME . '_user_id'] ?? null;

    if (
        !is_string($course_python_user) ||
        trim($course_python_user) === ''
    ) {
        course_python_frame_error(
            403,
            '로그인 후 시뮬레이션을 실행할 수 있습니다.'
        );
    }

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $course_python_context = course_simulation_execution_context(
        $dbh,
        $_GET['activity_id'] ?? null
    );

    $course_python_execution = $course_python_context['execution'];

    if (
        $course_python_execution['source_language'] !== 'python' ||
        $course_python_execution['execution_engine'] !==
            'python-stdlib-rgba-poc' ||
        $course_python_execution['execution_version'] !== '1'
    ) {
        course_python_frame_error(
            400,
            'Python 실행 활동이 아닙니다.'
        );
    }

    $channel = $_GET['channel'] ?? null;

    if (
        !is_string($channel) ||
        preg_match('/^[a-f0-9]{64}$/D', $channel) !== 1
    ) {
        course_python_frame_error(
            400,
            '실행 식별자가 올바르지 않습니다.'
        );
    }

} catch (InvalidArgumentException $exception) {
    course_python_frame_error(400, '실행 요청이 올바르지 않습니다.');

} catch (UnexpectedValueException $exception) {
    course_python_frame_error(
        403,
        '이 시뮬레이션을 실행할 권한이 없습니다.'
    );

} catch (Throwable $exception) {
    error_log(
        '[course_simulation_python_frame] ' .
        $exception->getMessage()
    );
    course_python_frame_error(
        500,
        'Python 실행 화면을 준비하지 못했습니다.'
    );
}

course_python_frame_clear_output();

// 실행 중 임시 저장·제출 요청이 세션 잠금을 기다리지 않도록 한다.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('Vary: Cookie', false);
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header(
    "Content-Security-Policy: sandbox allow-scripts; " .
    "default-src 'none'; " .
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
<head>
    <meta charset="utf-8">
    <title>수업 Python 코드 격리 실행</title>
</head>
<body>
<script src="https://1024.kr/template/syzoj/js/simulation-problem-preview-frame.js?v=1"></script>
</body>
</html>
