<?php
$show_title = $view_title;

function wordcloud_view_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$wordcloud_csrf_html = '';
ob_start();
include('./csrf.php');
$wordcloud_csrf_html = ob_get_clean();

$entry_texts = array();

if (isset($view_entries) && is_array($view_entries)) {
    foreach ($view_entries as $entry) {
        $entry_texts[] = (string)$entry['text_content'];
    }
}

$can_view_wordcloud_entries = (
    isset($view_manage_authorized) &&
    $view_manage_authorized === true
);

include(__DIR__ . '/../header.php');
?>
<style>
.wordcloud-page {
    margin-top: 2rem;
    margin-bottom: 3rem;
}
.wordcloud-card {
    background: #fff;
    border: 1px solid #d9e2ec;
    border-radius: 12px;
    padding: 1.4rem;
    margin-bottom: 1.2rem;
}
.wordcloud-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 520px);
    gap: 1.2rem;
    align-items: start;
}
.wordcloud-canvas-wrap {
    width: 100%;
    overflow: auto;
    background: #f8fafc;
    border: 1px solid #d9e2ec;
    border-radius: 10px;
    padding: 0.5rem;
}
#wordcloud-canvas {
    display: block;
    width: 100%;
    max-width: 760px;
    height: auto;
    background: #fff;
    border-radius: 8px;
}
.wordcloud-code {
    font-size: 1.2rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}
