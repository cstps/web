<?php $show_title = "$MSG_POINTCHECK- $OJ_NAME"; ?>

<?php include("template/$OJ_TEMPLATE/header.php"); ?>

<link rel="stylesheet" href="<?php echo $path_fix . "template/$OJ_TEMPLATE" ?>/css/pointcheck.css" />
<script src="<?php echo $OJ_CDN_URL ?>/include/codemirror.js"></script>
<script src="<?php echo $OJ_CDN_URL ?>/include/javascript.js"></script>
<script src="<?php echo $OJ_CDN_URL ?>/include/processing.min.js"></script>

<div class="cal_container">
    <h1> 🎯 실시간 점수 합 계산기 🎯 </h1>
    <div class='hint'>
        <h2> 📣사용법📣 </h2>
        <div class='description'>✨ 숫자를 입력하면 실시간으로 점수 계산</div>
        <div class='description'>✨ 입력후 Enter 자동으로 기록</div>
        <div class='description'>✨ ESC키 두 번 누르면 모두 초기화</div>
    </div>
    <input id="numberInput" type="number" placeholder="숫자입력 후 Enter > 자동 저장" />
    <div class="result" id="digitSum">
        🎉합: <span class="digit-sum">0</span>
    </div>
    <div class="button-container">
        <button id="saveButton" title="수 입력후 Enter">💾기록하기</button>
        <button id="copyAllButton">📋기록복사</button>
        <button id="resetButton" title='ESC 두번 누르면 됩니다.'>🔄초기화</button>
    </div>
    <div class="history" id="history">
        <strong>📝 기록 📝</strong>
        <ul id="historyList"></ul>
    </div>
</div>

<script>
    const numberInput = document.getElementById('numberInput');
    const digitSumDisplay = document.querySelector('.digit-sum'); // 숫자 부분만 선택
    const historyList = document.getElementById('historyList');   // 기록
    const history = []; // 기록을 저장할 배열

    // 자리수 합 계산 함수
    function calculateDigitSum(value) {
        return value ? value.split('').map(Number).reduce((a, b) => a + b, 0) : 0;
    }

    // 기록 업데이트 함수
    function updateHistory(value, sum) {
        const listItem = document.createElement('li');
        listItem.innerHTML = `입력: ${value}, 합: <span class="sum">${sum}</span>`;
        historyList.appendChild(listItem);
    }

    // 입력값 초기화 및 기록 남기기
    function inputWriteReset() {
        const inputValue = numberInput.value;
        if (inputValue) {
            const digitSum = calculateDigitSum(inputValue);
            history.push({ number: inputValue, sum: digitSum });  // 기록 배열에 추가
            updateHistory(inputValue, digitSum);  // 기록 화면에 출력
        }
        numberInput.value = '';
        digitSumDisplay.textContent = '0'; // 디폴트 값 0
        numberInput.focus();  // 입력창으로 다시 포커스 이동
    }

    // 초기 로드 시 input 요소에 포커스
    window.onload = () => {
        numberInput.focus();
    };

    // 이벤트 리스너 등록
    document.getElementById('saveButton').addEventListener('click', inputWriteReset);
    numberInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') inputWriteReset(); });
    numberInput.addEventListener('input', (e) => {
        digitSumDisplay.textContent = calculateDigitSum(e.target.value);
    });

    // 전체 복사 버튼 클릭 시 기록된 모든 내용을 복사
    document.getElementById('copyAllButton').addEventListener('click', () => {
        if (history.length === 0) {
            alert('복사할 기록이 없습니다.');
            return;
        }

        const allHistoryText = history.map(item => `입력: ${item.number}, 합: ${item.sum}`).join('\n');
        navigator.clipboard.writeText(allHistoryText).then(() => {
            alert('전체 기록이 클립보드에 복사되었습니다!');
        }).catch(err => {
            console.error('복사 실패:', err);
        });
    });

    // 리셋 버튼 클릭 및 ESC 키를 눌렀을 때 새로고침
    const resetAction = () => window.location.reload();
    document.getElementById('resetButton').addEventListener('click', resetAction);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') resetAction(); });
</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
