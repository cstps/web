<?php $show_title = "$MSG_POINTCHECK -$OJ_NAME"; ?>
<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools-common.css?v=20261003-2">


<?php include(__DIR__ . "/../header.php"); ?>

<style>
    /* 반응형 버튼 컨테이너 스타일 */
    .responsive-button-group {
        display: flex;
        flex-wrap: wrap;
        /* 화면 크기에 따라 줄바꿈 허용 */
        gap: 10px;
        /* 버튼 사이 여백 */
        margin: 20px 0;
    }

    .responsive-button-group .ui.button {
        flex: 1 1 130px;
        /* 기본 균등 분배, 최소 130px 확보 후 줄바꿈 */
        margin: 0 !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>

<div class="ui container tools-detail-page" style="max-width: 600px; margin-top: 30px; margin-bottom: 50px;">
    <div class="ui segment center aligned">
        <h1 class="ui header teal">
            <i class="calculator icon"></i>
            <div class="content">
                실시간 평가점수 계산기
                <div class="sub header">입력한 숫자의 각 자리수 합을 실시간으로 계산합니다.</div>
            </div>
        </h1>

        <div class="ui info message left aligned">
            <div class="header"><i class="info circle icon"></i> 사용법</div>
            <ul class="list">
                <li><b>[Enter]</b> : 입력값 기록 및 초기화</li>
                <li><b>[ESC 두 번]</b> : 전체 기록 초기화</li>
            </ul>
        </div>

        <div class="ui form" style="margin-top: 20px;">
            <div class="field">
                <div class="ui huge input icon">
                    <input id="numberInput" type="text" inputmode="numeric" placeholder="숫자 입력 후 Enter" autocomplete="off" autofocus />
                    <i class="keyboard icon"></i>
                </div>
            </div>
        </div>

        <div class="ui statistic teal" style="margin: 20px 0;">
            <div class="value" id="digitSumDisplay">0</div>
            <div class="label">🎉 실시간 합계</div>
        </div>

        <!-- 반응형 버튼 그룹 (유틸리티 목록 돌아가기 추가) -->
        <div class="responsive-button-group">
            <button class="ui primary button" id="saveButton" title="Enter 키로도 기록됨">
                <i class="save icon"></i> 기록 (Enter)
            </button>
            <button class="ui green button" id="copyAllButton">
                <i class="copy icon"></i> 기록 복사
            </button>
            <button class="ui red button" id="resetButton" title="ESC 두번 누르면 초기화">
                <i class="undo icon"></i> 초기화 (ESCx2)
            </button>
            <a href="/tools/" class="ui button grey tools-detail-back">
                <i class="arrow left icon"></i> 유틸리티 목록
            </a>
        </div>

        <div class="ui section divider"></div>

        <div class="left aligned">
            <h3 class="ui header">
                <i class="history icon"></i>
                <div class="content">입력 기록</div>
            </h3>
            <div class="ui relaxed divided list" id="historyList" style="max-height: 300px; overflow-y: auto;">
                <div class="item disabled-placeholder">아직 기록된 항목이 없습니다.</div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const numberInput = document.getElementById('numberInput');
        const digitSumDisplay = document.getElementById('digitSumDisplay');
        const historyList = document.getElementById('historyList');

        let history = [];
        let escCount = 0;
        let escTimer = null;

        // 각 자리 수 합 계산 함수
        function calculateDigitSum(value) {
            if (!value) return 0;
            return value
                .split('')
                .filter(c => /\d/.test(c))
                .map(Number)
                .reduce((a, b) => a + b, 0);
        }

        // 안전한 DOM 텍스트 삽입 (XSS 방지)
        function updateHistoryUI(value, sum) {
            const placeholder = historyList.querySelector('.disabled-placeholder');
            if (placeholder) placeholder.remove();

            const item = document.createElement('div');
            item.className = 'item';

            const content = document.createElement('div');
            content.className = 'content';

            const header = document.createElement('div');
            header.className = 'header';
            header.textContent = `입력: ${value}`;

            const description = document.createElement('div');
            description.className = 'description';
            description.textContent = `합계: ${sum}`;

            content.appendChild(header);
            content.appendChild(description);
            item.appendChild(content);

            historyList.insertBefore(item, historyList.firstChild);
        }

        // 기록 저장 및 입력창 초기화
        function inputWriteReset() {
            const inputValue = numberInput.value.trim();
            if (inputValue) {
                const sum = calculateDigitSum(inputValue);
                history.unshift({
                    number: inputValue,
                    sum: sum
                });
                updateHistoryUI(inputValue, sum);
            }
            numberInput.value = '';
            digitSumDisplay.textContent = '0';
            numberInput.focus();
        }

        // 전체 초기화
        function resetAction() {
            history = [];
            historyList.innerHTML = '<div class="item disabled-placeholder">아직 기록된 항목이 없습니다.</div>';
            numberInput.value = '';
            digitSumDisplay.textContent = '0';
            escCount = 0;
            numberInput.focus();
        }

        // 실시간 입력 연동
        numberInput.addEventListener('input', (e) => {
            digitSumDisplay.textContent = calculateDigitSum(e.target.value);
        });

        numberInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                inputWriteReset();
            }
        });

        // 버튼 이벤트
        document.getElementById('saveButton').addEventListener('click', inputWriteReset);
        document.getElementById('resetButton').addEventListener('click', resetAction);

        // 복사 기능 (Clipboard API)
        document.getElementById('copyAllButton').addEventListener('click', () => {
            if (history.length === 0) {
                alert('복사할 기록이 없습니다.');
                return;
            }
            const textToCopy = history.map(item => `입력: ${item.number} | 합: ${item.sum}`).join('\n');
            navigator.clipboard.writeText(textToCopy)
                .then(() => alert('기록이 클립보드에 복사되었습니다!'))
                .catch(err => alert('복사 실패: ' + err));
        });

        // ESC 2회 감지 초기화
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                escCount++;
                if (escCount >= 2) {
                    resetAction();
                    clearTimeout(escTimer);
                    escCount = 0;
                } else {
                    clearTimeout(escTimer);
                    escTimer = setTimeout(() => {
                        escCount = 0;
                    }, 700);
                }
            } else {
                escCount = 0;
            }
        });

        numberInput.focus();
    });
</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>