.wordcloud-room-url {
    word-break: break-all;
}
.wordcloud-entry-list {
    max-height: 300px;
    overflow: auto;
}
@media (max-width: 900px) {
    .wordcloud-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

<div class="ui container wordcloud-page">
    <h2 class="ui header">
        <i class="cloud teal icon"></i>
        <div class="content">
            워드클라우드
            <div class="sub header">
                교사가 공유 코드를 만들고 학생들이 입력한 내용을 단어 구름으로 봅니다.
            </div>
        </div>
    </h2>

    <?php if (isset($_GET['saved']) && $_GET['saved'] === '1') { ?>
        <div class="ui positive message">
            입력 내용을 저장했습니다.
        </div>
    <?php } ?>

    <?php if ($view_room) { ?>
        <div class="wordcloud-grid">
            <section class="wordcloud-card">
                <h3 class="ui header">
                    <?php echo wordcloud_view_escape($view_room['title']); ?>
                </h3>

                <p>
                    공유 코드:
                    <span class="wordcloud-code">
                        <?php echo wordcloud_view_escape($view_room['access_code']); ?>
                    </span>
                </p>

                <p class="wordcloud-room-url">
                    링크:
                    <?php
                    $room_url = 'https://' .
                        wordcloud_view_escape($_SERVER['HTTP_HOST']) .
                        '/tools/wordcloud.php?code=' .
                        rawurlencode((string)$view_room['access_code']);
                    ?>
                    <a href="<?php echo $room_url; ?>">
                        <?php echo $room_url; ?>
                    </a>
                </p>

                <?php if (!$can_view_wordcloud_entries) { ?>
                    <p>
                        <a class="ui small basic button"
                           href="/tools/wordcloud.php?code=<?php
                               echo rawurlencode((string)$view_room['access_code']);
                           ?>&manage=1">
                            관리자로 보기
                        </a>
                    </p>
                <?php } ?>

                <?php if (
                    isset($view_manage_mode) &&
                    $view_manage_mode === true &&
                    !$can_view_wordcloud_entries
                ) { ?>
                    <form class="ui form" method="post" action="/tools/wordcloud.php">
                        <?php echo $wordcloud_csrf_html; ?>
                        <input type="hidden" name="action" value="manage_login">
                        <input type="hidden" name="code"
                               value="<?php echo wordcloud_view_escape($view_room['access_code']); ?>">

                        <div class="field">
                            <label for="wordcloud-manage-password">관리 비밀번호</label>
                            <input id="wordcloud-manage-password"
                                   type="password"
                                   name="manage_password"
                                   required>
                        </div>

                        <button class="ui small teal button" type="submit">
                            관리 권한 확인
                        </button>
                    </form>
                    <div class="ui divider"></div>
                <?php } ?>

                <?php if (intval($view_room['status']) !== 1) { ?>
                    <div class="ui warning message">
                        현재 중지된 방입니다. 새 입력을 받을 수 없습니다.
                    </div>
                <?php } else { ?>
                    <form class="ui form" method="post" action="/tools/wordcloud.php">
                        <?php echo $wordcloud_csrf_html; ?>
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="code"
                               value="<?php echo wordcloud_view_escape($view_room['access_code']); ?>">

                        <div class="field">
                            <label for="wordcloud-display-name">이름 또는 별명</label>
                            <input id="wordcloud-display-name"
                                   type="text"
                                   name="display_name"
                                   maxlength="80"
                                   placeholder="선택 입력">
                        </div>

                        <div class="field">
                            <label for="wordcloud-text">입력 내용</label>
                            <textarea id="wordcloud-text"
                                      name="text_content"
                                      rows="4"
                                      maxlength="1000"
                                      required
                                      placeholder="단어, 문장, 소감을 입력하세요."></textarea>
                        </div>

                        <button class="ui teal button" type="submit">
                            입력하기
                        </button>
                    </form>
                <?php } ?>
            </section>

            <section class="wordcloud-card">
                <h3 class="ui header">결과</h3>

                <?php if (!$view_entries) { ?>
                    <div class="ui message">
                        아직 입력된 내용이 없습니다.
                    </div>
                <?php } ?>

                <div class="wordcloud-canvas-wrap">
                    <canvas id="wordcloud-canvas" width="760" height="460"></canvas>
                </div>

                <p>
                    <button id="wordcloud-redraw" type="button" class="ui small basic button">
                        다시 배치
                    </button>
                    <button id="wordcloud-download" type="button" class="ui small basic button">
                        이미지 저장
                    </button>
                </p>
            </section>
        </div>

        <?php if ($can_view_wordcloud_entries) { ?>
            <section class="wordcloud-card">
                <h3 class="ui header">최근 입력</h3>

                <?php if (!$view_entries) { ?>
                    <p>입력 기록이 없습니다.</p>
                <?php } else { ?>
                    <div class="wordcloud-entry-list">
                        <table class="ui compact table">
                            <thead>
                                <tr>
                                    <th>이름</th>
                                    <th>내용</th>
                                    <th>입력 시각</th>
                                </tr>
                            </thead>
                            <tbody id="wordcloud-entry-tbody">
                                <?php foreach ($view_entries as $entry) { ?>
                                    <tr>
                                        <td>
                                            <?php echo wordcloud_view_escape(
                                                $entry['display_name'] ?: ($entry['user_id'] ?: '익명')
                                            ); ?>
                                        </td>
                                        <td>
                                            <?php echo wordcloud_view_escape($entry['text_content']); ?>
                                        </td>
                                        <td>
                                            <?php echo wordcloud_view_escape($entry['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>
    <?php } ?>

    <section class="wordcloud-card">
        <h3 class="ui header">새 워드클라우드 방 만들기</h3>

        <?php if (
            isset($view_create_error) &&
            is_string($view_create_error) &&
            $view_create_error !== ''
        ) { ?>
            <div class="ui negative message">
                <?php echo wordcloud_view_escape($view_create_error); ?>
            </div>
        <?php } ?>

        <?php if (!isset($_SESSION[$OJ_NAME . '_user_id'])) { ?>
            <div class="ui info message">
                비로그인 상태에서는 생성 보안키를 입력하면 방을 만들 수 있습니다.
            </div>
        <?php } ?>

            <form class="ui form" method="post" action="/tools/wordcloud.php">
                <?php echo $wordcloud_csrf_html; ?>
                <input type="hidden" name="action" value="create">

                <div class="field">
                    <label for="wordcloud-room-title">방 제목</label>
                    <input id="wordcloud-room-title"
                           type="text"
                           name="title"
                           maxlength="120"
                           required
                           placeholder="예: 오늘 수업에서 기억나는 단어">
                </div>

                <?php if (!isset($_SESSION[$OJ_NAME . '_user_id'])) { ?>
                    <div class="field">
                        <label for="wordcloud-create-key">생성 보안키</label>
                        <input id="wordcloud-create-key"
                               type="password"
                               name="create_key"
                               maxlength="8"
                               inputmode="numeric"
                               placeholder="생성 보안키">
                    </div>
                <?php } ?>

                <div class="field">
                    <label for="wordcloud-room-password">관리 비밀번호</label>
                    <input id="wordcloud-room-password"
                           type="password"
                           name="manage_password"
                           maxlength="72"
                           <?php echo isset($_SESSION[$OJ_NAME . '_user_id']) ? '' : 'required'; ?>
                           placeholder="<?php echo isset($_SESSION[$OJ_NAME . '_user_id'])
                               ? '선택 입력, 4자 이상'
                               : '비로그인 생성 시 필수, 4자 이상'; ?>">
                </div>

                <div class="field">
                    <label>
                        <input type="checkbox" name="allow_anonymous" value="1" checked>
                        익명 또는 비로그인 학생 입력 허용
                    </label>
                </div>

                <button class="ui primary button" type="submit">
                    방 만들기
                </button>
            </form>
    </section>

    <?php if (isset($_SESSION[$OJ_NAME . '_user_id'])) { ?>
        <section class="wordcloud-card">
            <h3 class="ui header">내 워드클라우드 방</h3>

            <?php if (!$view_my_rooms) { ?>
                <p>아직 만든 방이 없습니다.</p>
            <?php } else { ?>
                <table class="ui compact table">
                    <thead>
                        <tr>
                            <th>제목</th>
                            <th>공유 코드</th>
                            <th>입력 수</th>
                            <th>상태</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($view_my_rooms as $room) { ?>
                            <tr>
                                <td>
                                    <a href="/tools/wordcloud.php?code=<?php
                                        echo rawurlencode((string)$room['access_code']);
                                    ?>">
                                        <?php echo wordcloud_view_escape($room['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo wordcloud_view_escape($room['access_code']); ?></td>
                                <td><?php echo intval($room['entry_count']); ?></td>
                                <td>
                                    <?php echo intval($room['status']) === 1 ? '진행 중' : '중지'; ?>
                                </td>
                                <td>
                                    <form method="post"
                                          action="/tools/wordcloud.php"
                                          style="display:inline;">
                                        <?php echo $wordcloud_csrf_html; ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="room_id"
                                               value="<?php echo intval($room['room_id']); ?>">
                                        <button class="ui mini basic button" type="submit">
                                            <?php echo intval($room['status']) === 1 ? '중지' : '다시 열기'; ?>
                                        </button>
                                    </form>

                                    <form method="post"
                                          action="/tools/wordcloud.php"
                                          style="display:inline;"
                                          onsubmit="return confirm('이 방과 입력 내용을 삭제할까요?');">
                                        <?php echo $wordcloud_csrf_html; ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_id"
                                               value="<?php echo intval($room['room_id']); ?>">
                                        <button class="ui mini red basic button" type="submit">
                                            삭제
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </section>
    <?php } ?>

    <p>
        <a class="ui basic button" href="/tools.php">
            유틸리티 목록으로
        </a>
    </p>
</div>

<script>
(function () {
    "use strict";

    let entries = <?php echo json_encode(
        $entry_texts,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;

    const canvas = document.getElementById("wordcloud-canvas");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    function wordsFromText(items) {
        const counts = new Map();

        items.join(" ")
            .replace(/[^\w가-힣ㄱ-ㅎㅏ-ㅣ\s]/g, " ")
            .split(/\s+/)
            .map(function (word) { return word.trim(); })
            .filter(function (word) {
                return word.length >= 2 &&
                    !["그리고", "하지만", "그래서", "있는", "없는", "합니다", "했다"].includes(word);
            })
            .forEach(function (word) {
                counts.set(word, (counts.get(word) || 0) + 1);
            });

        return Array.from(counts.entries())
            .map(function (item) {
                return { text: item[0], count: item[1] };
            })
            .sort(function (a, b) {
                return b.count - a.count || a.text.localeCompare(b.text);
            })
            .slice(0, 60);
    }

    function overlap(a, b) {
        return !(
            a.x + a.w < b.x ||
            b.x + b.w < a.x ||
            a.y + a.h < b.y ||
            b.y + b.h < a.y
        );
    }

    let lastEntryCount = entries.length;
    let refreshBusy = false;

    async function refreshEntries() {
        if (refreshBusy || !<?php echo $view_room ? 'true' : 'false'; ?>) {
            return;
        }

        refreshBusy = true;

        try {
            const code = <?php echo json_encode(
                $view_room ? (string)$view_room['access_code'] : '',
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ); ?>;

            if (!code) {
                return;
            }

            const response = await fetch(
                "/tools/wordcloud.php?code=" +
                encodeURIComponent(code) +
                "&ajax=entries",
                {
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: {"Accept": "application/json"}
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (
                !data ||
                data.ok !== true ||
                !Array.isArray(data.texts) ||
                typeof data.entry_count !== "number"
            ) {
                return;
            }

            if (data.entry_count !== lastEntryCount) {
                entries = data.texts.map(function (item) {
                    return String(item || "");
                });
                lastEntryCount = data.entry_count;
                draw();

                if (data.can_view_entries === true) {
                    refreshEntryTable(data.entries || []);
                }
            }
        } catch (error) {
            // 자동 갱신 실패는 화면 사용을 막지 않는다.
        } finally {
            refreshBusy = false;
        }
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function refreshEntryTable(items) {
        const tbody = document.getElementById("wordcloud-entry-tbody");

        if (!tbody || !Array.isArray(items)) {
            return;
        }

        tbody.innerHTML = items.map(function (item) {
            return "<tr>" +
                "<td>" + escapeHtml(item.display_name || "익명") + "</td>" +
                "<td>" + escapeHtml(item.text_content || "") + "</td>" +
                "<td>" + escapeHtml(item.created_at || "") + "</td>" +
                "</tr>";
        }).join("");
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const words = wordsFromText(entries);
        if (!words.length) {
            ctx.fillStyle = "#64748b";
            ctx.font = "22px sans-serif";
            ctx.textAlign = "center";
            ctx.fillText("입력된 단어가 없습니다.", canvas.width / 2, canvas.height / 2);
            return;
        }

        const max = words[0].count;
        const min = words[words.length - 1].count;
        const colors = [
            "#0f766e", "#2563eb", "#7c3aed", "#dc2626",
            "#ea580c", "#16a34a", "#0891b2", "#4f46e5"
        ];
        const boxes = [];

        words.forEach(function (word, index) {
            const ratio = max === min ? 0.6 : (word.count - min) / (max - min);
            const fontSize = Math.round(16 + ratio * 38);
            ctx.font = "700 " + fontSize + "px sans-serif";
            ctx.textBaseline = "top";

            const metrics = ctx.measureText(word.text);
            const width = metrics.width + 12;
            const height = fontSize + 10;
            let placed = null;

            for (let i = 0; i < 900; i++) {
                const angle = i * 0.45;
                const radius = 2 + i * 0.8;
                const x = canvas.width / 2 + Math.cos(angle) * radius - width / 2;
                const y = canvas.height / 2 + Math.sin(angle) * radius - height / 2;
                const box = { x: x, y: y, w: width, h: height };

                if (
                    x < 6 || y < 6 ||
                    x + width > canvas.width - 6 ||
                    y + height > canvas.height - 6
                ) {
                    continue;
                }

                if (!boxes.some(function (item) { return overlap(box, item); })) {
                    placed = box;
                    break;
                }
            }

            if (placed) {
                boxes.push(placed);
                ctx.fillStyle = colors[index % colors.length];
                ctx.fillText(word.text, placed.x + 6, placed.y + 5);
            }
        });
    }

    const redraw = document.getElementById("wordcloud-redraw");
    if (redraw) redraw.addEventListener("click", draw);

    const download = document.getElementById("wordcloud-download");
    if (download) {
        download.addEventListener("click", function () {
            const link = document.createElement("a");
            link.download = "wordcloud.png";
            link.href = canvas.toDataURL("image/png");
            link.click();
        });
    }

    draw();
    window.setInterval(refreshEntries, 5000);
})();
</script>

<?php include(__DIR__ . '/../footer.php'); ?>
