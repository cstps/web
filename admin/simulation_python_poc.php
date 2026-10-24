<?php
require_once(__DIR__ . '/admin-init.php');

if (!oj_is_admin()) {
    http_response_code(403);
    exit('관리자만 이용할 수 있는 실험 화면입니다.');
}

header('Cache-Control: private, no-store');
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'wasm-unsafe-eval'; " .
    "worker-src 'self'; " .
    "connect-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "font-src 'self' data:; " .
    "object-src 'none'; base-uri 'self'; " .
    "form-action 'self'; frame-ancestors 'self'"
);

$admin_page_title = 'Python 실행 실험';
$admin_active_menu = '';
require(__DIR__ . '/admin-layout-start.php');
?>
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Python 실행 실험</h1>
            <p class="admin-page-description">
                고정 예제로 브라우저 실행·중지·재실행을 확인합니다.
                DB에는 저장하지 않습니다.
            </p>
        </div>
    </div>

    <section class="admin-card">
        <p>
            <button id="python-poc-static" type="button"
                    class="admin-btn admin-btn-primary">
                정적 그림 실행
            </button>
            <button id="python-poc-animation" type="button"
                    class="admin-btn admin-btn-primary">
                움직이는 공 실행
            </button>
            <button id="python-poc-check" type="button"
                    class="admin-btn admin-btn-primary">
                Python 실행 확인
            </button>
            <button id="python-poc-loop" type="button"
                    class="admin-btn admin-btn-secondary">
                무한 반복 중지 검사
            </button>
            <button id="python-poc-stop" type="button"
                    class="admin-btn admin-btn-secondary" disabled>
                중지
            </button>
        </p>

        <p id="python-poc-status" role="status" aria-live="polite">
            실행 버튼을 눌러 주세요. 처음 실행할 때는 파일 로딩 시간이 필요합니다.
        </p>
        <pre id="python-poc-output"></pre>
        <canvas id="python-poc-canvas"
                width="400" height="400"
                aria-label="Python 그림 실행 결과"
                style="display:block;max-width:100%;height:auto;border:1px solid #ccc;background:white;">
        </canvas>
        <p>애니메이션은 120프레임까지 실행합니다. 표시 속도는 기기에 따라 달라질 수 있습니다.</p>
    </section>
</div>

<script type="module"
        src="/template/syzoj/js/course-python-poc.js?v=3"></script>
<?php require(__DIR__ . '/admin-layout-end.php'); ?>
