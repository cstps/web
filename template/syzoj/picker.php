<?php
$show_title = $view_title;
include("header.php");
?>

<style>
    .roulette-container {
        position: relative;
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        text-align: center;
    }

    #rouletteCanvas {
        background: #ffffff;
        border-radius: 50%;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        max-width: 100%;
        height: auto;
    }

    .winner-modal {
        display: none;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(15, 23, 42, 0.95);
        color: #ffffff;
        padding: 20px 30px;
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        z-index: 10;
        text-align: center;
        animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes popIn {
        0% {
            transform: translate(-50%, -50%) scale(0.5);
            opacity: 0;
        }

        100% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
    }
</style>

<div class="ui container" style="margin-top: 2em; margin-bottom: 3em;">
    <h2 class="ui dividing header">
        <i class="dot circle outline orange icon"></i>
        <div class="content">
            마블 룰렛 학생 추첨기
            <div class="sub header">원형 룰렛과 구슬(Marble) 애니메이션으로 공정하고 흥미롭게 발표자를 추첨합니다.</div>
        </div>
    </h2>

    <div class="ui grid stackable">
        <div class="five wide column">
            <div class="ui segment" style="padding: 1.5em;">
                <h4 class="ui header"><i class="settings icon"></i> 추첨 대상 설정</h4>
                <div class="ui form">
                    <div class="field">
                        <label>학생 인원수 (1번 ~ N번)</label>
                        <div class="ui action input">
                            <input type="number" id="max-student-num" value="12" min="2" max="50">
                            <button class="ui orange button" onclick="setByRange()">적용</button>
                        </div>
                    </div>

                    <div class="field" style="margin-top: 1em;">
                        <label>또는 이름 목록 (줄바꿈 구분)</label>
                        <textarea id="student-list" rows="6" placeholder="김철수&#10;이영희&#10;박민수"></textarea>
                    </div>

                    <div class="field">
                        <div class="ui checkbox">
                            <input type="checkbox" id="prevent-duplicate">
                            <label>중복 추첨 제외 (당첨자 자동 제거)</label>
                        </div>
                    </div>

                    <button class="ui orange fluid large button" onclick="initBoard()" style="margin-top: 1em;">
                        <i class="redo icon"></i> 룰렛 판 다시그리기
                    </button>
                </div>
            </div>
        </div>

        <div class="eleven wide column">
            <div class="ui segment text center" style="padding: 2em 1em; background-color: #f8fafc; min-height: 520px;">
                <div class="roulette-container">
                    <canvas id="rouletteCanvas" width="460" height="460"></canvas>

                    <div id="winnerModal" class="winner-modal">
                        <div style="font-size: 1.1em; color: #f97316; margin-bottom: 5px;">🎉 당첨자 발표 🎉</div>
                        <div id="winnerText" style="font-size: 2.5em; font-weight: bold;">-</div>
                    </div>
                </div>

                <div style="margin-top: 1.5em;">
                    <button class="ui massive orange button" id="btn-spin" onclick="spinMarble()">
                        <i class="play icon"></i> 마블 구슬 굴리기!
                    </button>
                </div>

                <div style="margin-top: 2em; text-align: left;">
                    <h5 class="ui header"><i class="history icon"></i> 추첨된 당첨자 목록</h5>
                    <div id="history-box" class="ui labels">
                        <span class="ui label grey">추첨 기록이 여기에 표시됩니다.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5em;">
        <a href="tools.php" class="ui button"><i class="arrow left icon"></i> 유틸리티 목록으로 돌아가기</a>
    </div>
</div>

<script>
    const canvas = document.getElementById('rouletteCanvas');
    const ctx = canvas.getContext('2d');
    const modal = document.getElementById('winnerModal');
    const winnerText = document.getElementById('winnerText');

    let candidates = [];
    let historyList = [];
    let isSpinning = false;

    let currentAngle = 0;
    let marbleAngle = 0;
    let marbleRadius = 180;
    let spinSpeed = 0;
    let marbleSpeed = 0;

    const colors = [
        '#f97316', '#0ea5e9', '#10b981', '#8b5cf6',
        '#ec4899', '#f59e0b', '#06b6d4', '#84cc16',
        '#6366f1', '#d97706', '#14b8a6', '#a855f7'
    ];

    function getCandidates() {
        let text = document.getElementById('student-list').value.trim();
        if (text !== '') {
            return text.split('\n').map(s => s.trim()).filter(s => s.length > 0);
        } else {
            let maxNum = parseInt(document.getElementById('max-student-num').value) || 12;
            let list = [];
            for (let i = 1; i <= maxNum; i++) list.push(`${i}번`);
            return list;
        }
    }

    function setByRange() {
        document.getElementById('student-list').value = '';
        initBoard();
    }

    function initBoard() {
        modal.style.display = 'none';
        candidates = getCandidates();

        if (document.getElementById('prevent-duplicate').checked) {
            candidates = candidates.filter(c => !historyList.includes(c));
        }

        if (candidates.length < 2) {
            alert("추첨 대상이 최소 2명 이상이어야 합니다.");
            return;
        }

        marbleAngle = 0;
        marbleRadius = 195;
        drawRoulette();
    }

    function drawRoulette() {
        const numSlices = candidates.length;
        const sliceAngle = (Math.PI * 2) / numSlices;
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 210;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        for (let i = 0; i < numSlices; i++) {
            const startA = currentAngle + i * sliceAngle;
            const endA = startA + sliceAngle;

            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startA, endA);
            ctx.closePath();
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#ffffff';
            ctx.stroke();

            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(startA + sliceAngle / 2);
            ctx.textAlign = 'right';
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 15px sans-serif';
            ctx.fillText(candidates[i], radius - 20, 5);
            ctx.restore();
        }

        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
        ctx.lineWidth = 8;
        ctx.strokeStyle = '#1e293b';
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(centerX, centerY, 30, 0, Math.PI * 2);
        ctx.fillStyle = '#1e293b';
        ctx.fill();

        const marbleX = centerX + Math.cos(marbleAngle) * marbleRadius;
        const marbleY = centerY + Math.sin(marbleAngle) * marbleRadius;

        ctx.beginPath();
        ctx.arc(marbleX, marbleY, 12, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#e2e8f0';
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(marbleX - 3, marbleY - 3, 4, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
        ctx.fill();
    }

    function spinMarble() {
        if (isSpinning) return;
        modal.style.display = 'none';

        if (candidates.length < 2) {
            initBoard();
            if (candidates.length < 2) return;
        }

        isSpinning = true;
        document.getElementById('btn-spin').disabled = true;

        spinSpeed = 0.25 + Math.random() * 0.1;
        marbleSpeed = -(0.35 + Math.random() * 0.15);
        marbleRadius = 195;

        function animate() {
            spinSpeed *= 0.985;
            marbleSpeed *= 0.982;

            currentAngle += spinSpeed;
            marbleAngle += marbleSpeed;

            if (marbleRadius > 110) {
                marbleRadius -= 0.35;
            }

            drawRoulette();

            if (Math.abs(spinSpeed) < 0.002 && Math.abs(marbleSpeed) < 0.002) {
                isSpinning = false;
                document.getElementById('btn-spin').disabled = false;
                determineWinner();
            } else {
                requestAnimationFrame(animate);
            }
        }

        animate();
    }

    function determineWinner() {
        const numSlices = candidates.length;
        const sliceAngle = (Math.PI * 2) / numSlices;

        let normMarbleA = (marbleAngle % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2);
        let normBoardA = (currentAngle % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2);

        let relativeAngle = (normMarbleA - normBoardA + Math.PI * 2) % (Math.PI * 2);
        let winningIndex = Math.floor(relativeAngle / sliceAngle);

        let winner = candidates[winningIndex];

        winnerText.innerText = winner;
        modal.style.display = 'block';

        if (!historyList.includes(winner)) {
            historyList.push(winner);
            updateHistoryUI();
        }
    }

    function updateHistoryUI() {
        let box = document.getElementById('history-box');
        if (historyList.length === 0) {
            box.innerHTML = '<span class="ui label grey">추첨 내역이 없습니다.</span>';
            return;
        }
        box.innerHTML = historyList.map(item => `<span class="ui orange label">${item}</span>`).join(' ');
    }

    initBoard();
</script>

<?php include("footer.php"); ?>