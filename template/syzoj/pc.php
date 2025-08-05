<?php $show_title = "$MSG_POINTCHECK- $OJ_NAME"; ?>

<?php include("template/$OJ_TEMPLATE/header.php"); ?>

<link rel="stylesheet" href="<?php echo $path_fix . "template/$OJ_TEMPLATE" ?>/css/pointcheck.css" />
<script src="<?php echo $OJ_CDN_URL ?>/include/codemirror.js"></script>
<script src="<?php echo $OJ_CDN_URL ?>/include/javascript.js"></script>
<script src="<?php echo $OJ_CDN_URL ?>/include/processing.min.js"></script>

<div class="cal_container" tabindex="0">
  <h1>🎯 실시간 평가점수 계산기</h1>
  <div class='hint'>
      <h2>📣 사용법</h2>
      <div class='description'>✨ 숫자를 입력하면 점수(각 자리수 합)가 실시간 계산됩니다.</div>
      <div class='description'>✨ [Enter] → 자동 기록, [ESC 두 번] → 전체 초기화</div>
  </div>
  <input id="numberInput" type="number" inputmode="numeric" placeholder="숫자 입력 후 Enter" autocomplete="off" />
  <div class="result" id="digitSum">
      🎉 합계: <span class="digit-sum">0</span>
  </div>
  <div class="button-container">
      <button id="saveButton" title="Enter 키로도 기록됨">💾 기록</button>
      <button id="copyAllButton">📋 복사</button>
      <button id="resetButton" title="ESC 두번 누르면 됩니다.">🔄 초기화</button>
  </div>
  <div class="history" id="history">
      <strong>📝 입력 기록</strong>
      <ul id="historyList"></ul>
  </div>
</div>

<script>
const numberInput = document.getElementById('numberInput');
const digitSumDisplay = document.querySelector('.digit-sum');
const historyList = document.getElementById('historyList');
let history = []; // 최근 입력순으로 관리

function calculateDigitSum(value) {
    return value ? value.split('').filter(c => /\d/.test(c)).map(Number).reduce((a, b) => a + b, 0) : 0;
}
function updateHistory(value, sum) {
    // 최근 입력이 위에 오게(최신순)
    const listItem = document.createElement('li');
    listItem.innerHTML = `입력: <b>${value}</b> &nbsp;|&nbsp; 합: <span class="sum">${sum}</span>`;
    historyList.insertBefore(listItem, historyList.firstChild);
}
function inputWriteReset() {
    const inputValue = numberInput.value.trim();
    if (inputValue) {
        const digitSum = calculateDigitSum(inputValue);
        history.unshift({ number: inputValue, sum: digitSum }); // 최신순 저장
        updateHistory(inputValue, digitSum);
    }
    numberInput.value = '';
    digitSumDisplay.textContent = '0';
    numberInput.focus();
}
window.onload = () => { numberInput.focus(); };

// 실시간 합계 표시
numberInput.addEventListener('input', (e) => {
    digitSumDisplay.textContent = calculateDigitSum(e.target.value);
});
numberInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') inputWriteReset();
});

// 기록 버튼(enter로도 가능)
document.getElementById('saveButton').addEventListener('click', inputWriteReset);

// 복사 기능
document.getElementById('copyAllButton').addEventListener('click', () => {
    if (history.length === 0) {
        alert('복사할 기록이 없습니다.');
        return;
    }
    const allHistoryText = history.map(item => `입력: ${item.number}, 합: ${item.sum}`).join('\n');
    navigator.clipboard.writeText(allHistoryText).then(() => {
        alert('기록이 복사되었습니다!');
    }).catch(err => {
        alert('복사 실패! ' + err);
    });
});

// 리셋 기능(ESC 2번 포함)
let escCount = 0;
const resetAction = () => {
    history = [];
    historyList.innerHTML = '';
    numberInput.value = '';
    digitSumDisplay.textContent = '0';
    escCount = 0;
    numberInput.focus();
}
document.getElementById('resetButton').addEventListener('click', resetAction);

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        escCount++;
        if (escCount >= 2) resetAction();
        setTimeout(() => escCount = 0, 700); // 0.7초 내에 두 번 눌러야
    } else {
        escCount = 0;
    }
});
</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>