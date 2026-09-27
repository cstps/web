<?php
$show_title = $view_title;
include("header.php");
?>

<style>
    /* 반응속도 테스트 전용 영역 */
    .hb-box {
        width: 100%;
        height: 320px;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
        font-size: 1.8rem;
        font-weight: bold;
        cursor: pointer;
        user-select: none;
        transition: background-color 0.2s ease;
        text-align: center;
        padding: 20px;
    }

    .hb-red {
        background-color: #ce2012;
    }

    /* 대기 상태 */
    .hb-green {
        background-color: #4bb543;
    }

    /* 클릭 신호 */
    .hb-blue {
        background-color: #2b73af;
    }

    /* 시작 전 / 결과 화면 */

    /* 순서 기억하기 그리드 영역 */
    .seq-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        max-width: 320px;
        margin: 20px auto;
    }

    .seq-tile {
        width: 90px;
        height: 90px;
        background-color: #2c3e50;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.15s;
    }

    .seq-tile.active {
        background-color: #ffffff;
        box-shadow: 0 0 15px #ffffff;
    }

    /* 숫자 기억하기 영역 */
    .num-display {
        font-size: 4rem;
        font-weight: 800;
        color: #2b73af;
        letter-spacing: 4px;
        margin: 20px 0;
    }

    .num-progress {
        width: 100%;
        height: 6px;
        background-color: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .num-progress-bar {
        height: 100%;
        background-color: #2b73af;
        width: 100%;
        transition: width linear;
    }

    /* Aim Trainer 캔버스 영역 */
    .aim-area {
        width: 100%;
        height: 380px;
        background-color: #1e293b;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        cursor: crosshair;
        user-select: none;
    }

    .aim-target {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: radial-gradient(circle, #ef4444 30%, #ffffff 31%, #ffffff 60%, #ef4444 61%);
        position: absolute;
        box-shadow: 0 0 15px rgba(239, 68, 68, 0.6);
        transform: translate(-50%, -50%);
        cursor: pointer;
    }

    /* Chimp Test 영역 */
    .chimp-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 8px;
        max-width: 560px;
        margin: 20px auto;
    }

    .chimp-tile {
        aspect-ratio: 1;
        background-color: #2b73af;
        border-radius: 8px;
        color: white;
        font-size: 1.8rem;
        font-weight: bold;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        user-select: none;
    }

    .chimp-tile.hidden-num {
        background-color: #ffffff;
        color: transparent;
        border: 2px solid #cbd5e1;
    }

    .chimp-tile.empty {
        visibility: hidden;
    }

    /* 탭 제어용 스타일 */
    .hb-tab-content {
        display: none;
    }

    .hb-tab-content.active {
        display: block;
    }

    /* 실시간 점수 대시보드 스타일 */
    .score-board {
        display: flex;
        justify-content: space-around;
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 20px;
    }

    .score-item {
        text-align: center;
    }

    .score-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .score-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0284c7;
    }
</style>

