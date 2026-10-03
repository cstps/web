<?php
$show_title = $view_title;
include(__DIR__ . "/../header.php");
?>
<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools-common.css?v=20261003-2">


<div class="ui container tools-detail-page" style="max-width: 1100px; margin-top: 30px; margin-bottom: 50px;">
    <!-- 헤더 -->
    <h1 class="ui header teal center aligned" style="margin-bottom: 20px;">
        <i class="random icon"></i>
        <div class="content">
            온라인 사다리 타기
            <div class="sub header">발표자 지정, 순번 정하기, 내기 추첨에 활용하는 실시간 사다리 게임입니다.</div>
        </div>
    </h1>

    <div class="ui stackable grid">
        <!-- 좌측: 설정 및 명단 입력 패널 (5 열) -->
        <div class="five wide column">
            <div class="ui segment teal">
                <h3 class="ui header teal" style="margin-top: 0;">
                    <i class="edit icon"></i> 명단 입력
                </h3>

                <form class="ui form" onsubmit="return false;">
                    <div class="field">
                        <label><i class="users icon"></i> 참가자 명단 (1줄에 1명)</label>
                        <textarea id="ladderNames" rows="6" placeholder="김민수&#10;이지우&#10;박서연&#10;최현우"></textarea>
                    </div>

                    <div class="field">
                        <label><i class="trophy icon"></i> 결과 항목 (비워두면 1번~N번 자동)</label>
                        <textarea id="ladderResults" rows="6" placeholder="1번 자리&#10;2번 자리&#10;3번 자리&#10;4번 자리"></textarea>
                    </div>

                    <div class="ui section divider" style="margin: 15px 0;"></div>

                    <button class="ui primary fluid button" id="btnDrawLadder">
                        <i class="table icon"></i> 1. 사다리 생성
                    </button>
                    <button class="ui green fluid button" id="btnStartAll" disabled style="margin-top: 8px;">
                        <i class="play icon"></i> 2. 전체 한번에 추첨
                    </button>
                    <button class="ui orange fluid button" id="btnReset" style="margin-top: 8px; display: none;">
                        <i class="redo icon"></i> 다시 설정하기
                    </button>
                </form>
            </div>
        </div>

        <!-- 우측: 사다리 Canvas 및 추첨 영역 (11 열) -->
        <div class="eleven wide column">
            <div class="ui segment center aligned">
                <!-- 상단 버튼 그룹 -->
                <div class="responsive-button-group" style="margin-bottom: 15px; justify-content: flex-end;">
                    <button type="button" class="ui blue button" id="btnCopyResult" style="display: none;">
                        <i class="copy icon"></i> 결과 복사
                    </button>
                    <a href="/tools/" class="ui button grey tools-detail-back">
                        <i class="arrow left icon"></i> 유틸리티 목록
                    </a>
                </div>

                <!-- 안내 메시지 -->
                <div class="ui info tiny message left aligned" style="margin-bottom: 15px;">
                    <i class="help circle icon"></i> <b>사용 방법:</b> 명단을 입력 후 <b>[사다리 생성]</b>을 누른 뒤, 사다리 상단의 <b>참가자 이름 버튼</b>을 누르면 개인별 경로를 확인할 수 있습니다.
                </div>

                <!-- 참가자 개별 클릭 선택 버튼 영역 -->
                <div id="participantButtons" class="ui small basic buttons fluid style-flex-wrap" style="margin-bottom: 15px; display: none; flex-wrap: wrap; gap: 5px;"></div>

                <!-- 사다리 Canvas 캔버스 영역 -->
                <div id="canvasContainer" style="overflow-x: auto; padding: 10px; background: #f8fafc; border-radius: 8px; min-height: 380px;">
                    <div id="placeholderMsg" class="ui placeholder segment">
                        <div class="ui icon header">
                            <i class="random icon teal"></i>
                            좌측 패널에 참가자와 결과를 입력한 후 [사다리 생성]을 클릭해 주세요.
                        </div>
                    </div>
                    <canvas id="ladderCanvas" style="display: none; max-width: 100%; height: auto; border-radius: 8px; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.05);"></canvas>
                </div>

                <!-- 최종 추첨 결과 표시 영역 -->
                <div id="ladderResult" style="margin-top: 20px; text-align: left;"></div>
            </div>
        </div>
    </div>
</div>

