<?php
require_once(
    "template/$OJ_TEMPLATE/header.php"
);
?>

<style>
.code-monitor-wrap {
    max-width: 1500px;
    margin: 24px auto;
    padding: 0 16px;
}

.code-monitor-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
}

.code-monitor-title {
    margin: 0;
}

.code-monitor-sub {
    margin-top: 6px;
    color: #666;
}

.code-monitor-table-wrap {
    overflow-x: auto;
}

.code-monitor-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.code-monitor-table th,
.code-monitor-table td {
    border: 1px solid #ddd;
    padding: 9px 10px;
    text-align: center;
    white-space: nowrap;
}

.code-monitor-table th {
    background: #f7f7f7;
}

.code-monitor-student {
    text-align: left !important;
}

.code-monitor-active {
    font-weight: 600;
}

.code-monitor-empty {
    color: #aaa;
}

.code-monitor-time {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    color: #777;
}

@media (max-width: 768px) {
    .code-monitor-head {
        display: block;
    }
}

.code-monitor-cell:has(.code-monitor-active) {
    cursor: pointer;
}

.code-monitor-cell:has(.code-monitor-active):hover {
    background: #f5f7fa;
}

.code-monitor-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(0, 0, 0, 0.45);
}

.code-monitor-modal.is-open {
    display: flex;
}

.code-monitor-modal-panel {
    width: min(1100px, 96vw);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}

.code-monitor-modal-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid #ddd;
}

.code-monitor-modal-title {
    margin: 0;
    font-size: 18px;
}

.code-monitor-modal-meta {
    margin-top: 5px;
    color: #666;
    font-size: 13px;
}

.code-monitor-modal-close {
    border: 0;
    background: transparent;
    font-size: 25px;
    cursor: pointer;
}

.code-monitor-source {
    margin: 0;
    padding: 18px;
    overflow: auto;
    min-height: 320px;
    max-height: 70vh;
    white-space: pre;
    tab-size: 4;
    font-family: Consolas, Monaco, monospace;
    font-size: 14px;
    line-height: 1.5;
    background: #fafafa;
}

.code-monitor-source-message {
    padding: 24px;
    color: #666;
}

</style>


