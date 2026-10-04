<?php
$test_run_readonly = !empty($view_source_readonly);
?>
<link rel="stylesheet" href="template/syzoj/css/submit-test-run.css?v=<?php
    echo rawurlencode((string)filemtime(__DIR__ . '/css/submit-test-run.css'));
?>">

<section class="submit-test-run" aria-labelledby="test-run-title">
    <h2 id="test-run-title">코드 실행</h2>
    <p>입력값으로 코드를 실행합니다. 정답 여부는 제출 후 확인할 수 있습니다.</p>
    <div class="test-run-grid">
        <div>
            <label for="input_text">실행 입력</label>
            <textarea id="input_text" name="input_text" rows="6"><?php
                echo htmlspecialchars((string)($view_sample_input ?? ''), ENT_QUOTES, 'UTF-8');
            ?></textarea>
        </div>
        <div>
            <label for="out">예제의 예상 출력</label>
            <textarea id="out" rows="6" readonly><?php
                echo htmlspecialchars((string)($view_sample_output ?? ''), ENT_QUOTES, 'UTF-8');
            ?></textarea>
        </div>
    </div>
    <?php if (!$test_run_readonly) { ?>
        <div class="test-run-actions">
            <button type="button" id="test-run-button" class="ui teal button" disabled>실행</button>
            <button type="button" id="test-run-retry" class="ui button" hidden>결과 다시 확인</button>
        </div>
    <?php } ?>
    <p id="test-run-message" role="status" aria-live="polite" aria-atomic="true"></p>
    <section id="test-run-results" aria-labelledby="test-run-result-title" hidden>
        <h3 id="test-run-result-title">실행 결과</h3>
        <dl class="test-run-summary">
            <div><dt>상태</dt><dd id="test-run-status">—</dd></div>
            <div><dt>시간</dt><dd id="test-run-time">—</dd></div>
            <div><dt>메모리</dt><dd id="test-run-memory">—</dd></div>
        </dl>
        <div class="test-run-grid">
            <div><h4>표준 출력</h4><pre id="test-run-stdout" tabindex="0"></pre></div>
            <div><h4>오류 출력</h4><pre id="test-run-stderr" tabindex="0"></pre></div>
        </div>
        <div id="test-run-compile-box" hidden>
            <h4>컴파일 오류</h4><pre id="test-run-compile" tabindex="0"></pre>
        </div>
        <p id="test-run-warnings" hidden></p>
    </section>
</section>

<script src="template/syzoj/submit-test-run.js?v=<?php
    echo rawurlencode((string)filemtime(__DIR__ . '/submit-test-run.js'));
?>" defer></script>
