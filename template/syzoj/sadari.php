<?php $show_title = "🎲 온라인 사다리 타기 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>


<div style="max-width:600px; margin: 40px auto 20px auto; background: #fff; border-radius:16px; box-shadow: 0 4px 18px #0001; padding: 30px 22px;">
  <h1 style="text-align:center; margin-bottom:24px;">🎲 온라인 사다리 타기</h1>
  <form onsubmit="return false;" style="margin-bottom:18px;">
    <div style="display:flex; gap:1em; flex-wrap:wrap;">
      <label style="flex:1; min-width:180px;">
        참가자명(1줄1명)
        <textarea id="ladderNames" style="width:100%;min-height:85px;resize:vertical;margin-bottom:7px;" placeholder="김민수&#10;이지우&#10;박서연"></textarea>
      </label>
      <label style="flex:1; min-width:180px;">
        결과항목(1줄1개, 없으면 1번~N번 자동)
        <textarea id="ladderResults" style="width:100%;min-height:85px;resize:vertical;margin-bottom:7px;" placeholder="1번 자리&#10;2번 자리&#10;3번 자리"></textarea>
      </label>
    </div>
    <div style="text-align:center; margin:8px 0;">
      <button type="button" class="btn btn-primary" onclick="drawLadder()">사다리 생성</button>
      <button type="button" class="btn btn-success" onclick="startLadder()" disabled id="startBtn">추첨 시작</button>
      <button type="button" class="btn btn-warning" onclick="resetLadder()" id="resetBtn" style="display:none;">다시하기</button>
    </div>
  </form>
  <div id="ladderArea" style="text-align:center; margin-bottom:16px;">
    <canvas id="ladderCanvas" width="500" height="360" style="max-width:98%; background:#fcfcff; border-radius:8px; box-shadow:0 1px 10px #0002; border:1px solid #c9d2e6; display:none;"></canvas>
  </div>
  <div id="ladderResult" style="font-size:1.15em; text-align:center; min-height:2.2em;"></div>
</div>

<script>
let ladderData = null;

function drawLadder() {
    const names = document.getElementById('ladderNames').value.split('\n').map(x=>x.trim()).filter(x=>x);
    let results = document.getElementById('ladderResults').value.split('\n').map(x=>x.trim()).filter(x=>x);
    if (names.length < 2) {
        alert("참가자는 2명 이상 입력하세요!");
        return;
    }
    if (results.length === 0) results = names.map((_,i)=>`${i+1}번 자리`);
    if (results.length !== names.length) {
        alert("참가자 수와 결과항목 수가 다릅니다!");
        return;
    }

    // 사다리 데이터 생성
    const cols = names.length, rows = Math.max(22, cols*6); // 선분 충분히
    // 가로선 랜덤 생성(이웃끼리만, 연속X)
    let horizontals = [];
    for (let r=1; r<rows-1; r++) {
        for (let c=0; c<cols-1; c++) {
            // 이전 행에서 이미 가로선이면 연속 불가
            if (horizontals.some(h=>h.row===r-1 && (h.col===c || h.col===c-1))) continue;
            // 30% 확률로 생성 (조정 가능)
            if (Math.random() < 0.32) horizontals.push({row:r, col:c});
        }
    }
    ladderData = {names, results, cols, rows, horizontals};
    // Canvas 그리기
    renderLadderCanvas();
    document.getElementById('ladderResult').innerHTML = "";
    document.getElementById('startBtn').disabled = false;
    document.getElementById('resetBtn').style.display = "none";
}

