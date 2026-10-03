<?php
$show_title = $view_title;
include(__DIR__ . "/../header.php");
?>
<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools-common.css?v=20261003-2">


<style>
    .roulette-container {
        position: relative;
        width: 100%;
        max-width: 480px;
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
        width: 80%;
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

    .responsive-button-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
    }

    .responsive-button-group .ui.button {
        flex: 1 1 120px;
        margin: 0 !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>

<div class="ui container tools-detail-page" style="max-width: 1100px; margin-top: 30px; margin-bottom: 50px;">
    <h1 class="ui header teal center aligned" style="margin-bottom: 20px;">
        <i class="dot circle outline orange icon"></i>
        <div class="content">
            마블 룰렛 학생 추첨기
            <div class="sub header">원형 룰렛과 구슬(Marble) 애니메이션으로 공정하고 흥미롭게 발표자를 추첨합니다.</div>
        </div>
    </h1>

    <div class="ui stackable grid">
        <!-- 좌측: 설정 및 명단 패널 -->
        <div class="five wide column">
            <div class="ui segment teal">
                <h3 class="ui header teal" style="margin-top: 0;">
                    <i class="settings icon"></i> 추첨 대상 설정
                </h3>

                <div class="ui form">
                    <div class="field">
                        <label>학생 인원수 (1번 ~ N번 생성)</label>
                        <div class="ui action input">
                            <input type="number" id="max-student-num" value="12" min="2" max="60">
                            <button class="ui orange button" onclick="setByRange()"><i class="plus icon"></i> 생성</button>
                        </div>
                    </div>

                    <div class="field" style="margin-top: 1em;">
                        <label>
                            학생 이름 / 번호 목록 (줄바꿈 구분)
                            <span class="ui teal label tiny right floated" id="candidate-count-badge">0명</span>
                        </label>
                        <textarea id="student-list" rows="8" placeholder="1번&#10;2번&#10;3번&#10;또는&#10;김철수&#10;이영희"></textarea>
                    </div>

                    <div class="field">
                        <div class="ui checkbox checkbox-prevent">
                            <input type="checkbox" id="prevent-duplicate" checked>
                            <label><b>중복 추첨 제외</b> (당첨자 자동 제거)</label>
                        </div>
                    </div>

                    <div class="ui section divider" style="margin: 15px 0;"></div>

                    <button class="ui teal fluid button" onclick="initBoard()">
                        <i class="redo icon"></i> 룰렛 판 다시그리기
                    </button>
                </div>
            </div>
        </div>

        <!-- 우측: 룰렛 시각화 및 추첨 메인 화면 -->
        <div class="eleven wide column">
            <div class="ui segment center aligned" style="padding: 2em 1em; background-color: #f8fafc; min-height: 520px;">
                <div class="roulette-container">
                    <canvas id="rouletteCanvas" width="460" height="460"></canvas>

                    <div id="winnerModal" class="winner-modal">
                        <div style="font-size: 1.1em; color: #f97316; margin-bottom: 5px;">🎉 당첨자 발표 🎉</div>
                        <div id="winnerText" style="font-size: 2.2em; font-weight: bold; word-break: break-all;">-</div>
                        <button class="ui mini orange button" style="margin-top: 15px;" onclick="closeModal()">확인</button>
                    </div>
                </div>

                <div style="margin-top: 1.5em;">
                    <button class="ui massive orange button" id="btn-spin" onclick="spinMarble()">
                        <i class="play icon"></i> 마블 구슬 굴리기!
                    </button>
                </div>

                <!-- 추첨 기록 및 관리 -->
                <div style="margin-top: 2em; text-align: left;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <h5 class="ui header style-margin-0"><i class="history icon"></i> 당첨자 목록</h5>
                        <button class="ui mini button basic red" onclick="clearHistory()"><i class="trash icon"></i> 기록 초기화</button>
                    </div>
                    <div id="history-box" class="ui labels" style="min-height: 38px; padding: 8px; background: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <span class="ui label grey">추첨 기록이 여기에 표시됩니다.</span>
                    </div>
                </div>

                <div class="ui section divider" style="margin: 20px 0;"></div>

                <!-- 하단 유틸리티 이동 버튼 -->
                <div class="responsive-button-group">
                    <a href="/tools/" class="ui button grey tools-detail-back">
                        <i class="arrow left icon"></i> 유틸리티 목록
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const canvas = document.getElementById('rouletteCanvas');
        const ctx = canvas.getContext('2d');
        const modal = document.getElementById('winnerModal');
        const winnerText = document.getElementById('winnerText');
        const candidateBadge = document.getElementById('candidate-count-badge');
        const studentTextarea = document.getElementById('student-list');
        const preventDuplicateCheck = document.getElementById('prevent-duplicate');

        let candidates = [];
        let historyList = [];
        let isSpinning = false;

        let currentAngle = 0;
        let marbleAngle = 0;
        let marbleRadius = 185;
        let spinSpeed = 0;
        let marbleSpeed = 0;
        let animId = null;

        const colors = [
            '#f97316', '#0ea5e9', '#10b981', '#8b5cf6',
            '#ec4899', '#f59e0b', '#06b6d4', '#84cc16',
            '#6366f1', '#d97706', '#14b8a6', '#a855f7'
        ];

        // 번호 범위 자동 생성
        window.setByRange = function() {
            const maxNum = parseInt(document.getElementById('max-student-num').value) || 12;
            let list = [];
            for (let i = 1; i <= maxNum; i++) {
                list.push(`${i}번`);
            }
            studentTextarea.value = list.join('\n');
            initBoard();
        };

        // 텍스트 영역에서 후보 목록 불러오기
        function getRawCandidates() {
            const text = studentTextarea.value.trim();
            if (text === '') return [];
            return text.split('\n').map(s => s.trim()).filter(s => s.length > 0);
        }

        // 보드 초기화
        window.initBoard = function() {
            modal.style.display = 'none';
            let raw = getRawCandidates();

            // 텍스트 영역이 비어있으면 기본값 세팅
            if (raw.length === 0) {
                setByRange();
                return;
            }

            // 중복 추첨 제외 옵션 적용
            if (preventDuplicateCheck.checked) {
                candidates = raw.filter(c => !historyList.includes(c));
            } else {
                candidates = [...raw];
            }

            candidateBadge.textContent = `${candidates.length}명 남음`;

            if (candidates.length === 0) {
                drawEmptyBoard("추첨할 대상이 없습니다!");
                return;
            }

            marbleAngle = 0;
            marbleRadius = 185;
            drawRoulette();
        };

        // 빈 상태 룰렛 그리기
        function drawEmptyBoard(msg) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;

            ctx.beginPath();
            ctx.arc(centerX, centerY, 210, 0, Math.PI * 2);
            ctx.fillStyle = '#e2e8f0';
            ctx.fill();
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 4;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.font = 'bold 18px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(msg, centerX, centerY);
        }

        // 룰렛 판 및 구슬 구현
        function drawRoulette() {
            const numSlices = candidates.length;
            if (numSlices === 0) return;

            const sliceAngle = (Math.PI * 2) / numSlices;
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = 210;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // 1. 섹션 그리기
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

                // 텍스트
                ctx.save();
                ctx.translate(centerX, centerY);
                ctx.rotate(startA + sliceAngle / 2);
                ctx.textAlign = 'right';
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 15px sans-serif';
                ctx.fillText(candidates[i], radius - 20, 5);
                ctx.restore();
            }

            // 2. 외각 테두리 & 중앙 원
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.lineWidth = 8;
            ctx.strokeStyle = '#1e293b';
            ctx.stroke();

            ctx.beginPath();
            ctx.arc(centerX, centerY, 30, 0, Math.PI * 2);
            ctx.fillStyle = '#1e293b';
            ctx.fill();

            // 3. 마블 구슬 (Marble)
            const marbleX = centerX + Math.cos(marbleAngle) * marbleRadius;
            const marbleY = centerY + Math.sin(marbleAngle) * marbleRadius;

            ctx.beginPath();
            ctx.arc(marbleX, marbleY, 11, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.lineWidth = 3;
            ctx.strokeStyle = '#e2e8f0';
            ctx.stroke();

            ctx.beginPath();
            ctx.arc(marbleX - 3, marbleY - 3, 3, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.fill();
        }

        // 마블 굴리기
        window.spinMarble = function() {
            if (isSpinning) return;
            modal.style.display = 'none';

            initBoard();

            if (candidates.length < 1) {
                alert("추첨 대상이 없습니다! 목록을 확인해 주세요.");
                return;
            }

            isSpinning = true;
            document.getElementById('btn-spin').disabled = true;

            spinSpeed = 0.25 + Math.random() * 0.1;
            marbleSpeed = -(0.35 + Math.random() * 0.15);
            marbleRadius = 185;

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
                    animId = requestAnimationFrame(animate);
                }
            }

            animate();
        };

        // 당첨자 판정
        function determineWinner() {
            const numSlices = candidates.length;
            if (numSlices === 0) return;

            const sliceAngle = (Math.PI * 2) / numSlices;

            let normMarbleA = (marbleAngle % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2);
            let normBoardA = (currentAngle % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2);

            let relativeAngle = (normMarbleA - normBoardA + Math.PI * 2) % (Math.PI * 2);
            let winningIndex = Math.floor(relativeAngle / sliceAngle);

            if (winningIndex >= numSlices) winningIndex = numSlices - 1;

            const winner = candidates[winningIndex];

            winnerText.innerText = winner;
            modal.style.display = 'block';

            if (!historyList.includes(winner)) {
                historyList.push(winner);
                updateHistoryUI();
            }

            // 중복 제외 설정인 경우 다음 추첨을 위해 보드 갱신 준비
            setTimeout(() => {
                initBoard();
            }, 800);
        }

        window.closeModal = function() {
            modal.style.display = 'none';
        };

        // 당첨 기록 UI 업데이트
        function updateHistoryUI() {
            const box = document.getElementById('history-box');
            if (historyList.length === 0) {
                box.innerHTML = '<span class="ui label grey">추첨 기록이 여기에 표시됩니다.</span>';
                return;
            }
            box.innerHTML = historyList.map(item => `<span class="ui orange label">${item}</span>`).join(' ');
        }

        // 기록 초기화
        window.clearHistory = function() {
            if (confirm("당첨 내역을 모두 초기화하시겠습니까?")) {
                historyList = [];
                updateHistoryUI();
                initBoard();
            }
        };

        studentTextarea.addEventListener('input', initBoard);
        preventDuplicateCheck.addEventListener('change', initBoard);

        // 최초 1회 초기화 실행
        setByRange();
    });
</script>

<?php include(__DIR__ . "/../footer.php"); ?>