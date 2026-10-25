<?php
require_once(__DIR__ . '/simulation-problem-init.php');

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
<head><meta charset="utf-8"><title>정답 그림 격리 실행</title></head>
<body>
<script src="https://1024.kr/template/syzoj/js/simulation-problem-preview-frame.js?v=1"></script>
</body>
</html>
