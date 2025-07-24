<?php $show_title = "$MSG_CHARCOUNT- $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>

<link rel="stylesheet" href="<?php echo $path_fix . "template/$OJ_TEMPLATE" ?>/css/pointcheck.css" />

<style>
.cal_container {
    max-width: 800px;
    margin: 30px auto;
    background: #fefefe;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
    font-family: 'Segoe UI', sans-serif;
}
.cal_container h1 {
    text-align: center;
    font-size: 2em;
    margin-bottom: 20px;
}
.textarea-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
textarea {
    width: 100%;
    height: 210px;
    font-size: 1.1em;
    padding: 15px;
    border-radius: 10px;
    border: 1px solid #ccc;
    resize: none;
}
.counter {
    font-size: 1.2em;
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
}
.counter span {
    padding: 5px 12px;
    border-radius: 12px;
}
#byteCount {
    background: #f0f8ff;
}
#charCount {
    background: #fffaf0;
}
.alert {
    color: red;
    font-weight: bold;
    margin-top: 10px;
    text-align: center;
}
</style>

<div class="cal_container">
    <h1>✍️ 실시간 글자 수 & 바이트 계산기 ✍️</h1>
    <div class="textarea-box">
        <textarea id="textInput" placeholder="여기에 텍스트를 입력하세요...한글 3byte 영/숫/enter 1byte"></textarea>
        <div class="counter">
            <span id="charCount">🔠 글자 수: 0</span>
            <span id="byteCount">📏 바이트 수: 0 / 1500</span>
        </div>
        <div class="alert" id="alertText" style="display:none;">⚠️ 바이트 수가 1500byte를 초과했습니다!</div>
    </div>
</div>

<script>
function calculateByteLength(text) {
    let bytes = 0;
    for (let i = 0; i < text.length; i++) {
        const ch = text.charAt(i);
        const code = ch.charCodeAt(0);

        if (ch === '\n') {
            bytes += 1;
        } else if (code >= 0xAC00 && code <= 0xD7A3) {
            bytes += 3; // 한글
        } else if (/[a-zA-Z0-9]/.test(ch)) {
            bytes += 1; // 영문 숫자
        } else {
            bytes += 1; // 특수문자
        }
    }
    return bytes;
}

function calculateCharLength(text) {
    let hangulCount = 0;
    let others = 0;

    for (let i = 0; i < text.length; i++) {
        const ch = text.charAt(i);
        const code = ch.charCodeAt(0);

        if (code >= 0xAC00 && code <= 0xD7A3) {
            hangulCount += 1;
        } else if (/[a-zA-Z0-9]/.test(ch) || ch === '\n') {
            others += 1; // 영어, 숫자, 엔터
        } else {
            hangulCount += 1; // 특수문자는 글자 1로 취급
        }
    }

    return hangulCount + Math.ceil(others / 3);
}

const textInput = document.getElementById("textInput");
const byteCount = document.getElementById("byteCount");
const charCount = document.getElementById("charCount");
const alertText = document.getElementById("alertText");

textInput.addEventListener("input", () => {
    const text = textInput.value;
    const byteLen = calculateByteLength(text);
    const charLen = calculateCharLength(text);

    byteCount.textContent = `📏 바이트 수: ${byteLen} / 1500`;
    charCount.textContent = `🔠 글자 수: ${charLen}`;

    alertText.style.display = byteLen > 1500 ? "block" : "none";
});
</script>


<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
