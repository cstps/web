<?php
require_once(__DIR__ . '/admin-init.php');
if (!oj_is_admin()) {
    http_response_code(403);
    exit('관리자만 이용할 수 있습니다.');
}
header('Cache-Control: private, no-store');
header(
    "Content-Security-Policy: default-src 'none'; " .
    "script-src 'self'; frame-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "object-src 'none'; base-uri 'none'; frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Python 실행 격리 검사</title>
</head>
<body>
    <h1>Python 실행 격리 검사</h1>
    <p>고정 검사만 실행하며 DB에는 저장하지 않습니다.</p>
    <button id="isolation-start" type="button">격리 검사 시작</button>
    <button id="isolation-stop" type="button" disabled>중지</button>
    <p id="isolation-status" role="status">검사를 시작해 주세요.</p>
    <pre id="isolation-output"></pre>
    <div id="isolation-frame-host"></div>
    <p><a href="simulation_python_poc.php">기존 그림 실험 화면</a></p>
    <script src="/template/syzoj/js/course-python-isolation-poc.js?v=1"></script>
</body>
</html>
