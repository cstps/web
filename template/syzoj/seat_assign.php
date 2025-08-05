<?php $show_title = "교실 자리 배치 도구 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>

<link rel="stylesheet" href="<?php echo $path_fix . "template/$OJ_TEMPLATE" ?>/css/seat_assign.css" />
<script src="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE/js/"?>html2canvas.min.js"></script>
<div class="cal_container">
    <h1> 🪑 교실 자리 배치 도구 </h1>
    <div class='hint'>
        <h2> 📝 사용법 </h2>
        <div class='description'>① 학생 명단, 행, 열을 입력 후 [자리표 생성]</div>
        <div class='description'>② 자리표에서 원하는 칸을 클릭해 고정할 학생을 직접 지정/해제</div>
        <div class='description'>③ [자리 자동배치]로 나머지 학생 자리 자동 배정</div>
        <div class='description'>④ <b>자리표에서 두 칸을 차례로 클릭하면 학생/빈자리 모두 교환 가능 (고정자리는 불가)</b></div>
    </div>
    <div style="margin-bottom:1em; display:flex; gap:0.6em; flex-wrap:wrap;">
        <button type="button" class="btn btn-success" onclick="window.print()">
            🖨️ 자리표 인쇄/출력
        </button>
        <button type="button" class="btn btn-secondary" onclick="copyTableAsHTML()" style="display: flex; align-items: center; gap:0.35em;">
            <!-- 복사 아이콘 (SVG) -->
            <svg width="20" height="20" fill="none" stroke="#5a2" stroke-width="2" viewBox="0 0 20 20" style="vertical-align:middle">
                <rect x="5" y="3" width="12" height="14" rx="2" fill="#ecffe2" stroke="#5a2"/>
                <rect x="2" y="6" width="12" height="11" rx="2" fill="white" stroke="#a4c4b4"/>
            </svg>
            자리표 복사
        </button>
        <button type="button" onclick="saveTableAsImage()" style="display: flex; align-items: center; gap:0.35em; background: #ffe8ec; color: #d33044; border:1px solid #e9aabb; border-radius:6px; font-weight:600; padding: 6px 16px; transition: background .18s;">
            <!-- 이미지 저장 아이콘 (SVG) -->
            <svg width="20" height="20" fill="none" stroke="#e0355c" stroke-width="2" viewBox="0 0 20 20" style="vertical-align:middle">
            <rect x="3" y="4" width="14" height="12" rx="2.5" fill="#fff4f6" stroke="#e0355c"/>
            <circle cx="8" cy="9" r="2" fill="#ffe3ea" stroke="#e0355c"/>
            <path d="M3 15l4.5-5a2 2 0 0 1 3 0l6 7" stroke="#e0355c" fill="none"/>
            </svg>
            이미지로 저장
        </button>
    </div>



    <form id="seatForm" onsubmit="return false;">
        <textarea id="studentList" placeholder="예시:&#10;김민수&#10;이지우&#10;박서연"></textarea>
        <div style="display:flex; gap:1em; margin:1em 0; flex-wrap:wrap;">
            <label>행: <input type="number" id="rowCnt" value="5" min="1" max="20" style="width:2em;"></label>
            <label>열: <input type="number" id="colCnt" value="6" min="1" max="20" style="width:2em;"></label>
            <button type="button" class="btn btn-primary" onclick="drawSeatGrid()">자리표 생성</button>
            <label>효과시간(ms): 
                <input type="number" id="animSpeed" value="260" min="60" max="2000" style="width:4em;">
            </label>
            <label >배치방식:
                <select id="assignMode">
                    <option value="random">랜덤</option>
                    <option value="order">입력순서</option>
                    <option value="alpha">가나다순</option>
                </select>
            </label>
            <button type="button" class="btn btn-info" onclick="autoAssignSeats(true)">자리배치</button>

        </div>
    </form>
    <div id="seatTable"></div>
    <div id="unassignedBox" style="margin:10px 0 0 0"></div>
</div>

<script>
let studentList = [], rows = 5, cols = 6;
let fixedSeats = {}; // "row,col" : 학생명
let seatGrid = [];