<div class="ui container" style="margin-top: 2em; margin-bottom: 3em;">
    <h2 class="ui dividing header">
        <i class="gamepad teal icon"></i>
        <div class="content">
            휴먼벤치마크 (Human Benchmark)
            <div class="sub header">반응속도, 순서 기억, 숫자 암기, 에임 반응, 침팬지 테스트로 뇌의 인지능력을 측정해보세요!</div>
        </div>
    </h2>

    <!-- 탭 메뉴 -->
    <div class="ui five item top attached tabular menu">
        <a class="item active" onclick="switchHbTab(event, 'reaction')"><i class="lightning icon"></i> 반응속도</a>
        <a class="item" onclick="switchHbTab(event, 'sequence')"><i class="th icon"></i> 순서 기억</a>
        <a class="item" onclick="switchHbTab(event, 'number')"><i class="sort numeric down icon"></i> 숫자 암기</a>
        <a class="item" onclick="switchHbTab(event, 'aim')"><i class="crosshair icon"></i> Aim Trainer</a>
        <a class="item" onclick="switchHbTab(event, 'chimp')"><i class="paw icon"></i> Chimp Test</a>
    </div>

    <!-- 1. 반응속도 테스트 탭 -->
    <div id="tab-reaction" class="ui bottom attached segment hb-tab-content active">
        <div class="score-board">
            <div class="score-item">
                <div class="score-label">최근 측정 속도</div>
                <div class="score-value" id="reaction-current-score">- ms</div>
            </div>
            <div class="score-item" style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
                <div class="score-label">현재 최고 기록 (Best)</div>
                <div class="score-value" id="reaction-best-score" style="color: #16a34a;">- ms</div>
            </div>
        </div>

        <div id="reaction-box" class="hb-box hb-blue" onclick="handleReactionClick()">
            <i class="lightning icon" style="font-size: 3rem; margin-bottom: 10px;"></i>
            <div id="reaction-text">클릭하여 시작하세요</div>
            <div id="reaction-sub" style="font-size: 1rem; font-weight: normal; margin-top: 10px;">화면이 초록색으로 변하면 즉시 클릭하세요!</div>
        </div>
    </div>

    <!-- 2. 순서 기억하기 탭 -->
    <div id="tab-sequence" class="ui bottom attached segment hb-tab-content">
        <div class="score-board">
            <div class="score-item">
                <div class="score-label">현재 진행 레벨</div>
                <div class="score-value" id="seq-current-score">0 Level</div>
            </div>
            <div class="score-item" style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
                <div class="score-label">최고 달성 레벨 (Best)</div>
                <div class="score-value" id="seq-best-score" style="color: #16a34a;">0 Level</div>
            </div>
        </div>

        <div class="ui text center aligned">
            <h3 id="seq-status">시작 버튼을 눌러 점등 순서를 기억하세요</h3>
            <div class="seq-grid">
                <div class="seq-tile" onclick="clickTile(0)"></div>
                <div class="seq-tile" onclick="clickTile(1)"></div>
                <div class="seq-tile" onclick="clickTile(2)"></div>
                <div class="seq-tile" onclick="clickTile(3)"></div>
                <div class="seq-tile" onclick="clickTile(4)"></div>
                <div class="seq-tile" onclick="clickTile(5)"></div>
                <div class="seq-tile" onclick="clickTile(6)"></div>
                <div class="seq-tile" onclick="clickTile(7)"></div>
                <div class="seq-tile" onclick="clickTile(8)"></div>
            </div>
            <button id="seq-start-btn" class="ui teal button large" onclick="startSequenceGame()">게임 시작 🚀</button>
        </div>
    </div>

    <!-- 3. 숫자 기억하기 탭 -->
    <div id="tab-number" class="ui bottom attached segment hb-tab-content">
        <div class="score-board">
            <div class="score-item">
                <div class="score-label">현재 도전 단계</div>
                <div class="score-value" id="num-current-score">0 자리</div>
            </div>
            <div class="score-item" style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
                <div class="score-label">최고 기억 자리수 (Best)</div>
                <div class="score-value" id="num-best-score" style="color: #16a34a;">0 자리</div>
            </div>
        </div>

        <div class="ui text center aligned" style="max-width: 500px; margin: 0 auto; padding: 10px 0;">
            <div id="num-game-start">
                <p style="font-size: 1.2rem; color: #64748b;">화면에 표시되는 숫자를 기억한 후 정답을 입력하세요.<br>단계가 올라갈수록 숫자가 한 자리씩 길어집니다.</p>
                <button class="ui teal button large" onclick="startNumberGame()">숫자 기억 시작 🚀</button>
            </div>

            <div id="num-game-view" style="display: none;">
                <div class="num-progress">
                    <div id="num-bar" class="num-progress-bar"></div>
                </div>
                <div id="num-target" class="num-display">-</div>
                <p style="color: #64748b;">숫자를 잘 기억하세요!</p>
            </div>

            <div id="num-game-input" style="display: none;">
                <h3>기억한 숫자를 입력하세요</h3>
                <div class="ui action input fluid" style="margin: 20px 0;">
                    <input type="number" id="num-user-input" placeholder="숫자 입력" onkeydown="if(event.key==='Enter') submitNumberAnswer()">
                    <button class="ui teal button" onclick="submitNumberAnswer()">제출</button>
                </div>
            </div>

            <div id="num-game-result" style="display: none;">
                <h2 id="num-result-title" class="ui header">결과</h2>
                <div id="num-result-detail" style="font-size: 1.1rem; margin: 15px 0;"></div>
                <button id="num-next-btn" class="ui teal button large" onclick="nextNumberLevel()">다음 단계로 ➡️</button>
                <button id="num-retry-btn" class="ui orange button large" onclick="startNumberGame()" style="display: none;">다시 도전 🔄</button>
            </div>
        </div>
    </div>

    <!-- 4. Aim Trainer 탭 -->
    <div id="tab-aim" class="ui bottom attached segment hb-tab-content">
        <div class="score-board">
            <div class="score-item">
                <div class="score-label">남은 타겟 수</div>
                <div class="score-value" id="aim-remaining-targets">30 / 30</div>
            </div>
            <div class="score-item" style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
                <div class="score-label">최고 평균 클릭 속도 (Best)</div>
                <div class="score-value" id="aim-best-score" style="color: #16a34a;">- ms</div>
            </div>
        </div>

        <div class="ui text center aligned">
            <div id="aim-area" class="aim-area" onclick="startAimGame()">
                <div id="aim-start-text" style="color: white; font-size: 1.8rem; font-weight: bold; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <i class="crosshair icon" style="font-size: 3rem; margin-bottom: 10px;"></i>
                    클릭하여 Aim Trainer를 시작하세요
                    <span style="font-size: 1rem; font-weight: normal; margin-top: 10px; color: #94a3b8;">30개의 타겟을 최대한 빠르게 클릭하세요!</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Chimp Test 탭 -->
    <div id="tab-chimp" class="ui bottom attached segment hb-tab-content">
        <div class="score-board">
            <div class="score-item">
                <div class="score-label">현재 숫자 개수</div>
                <div class="score-value" id="chimp-current-score">4 개</div>
            </div>
            <div class="score-item" style="border-left: 1px solid #cbd5e1; padding-left: 20px;">
                <div class="score-label">최고 통과 개수 (Best)</div>
                <div class="score-value" id="chimp-best-score" style="color: #16a34a;">0 개</div>
            </div>
        </div>

        <div class="ui text center aligned">
            <h3 id="chimp-status">숫자를 1부터 순서대로 클릭하세요 (1을 누르면 다른 숫자가 숨겨집니다)</h3>
            <div id="chimp-grid" class="chimp-grid"></div>
            <button id="chimp-start-btn" class="ui teal button large" onclick="startChimpGame()">게임 시작 🚀</button>
        </div>
    </div>

    <div style="margin-top: 2em;">
        <a href="<?php echo isset($path_fix) ? $path_fix : ''; ?>tools.php" class="ui button"><i class="arrow left icon"></i> 유틸리티 목록으로 돌아가기</a>
    </div>
