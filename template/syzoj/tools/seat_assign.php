<?php
$show_title = $view_title;
include(__DIR__ . "/../header.php");
?>
<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools-common.css?v=20261003-2">


<!-- html2canvas 라이브러리 로드 -->
<script src="<?php
echo
    rtrim((string)$OJ_CDN_URL, '/') .
    "/template/" .
    $OJ_TEMPLATE .
    "/js/html2canvas.min.js";
?>"></script>

<div class="ui container tools-detail-page" style="max-width: 1100px; margin-top: 30px; margin-bottom: 50px;">
    <!-- 헤더 -->
    <h1 class="ui header teal center aligned" style="margin-bottom: 20px;">
        <i class="users icon"></i>
        <div class="content">
            교실 자리 배치 도구
            <div class="sub header">실습실 및 교실 좌석을 편리하고 공정하게 배치합니다.</div>
        </div>
    </h1>

    <div class="ui stackable grid">
        <!-- 좌측: 설정 및 입력 패널 (5 열) -->
        <div class="five wide column">
            <div class="ui segment teal">
                <h3 class="ui header teal" style="margin-top: 0;">
                    <i class="sliders horizontal icon"></i> 배치 설정
                </h3>

                <form class="ui form" onsubmit="return false;">
                    <div class="field">
                        <label><i class="user list icon"></i> 학생 명단 (줄바꿈 구분)</label>
                        <textarea id="studentList" rows="8" placeholder="예시:&#10;김민수&#10;이지우&#10;박서연&#10;최현우"></textarea>
                    </div>

                    <div class="two fields">
                        <div class="field">
                            <label>행 (줄)</label>
                            <input type="number" id="rowCnt" value="5" min="1" max="15">
                        </div>
                        <div class="field">
                            <label>열 (칸)</label>
                            <input type="number" id="colCnt" value="6" min="1" max="15">
                        </div>
                    </div>

                    <div class="field">
                        <label>배치 순서 / 방식</label>
                        <select id="assignMode" class="ui dropdown">
                            <option value="random">🎲 무작위 (랜덤)</option>
                            <option value="order">📝 입력된 순서대로</option>
                            <option value="alpha">🔤 가나다 이름순</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>배치 애니메이션 속도(ms)</label>
                        <input type="number" id="animSpeed" value="150" min="0" max="1000" step="50">
                    </div>

                    <div class="ui section divider" style="margin: 15px 0;"></div>

                    <button class="ui primary fluid button" id="btnDrawGrid">
                        <i class="table icon"></i> 1. 자리표 판 생성
                    </button>
                    <button class="ui teal fluid button" id="btnAutoAssign" style="margin-top: 8px;">
                        <i class="random icon"></i> 2. 자리 자동 배치
                    </button>
                </form>
            </div>

            <!-- 미배정 학생 정보 -->
            <div class="ui segment grey" id="unassignedSegment">
                <h4 class="ui header grey style-margin-0">
                    <i class="info circle icon"></i> 미배정/고정 미지정 학생
                </h4>
                <div id="unassignedBox" style="margin-top: 8px; font-size: 0.95em; color: #555; word-break: break-all;">
                    자리표를 생성하면 학생 명단이 표시됩니다.
                </div>
            </div>
        </div>

        <!-- 우측: 자리표 출력 및 조작 영역 (11 열) -->
        <div class="eleven wide column">
            <div class="ui segment">
                <!-- 상단 툴바 버튼 -->
                <div class="responsive-button-group" style="margin-bottom: 15px;">
                    <button type="button" class="ui green button" onclick="window.print()">
                        <i class="print icon"></i> 인쇄 / 출력
                    </button>
                    <button type="button" class="ui blue button" id="btnCopyTable">
                        <i class="copy icon"></i> 자리표 복사
                    </button>
                    <button type="button" class="ui red button" id="btnSaveImg">
                        <i class="image icon"></i> 이미지 저장
                    </button>
                    <a href="/tools/" class="ui button grey tools-detail-back">
                        <i class="arrow left icon"></i> 유틸리티 목록
                    </a>
                </div>

                <div class="ui info tiny message" style="margin-bottom: 15px;">
                    <i class="help circle icon"></i> <b>사용 방법:</b><br>
                    • <b>자리 지정/고정</b>: 자리를 클릭하여 학생 지정 모달을 열고 <b>고정(🔒)</b>할 수 있습니다.<br>
                    • <b>자리 위치 교환</b>: 고정되지 않은 두 자리를 차례로 클릭하면 학생 위치가 바로 교환됩니다.
                </div>

                <!-- 실시간 자리표 메인 캔버스 영역 -->
                <div id="seatTableContainer" style="padding: 10px; background: #f9fafb; border-radius: 8px; min-height: 380px; overflow-x: auto;">
                    <div id="seatTable" class="ui center aligned segment basic" style="padding: 0;">
                        <div class="ui placeholder segment">
                            <div class="ui icon header">
                                <i class="th icon teal"></i>
                                좌측에서 학생 명단과 행/열을 설정한 후 [자리표 판 생성]을 눌러주세요.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Native HTML5 Dialog (학생 지정/고정) -->