<div class="code-monitor-wrap">

    <div class="code-monitor-head">

        <div>

            <h2 class="code-monitor-title">
                학생 코드 모니터링
            </h2>

            <div class="code-monitor-sub">
                <?php
                echo htmlspecialchars(
                    $contest_title,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
                · Contest #<?php echo intval($cid); ?>
            </div>

        </div>

        <div>
            <a
                class="ui button"
                href="contest_process.php?cid=<?php
                    echo intval($cid);
                ?>">
                과정 현황
            </a>
        </div>

    </div>


    <div class="code-monitor-table-wrap">

        <table class="code-monitor-table">

            <thead>
                <tr>

                    <th>
                        학생
                    </th>

                    <?php
                    foreach (
                        $contest_problems
                        as
                        $problem
                    ) {
                    ?>

                        <th
                            title="<?php
                                echo htmlspecialchars(
                                    $problem['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>">

                            <?php
                            echo htmlspecialchars(
                                $problem['label'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </th>

                    <?php
                    }
                    ?>

                </tr>
            </thead>


            <tbody>

                <?php
                if (!$monitor_students) {
                ?>

                    <tr>
                        <td
                            colspan="<?php
                                echo max(
                                    1,
                                    count($contest_problems) + 1
                                );
                            ?>">

                            모니터링할 학생이 없습니다.

                        </td>
                    </tr>

                <?php
                }
                ?>


                <?php
                foreach (
                    $monitor_students
                    as
                    $student
                ) {

                    $uid =
                        $student['user_id'];
                ?>

                    <tr>

                        <td class="code-monitor-student">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $uid,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </strong>

                            <?php
                            if ($student['nick'] !== '') {
                            ?>

                                <span>
                                    (<?php
                                    echo htmlspecialchars(
                                        $student['nick'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>)
                                </span>

                            <?php
                            }
                            ?>

                        </td>


                        <?php
                        foreach (
                            $contest_problems
                            as
                            $problem_id =>
                                $problem
                        ) {

                            $draft =
                                isset(
                                    $draft_map[$uid][$problem_id]
                                )
                                    ? $draft_map[$uid][$problem_id]
                                    : null;
                        ?>

                            <td
                                class="code-monitor-cell"
                                data-user-id="<?php
                                    echo htmlspecialchars(
                                        $uid,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                                data-problem-id="<?php
                                    echo intval($problem_id);
                                ?>">

                                <?php
                                if ($draft) {
                                ?>

                                    <span class="code-monitor-active">
                                        작성 중
                                    </span>

                                    <span class="code-monitor-time">
                                        <?php
                                        echo htmlspecialchars(
                                            $draft['updated_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </span>

                                <?php
                                }
                                else {
                                ?>

                                    <span class="code-monitor-empty">
                                        —
                                    </span>

                                <?php
                                }
                                ?>

                            </td>

                        <?php
                        }
                        ?>

                    </tr>

                <?php
                }
                ?>

            </tbody>

        </table>

    </div>

</div>


<script>
(() => {

    "use strict";

    const contestId =
        <?php echo intval($cid); ?>;

    let refreshing = false;


    // --------------------------------------------------------
    // HTML 특수문자 처리
    // --------------------------------------------------------

    function escapeHtml(value) {

        const span =
            document.createElement("span");

        span.textContent =
            String(value);

        return span.innerHTML;
    }


    // --------------------------------------------------------
    // 모든 셀을 미작성 상태로 초기화
    // --------------------------------------------------------

    function clearDraftCells() {

        document
            .querySelectorAll(
                ".code-monitor-cell"
            )
            .forEach((cell) => {

                cell.innerHTML =
                    '<span class="code-monitor-empty">—</span>';
            });
    }


    // --------------------------------------------------------
    // 서버 상태 반영
    // --------------------------------------------------------

    function renderDrafts(drafts) {

        clearDraftCells();

        drafts.forEach((draft) => {

            const selector =
                '.code-monitor-cell' +
                '[data-user-id="' +
                CSS.escape(
                    String(draft.user_id)
                ) +
                '"]' +
                '[data-problem-id="' +
                Number(draft.problem_id) +
                '"]';

            const cell =
                document.querySelector(
                    selector
                );

            if (!cell) {
                return;
            }

            cell.innerHTML =
                '<span class="code-monitor-active">' +
                '작성 중' +
                '</span>' +
                '<span class="code-monitor-time">' +
                escapeHtml(
                    draft.updated_at
                ) +
                '</span>';
        });
    }


    // --------------------------------------------------------
    // 최신 상태 조회
    // --------------------------------------------------------

    async function refreshMonitor() {

        if (refreshing) {
            return;
        }

        refreshing = true;

        try {

            const url =
                new URL(
                    "contest_code_monitor_ajax.php",
                    window.location.href
                );

            url.searchParams.set(
                "cid",
                String(contestId)
            );


            const response =
                await fetch(
                    url.toString(),
                    {
                        method: "GET",
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );


            const data =
                await response.json();


            if (
                !response.ok ||
                data.ok !== true ||
                !Array.isArray(data.drafts)
            ) {

                throw new Error(
                    data.message ||
                    "코드 상태를 확인하지 못했습니다."
                );
            }


            renderDrafts(
                data.drafts
            );

        }
        catch (error) {

            console.warn(
                "학생 코드 모니터링 갱신 실패",
                error
            );

        }
        finally {

            refreshing = false;
        }
    }


    // 페이지가 열린 직후 한 번 확인한다.
    refreshMonitor();


    // 이후 5초마다 메타정보만 갱신한다.
    setInterval(
        refreshMonitor,
        5000
    );


})();
</script>



<div
    id="code-monitor-modal"
    class="code-monitor-modal"
    aria-hidden="true">

    <div
        class="code-monitor-modal-panel"
        role="dialog"
        aria-modal="true">

        <div class="code-monitor-modal-head">

            <div>
                <h3
                    id="code-monitor-modal-title"
                    class="code-monitor-modal-title">
                    학생 코드
                </h3>

                <div
                    id="code-monitor-modal-meta"
                    class="code-monitor-modal-meta">
                </div>
            </div>

            <button
                type="button"
                id="code-monitor-modal-close"
                class="code-monitor-modal-close"
                aria-label="닫기">
                ×
            </button>

        </div>

        <div
            id="code-monitor-source-message"
            class="code-monitor-source-message"
            hidden>
        </div>

        <pre
            id="code-monitor-source"
            class="code-monitor-source"></pre>

    </div>
</div>


<script>
(() => {

    "use strict";

    const contestId =
        <?php echo intval($cid); ?>;

    const modal =
        document.getElementById(
            "code-monitor-modal"
        );

    const closeButton =
        document.getElementById(
            "code-monitor-modal-close"
        );

    const title =
        document.getElementById(
            "code-monitor-modal-title"
        );

    const meta =
        document.getElementById(
            "code-monitor-modal-meta"
        );

    const source =
        document.getElementById(
            "code-monitor-source"
        );

    const message =
        document.getElementById(
            "code-monitor-source-message"
        );


    let currentUserId = "";
    let currentProblemId = 0;
    let currentUpdatedAt = "";
    let sourceRefreshing = false;


    function openModal() {

        modal.classList.add(
            "is-open"
        );

        modal.setAttribute(
            "aria-hidden",
            "false"
        );
    }


    function closeModal() {

        modal.classList.remove(
            "is-open"
        );

        modal.setAttribute(
            "aria-hidden",
            "true"
        );
    }


    async function loadStudentCode(
        cell
    ) {

        // 현재 draft가 있는 셀만 연다.
        if (
            !cell.querySelector(
                ".code-monitor-active"
            )
        ) {
            return;
        }


        const userId =
            cell.dataset.userId || "";

        const problemId =
            Number(
                cell.dataset.problemId
            );


        if (
            !userId ||
            !Number.isInteger(problemId) ||
            problemId <= 0
        ) {
            return;
        }


        currentUserId =
            userId;

        currentProblemId =
            problemId;

        currentUpdatedAt =
            "";


        title.textContent =
            userId + " · 문제 코드";

        meta.textContent =
            "코드를 불러오는 중입니다.";

        source.textContent = "";
        source.hidden = true;

        message.hidden = false;
        message.textContent =
            "현재 코드를 불러오는 중입니다.";

        openModal();


        try {

            const url =
                new URL(
                    "contest_code_monitor_source.php",
                    window.location.href
                );

            url.searchParams.set(
                "cid",
                String(contestId)
            );

            url.searchParams.set(
                "user_id",
                userId
            );

            url.searchParams.set(
                "problem_id",
                String(problemId)
            );


            const response =
                await fetch(
                    url.toString(),
                    {
                        method: "GET",
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );


            const data =
                await response.json();


            if (
                !response.ok ||
                data.ok !== true
            ) {

                throw new Error(
                    data.message ||
                    "학생 코드를 불러오지 못했습니다."
                );
            }


            title.textContent =
                data.user_id +
                " · 문제 #" +
                data.problem_id;

            meta.textContent =
                data.language_name +
                " · 마지막 저장 " +
                data.updated_at;

            // 코드 내용은 HTML로 해석하지 않고
            // textContent로만 표시한다.
            source.textContent =
                data.source || "";

            currentUpdatedAt =
                data.updated_at || "";

            message.hidden = true;
            source.hidden = false;

        }
        catch (error) {

            source.hidden = true;

            message.hidden = false;
            message.textContent =
                error.message;
        }
    }


    document.addEventListener(
        "click",
        (event) => {

            const cell =
                event.target.closest(
                    ".code-monitor-cell"
                );

            if (!cell) {
                return;
            }

            loadStudentCode(
                cell
            );
        }
    );


    closeButton.addEventListener(
        "click",
        closeModal
    );


    modal.addEventListener(
        "click",
        (event) => {

            if (event.target === modal) {
                closeModal();
            }
        }
    );


    document.addEventListener(
        "keydown",
        (event) => {

            if (
                event.key === "Escape" &&
                modal.classList.contains(
                    "is-open"
                )
            ) {
                closeModal();
            }
        }
    );



    // --------------------------------------------------------
    // 열린 코드 모달 자동 갱신
    //
    // 학생이 코드를 새로 자동저장하면
    // 모달을 닫지 않아도 최신 코드로 갱신한다.
    // --------------------------------------------------------

    async function refreshOpenSource() {

        if (
            !modal.classList.contains("is-open") ||
            !currentUserId ||
            currentProblemId <= 0 ||
            sourceRefreshing
        ) {
            return;
        }

        sourceRefreshing = true;

        try {

            const url =
                new URL(
                    "contest_code_monitor_source.php",
                    window.location.href
                );

            url.searchParams.set(
                "cid",
                String(contestId)
            );

            url.searchParams.set(
                "user_id",
                currentUserId
            );

            url.searchParams.set(
                "problem_id",
                String(currentProblemId)
            );

            const response =
                await fetch(
                    url.toString(),
                    {
                        method: "GET",
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );

            const data =
                await response.json();

            if (
                !response.ok ||
                data.ok !== true
            ) {
                return;
            }

            if (
                data.updated_at ===
                currentUpdatedAt
            ) {
                return;
            }

            source.textContent =
                data.source || "";

            meta.textContent =
                data.language_name +
                " · 마지막 저장 " +
                data.updated_at;

            currentUpdatedAt =
                data.updated_at || "";

        }
        catch (error) {

            console.warn(
                "학생 코드 자동 갱신 실패",
                error
            );

        }
        finally {

            sourceRefreshing = false;
        }
    }


    setInterval(
        refreshOpenSource,
        5000
    );

})();
</script>


<?php
require_once(
    "template/$OJ_TEMPLATE/footer.php"
);
?>
