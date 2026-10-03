<?php
$show_title = $view_title;
include __DIR__ . "/../header.php";
?>
<link rel="stylesheet" href="/tools/css/tools-common.css">

<div class="ui container" style="margin-top: 2em; margin-bottom: 3em;">
    <h2 class="ui dividing header">
        <i class="chart line teal icon"></i>
        <div class="content">
            <?php echo htmlspecialchars($view_title, ENT_QUOTES, 'UTF-8'); ?>
            <div class="sub header">다양한 교과 수학 함수식을 선택하고 계수를 조절하여 그래프를 실시간 탐구합니다.</div>
        </div>
    </h2>

    <div class="ui stackable grid">
        <!-- 컨트롤 패널 -->
        <div class="six wide column">
            <div class="ui segment">
                <h4 class="ui header">함수 및 계수 설정</h4>
                <div class="ui form">
                    <div class="field">
                        <label>함수 유형 선택</label>
                        <select id="funcType" class="ui dropdown" onchange="onFuncChange()">
                            <optgroup label="다항/유리/무리함수 (공통수학/수학Ⅱ)">
                                <option value="linear">1차함수: y = ax + b</option>
                                <option value="quad" selected>2차함수: y = ax² + bx + c</option>
                                <option value="cubic">3차함수: y = ax³ + bx² + cx + d</option>
                                <option value="rational">유리함수: y = a / (x - b) + c</option>
                                <option value="irrational">무리함수: y = a√(x - b) + c</option>
                                <option value="abs">절댓값함수: y = a|x - b| + c</option>
                            </optgroup>
                            <optgroup label="삼각/지수/로그함수 (수학Ⅰ)">
                                <option value="sin">사인함수: y = a·sin(bx + c) + d</option>
                                <option value="cos">코사인함수: y = a·cos(bx + c) + d</option>
                                <option value="exp">지수함수: y = a·2^(bx + c) + d</option>
                                <option value="log">로그함수: y = a·log₂(bx + c) + d</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="ui divider"></div>

                    <div class="field" id="fieldA">
                        <label>a = <span id="valA">1</span></label>
                        <input type="range" id="paramA" min="-5" max="5" step="0.1" value="1" oninput="drawGraph()">
                    </div>
                    <div class="field" id="fieldB">
                        <label>b = <span id="valB">0</span></label>
                        <input type="range" id="paramB" min="-5" max="5" step="0.1" value="0" oninput="drawGraph()">
                    </div>
                    <div class="field" id="fieldC">
                        <label>c = <span id="valC">0</span></label>
                        <input type="range" id="paramC" min="-5" max="5" step="0.1" value="0" oninput="drawGraph()">
                    </div>
                    <div class="field" id="fieldD" style="display:none;">
                        <label>d = <span id="valD">0</span></label>
                        <input type="range" id="paramD" min="-5" max="5" step="0.1" value="0" oninput="drawGraph()">
                    </div>

                    <button type="button" class="ui button tiny fluid gray" onclick="resetParams()">계수 초기화</button>
                </div>
            </div>

            <div class="ui segment teal text-center">
                <strong>수식: </strong><span id="formulaText" style="font-size: 1.1em; color: #008080;"></span>
            </div>
        </div>

        <!-- 캔버스 영역 -->
        <div class="ten wide column">
            <div class="ui segment text-center">
                <canvas id="graphCanvas" width="550" height="420" style="border:1px solid #ddd; background:#fff; width:100%; max-width:550px;"></canvas>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5em;">
        <a href="/tools.php" class="ui button"><i class="arrow left icon"></i> 유틸리티 목록으로 돌아가기</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        onFuncChange();
    });

    function onFuncChange() {
        const type = document.getElementById('funcType').value;
        const fieldD = document.getElementById('fieldD');

        if (['cubic', 'sin', 'cos', 'exp', 'log'].includes(type)) {
            fieldD.style.display = 'block';
        } else {
            fieldD.style.display = 'none';
        }
        drawGraph();
    }

    function resetParams() {
        document.getElementById('paramA').value = 1;
        document.getElementById('paramB').value = 0;
        document.getElementById('paramC').value = 0;
        document.getElementById('paramD').value = 0;
        drawGraph();
    }

    function drawGraph() {
        const canvas = document.getElementById('graphCanvas');
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;

        const a = parseFloat(document.getElementById('paramA').value);
        const b = parseFloat(document.getElementById('paramB').value);
        const c = parseFloat(document.getElementById('paramC').value);
        const d = parseFloat(document.getElementById('paramD').value);

        document.getElementById('valA').textContent = a;
        document.getElementById('valB').textContent = b;
        document.getElementById('valC').textContent = c;
        document.getElementById('valD').textContent = d;

        const type = document.getElementById('funcType').value;
        updateFormulaDisplay(type, a, b, c, d);

        ctx.clearRect(0, 0, width, height);

        const centerX = width / 2;
        const centerY = height / 2;
        const scale = 25; // 1단위 당 25px

        // 격자 및 축 그리기
        ctx.strokeStyle = '#f0f0f0';
        ctx.lineWidth = 1;
        for (let x = 0; x < width; x += scale) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
        }
        for (let y = 0; y < height; y += scale) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(width, y);
            ctx.stroke();
        }

        ctx.strokeStyle = '#999';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(0, centerY);
        ctx.lineTo(width, centerY);
        ctx.moveTo(centerX, 0);
        ctx.lineTo(centerX, height);
        ctx.stroke();

        // 곡선 렌더링
        ctx.strokeStyle = '#00b5ad';
        ctx.lineWidth = 2.5;
        ctx.beginPath();

        let isFirst = true;
        for (let pixelX = 0; pixelX < width; pixelX++) {
            const x = (pixelX - centerX) / scale;
            let y = null;

            try {
                if (type === 'linear') y = a * x + b;
                else if (type === 'quad') y = a * x * x + b * x + c;
                else if (type === 'cubic') y = a * x * x * x + b * x * x + c * x + d;
                else if (type === 'rational') {
                    if (Math.abs(x - b) > 0.05) y = a / (x - b) + c;
                } else if (type === 'irrational') {
                    if (x - b >= 0) y = a * Math.sqrt(x - b) + c;
                } else if (type === 'abs') y = a * Math.abs(x - b) + c;
                else if (type === 'sin') y = a * Math.sin(b * x + c) + d;
                else if (type === 'cos') y = a * Math.cos(b * x + c) + d;
                else if (type === 'exp') y = a * Math.pow(2, b * x + c) + d;
                else if (type === 'log') {
                    if (b * x + c > 0) y = a * (Math.log2(b * x + c)) + d;
                }
            } catch (e) {
                y = null;
            }

            if (y !== null && !isNaN(y) && isFinite(y)) {
                const pixelY = centerY - y * scale;
                if (isFirst) {
                    ctx.moveTo(pixelX, pixelY);
                    isFirst = false;
                } else {
                    ctx.lineTo(pixelX, pixelY);
                }
            } else {
                isFirst = true;
            }
        }
        ctx.stroke();
    }

    function updateFormulaDisplay(type, a, b, c, d) {
        let str = "y = ";
        if (type === 'linear') str += `${a}x + ${b}`;
        else if (type === 'quad') str += `${a}x² + ${b}x + ${c}`;
        else if (type === 'cubic') str += `${a}x³ + ${b}x² + ${c}x + ${d}`;
        else if (type === 'rational') str += `${a} / (x - ${b}) + ${c}`;
        else if (type === 'irrational') str += `${a}√(x - ${b}) + ${c}`;
        else if (type === 'abs') str += `${a}|x - ${b}| + ${c}`;
        else if (type === 'sin') str += `${a}·sin(${b}x + ${c}) + ${d}`;
        else if (type === 'cos') str += `${a}·cos(${b}x + ${c}) + ${d}`;
        else if (type === 'exp') str += `${a}·2^(${b}x + ${c}) + ${d}`;
        else if (type === 'log') str += `${a}·log₂(${b}x + ${c}) + ${d}`;
        document.getElementById('formulaText').textContent = str;
    }
</script>

<?php include __DIR__ . "/../footer.php"; ?>