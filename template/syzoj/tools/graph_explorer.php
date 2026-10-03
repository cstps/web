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
            <div class="sub header">함수식을 선택하고 계수를 조절하여 그래프의 성질을 실시간 탐구합니다.</div>
        </div>
    </h2>

    <div class="ui stackable grid">
        <div class="six wide column">
            <div class="ui segment">
                <h4 class="ui header">함수 및 계수 설정</h4>
                <div class="ui form">
                    <div class="field">
                        <label>함수 유형</label>
                        <select id="funcType" class="ui dropdown" onchange="updateFunc()">
                            <option value="quad">이차함수: y = ax² + bx + c</option>
                            <option value="sin">삼각함수: y = a·sin(bx + c)</option>
                            <option value="exp">지수함수: y = a·2^(bx) + c</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>a = <span id="valA">1</span></label>
                        <input type="range" id="paramA" min="-5" max="5" step="0.1" value="1" oninput="drawGraph()">
                    </div>
                    <div class="field">
                        <label>b = <span id="valB">1</span></label>
                        <input type="range" id="paramB" min="-5" max="5" step="0.1" value="1" oninput="drawGraph()">
                    </div>
                    <div class="field">
                        <label>c = <span id="valC">0</span></label>
                        <input type="range" id="paramC" min="-5" max="5" step="0.1" value="0" oninput="drawGraph()">
                    </div>
                </div>
            </div>
        </div>
        <div class="ten wide column">
            <div class="ui segment text-center">
                <canvas id="graphCanvas" width="500" height="400" style="border:1px solid #ddd; background:#fff; width:100%; max-width:500px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        drawGraph();
    });

    function drawGraph() {
        const canvas = document.getElementById('graphCanvas');
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;

        const a = parseFloat(document.getElementById('paramA').value);
        const b = parseFloat(document.getElementById('paramB').value);
        const c = parseFloat(document.getElementById('paramC').value);

        document.getElementById('valA').textContent = a;
        document.getElementById('valB').textContent = b;
        document.getElementById('valC').textContent = c;

        ctx.clearRect(0, 0, width, height);

        // 축 그리기
        const centerX = width / 2;
        const centerY = height / 2;
        const scale = 20; // 1단위당 피스셀

        ctx.strokeStyle = '#ccc';
        ctx.beginPath();
        ctx.moveTo(0, centerY);
        ctx.lineTo(width, centerY);
        ctx.moveTo(centerX, 0);
        ctx.lineTo(centerX, height);
        ctx.stroke();

        // 함수 곡선 그리기
        const type = document.getElementById('funcType').value;
        ctx.strokeStyle = '#00b5ad';
        ctx.lineWidth = 2;
        ctx.beginPath();

        let isFirst = true;
        for (let pixelX = 0; pixelX < width; pixelX++) {
            const x = (pixelX - centerX) / scale;
            let y = 0;

            if (type === 'quad') y = a * x * x + b * x + c;
            else if (type === 'sin') y = a * Math.sin(b * x + c);
            else if (type === 'exp') y = a * Math.pow(2, b * x) + c;

            const pixelY = centerY - y * scale;

            if (isFirst) {
                ctx.moveTo(pixelX, pixelY);
                isFirst = false;
            } else {
                ctx.lineTo(pixelX, pixelY);
            }
        }
        ctx.stroke();
    }

    function updateFunc() {
        drawGraph();
    }
</script>

<?php include __DIR__ . "/../footer.php"; ?>