<style>
    /* 반응형 버튼 그룹 스타일 */
    .responsive-button-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .responsive-button-group .ui.button {
        flex: 0 1 auto;
        margin: 0 !important;
    }

    .style-flex-wrap {
        display: flex !important;
        flex-wrap: wrap !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        let ladderData = null;
        let isAnimating = false;
        let finalPairings = {};

        const ladderNamesInput = document.getElementById('ladderNames');
        const ladderResultsInput = document.getElementById('ladderResults');
        const btnDrawLadder = document.getElementById('btnDrawLadder');
        const btnStartAll = document.getElementById('btnStartAll');
        const btnReset = document.getElementById('btnReset');
        const btnCopyResult = document.getElementById('btnCopyResult');
        const participantButtons = document.getElementById('participantButtons');
        const placeholderMsg = document.getElementById('placeholderMsg');
        const canvas = document.getElementById('ladderCanvas');
        const resultBox = document.getElementById('ladderResult');

        function shuffle(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
        }

        // 1. 사다리 데이터 및 선분 생성
        function drawLadder() {
            if (isAnimating) return;

            const names = ladderNamesInput.value.split('\n').map(x => x.trim()).filter(x => x);
            let results = ladderResultsInput.value.split('\n').map(x => x.trim()).filter(x => x);

            if (names.length < 2) {
                alert("참가자는 최소 2명 이상 입력해 주세요!");
                return;
            }

            if (results.length === 0) {
                results = names.map((_, i) => `${i + 1}번 자리`);
            }

            if (results.length !== names.length) {
                alert(`참가자 수(${names.length}명)와 결과 항목 수(${results.length}개)가 일치해야 합니다!`);
                return;
            }

            const cols = names.length;
            const rows = Math.max(20, cols * 5);

            // 가로선 랜덤 생성 (이웃선 중복 방지)
            let horizontals = [];
            for (let r = 1; r < rows - 1; r++) {
                let colArr = [];
                for (let c = 0; c < cols - 1; c++) colArr.push(c);
                shuffle(colArr);

                if (Math.random() < 0.45) {
                    for (let ci = 0; ci < colArr.length; ci++) {
                        let c = colArr[ci];
                        if (!horizontals.some(h =>
                                (h.row === r - 1 && (h.col === c || h.col === c - 1)) ||
                                (h.row === r + 1 && (h.col === c || h.col === c - 1)) ||
                                (h.row === r && (h.col === c - 1 || h.col === c + 1))
                            )) {
                            horizontals.push({
                                row: r,
                                col: c
                            });
                            break;
                        }
                    }
                }
            }

            ladderData = {
                names,
                results,
                cols,
                rows,
                horizontals
            };
            finalPairings = {};

            // 미리 모든 경로 매핑 계산
            for (let i = 0; i < cols; i++) {
                const pathInfo = getLadderPath(i);
                finalPairings[names[i]] = results[pathInfo.resultIdx];
            }

            placeholderMsg.style.display = 'none';
            canvas.style.display = 'block';
            btnStartAll.disabled = false;
            btnReset.style.display = 'block';
            btnCopyResult.style.display = 'none';
            resultBox.innerHTML = '';

            renderParticipantButtons();
            renderLadderCanvas();
        }

        // 개별 참가자 클릭 버튼 생성
        function renderParticipantButtons() {
            participantButtons.innerHTML = '';
            participantButtons.style.display = 'flex';

            ladderData.names.forEach((name, idx) => {
                const btn = document.createElement('button');
                btn.className = 'ui button teal basic mini';
                btn.innerHTML = `<i class="user icon"></i> ${name}`;
                btn.addEventListener('click', () => animateSinglePath(idx));
                participantButtons.appendChild(btn);
            });
        }

        // 2. 사다리 Canvas 렌더링
        function renderLadderCanvas(highlightPath = null) {
            if (!ladderData) return;

            const {
                names,
                results,
                cols,
                rows,
                horizontals
            } = ladderData;
            const w = canvas.width = Math.max(500, cols * 90);
            const h = canvas.height = 380 + (cols > 6 ? (cols - 6) * 20 : 0);
            const ctx = canvas.getContext('2d');

            ctx.clearRect(0, 0, w, h);

            const xgap = w / (cols + 1);
            const ygap = (h - 100) / (rows + 1);

            // 세로줄 그리기
            for (let c = 0; c < cols; c++) {
                ctx.strokeStyle = "#3b82f6";
                ctx.lineWidth = 4;
                ctx.beginPath();
                ctx.moveTo(xgap * (c + 1), 60);
                ctx.lineTo(xgap * (c + 1), h - 50);
                ctx.stroke();
            }

            // 가로줄 그리기
            for (const hline of horizontals) {
                const y = 60 + ygap * hline.row;
                const x1 = xgap * (hline.col + 1);
                const x2 = xgap * (hline.col + 2);

                ctx.strokeStyle = "#f97316";
                ctx.lineWidth = 4;
                ctx.beginPath();
                ctx.moveTo(x1, y);
                ctx.lineTo(x2, y);
                ctx.stroke();
            }

            // 참가자 이름 & 결과 텍스트
            ctx.font = "bold 15px Pretendard, sans-serif";
            ctx.textAlign = "center";

            for (let c = 0; c < cols; c++) {
                // 상단 참가자 이름
                ctx.fillStyle = "#1e293b";
                ctx.fillText(names[c], xgap * (c + 1), 35);

                // 하단 결과
                ctx.fillStyle = "#8b5cf6";
                ctx.fillText(results[c], xgap * (c + 1), h - 20);
            }

            // 경로 하이라이트 애니메이션
            if (highlightPath && highlightPath.length > 0) {
                ctx.strokeStyle = "#ef4444";
                ctx.lineWidth = 6;
                ctx.shadowColor = "rgba(239, 68, 68, 0.5)";
                ctx.shadowBlur = 12;

                ctx.beginPath();
                ctx.moveTo(highlightPath[0].x, highlightPath[0].y);
                for (const pt of highlightPath) {
                    ctx.lineTo(pt.x, pt.y);
                }
                ctx.stroke();
                ctx.shadowBlur = 0;
            }
        }

        // 경로 데이터 추출
        function getLadderPath(startIdx) {
            const {
                cols,
                rows,
                horizontals
            } = ladderData;
            const w = canvas.width,
                h = canvas.height;
            const xgap = w / (cols + 1),
                ygap = (h - 100) / (rows + 1);

            let c = startIdx;
            let path = [];
            let x = xgap * (c + 1);
            let y = 60;

            path.push({
                x,
                y
            });

            for (let r = 1; r <= rows; r++) {
                y = 60 + ygap * r;
                path.push({
                    x,
                    y
                });

                const hL = horizontals.find(hh => hh.row === r && hh.col === c - 1);
                const hR = horizontals.find(hh => hh.row === r && hh.col === c);

                if (hL) {
                    x -= xgap;
                    path.push({
                        x,
                        y
                    });
                    c--;
                } else if (hR) {
                    x += xgap;
                    path.push({
                        x,
                        y
                    });
                    c++;
                }
            }

            y = h - 50;
            path.push({
                x,
                y
            });

            return {
                path,
                resultIdx: c
            };
        }

        // 3. 개별 참가자 따라가기 애니메이션
        function animateSinglePath(startIdx) {
            if (!ladderData || isAnimating) return;
            isAnimating = true;

            const {
                names,
                results
            } = ladderData;
            const {
                path,
                resultIdx
            } = getLadderPath(startIdx);
            let currentStep = 0;

            const interval = setInterval(() => {
                currentStep += 2;
                if (currentStep >= path.length) {
                    currentStep = path.length;
                    clearInterval(interval);
                    isAnimating = false;

                    // 결과 개별 표시
                    appendResultItem(names[startIdx], results[resultIdx]);
                }
                renderLadderCanvas(path.slice(0, currentStep));
            }, 20);
        }

        // 4. 전체 한 번에 추첨
        function startAllDraw() {
            if (!ladderData || isAnimating) return;

            renderLadderCanvas();
            showFullResults();
        }

        function appendResultItem(name, result) {
            btnCopyResult.style.display = 'inline-block';
            let existingTable = document.getElementById('resultTable');

            if (!existingTable) {
                resultBox.innerHTML = `
                <div class="ui segment teal style-margin-top-15">
                    <h4 class="ui header teal"><i class="trophy icon"></i> 추첨 결과</h4>
                    <table class="ui celled table striped" id="resultTable">
                        <thead>
                            <tr><th>참가자</th><th>이동 결과</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            `;
                existingTable = document.getElementById('resultTable');
            }

            const tbody = existingTable.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');

            // 중복 추가 방지
            for (let tr of rows) {
                if (tr.children[0].textContent === name) return;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `<td><b>${name}</b></td><td><span class="ui teal label">${result}</span></td>`;
            tbody.appendChild(tr);
        }

        function showFullResults() {
            btnCopyResult.style.display = 'inline-block';
            let html = `
            <div class="ui segment teal style-margin-top-15">
                <h4 class="ui header teal"><i class="trophy icon"></i> 전체 추첨 결과</h4>
                <table class="ui celled table striped" id="resultTable">
                    <thead>
                        <tr><th>참가자</th><th>이동 결과</th></tr>
                    </thead>
                    <tbody>
        `;

            for (let name in finalPairings) {
                html += `<tr><td><b>${name}</b></td><td><span class="ui teal label">${finalPairings[name]}</span></td></tr>`;
            }

            html += `
                    </tbody>
                </table>
            </div>
        `;
            resultBox.innerHTML = html;
        }

        // 결과 복사
        btnCopyResult.addEventListener('click', () => {
            if (!finalPairings || Object.keys(finalPairings).length === 0) {
                alert("복사할 결과가 없습니다.");
                return;
            }

            let text = "[ 온라인 사다리 타기 결과 ]\n\n";
            for (let name in finalPairings) {
                text += `${name} → ${finalPairings[name]}\n`;
            }

            navigator.clipboard.writeText(text)
                .then(() => alert("추첨 결과가 클립보드에 복사되었습니다!"))
                .catch(err => alert("복사 실패: " + err));
        });

        // 메인 버튼 이벤트 바인딩
        btnDrawLadder.addEventListener('click', drawLadder);
        btnStartAll.addEventListener('click', startAllDraw);
        btnReset.addEventListener('click', () => {
            ladderNamesInput.value = '';
            ladderResultsInput.value = '';
            ladderData = null;
            placeholderMsg.style.display = 'block';
            canvas.style.display = 'none';
            participantButtons.style.display = 'none';
            btnStartAll.disabled = true;
            btnReset.style.display = 'none';
            btnCopyResult.style.display = 'none';
            resultBox.innerHTML = '';
        });
    });
</script>

<?php include(__DIR__ . "/../footer.php"); ?>