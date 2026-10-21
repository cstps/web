<?php
$show_title = $view_title;
include __DIR__ . "/../header.php";
?>

<div class="ui container margin-top-md margin-bottom-xl">
    <!-- 헤더 영역 -->
    <div class="ui header-card segment">
        <div class="ui horizontal list flex-between">
            <div class="item">
                <h2 class="ui header">
                    <i class="cloud teal icon"></i>
                    <div class="content">
                        워드 클라우드 생성기
                        <div class="sub header">텍스트 데이터에서 자주 등장하는 단어들의 빈도를 시각화하는 도구입니다.</div>
                    </div>
                </h2>
            </div>
            <div class="item">
                <a href="/tools.php" class="ui button grey icon label-btn">
                    <i class="arrow left icon"></i> 유틸리티 목록
                </a>
            </div>
        </div>
    </div>

    <!-- 내용 영역 -->
    <div class="ui grid stackable">
        <!-- 입력 폼 영역 -->
        <div class="six wide column">
            <div class="ui segment">
                <h3 class="ui dividing header"><i class="edit icon"></i> 텍스트 입력</h3>
                <div class="ui form">
                    <div class="field">
                        <label>분석할 문장 또는 본문 입력</label>
                        <textarea id="textInput" rows="10" placeholder="여기에 글을 입력하거나 붙여넣으세요..."></textarea>
                    </div>
                    <div class="field">
                        <label>최소 단어 길이</label>
                        <input type="number" id="minLen" value="2" min="1" max="5">
                    </div>
                    <div class="field">
                        <label>표시할 최대 단어 수</label>
                        <input type="number" id="maxWords" value="40" min="10" max="100">
                    </div>
                    <button type="button" class="ui teal fluid button" onclick="generateWordCloud()">
                        <i class="magic icon"></i> 워드 클라우드 생성
                    </button>
                </div>
            </div>
        </div>

        <!-- 캔버스 및 결과 영역 -->
        <div class="ten wide column">
            <div class="ui segment text-center">
                <h3 class="ui dividing header"><i class="chart bar icon"></i> 시각화 결과</h3>
                <div style="width: 100%; overflow-x: auto;">
                    <canvas id="cloudCanvas" width="600" height="420" style="border:1px solid #ddd; background:#fff; max-width:100%; height:auto;"></canvas>
                </div>
                <div class="margin-top-sm">
                    <button type="button" class="ui blue button tiny" onclick="downloadCanvas()">
                        <i class="download icon"></i> 이미지로 저장
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function generateWordCloud() {
        const text = document.getElementById('textInput').value.trim();
        const minLen = parseInt(document.getElementById('minLen').value) || 2;
        const maxWords = parseInt(document.getElementById('maxWords').value) || 40;
        const canvas = document.getElementById('cloudCanvas');
        const ctx = canvas.getContext('2d');

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!text) {
            alert("텍스트를 입력해 주세요.");
            return;
        }

        // 간단한 특수문자 제거 및 단어 추출
        const rawWords = text.replace(/[^\w\s가-힣]/g, ' ').split(/\s+/);
        const freqMap = {};

        rawWords.forEach(word => {
            const cleaned = word.trim();
            if (cleaned.length >= minLen) {
                freqMap[cleaned] = (freqMap[cleaned] || 0) + 1;
            }
        });

        // 빈도순 정렬
        const sorted = Object.keys(freqMap)
            .map(key => ({
                text: key,
                size: freqMap[key]
            }))
            .sort((a, b) => b.size - a.size)
            .slice(0, maxWords);

        if (sorted.length === 0) {
            alert("분석할 단어가 없습니다. 최소 단어 길이를 조절해 보세요.");
            return;
        }

        const maxFreq = sorted[0].size;
        const minFreq = sorted[sorted.length - 1].size;

        const colors = ['#008080', '#1e90ff', '#ff6b6b', '#2ed573', '#ffa500', '#70a1ff', '#5352ed', '#ff4757'];
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const placedBoxes = [];

        sorted.forEach((item, index) => {
            // 폰트 크기 계산 (14px ~ 48px)
            const fontSize = maxFreq === minFreq ? 24 : Math.floor(14 + ((item.size - minFreq) / (maxFreq - minFreq)) * 34);
            ctx.font = `bold ${fontSize}px sans-serif`;
            const metrics = ctx.measureText(item.text);
            const w = metrics.width + 10;
            const h = fontSize + 6;

            let posX = centerX;
            let posY = centerY;
            let angle = 0;
            let radius = 0;
            let placed = false;

            // 나선형(Spiral) 배치 알고리즘
            for (let i = 0; i < 500; i++) {
                angle += 0.5;
                radius += 1.5;
                posX = centerX + radius * Math.cos(angle) - w / 2;
                posY = centerY + radius * Math.sin(angle) + h / 4;

                // 캔버스 범위를 벗어나지 않도록 검사
                if (posX < 5 || posX + w > canvas.width - 5 || posY - h < 5 || posY > canvas.height - 5) {
                    continue;
                }

                // 다른 단어와의 충돌(Overlap) 검사
                let collision = false;
                for (let box of placedBoxes) {
                    if (!(posX + w < box.x || posX > box.x + box.w || posY < box.y - box.h || posY - h > box.y)) {
                        collision = true;
                        break;
                    }
                }

                if (!collision) {
                    placed = true;
                    break;
                }
            }

            if (placed || index === 0) {
                ctx.fillStyle = colors[index % colors.length];
                ctx.fillText(item.text, posX, posY);
                placedBoxes.push({
                    x: posX,
                    y: posY,
                    w: w,
                    h: h
                });
            }
        });
    }

    function downloadCanvas() {
        const canvas = document.getElementById('cloudCanvas');
        const link = document.createElement('a');
        link.download = 'wordcloud.png';
        link.href = canvas.toDataURL();
        link.click();
    }
</script>

<?php include __DIR__ . "/../footer.php"; ?>