<dialog id="studentDialog" style="border: none; border-radius: 12px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); max-width: 400px; width: 90%;">
    <h3 style="margin-top: 0; color: #00b5ad;"><i class="user fix icon"></i> 좌석 학생 고정/지정</h3>
    <p style="color: #666; font-size: 0.9em;">이 자리에 지정/고정할 학생을 선택해 주세요.</p>

    <div style="margin: 20px 0;">
        <select id="dialogStudentSelect" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 1.05em;">
            <!-- JS Dynamic Options -->
        </select>
    </div>

    <div style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
        <button type="button" class="ui red button mini" id="dialogClearBtn" style="margin-right: auto;">
            <i class="trash icon"></i> 고정 해제 (빈자리)
        </button>
        <button type="button" class="ui button mini" id="dialogCancelBtn">취소</button>
        <button type="button" class="ui primary button mini" id="dialogSaveBtn">
            <i class="check icon"></i> 고정 적용 (🔒)
        </button>
    </div>
</dialog>

<style>
    /* 반응형 버튼 컨테이너 */
    .responsive-button-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .responsive-button-group .ui.button {
        flex: 1 1 120px;
        margin: 0 !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* 커스텀 자리표 그리드 스타일 */
    .seat-grid-container {
        display: grid;
        gap: 10px;
        width: 100%;
        margin: 0 auto;
    }

    .seat-card {
        background: #ffffff;
        border: 2px solid #e0e1e2;
        border-radius: 8px;
        padding: 12px 6px;
        text-align: center;
        position: relative;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
        min-height: 75px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .seat-card:hover {
        border-color: #00b5ad;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
    }

    .seat-card.seat-empty {
        background-color: #f8fafc;
        border-style: dashed;
        color: #aaa;
    }

    .seat-card.locked {
        background-color: #e6fffa;
        border-color: #00b5ad;
        border-width: 2px;
    }

    .seat-card.selected {
        border-color: #2185d0 !important;
        background-color: #ebf8ff !important;
        box-shadow: 0 0 0 3px rgba(33, 133, 208, 0.4) !important;
    }

    .seat-card.seat-anim {
        animation: popIn 0.25s ease-out;
    }

    .seat-card .lock-icon {
        position: absolute;
        top: 4px;
        right: 6px;
        font-size: 0.85em;
        color: #00b5ad;
    }

    .seat-card .seat-name {
        font-size: 1.1em;
        font-weight: bold;
        color: #2d3748;
        word-break: break-all;
    }

    .seat-card.seat-empty .seat-name {
        font-size: 0.9em;
        font-weight: normal;
        color: #a0aec0;
    }

    .seat-card .seat-pos {
        font-size: 0.75em;
        color: #cbd5e0;
        margin-top: 4px;
    }

    dialog::backdrop {
        background: rgba(0, 0, 0, 0.5);
    }

    @keyframes popIn {
        0% {
            transform: scale(0.8);
            opacity: 0.5;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #seatTableContainer,
        #seatTableContainer * {
            visibility: visible;
        }

        #seatTableContainer {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .responsive-button-group,
        .ui.info.message,
        .five.wide.column {
            display: none !important;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        let studentList = [];
        let rows = 5,
            cols = 6;
        let seatGrid = [];
        let activeTargetCard = null;
        let firstClickCard = null; // 교환용 첫 번째 선택 카드

        const dialog = document.getElementById('studentDialog');
        const dialogSelect = document.getElementById('dialogStudentSelect');

        function shuffle(arr) {
            for (let i = arr.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [arr[i], arr[j]] = [arr[j], arr[i]];
            }
        }

        // 1. 자리표 그리드 생성
        function drawSeatGrid() {
            const listText = document.getElementById('studentList').value.trim();
            studentList = listText ? listText.split('\n').map(x => x.trim()).filter(x => x) : [];
            rows = parseInt(document.getElementById('rowCnt').value) || 1;
            cols = parseInt(document.getElementById('colCnt').value) || 1;

            if (studentList.length === 0) {
                alert("학생 명단을 입력해 주세요.");
                return;
            }
            if (rows < 1 || cols < 1) {
                alert("행/열 값을 올바르게 입력해 주세요.");
                return;
            }

            resetSelection();
            seatGrid = [];

            const seatTable = document.getElementById('seatTable');
            seatTable.innerHTML = '';

            const gridContainer = document.createElement('div');
            gridContainer.className = 'seat-grid-container';
            gridContainer.style.gridTemplateColumns = `repeat(${cols}, minmax(80px, 1fr))`;

            for (let r = 0; r < rows; r++) {
                for (let c = 0; c < cols; c++) {
                    const card = document.createElement('div');
                    card.className = 'seat-card seat-empty';
                    card.dataset.pos = `${r},${c}`;
                    card.innerHTML = `
                    <i class="lock icon lock-icon" style="display:none;"></i>
                    <div class="seat-name">빈자리</div>
                    <div class="seat-pos">${r + 1}-${c + 1}</div>
                `;
                    gridContainer.appendChild(card);
                    seatGrid.push(card);

                    // 통합 카드 클릭 처리
                    card.addEventListener('click', () => handleCardClick(card));
                }
            }

            seatTable.appendChild(gridContainer);
            updateUnassignedList();
        }

        // 클릭 이벤트 처리 (고정 자리는 모달, 비고정 자리는 교환 또는 모달)
        function handleCardClick(card) {
            // 이미 고정된 자리를 누른 경우 -> 무조건 고정 설정 모달
            if (card.classList.contains('locked')) {
                resetSelection();
                openStudentDialog(card);
                return;
            }

            // 첫 번째 카드가 지정되지 않은 경우 -> 첫 번째 교환 자리로 선택
            if (!firstClickCard) {
                firstClickCard = card;
                card.classList.add('selected');
                return;
            }

            // 선택했던 카드를 다시 클릭한 경우 -> 지정 모달 열기
            if (firstClickCard === card) {
                resetSelection();
                openStudentDialog(card);
                return;
            }

            // 다른 자리를 누른 경우 -> 두 자리 위치 교환 (Swap)
            const name1 = firstClickCard.querySelector('.seat-name').textContent;
            const name2 = card.querySelector('.seat-name').textContent;

            firstClickCard.querySelector('.seat-name').textContent = name2;
            card.querySelector('.seat-name').textContent = name1;

            syncSeatStyle(firstClickCard);
            syncSeatStyle(card);

            resetSelection();
            updateUnassignedList();
        }

        function resetSelection() {
            if (firstClickCard) {
                firstClickCard.classList.remove('selected');
                firstClickCard = null;
            }
        }

        // 고정 다이얼로그 열기
        function openStudentDialog(card) {
            activeTargetCard = card;
            const currentVal = card.querySelector('.seat-name').textContent;

            // 현재 실제로 고정된 학생 목록
            const lockedNames = [];
            seatGrid.forEach(c => {
                if (c.classList.contains('locked')) {
                    const n = c.querySelector('.seat-name').textContent;
                    if (n && n !== "빈자리") lockedNames.push(n);
                }
            });

            // 고정 가능한 학생 목록
            const availableOptions = studentList.filter(s => !lockedNames.includes(s) || s === currentVal);

            dialogSelect.innerHTML = '<option value="">선택 안 함 (빈자리)</option>';
            availableOptions.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                if (name === currentVal && name !== "빈자리") opt.selected = true;
                dialogSelect.appendChild(opt);
            });

            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'true');
            }
        }

        // 다이얼로그 버튼 처리
        document.getElementById('dialogClearBtn').addEventListener('click', () => {
            if (activeTargetCard) {
                applySeatFix(activeTargetCard, "");
                closeDialog();
            }
        });

        document.getElementById('dialogSaveBtn').addEventListener('click', () => {
            if (activeTargetCard) {
                const val = dialogSelect.value;
                applySeatFix(activeTargetCard, val);
                closeDialog();
            }
        });

        document.getElementById('dialogCancelBtn').addEventListener('click', closeDialog);

        function closeDialog() {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        }

        // 좌석 고정 적용 / 해제
        function applySeatFix(card, val) {
            if (val) {
                card.className = "seat-card locked";
                card.querySelector('.seat-name').textContent = val;
                card.querySelector('.lock-icon').style.display = "block";
            } else {
                card.className = "seat-card seat-empty";
                card.querySelector('.seat-name').textContent = "빈자리";
                card.querySelector('.lock-icon').style.display = "none";
            }
            resetSelection();
            updateUnassignedList();
        }

        // 미배정 학생 정보 업데이트
        function updateUnassignedList() {
            // 이미 배치되어 있는 모든 학생 목록 (고정 + 자동 배치)
            const placedNames = [];
            seatGrid.forEach(card => {
                const name = card.querySelector('.seat-name').textContent;
                if (name && name !== "빈자리") {
                    placedNames.push(name);
                }
            });

            const left = studentList.filter(n => !placedNames.includes(n));
            const box = document.getElementById('unassignedBox');

            if (left.length === 0) {
                box.innerHTML = '<span class="ui teal label">모두 배정/고정됨</span>';
            } else {
                box.innerHTML = left.map(name => `<span class="ui label compact" style="margin:2px;">${name}</span>`).join('');
            }
        }

        // 2. 자리 자동 배치 (고정 자리는 제외하고 채움)
        function autoAssignSeats(animated = true) {
            if (seatGrid.length === 0) {
                alert("먼저 [자리표 판 생성]을 클릭해 주세요.");
                return;
            }

            let animSpeed = parseInt(document.getElementById('animSpeed').value) || 150;
            resetSelection();

            // 고정(🔒)된 학생 목록 추출
            const used = [];
            seatGrid.forEach(card => {
                if (card.classList.contains('locked')) {
                    const name = card.querySelector('.seat-name').textContent;
                    if (name && name !== "빈자리") used.push(name);
                }
            });

            let left = studentList.filter(n => !used.includes(n));
            const mode = document.getElementById('assignMode').value;

            if (mode === "random") shuffle(left);
            else if (mode === "alpha") left.sort((a, b) => a.localeCompare(b, "ko"));

            // 고정되지 않은 카드만 배정 슬롯으로 선정
            const slots = seatGrid.filter(card => !card.classList.contains('locked'));

            if (animated && animSpeed > 0) {
                let idx = 0;

                function fillNext() {
                    if (idx >= slots.length) {
                        updateUnassignedList();
                        return;
                    }
                    const card = slots[idx];
                    const name = left[idx] || "";

                    if (name) {
                        card.className = "seat-card seat-anim";
                        card.querySelector('.seat-name').textContent = name;
                        card.querySelector('.lock-icon').style.display = "none";
                    } else {
                        card.className = "seat-card seat-empty";
                        card.querySelector('.seat-name').textContent = "빈자리";
                        card.querySelector('.lock-icon').style.display = "none";
                    }
                    idx++;
                    setTimeout(fillNext, animSpeed);
                }
                fillNext();
            } else {
                slots.forEach((card, i) => {
                    const name = left[i] || "";
                    if (name) {
                        card.className = "seat-card";
                        card.querySelector('.seat-name').textContent = name;
                        card.querySelector('.lock-icon').style.display = "none";
                    } else {
                        card.className = "seat-card seat-empty";
                        card.querySelector('.seat-name').textContent = "빈자리";
                        card.querySelector('.lock-icon').style.display = "none";
                    }
                });
                updateUnassignedList();
            }
        }

        function syncSeatStyle(card) {
            if (card.classList.contains('locked')) return;
            const text = card.querySelector('.seat-name').textContent;
            if (text === "빈자리" || !text) {
                card.className = "seat-card seat-empty";
            } else {
                card.className = "seat-card";
            }
        }

        // 메인 버튼 이벤트 바인딩
        document.getElementById('btnDrawGrid').addEventListener('click', drawSeatGrid);
        document.getElementById('btnAutoAssign').addEventListener('click', () => autoAssignSeats(true));

        document.getElementById('btnCopyTable').addEventListener('click', () => {
            const table = document.getElementById('seatTable');
            if (!navigator.clipboard) {
                alert("브라우저에서 클립보드 복사를 지원하지 않습니다.");
                return;
            }
            const range = document.createRange();
            range.selectNode(table);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            try {
                document.execCommand('copy');
                alert("자리표가 복사되었습니다!");
            } catch {
                alert("복사에 실패했습니다.");
            }
            window.getSelection().removeAllRanges();
        });

        document.getElementById('btnSaveImg').addEventListener('click', () => {
            const target = document.getElementById('seatTable');
            if (typeof html2canvas === 'undefined') {
                alert("이미지 저장 라이브러리를 로드할 수 없습니다.");
                return;
            }
            html2canvas(target).then(canvas => {
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = "교실_자리표.png";
                link.click();
            });
        });
    });
</script>

<?php include(__DIR__ . "/../footer.php"); ?>