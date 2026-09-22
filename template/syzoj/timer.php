<?php
$show_title = $view_title;
include($path_fix . "header.php");
?>

<style>
    /* 대형 타이머 전용 가독성 스타일 */
    .timer-huge-display {
        font-size: 14vw;
        /* 화면 너비에 맞춰 대형 확장 */
        font-size: clamp(8rem, 15vw, 18rem);
        /* 최소 8rem ~ 최대 18rem */
        font-weight: 900;
        font-family: 'SF Mono', 'Roboto Mono', 'Courier New', monospace;
        font-variant-numeric: tabular-nums;
        /* 숫자 변경 시 너비 고정 */
        line-height: 1;
        color: #1e293b;
        text-shadow: 2px 4px 10px rgba(0, 0, 0, 0.08);
        letter-spacing: -2px;
        margin: 0.2em 0;
        transition: color 0.3s ease;
    }

    /* 1분 이하 남았을 때 빨간색 + Pulse 애니메이션 */
    .timer-warning {
        color: #e11d48 !important;
        animation: pulse-glow 1.5s infinite;
    }

    @keyframes pulse-glow {
        0% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.85;
            transform: scale(1.02);
        }

        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* 전체 화면 모드 지원 */
    #timer-segment:fullscreen {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: #ffffff !important;
        padding: 2em !important;
    }

    #timer-segment:fullscreen .timer-huge-display {
        font-size: 22vw !important;
    }
</style>

<div class="ui container" style="margin-top: 2em; margin-bottom: 3em;">
    <div class="ui clearing basic segment" style="padding: 0; margin-bottom: 1em;">
        <h2 class="ui left floated header" style="margin: 0;">
            <i class="stopwatch teal icon"></i>
            <div class="content">
                수업용 대형 타이머
                <div class="sub header">실습 과제, 발표, 시험 진행 시 시간을 대형 화면으로 시각화합니다.</div>
            </div>
        </h2>
        <!-- 전체 화면 전환 버튼 -->
        <button class="ui right floated icon button basic" onclick="toggleFullScreen()" title="전체 화면">
            <i class="expand icon"></i> 전체화면
        </button>
    </div>

    <!-- 메인 타이머 카드 세그먼트 -->
    <div id="timer-segment" class="ui segment text center" style="padding: 3em 1em; background-color: #f8fafc; border-radius: 12px;">

        <!-- 상태 안내 문구 -->
        <div id="status-text" style="font-size: 1.5rem; font-weight: 600; color: #64748b; margin-bottom: 0.5em;">
            시간을 설정하고 시작을 눌러주세요
        </div>

        <!--超 대형 시계 디스플레이 -->
        <div id="timer-display" class="timer-huge-display">
            00:00
        </div>

        <!-- 빠른 시간 선택 버튼 (Preset) -->
        <div class="ui large buttons" style="margin-bottom: 1.5em; flex-wrap: wrap;">
            <button class="ui button" onclick="setPreset(1)">1분</button>
            <button class="ui button" onclick="setPreset(3)">3분</button>
            <button class="ui button" onclick="setPreset(5)">5분</button>
            <button class="ui button" onclick="setPreset(10)">10분</button>
            <button class="ui button" onclick="setPreset(15)">15분</button>
            <button class="ui button" onclick="setPreset(20)">20분</button>
            <button class="ui button" onclick="setPreset(30)">30분</button>
        </div>

        <br>

        <!-- 커스텀 시간 입력 및 주요 제어 버튼 -->
        <div style="display: flex; justify-content: center; align-items: center; gap: 1em; flex-wrap: wrap; margin-top: 1em;">
            <div class="ui action input" style="max-width: 180px;">
                <input type="number" id="custom-minutes" placeholder="분 입력" min="1" max="300">
                <button class="ui button" onclick="setCustomMinutes()">설정</button>
            </div>

            <div class="ui huge buttons">
                <button class="ui green button" id="btn-start" onclick="startTimer()"><i class="play icon"></i> 시작</button>
                <button class="ui orange button" id="btn-pause" onclick="pauseTimer()" disabled><i class="pause icon"></i> 일시정지</button>
                <button class="ui red button" id="btn-reset" onclick="resetTimer()"><i class="redo icon"></i> 리셋</button>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5em;">
        <a href="<?php echo $path_fix; ?>tools.php" class="ui button"><i class="arrow left icon"></i> 유틸리티 목록으로 돌아가기</a>
    </div>
</div>

<script>
    let totalSeconds = 0;
    let remainingSeconds = 0;
    let timerInterval = null;

    function updateDisplay() {
        let m = Math.floor(remainingSeconds / 60);
        let s = remainingSeconds % 60;
        let formattedM = String(m).padStart(2, '0');
        let formattedS = String(s).padStart(2, '0');

        let displayEl = document.getElementById('timer-display');
        displayEl.innerText = `${formattedM}:${formattedS}`;

        // 1분 이하 남은 경우 빨간색 경고 애니메이션 효과
        if (remainingSeconds > 0 && remainingSeconds <= 60) {
            displayEl.classList.add('timer-warning');
        } else {
            displayEl.classList.remove('timer-warning');
        }
    }

    function setPreset(minutes) {
        pauseTimer();
        totalSeconds = minutes * 60;
        remainingSeconds = totalSeconds;
        updateDisplay();
        document.getElementById('status-text').innerText = `${minutes}분 타이머가 설정되었습니다.`;
        document.getElementById('status-text').style.color = "#64748b";
    }

    function setCustomMinutes() {
        let min = parseInt(document.getElementById('custom-minutes').value);
        if (!isNaN(min) && min > 0) {
            setPreset(min);
        }
    }

    function startTimer() {
        if (remainingSeconds <= 0) {
            alert("시간을 먼저 설정해 주세요.");
            return;
        }
        if (timerInterval) return;

        document.getElementById('btn-start').disabled = true;
        document.getElementById('btn-pause').disabled = false;
        document.getElementById('status-text').innerText = "⏱️ 타이머 진행 중...";
        document.getElementById('status-text').style.color = "#0284c7";

        timerInterval = setInterval(() => {
            if (remainingSeconds > 0) {
                remainingSeconds--;
                updateDisplay();
                if (remainingSeconds === 0) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                    document.getElementById('status-text').innerText = "⏰ 시간이 종료되었습니다!";
                    document.getElementById('status-text').style.color = "#e11d48";
                    document.getElementById('btn-start').disabled = false;
                    document.getElementById('btn-pause').disabled = true;
                }
            }
        }, 1000);
    }

    function pauseTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
            document.getElementById('btn-start').disabled = false;
            document.getElementById('btn-pause').disabled = true;
            document.getElementById('status-text').innerText = "⏸️ 일시 정지됨";
            document.getElementById('status-text').style.color = "#d97706";
        }
    }

    function resetTimer() {
        pauseTimer();
        remainingSeconds = totalSeconds;
        updateDisplay();
        document.getElementById('status-text').innerText = "리셋되었습니다.";
        document.getElementById('status-text').style.color = "#64748b";
    }

    // 전체 화면 토글 함수
    function toggleFullScreen() {
        let elem = document.getElementById("timer-segment");
        if (!document.fullscreenElement) {
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                /* Safari */
                elem.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    updateDisplay();
</script>

<?php include($path_fix . "footer.php"); ?>