</div>

<script>
    /* 순수 JS 탭 전환 함수 */
    function switchHbTab(e, tabName) {
        const items = document.querySelectorAll('.tabular.menu .item');
        items.forEach(item => item.classList.remove('active'));

        const contents = document.querySelectorAll('.hb-tab-content');
        contents.forEach(content => content.classList.remove('active'));

        e.currentTarget.classList.add('active');
        document.getElementById('tab-' + tabName).classList.add('active');
    }

    /* --- 1. 반응속도 테스트 --- */
    let reactionState = "WAITING";
    let timerId = null;
    let startTime = 0;
    let reactionBestMs = null;

    function handleReactionClick() {
        const box = document.getElementById('reaction-box');
        const text = document.getElementById('reaction-text');
        const sub = document.getElementById('reaction-sub');

        if (reactionState === "WAITING" || reactionState === "RESULT") {
            reactionState = "READY";
            box.className = "hb-box hb-red";
            text.innerText = "초록색이 될 때까지 기다리세요...";
            sub.innerText = "너무 일찍 클릭하면 안 됩니다!";

            const randomDelay = Math.floor(Math.random() * 3000) + 2000;
            timerId = setTimeout(() => {
                reactionState = "GO";
                box.className = "hb-box hb-green";
                text.innerText = "지금 클릭하세요!";
                sub.innerText = "";
                startTime = Date.now();
            }, randomDelay);

        } else if (reactionState === "READY") {
            clearTimeout(timerId);
            reactionState = "RESULT";
            box.className = "hb-box hb-blue";
            text.innerText = "너무 일찍 클릭하셨습니다! 😅";
            sub.innerText = "클릭하여 다시 시도하세요.";

        } else if (reactionState === "GO") {
            const elapsed = Date.now() - startTime;
            reactionState = "RESULT";
            box.className = "hb-box hb-blue";
            text.innerText = elapsed + " ms";
            sub.innerText = "클릭하여 다시 도전하기";

            document.getElementById('reaction-current-score').innerText = elapsed + " ms";
            if (reactionBestMs === null || elapsed < reactionBestMs) {
                reactionBestMs = elapsed;
                document.getElementById('reaction-best-score').innerText = reactionBestMs + " ms";
            }
        }
    }

    /* --- 2. 순서 기억하기 --- */
    let sequence = [];
    let userStep = 0;
    let isPlayingSequence = false;
    let seqBestLevel = 0;

    function startSequenceGame() {
        sequence = [];
        userStep = 0;
        document.getElementById('seq-start-btn').style.display = 'none';
        nextSequenceLevel();
    }

    function nextSequenceLevel() {
        userStep = 0;
        sequence.push(Math.floor(Math.random() * 9));
        const currentLevel = sequence.length;

        document.getElementById('seq-current-score').innerText = currentLevel + " Level";
        document.getElementById('seq-status').innerText = `레벨 ${currentLevel} - 타일의 순서를 기억하세요`;
        playSequence();
    }

    function playSequence() {
        isPlayingSequence = true;
        let i = 0;
        const tiles = document.querySelectorAll('.seq-tile');

        const interval = setInterval(() => {
            if (i > 0) tiles[sequence[i - 1]].classList.remove('active');

            if (i < sequence.length) {
                tiles[sequence[i]].classList.add('active');
                i++;
            } else {
                clearInterval(interval);
                isPlayingSequence = false;
                document.getElementById('seq-status').innerText = `레벨 ${sequence.length} - 당신의 차례입니다!`;
            }
        }, 600);
    }

    function clickTile(index) {
        if (isPlayingSequence || sequence.length === 0) return;

        const tiles = document.querySelectorAll('.seq-tile');
        tiles[index].classList.add('active');
        setTimeout(() => tiles[index].classList.remove('active'), 200);

        if (index === sequence[userStep]) {
            userStep++;
            if (userStep === sequence.length) {
                const clearedLevel = sequence.length;
                if (clearedLevel > seqBestLevel) {
                    seqBestLevel = clearedLevel;
                    document.getElementById('seq-best-score').innerText = seqBestLevel + " Level";
                }
                document.getElementById('seq-status').innerText = "성공! 다음 단계로 이동합니다.";
                setTimeout(nextSequenceLevel, 1000);
            }
        } else {
            const finalLevel = sequence.length - 1;
            document.getElementById('seq-status').innerText = `게임 오버! (최종 기록: 레벨 ${finalLevel})`;
            document.getElementById('seq-start-btn').innerText = "다시 도전하기 🔄";
            document.getElementById('seq-start-btn').style.display = 'inline-block';
            sequence = [];
        }
    }

    /* --- 3. 숫자 기억하기 --- */
    let currentNumDigits = 1;
    let currentTargetNumber = "";
    let numTimer = null;
    let numBestDigits = 0;

    function startNumberGame() {
        currentNumDigits = 1;
        nextNumberLevel();
    }

    function nextNumberLevel() {
        document.getElementById('num-game-start').style.display = 'none';
        document.getElementById('num-game-result').style.display = 'none';
        document.getElementById('num-game-input').style.display = 'none';
        document.getElementById('num-game-view').style.display = 'block';

        document.getElementById('num-current-score').innerText = currentNumDigits + " 자리";

        currentTargetNumber = "";
        for (let i = 0; i < currentNumDigits; i++) {
            if (i === 0) currentTargetNumber += Math.floor(Math.random() * 9) + 1;
            else currentTargetNumber += Math.floor(Math.random() * 10);
        }

        document.getElementById('num-target').innerText = currentTargetNumber;

        const bar = document.getElementById('num-bar');
        const displayTime = 1500 + (currentNumDigits * 600);

        bar.style.transition = 'none';
        bar.style.width = '100%';
        setTimeout(() => {
            bar.style.transition = `width ${displayTime}ms linear`;
            bar.style.width = '0%';
        }, 50);

        numTimer = setTimeout(() => {
            document.getElementById('num-game-view').style.display = 'none';
            document.getElementById('num-game-input').style.display = 'block';
            document.getElementById('num-user-input').value = '';
            document.getElementById('num-user-input').focus();
        }, displayTime + 100);
    }

    function submitNumberAnswer() {
        const inputVal = document.getElementById('num-user-input').value.trim();
        document.getElementById('num-game-input').style.display = 'none';
        document.getElementById('num-game-result').style.display = 'block';

        if (inputVal === currentTargetNumber) {
            if (currentNumDigits > numBestDigits) {
                numBestDigits = currentNumDigits;
                document.getElementById('num-best-score').innerText = numBestDigits + " 자리";
            }
            document.getElementById('num-result-title').innerText = "⭕ 정답입니다!";
            document.getElementById('num-result-title').style.color = "#2b73af";
            document.getElementById('num-result-detail').innerHTML = `제시된 숫자: <b>${currentTargetNumber}</b><br>입력한 숫자: <b>${inputVal}</b>`;
            document.getElementById('num-next-btn').style.display = 'inline-block';
            document.getElementById('num-retry-btn').style.display = 'none';
            currentNumDigits++;
        } else {
            document.getElementById('num-result-title').innerText = "❌ 틀렸습니다!";
            document.getElementById('num-result-title').style.color = "#ce2012";
            document.getElementById('num-result-detail').innerHTML = `정답: <b>${currentTargetNumber}</b><br>입력값: <b>${inputVal}</b><br><br>최종 달성 레벨: <b>${currentNumDigits - 1} 자리</b>`;
            document.getElementById('num-next-btn').style.display = 'none';
            document.getElementById('num-retry-btn').style.display = 'inline-block';
        }
    }

    /* --- 4. Aim Trainer --- */
    let aimRemaining = 30;
    let aimStartTime = 0;
    let aimBestMs = null;
    let isAimRunning = false;

    function startAimGame() {
        if (isAimRunning) return;
        isAimRunning = true;
        aimRemaining = 30;
        document.getElementById('aim-remaining-targets').innerText = "30 / 30";

        const area = document.getElementById('aim-area');
        area.innerHTML = '';

        aimStartTime = Date.now();
        spawnAimTarget();
    }

    function spawnAimTarget() {
        const area = document.getElementById('aim-area');
        area.innerHTML = '';

        if (aimRemaining <= 0) {
            const totalTime = Date.now() - aimStartTime;
            const avgTime = Math.round(totalTime / 30);
            isAimRunning = false;

            if (aimBestMs === null || avgTime < aimBestMs) {
                aimBestMs = avgTime;
                document.getElementById('aim-best-score').innerText = aimBestMs + " ms";
            }

            area.innerHTML = `
            <div style="color: white; font-size: 1.8rem; font-weight: bold; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                <i class="trophy yellow icon" style="font-size: 3rem; margin-bottom: 10px;"></i>
                평균 반응속도: ${avgTime} ms
                <span style="font-size: 1rem; font-weight: normal; margin-top: 10px; color: #94a3b8;">클릭하여 다시 도전하기</span>
            </div>
        `;
            return;
        }

        const target = document.createElement('div');
        target.className = 'aim-target';

        // 영역 내 무작위 좌표 계산 (여백 40px)
        const maxX = area.clientWidth - 80;
        const maxY = area.clientHeight - 80;
        const x = Math.floor(Math.random() * maxX) + 40;
        const y = Math.floor(Math.random() * maxY) + 40;

        target.style.left = x + 'px';
        target.style.top = y + 'px';

        target.onclick = (e) => {
            e.stopPropagation();
            aimRemaining--;
            document.getElementById('aim-remaining-targets').innerText = aimRemaining + " / 30";
            spawnAimTarget();
        };

        area.appendChild(target);
    }

    /* --- 5. Chimp Test --- */
    let chimpCount = 4;
    let chimpNextNum = 1;
    let chimpBestCount = 0;
    let isChimpHidden = false;

    function startChimpGame() {
        chimpCount = 4;
        nextChimpLevel();
    }

    function nextChimpLevel() {
        chimpNextNum = 1;
        isChimpHidden = false;
        document.getElementById('chimp-current-score').innerText = chimpCount + " 개";
        document.getElementById('chimp-status').innerText = `레벨 ${chimpCount - 3}: 숫자를 1부터 순서대로 클릭하세요`;
        document.getElementById('chimp-start-btn').style.display = 'none';

        renderChimpBoard();
    }

    function renderChimpBoard() {
        const grid = document.getElementById('chimp-grid');
        grid.innerHTML = '';

        // 40개 셀 중 무작위 배치 (8x5 영역)
        const totalCells = 40;
        let positions = [];
        for (let i = 0; i < totalCells; i++) positions.push(i);
        positions.sort(() => Math.random() - 0.5);

        let cellMap = {};
        for (let i = 1; i <= chimpCount; i++) {
            cellMap[positions[i - 1]] = i;
        }

        for (let i = 0; i < totalCells; i++) {
            const tile = document.createElement('div');
            if (cellMap[i]) {
                const num = cellMap[i];
                tile.className = 'chimp-tile';
                tile.innerText = num;
                tile.onclick = () => handleChimpClick(tile, num);
            } else {
                tile.className = 'chimp-tile empty';
            }
            grid.appendChild(tile);
        }
    }

    function handleChimpClick(tile, num) {
        if (num === chimpNextNum) {
            if (num === 1) {
                // 1을 누르는 순간 남아있는 타일들의 숫자 숨김 처리
                isChimpHidden = true;
                document.querySelectorAll('.chimp-tile').forEach(el => {
                    if (!el.classList.contains('empty') && el.innerText !== '') {
                        el.classList.add('hidden-num');
                    }
                });
            }

            tile.className = 'chimp-tile empty';
            chimpNextNum++;

            if (chimpNextNum > chimpCount) {
                if (chimpCount > chimpBestCount) {
                    chimpBestCount = chimpCount;
                    document.getElementById('chimp-best-score').innerText = chimpBestCount + " 개";
                }
                document.getElementById('chimp-status').innerText = "성공! 다음 단계로 진행합니다.";
                chimpCount++;
                setTimeout(nextChimpLevel, 800);
            }
        } else {
            document.getElementById('chimp-status').innerText = `게임 오버! (최종 기록: ${chimpCount - 1}개 통과)`;
            document.getElementById('chimp-start-btn').innerText = "다시 도전하기 🔄";
            document.getElementById('chimp-start-btn').style.display = 'inline-block';
            document.getElementById('chimp-grid').innerHTML = '';
        }
    }
</script>

<?php include("footer.php"); ?>