function renderLadderCanvas(highlightPath = null) {
    const {names, results, cols, rows, horizontals} = ladderData;
    const canvas = document.getElementById('ladderCanvas');
    canvas.style.display = '';
    const w = canvas.width = Math.max(420, Math.min(600, cols*85));
    const h = canvas.height = 340 + (cols > 6 ? (cols-6)*30 : 0);
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0,0,w,h);
    // 사다리 간격/좌표
    const xgap = w/(cols+1);
    const ygap = (h-100)/(rows+1);
    // 세로줄
    for (let c=0; c<cols; c++) {
        ctx.strokeStyle="#3867d6"; ctx.lineWidth=3;
        ctx.beginPath();
        ctx.moveTo(xgap*(c+1), 60);
        ctx.lineTo(xgap*(c+1), h-50);
        ctx.stroke();
    }
    // 가로줄
    for (const hline of horizontals) {
        const y = 60+ygap*hline.row;
        const x1 = xgap*(hline.col+1), x2 = xgap*(hline.col+2);
        ctx.strokeStyle="#e17055"; ctx.lineWidth=4;
        ctx.beginPath();
        ctx.moveTo(x1, y);
        ctx.lineTo(x2, y);
        ctx.stroke();
    }
    // 이름
    ctx.font="bold 1em Pretendard, sans-serif";
    ctx.textAlign="center";
    for (let c=0; c<cols; c++) {
        ctx.fillStyle="#444";
        ctx.fillText(names[c], xgap*(c+1), 35);
        ctx.fillStyle="#9a59b5";
        ctx.fillText(results[c], xgap*(c+1), h-20);
    }
    // 하이라이트 경로(애니메이션)
    if (highlightPath) {
        ctx.strokeStyle="#fbc531";
        ctx.lineWidth=6;
        ctx.shadowColor = "#ffe066";
        ctx.shadowBlur = 18;
        ctx.beginPath();
        ctx.moveTo(highlightPath[0].x, highlightPath[0].y);
        for (const pt of highlightPath) ctx.lineTo(pt.x, pt.y);
        ctx.stroke();
        ctx.shadowBlur = 0;
    }
}

// 사다리 경로(좌표) 반환
function getLadderPath(idx) {
    const {cols, rows, horizontals} = ladderData;
    const canvas = document.getElementById('ladderCanvas');
    const w = canvas.width, h = canvas.height;
    const xgap = w/(cols+1), ygap = (h-100)/(rows+1);
    let c = idx, path = [];
    let x = xgap*(c+1), y = 60;
    path.push({x, y});
    for (let r=1; r<=rows; r++) {
        // 아래로
        y = 60+ygap*r;
        // 가로선 만남(왼/오)
        const hL = horizontals.find(hh=>hh.row===r && hh.col===c-1);
        const hR = horizontals.find(hh=>hh.row===r && hh.col===c);
        if (hL) { // 왼쪽 이동
            x -= xgap;
            path.push({x, y});
            c--;
        } else if (hR) { // 오른쪽 이동
            x += xgap;
            path.push({x, y});
            c++;
        }
        path.push({x, y});
    }
    return {path, resultIdx: c};
}

// 애니메이션 및 결과 출력
function startLadder() {
    if (!ladderData) return;
    document.getElementById('startBtn').disabled = true;
    document.getElementById('resetBtn').style.display = "";
    const {names, results, cols} = ladderData;
    let order = Array.from(Array(cols).keys());
    shuffle(order); // 누구부터 애니메이션할지 랜덤

    let pairings = [];
    let i = 0;
    function animateNext() {
        if (i >= order.length) {
            // 최종 결과
            let html = "<b>결과:</b><br><table style='margin:0 auto; font-size:1.07em;'>";
            for (const [name, result] of pairings) {
                html += `<tr><td style='padding:3px 12px; text-align:right; color:#333;'>${name}</td><td>→</td><td style='color:#1976d2;'>${result}</td></tr>`;
            }
            html += "</table>";
            document.getElementById('ladderResult').innerHTML = html;
            return;
        }
        let idx = order[i];
        let {path, resultIdx} = getLadderPath(idx);
        renderLadderCanvas(path);
        setTimeout(()=>{
            renderLadderCanvas(); // 경로 하이라이트 제거
            pairings.push([names[idx], results[resultIdx]]);
            i++;
            animateNext();
        }, 1200 + Math.random()*300);
    }
    animateNext();
}

function resetLadder() {
    drawLadder();
}

function shuffle(a){for(let i=a.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[a[i],a[j]]=[a[j],a[i]];}}
</script>


<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
