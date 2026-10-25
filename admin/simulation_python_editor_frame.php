<?php
require_once(__DIR__ . '/admin-init.php');
if (!oj_is_admin()) {
    http_response_code(403);
    exit('관리자만 이용할 수 있습니다.');
}
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: sandbox allow-scripts; default-src 'none'; " .
    "script-src 'wasm-unsafe-eval' " .
    "https://1024.kr/template/syzoj/js/course-python-editor-frame.js " .
    "https://1024.kr/template/syzoj/js/vendor/pyodide-314.0.7/; " .
    "worker-src data:; " .
    "connect-src https://1024.kr/template/syzoj/js/vendor/pyodide-314.0.7/; " .
    "frame-src 'none'; object-src 'none'; base-uri 'none'; " .
    "form-action 'none'; frame-ancestors https://1024.kr"
);
?>
<!doctype html>
<html lang="ko">
<head><meta charset="utf-8"><title>Python 그림 격리 실행</title></head>
<body>
<script src="https://1024.kr/template/syzoj/js/course-python-editor-frame.js?v=1"></script>
</body>
</html>