function shuffle(arr) {
    for(let i=arr.length-1; i>0; i--) {
        const j = Math.floor(Math.random()*(i+1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
}

function drawSeatGrid() {
    // 입력값 반영
    studentList = document.getElementById('studentList').value.split('\n').map(x=>x.trim()).filter(x=>x);
    rows = parseInt(document.getElementById('rowCnt').value)||1;
    cols = parseInt(document.getElementById('colCnt').value)||1;
    if(studentList.length === 0) { alert("학생 명단을 입력하세요."); return; }
    if(rows<1||cols<1) { alert("행/열 입력 오류"); return; }
    // 상태 리셋
    fixedSeats = {};
    seatGrid = [];
    // 자리표 생성
    let html = `<ul id="seatGrid" class="seat-grid"></ul>`;
    document.getElementById('seatTable').innerHTML = html;
    let grid = document.getElementById('seatGrid');
    grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    // 칸 그리기
    for(let r=0;r<rows;r++) {
        for(let c=0;c<cols;c++) {
            let li = document.createElement('li');
            li.className = "seat-item seat-empty";
            li.dataset.pos = `${r},${c}`;
            li.innerHTML = `
                <span class="lock-btn" style="display:none;" title="고정 해제">🔒</span>
                <span class="seat-content">빈자리</span>
                <div class="seat-num">${r+1},${c+1}</div>
            `;
            grid.appendChild(li);
            seatGrid.push(li);
            // 고정학생 지정/해제
            li.addEventListener('click', function(e){
                if(e.target.classList.contains('lock-btn')) return;
                showStudentSelect(li);
            });
            li.querySelector('.lock-btn').addEventListener('click', function(e){
                e.stopPropagation();
                let pos = li.dataset.pos;
                delete fixedSeats[pos];
                li.className = "seat-item seat-empty";
                li.querySelector('.seat-content').textContent = "빈자리";
                li.querySelector('.lock-btn').style.display = "none";
                updateUnassignedList();
            });
        }
    }
    updateUnassignedList();
    enableSeatSwap(); // 교환 기능 활성화
}

function showStudentSelect(li) {
    // 이미 드롭다운 있으면 제거
    let old = document.getElementById('studentSelect');
    if(old) old.remove();
    // 이미 고정된 학생 목록
    let assigned = Object.values(fixedSeats);
    // 현재 칸의 학생 (수정/해제 지원)
    let curVal = li.querySelector('.seat-content').textContent;
    // 배정 가능 학생
    let options = studentList.filter(s => !assigned.includes(s) || s===curVal);
    options.unshift(""); // "빈자리" 선택
    // 드롭다운 생성
    let sel = document.createElement('select');
    sel.id = 'studentSelect';
    sel.style.position = 'absolute';
    sel.style.zIndex = 100;
    sel.style.top = '32px';
    sel.style.left = '16px';
    sel.innerHTML = options.map(n=>`<option value="${n}"${n===curVal?' selected':''}>${n||"선택안함(빈자리)"}</option>`).join('');
    li.appendChild(sel);
    sel.focus();
    sel.addEventListener('change', function() {
        let pos = li.dataset.pos;
        let val = sel.value;
        if(val) {
            fixedSeats[pos] = val;
            li.classList.remove("seat-empty");
            li.classList.add("locked");
            li.querySelector('.seat-content').textContent = val;
            li.querySelector('.lock-btn').style.display = "";
        } else {
            delete fixedSeats[pos];
            li.className = "seat-item seat-empty";
            li.querySelector('.seat-content').textContent = "빈자리";
            li.querySelector('.lock-btn').style.display = "none";
        }
        sel.remove();
        updateUnassignedList();
    });
    sel.addEventListener('blur', ()=>setTimeout(()=>sel.remove(),200));
}

function updateUnassignedList() {
    // 남은 학생 목록
    let used = Object.values(fixedSeats);
    let left = studentList.filter(n=>!used.includes(n));
    document.getElementById('unassignedBox').innerHTML = 
        `<b>고정하지 않은 학생: </b>${left.length?left.join(", "):"없음"}`;
}

// 자리 자동 배치
function autoAssignSeats(animated = true) {
    let animSpeed = parseInt(document.getElementById('animSpeed').value) || 260;
    if(animSpeed < 30) animSpeed = 30;
    if(animSpeed > 5000) animSpeed = 5000;

    let used = Object.values(fixedSeats);
    let left = studentList.filter(n=>!used.includes(n));
    let mode = document.getElementById('assignMode').value;
    if(mode=="random") shuffle(left);
    else if(mode=="alpha") left.sort((a,b)=>a.localeCompare(b,"ko"));

    let slots = [];
    seatGrid.forEach(li=>{
        let pos = li.dataset.pos;
        if(!fixedSeats[pos]) slots.push(li);
    });

    if(animated) {
        function fillNext(i) {
            if(i >= slots.length) { updateUnassignedList(); enableSeatSwap(); return; }
            let li = slots[i];
            let name = left[i] || "";
            if(name) {
                li.className = "seat-item seat-anim";
                li.querySelector('.seat-content').textContent = name;
                li.querySelector('.lock-btn').style.display = "none";
            } else {
                li.className = "seat-item seat-empty";
                li.querySelector('.seat-content').textContent = "빈자리";
                li.querySelector('.lock-btn').style.display = "none";
            }
            setTimeout(()=> {
                li.classList.remove("seat-anim");
                fillNext(i+1);
            }, animSpeed);
        }
        fillNext(0);
    } else {
        slots.forEach((li,i)=>{
            let name = left[i]||"";
            if(name) {
                li.className = "seat-item";
                li.querySelector('.seat-content').textContent = name;
                li.querySelector('.lock-btn').style.display = "none";
            } else {
                li.className = "seat-item seat-empty";
                li.querySelector('.seat-content').textContent = "빈자리";
                li.querySelector('.lock-btn').style.display = "none";
            }
        });
        updateUnassignedList();
        enableSeatSwap();
    }
}



// 클릭-클릭 자리 교환 활성화
function enableSeatSwap() {
    let firstClick = null;
    seatGrid.forEach(li=>{
        // 기존에 등록된 이벤트 제거
        li.onmousedown = null;
        li.onmouseup = null;
        // 교환용 클릭 이벤트
        li.onmousedown = function(e){
            // 고정자리는 클릭 불가
            if(li.classList.contains('locked')) return;
            if(e.target.classList.contains('lock-btn') || e.target.tagName==='SELECT') return;
            if(!firstClick) {
                firstClick = li;
                li.classList.add('selected');
            } else if(firstClick !== li) {
                if(firstClick.classList.contains('locked')) {
                    firstClick.classList.remove('selected');
                    firstClick = null;
                    return;
                }
                // swap
                let t1 = firstClick.querySelector('.seat-content').textContent;
                let t2 = li.querySelector('.seat-content').textContent;
                firstClick.querySelector('.seat-content').textContent = t2;
                li.querySelector('.seat-content').textContent = t1;
                syncEmptyClass(firstClick);
                syncEmptyClass(li);
                firstClick.classList.remove('selected');
                firstClick = null;
            } else {
                firstClick.classList.remove('selected');
                firstClick = null;
            }
        };
    });
}
// 학생이름/빈자리 스타일 동기화
function syncEmptyClass(li) {
    let text = li.querySelector('.seat-content').textContent;
    if(text === "빈자리") li.classList.add("seat-empty");
    else li.classList.remove("seat-empty");
}
function copyTableAsHTML() {
    let table = document.getElementById('seatTable');
    if (!navigator.clipboard) {
        alert("브라우저가 지원하지 않습니다.");
        return;
    }
    // HTML 복사
    let range = document.createRange();
    range.selectNode(table);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    try {
        document.execCommand('copy');
        alert("화면 모양 그대로 복사되었습니다!\n(엑셀/워드 등에도 붙여넣기 가능)");
    } catch {
        alert("복사 실패 (브라우저 제한)");
    }
    window.getSelection().removeAllRanges();
}
// html2canvas 라이브러리 추가 후
function saveTableAsImage() {
    html2canvas(document.getElementById('seatTable')).then(canvas => {
        let link = document.createElement('a');
        link.href = canvas.toDataURL();
        link.download = "자리표.png";
        link.click();
    });
}


</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
