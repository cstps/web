<?php
$show_title = $view_title;
include(__DIR__ . "/../header.php");
?>

<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools-common.css?v=20261003-2">

<div class="ui container tools-detail-page" style="max-width: 800px;">
    <div class="ui segment">
        <h1 class="ui header teal center aligned" style="margin-top: 10px;">
            <i class="font icon"></i>
            <div class="content">
                실시간 글자 수 & 바이트 계산기
                <div class="sub header">한글 3byte | 영문/숫자/공백/엔터 1byte 기준</div>
            </div>
        </h1>

        <div class="ui form" style="margin-top: 20px;">
            <div class="field">
                <textarea id="textInput" rows="10" placeholder="여기에 텍스트를 입력하거나 붙여넣으세요..." style="font-size: 1.1em; line-height: 1.6; resize: vertical;"></textarea>
            </div>
        </div>

        <!-- 실시간 통계 정보 카운터 -->
        <div class="ui three statistics small style-stats" style="margin: 20px 0;">
            <div class="statistic teal">
                <div class="value" id="byteDisplay">0 <span style="font-size: 0.5em; color: #777;">/ 1500 B</span></div>
                <div class="label">📏 바이트 (Byte)</div>
            </div>
            <div class="statistic blue">
                <div class="value" id="charWithSpaceDisplay">0</div>
                <div class="label">🔠 글자 수 (공백 포함)</div>
            </div>
            <div class="statistic violet">
                <div class="value" id="charNoSpaceDisplay">0</div>
                <div class="label">✂️ 글자 수 (공백 제외)</div>
            </div>
        </div>

        <!-- 바이트 제한 초과 경고 메시지 -->
        <div class="ui negative message" id="alertBox" style="display: none; text-align: center;">
            <i class="warning sign icon"></i>
            <b>경고:</b> 입력한 텍스트가 제한 용량 <b>1,500 Byte</b>를 초과했습니다!
        </div>

        <div class="ui section divider"></div>

        <!-- 반응형 버튼 그룹 -->
        <div class="responsive-button-group">
            <button class="ui teal button" id="copyBtn">
                <i class="copy icon"></i> 텍스트 복사
            </button>
            <button class="ui orange button" id="clearBtn">
                <i class="erase icon"></i> 전체 비우기
            </button>
            <a href="/tools/" class="ui button grey tools-detail-back">
                <i class="arrow left icon"></i> 유틸리티 목록
            </a>
        </div>
    </div>
</div>

<style>
    /* 반응형 버튼 그룹 스타일 */
    .responsive-button-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
    }

    .responsive-button-group .ui.button {
        flex: 1 1 150px;
        max-width: 220px;
        margin: 0 !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const textInput = document.getElementById('textInput');
        const byteDisplay = document.getElementById('byteDisplay');
        const charWithSpaceDisplay = document.getElementById('charWithSpaceDisplay');
        const charNoSpaceDisplay = document.getElementById('charNoSpaceDisplay');
        const alertBox = document.getElementById('alertBox');
        const copyBtn = document.getElementById('copyBtn');
        const clearBtn = document.getElementById('clearBtn');

        const MAX_BYTES = 1500;

        // 정확한 UTF-8 바이트 수 계산 (한글 3byte, 영문/숫자/기호/줄바꿈 1byte)
        function getByteLength(str) {
            let bytes = 0;
            for (let i = 0; i < str.length; i++) {
                const code = str.charCodeAt(i);
                if (code <= 0x007F) {
                    bytes += 1; // ASCII (영문, 숫자, 특수문자, \n 등)
                } else if (code <= 0x07FF) {
                    bytes += 2;
                } else if (code <= 0xFFFF) {
                    bytes += 3; // 한글, 한자 등
                } else {
                    bytes += 4; // 유니코드 확장 / 이모지
                }
            }
            return bytes;
        }

        // 카운터 갱신 함수
        function updateCounters() {
            const text = textInput.value;
            const byteLen = getByteLength(text);
            const charWithSpace = text.length;
            const charNoSpace = text.replace(/\s/g, '').length;

            // 화면 갱신
            byteDisplay.innerHTML = `${byteLen.toLocaleString()} <span style="font-size: 0.5em; color: #666;">/ ${MAX_BYTES.toLocaleString()} B</span>`;
            charWithSpaceDisplay.textContent = charWithSpace.toLocaleString();
            charNoSpaceDisplay.textContent = charNoSpace.toLocaleString();

            // 바이트 초과 시 스타일 제어
            if (byteLen > MAX_BYTES) {
                alertBox.style.display = 'block';
                byteDisplay.parentElement.classList.remove('teal');
                byteDisplay.parentElement.classList.add('red');
            } else {
                alertBox.style.display = 'none';
                byteDisplay.parentElement.classList.remove('red');
                byteDisplay.parentElement.classList.add('teal');
            }
        }

        textInput.addEventListener('input', updateCounters);

        // 텍스트 복사 버튼
        copyBtn.addEventListener('click', () => {
            if (!textInput.value.trim()) {
                alert('복사할 텍스트가 없습니다.');
                return;
            }
            navigator.clipboard.writeText(textInput.value)
                .then(() => alert('텍스트가 클립보드에 복사되었습니다!'))
                .catch(err => alert('복사 실패: ' + err));
        });

        // 전체 비우기 버튼
        clearBtn.addEventListener('click', () => {
            if (textInput.value && confirm('입력한 내용을 모두 비우시겠습니까?')) {
                textInput.value = '';
                updateCounters();
                textInput.focus();
            }
        });

        textInput.focus();
    });
</script>

<?php include("footer.php"); ?>