<?php
require_once(__DIR__ . '/admin-init.php');
if (!oj_is_admin()) {
    http_response_code(403);
    exit('관리자만 이용할 수 있습니다.');
}
header('Cache-Control: private, no-store');
header(
    "Content-Security-Policy: default-src 'none'; " .
    "script-src 'self'; connect-src 'self'; frame-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; img-src 'self' data:; " .
    "object-src 'none'; base-uri 'none'; form-action 'none'; " .
    "frame-ancestors 'self'"
);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Python 그림 코드 입력 실험</title>
</head>
<body style="max-width:1100px;margin:24px auto;padding:0 16px;">
    <h1>Python 그림 코드 입력 실험</h1>
    <p>입력 코드는 격리 환경에서 실행하며 저장하지 않습니다.</p>

    <p>
        <button id="editor-static" type="button">정적 예제 불러오기</button>
        <button id="editor-animation" type="button">움직임 예제 불러오기</button>
    </p>

    <label for="editor-source">Python 코드</label>
    <div id="editor-ace" hidden
         style="width:100%;height:280px;resize:vertical;overflow:hidden;border:1px solid #ccc;"></div>
    <textarea id="editor-source" rows="15" spellcheck="false"
              style="width:100%;box-sizing:border-box;font-family:monospace;resize:vertical;">canvas(400, 400)
background(255)
no_stroke()
fill(0, 255, 0)

for x in range(80, 321, 60):
    circle(x, 200, 42)
</textarea>

    <p>
        <label>
            <input id="editor-animated" type="checkbox">
            프레임을 순서대로 실행
        </label>
        <label>
            최대 프레임
            <input id="editor-frames" type="number"
                   min="1" max="600" value="120" style="width:90px;">
        </label>
    </p>
    <p>
        <button id="editor-run" type="button">실행</button>
        <button id="editor-stop" type="button" disabled>중지</button>
    </p>
    <p id="editor-status" role="status" aria-live="polite">실행 준비 중입니다.</p>
    <pre id="editor-output"></pre>

    <canvas id="editor-canvas" width="400" height="400"
            aria-label="Python 그림 결과"
            style="display:block;max-width:100%;height:auto;border:1px solid #ccc;"></canvas>

    <p>그림 함수는 canvas, background, fill, stroke, stroke_width,
       no_stroke, circle, rect, line을 제공합니다. math는 준비되어 있습니다.</p>
    <p><a href="simulation_python_isolation_poc.php">격리 검사 화면</a></p>
    <div id="editor-frame-host"></div>

    <?php if (
        is_readable(__DIR__ . '/../ace/ace.js') &&
        is_readable(__DIR__ . '/../ace/mode-python.js') &&
        is_readable(__DIR__ . '/../ace/theme-chrome.js')
    ): ?>
        <script src="/ace/ace.js"></script>
        <script src="/ace/mode-python.js"></script>
        <script src="/ace/theme-chrome.js"></script>
    <?php endif; ?>
    <script src="/template/syzoj/js/course-python-editor-poc.js?v=1"></script>
</body>
</html>
