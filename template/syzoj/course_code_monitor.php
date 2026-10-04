<?php
require_once(
    "template/$OJ_TEMPLATE/header.php"
);
?>

<style>
.course-code-monitor {
    max-width: 1450px;
    margin: 24px auto;
    padding: 0 16px;
}

.course-code-monitor-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 20px;
}

.course-code-monitor-title {
    margin: 0;
}

.course-code-monitor-sub {
    margin-top: 6px;
    color: #666;
}

.course-code-monitor-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

.course-code-monitor-summary-item {
    min-width: 130px;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
}

.course-code-monitor-summary-label {
    color: #666;
    font-size: 13px;
}

.course-code-monitor-summary-value {
    margin-top: 3px;
    font-size: 22px;
    font-weight: 700;
}

.course-code-monitor-lessons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: -6px 0 18px;
}

.course-code-monitor-lesson-item {
    padding: 7px 11px;
    border: 1px solid #ddd;
    border-radius: 16px;
    background: #fafafa;
    font-size: 13px;
}

.course-code-monitor-lesson-item strong {
    margin-left: 4px;
}

.course-code-monitor-lessons-empty {
    color: #999;
    font-size: 13px;
}

.course-code-monitor-table-wrap {
    overflow-x: auto;
}

.course-code-monitor-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.course-code-monitor-table th,
.course-code-monitor-table td {
    border: 1px solid #ddd;
    padding: 10px 12px;
    text-align: center;
}

.course-code-monitor-table th {
    background: #f7f7f7;
    white-space: nowrap;
}

.course-code-monitor-student {
    min-width: 180px;
    width: 180px;
    text-align: left !important;
    white-space: nowrap;
}

.course-code-monitor-contest {
    max-width: 320px;
    text-align: left !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.course-code-monitor-problem {
    max-width: 260px;
    text-align: left !important;
}

.course-code-monitor-problem strong {
    display: inline-block;
    margin-right: 6px;
}

.course-code-monitor-problem-title {
    color: #555;
}

.course-code-monitor-active {
    font-weight: 600;
}

.course-code-monitor-wait {
    color: #999;
}

.course-code-monitor-time {
    white-space: nowrap;
    color: #666;
}

@media (max-width: 768px) {

    .course-code-monitor-head {
        display: block;
    }
}
</style>


<div class="course-code-monitor">

    <div class="course-code-monitor-head">

        <div>

            <h2 class="course-code-monitor-title">
                수업 코드 모니터링
            </h2>

            <div class="course-code-monitor-sub">

                <?php
                echo htmlspecialchars(
                    $course['course_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        </div>

        <div>

            <a
                class="ui button"
                href="course_view.php?course_id=<?php
                    echo intval($course_id);
                ?>">
                수업으로 돌아가기
            </a>

        </div>

    </div>


    <div class="course-code-monitor-summary">

        <div class="course-code-monitor-summary-item">

            <div class="course-code-monitor-summary-label">
                전체 학생
            </div>

            <div
                id="course-code-total-count"
                class="course-code-monitor-summary-value">
                <?php
                echo intval(
                    $monitor_total_count
                );
                ?>
            </div>

        </div>


        <div class="course-code-monitor-summary-item">

            <div class="course-code-monitor-summary-label">
                작성 중
            </div>

            <div
                id="course-code-active-count"
                class="course-code-monitor-summary-value">
                <?php
                echo intval(
                    $monitor_active_count
                );
                ?>
            </div>

        </div>


        <div class="course-code-monitor-summary-item">

            <div class="course-code-monitor-summary-label">
                대기
            </div>

            <div
                id="course-code-wait-count"
                class="course-code-monitor-summary-value">
                <?php
                echo intval(
                    $monitor_wait_count
                );
                ?>
            </div>

        </div>

    </div>


    <div
        id="course-code-lesson-summary"
        class="course-code-monitor-lessons">

        <?php
        if ($monitor_lesson_counts) {

            foreach (
                $monitor_lesson_counts
                as
                $lesson_no => $count
            ) {
        ?>

                <span class="course-code-monitor-lesson-item">

                    <?php
                    echo intval($lesson_no);
                    ?>차시

                    <strong>
                        <?php
                        echo intval($count);
                        ?>명
                    </strong>

                </span>

        <?php
            }
        }
        else {
        ?>

            <span class="course-code-monitor-lessons-empty">
                현재 작성 중인 학생이 없습니다.
            </span>

        <?php
        }
        ?>

    </div>


    <div class="course-code-monitor-table-wrap">

        <table class="course-code-monitor-table">

            <thead>

                <tr>

                    <th>학생</th>

                    <th>현재 차시</th>

                    <th>대회</th>

                    <th>문제</th>

                    <th>언어</th>

                    <th>마지막 저장</th>

                    <th>상태</th>

                </tr>

            </thead>


            <tbody id="course-code-monitor-body">

                <?php
                if (!$course_students) {
                ?>

                    <tr>
                        <td colspan="7">
                            등록된 학생이 없습니다.
                        </td>
                    </tr>

                <?php
                }
                ?>


                <?php
                foreach (
                    $course_students
                    as
                    $student
                ) {

                    $current =
                        $student['current'];
                ?>

                    <tr
                        class="course-code-monitor-row"
                        data-user-id="<?php
                            echo htmlspecialchars(
                                $student['user_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>">

                        <td class="course-code-monitor-student">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $student['user_id'],
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
                        if ($current) {
                        ?>

                            <td>
                                <?php
                                echo intval(
                                    $current['lesson_no']
                                );
                                ?>차시
                            </td>


                            <td class="course-code-monitor-contest">

                                <?php
                                echo htmlspecialchars(
                                    $current['contest_title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </td>


                            <td class="course-code-monitor-problem">

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $current['problem_label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </strong>

                                <span class="course-code-monitor-problem-title">
                                    <?php
                                    echo htmlspecialchars(
                                        $current['problem_title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $current['language_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </td>


                            <td class="course-code-monitor-time">

                                <?php
                                echo htmlspecialchars(
                                    $current['updated_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                if (!empty($current['active'])) {
                                ?>

                                    <button
                                        type="button"
                                        class="ui tiny primary button course-code-open"
                                        data-contest-id="<?php
                                            echo intval(
                                                $current['contest_id']
                                            );
                                        ?>"
                                        data-problem-id="<?php
                                            echo intval(
                                                $current['problem_id']
                                            );
                                        ?>">
                                        작성 중
                                    </button>

                                <?php
                                }
                                else {
                                ?>

                                    <button
                                        type="button"
                                        class="ui tiny button course-code-open"
                                        data-contest-id="<?php
                                            echo intval(
                                                $current['contest_id']
                                            );
                                        ?>"
                                        data-problem-id="<?php
                                            echo intval(
                                                $current['problem_id']
                                            );
                                        ?>">
                                        최근 코드
                                    </button>

                                    <span class="course-code-monitor-wait">
                                        대기
                                    </span>

                                <?php
                                }
                                ?>

                            </td>

                        <?php
                        }
                        else {
                        ?>

                            <td>
                                <span class="course-code-monitor-wait">
                                    —
                                </span>
                            </td>

                            <td class="course-code-monitor-contest">
                                <span class="course-code-monitor-wait">
                                    —
                                </span>
                            </td>

                            <td>
                                <span class="course-code-monitor-wait">
                                    —
                                </span>
                            </td>

                            <td>
                                <span class="course-code-monitor-wait">
                                    —
                                </span>
                            </td>

                            <td class="course-code-monitor-time">
                                <span class="course-code-monitor-wait">
                                    —
                                </span>
                            </td>

                            <td>
                                <span class="course-code-monitor-wait">
                                    대기
                                </span>
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



<style>
.course-code-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(0, 0, 0, 0.45);
}

.course-code-modal.is-open {
    display: flex;
}

.course-code-modal-panel {
    width: min(1100px, 96vw);
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}

.course-code-modal-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid #ddd;
}

.course-code-modal-title {
    margin: 0;
    font-size: 18px;
}

.course-code-modal-meta {
    margin-top: 5px;
    color: #666;
    font-size: 13px;
}

.course-code-modal-close {
    border: 0;
    background: transparent;
    font-size: 25px;
    cursor: pointer;
}

.course-code-modal-source {
    margin: 0;
    padding: 18px;
    min-height: 320px;
    max-height: 70vh;
    overflow: auto;
    white-space: pre;
    tab-size: 4;
    font-family: Consolas, Monaco, monospace;
    font-size: 14px;
    line-height: 1.5;
    background: #fafafa;
}

.course-code-modal-message {
    padding: 24px;
    color: #666;
}
</style>


<div
    id="course-code-modal"
    class="course-code-modal"
    aria-hidden="true">

    <div
        class="course-code-modal-panel"
        role="dialog"
        aria-modal="true">

        <div class="course-code-modal-head">

            <div>
                <h3
                    id="course-code-modal-title"
                    class="course-code-modal-title">
                    학생 코드
                </h3>

                <div
                    id="course-code-modal-meta"
                    class="course-code-modal-meta">
                </div>
            </div>

            <button
                type="button"
                id="course-code-modal-close"
                class="course-code-modal-close"
                aria-label="닫기">
                ×
            </button>

        </div>

        <div
            id="course-code-modal-message"
            class="course-code-modal-message"
            hidden>
        </div>

        <pre
            id="course-code-modal-source"
            class="course-code-modal-source"></pre>

    </div>
</div>


<script>
(() => {

    "use strict";

    const courseId =
        <?php echo intval($course_id); ?>;

    let monitorRefreshing = false;

    const modal =
        document.getElementById(
            "course-code-modal"
        );

    const modalTitle =
        document.getElementById(
            "course-code-modal-title"
        );

    const modalMeta =
        document.getElementById(
            "course-code-modal-meta"
        );

    const modalSource =
        document.getElementById(
            "course-code-modal-source"
        );

    const modalMessage =
        document.getElementById(
            "course-code-modal-message"
        );

    const modalClose =
        document.getElementById(
            "course-code-modal-close"
        );


    let currentUserId = "";
    let currentContestId = 0;
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


    async function loadCurrentCode(
        userId,
        contestId,
        problemId
    ) {

        currentUserId =
            userId;

        currentContestId =
            contestId;

        currentProblemId =
            problemId;

        currentUpdatedAt =
            "";

        modalTitle.textContent =
            userId + " · 현재 작성 코드";

        modalMeta.textContent =
            "코드를 불러오는 중입니다.";

        modalSource.textContent = "";
        modalSource.hidden = true;

        modalMessage.hidden = false;
        modalMessage.textContent =
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

            modalTitle.textContent =
                data.user_id +
                " · 문제 #" +
                data.problem_id;

            modalMeta.textContent =
                data.language_name +
                " · 마지막 저장 " +
                data.updated_at;

            modalSource.textContent =
                data.source || "";

            currentUpdatedAt =
                data.updated_at || "";

            modalMessage.hidden = true;
            modalSource.hidden = false;

        }
        catch (error) {

            modalSource.hidden = true;
            modalMessage.hidden = false;
            modalMessage.textContent =
                error.message;
        }
    }


    async function refreshMonitor() {

        if (monitorRefreshing) {
            return;
        }

        monitorRefreshing = true;

        try {

            const url =
                new URL(
                    "course_code_monitor_ajax.php",
                    window.location.href
                );

            url.searchParams.set(
                "course_id",
                String(courseId)
            );

            const response =
                await fetch(
                    url.toString(),
                    {
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );

            const data =
                await response.json();

            if (
                !response.ok ||
                data.ok !== true ||
                !Array.isArray(data.students)
            ) {
                return;
            }

            const tableBody =
                document.getElementById(
                    "course-code-monitor-body"
                );


            // ------------------------------------------------
            // 상단 요약 갱신
            // ------------------------------------------------

            const totalCount =
                data.students.length;

            const activeCount =
                data.students.filter(
                    (student) =>
                        student.current &&
                        student.current.active === true
                ).length;

            const waitCount =
                totalCount -
                activeCount;


            const totalElement =
                document.getElementById(
                    "course-code-total-count"
                );

            const activeElement =
                document.getElementById(
                    "course-code-active-count"
                );

            const waitElement =
                document.getElementById(
                    "course-code-wait-count"
                );


            if (totalElement) {
                totalElement.textContent =
                    String(totalCount);
            }

            if (activeElement) {
                activeElement.textContent =
                    String(activeCount);
            }

            if (waitElement) {
                waitElement.textContent =
                    String(waitCount);
            }


            // ------------------------------------------------
            // 차시별 작성 중 인원 갱신
            // ------------------------------------------------

            const lessonCounts = {};

            data.students.forEach(
                (student) => {

                    if (
                        !student.current ||
                        student.current.active !== true
                    ) {
                        return;
                    }

                    const lessonNo =
                        Number(
                            student.current.lesson_no
                        );

                    if (
                        !Number.isInteger(lessonNo) ||
                        lessonNo <= 0
                    ) {
                        return;
                    }

                    lessonCounts[lessonNo] =
                        (lessonCounts[lessonNo] || 0) + 1;
                }
            );


            const lessonSummary =
                document.getElementById(
                    "course-code-lesson-summary"
                );

            if (lessonSummary) {

                const lessonNumbers =
                    Object.keys(
                        lessonCounts
                    )
                    .map(Number)
                    .sort(
                        (a, b) => a - b
                    );

                if (lessonNumbers.length === 0) {

                    lessonSummary.innerHTML =
                        '<span class="course-code-monitor-lessons-empty">' +
                        '현재 작성 중인 학생이 없습니다.' +
                        '</span>';

                }
                else {

                    lessonSummary.innerHTML =
                        lessonNumbers
                            .map(
                                (lessonNo) =>
                                    '<span class="course-code-monitor-lesson-item">' +
                                    lessonNo +
                                    '차시 ' +
                                    '<strong>' +
                                    lessonCounts[lessonNo] +
                                    '명' +
                                    '</strong>' +
                                    '</span>'
                            )
                            .join("");
                }
            }


            // 서버에서 이미
            // 작성 중 → 최근 저장 → 학생 아이디
            // 순서로 정렬된 학생 목록이 넘어온다.
            const orderedRows = [];

            data.students.forEach(
                (student) => {

                    const row =
                        document.querySelector(
                            '.course-code-monitor-row' +
                            '[data-user-id="' +
                            CSS.escape(
                                String(student.user_id)
                            ) +
                            '"]'
                        );

                    if (!row) {
                        return;
                    }

                    const cells =
                        row.querySelectorAll("td");

                    if (cells.length < 7) {
                        return;
                    }

                    if (!student.current) {

                        cells[1].textContent = "—";
                        cells[2].textContent = "—";
                        cells[3].textContent = "—";
                        cells[4].textContent = "—";
                        cells[5].textContent = "—";
                        cells[6].innerHTML =
                            '<span class="course-code-monitor-wait">대기</span>';

                        orderedRows.push(row);

                        return;
                    }

                    const current =
                        student.current;

                    cells[1].textContent =
                        current.lesson_no + "차시";

                    cells[2].textContent =
                        current.contest_title;

                    cells[3].textContent = "";

                    const problemLabel =
                        document.createElement(
                            "strong"
                        );

                    problemLabel.textContent =
                        current.problem_label;

                    const problemTitle =
                        document.createElement(
                            "span"
                        );

                    problemTitle.className =
                        "course-code-monitor-problem-title";

                    problemTitle.textContent =
                        current.problem_title || "";

                    cells[3].appendChild(
                        problemLabel
                    );

                    cells[3].appendChild(
                        problemTitle
                    );

                    cells[4].textContent =
                        current.language_name;

                    cells[5].textContent =
                        current.updated_at;

                    if (current.active === true) {

                        cells[6].innerHTML =
                            '<button type="button"' +
                            ' class="ui tiny primary button course-code-open"' +
                            ' data-contest-id="' +
                            Number(current.contest_id) +
                            '"' +
                            ' data-problem-id="' +
                            Number(current.problem_id) +
                            '">' +
                            '작성 중' +
                            '</button>';

                    }
                    else {

                        cells[6].innerHTML =
                            '<button type="button"' +
                            ' class="ui tiny button course-code-open"' +
                            ' data-contest-id="' +
                            Number(current.contest_id) +
                            '"' +
                            ' data-problem-id="' +
                            Number(current.problem_id) +
                            '">' +
                            '최근 코드' +
                            '</button>' +
                            ' <span class="course-code-monitor-wait">' +
                            '대기' +
                            '</span>';
                    }

                    orderedRows.push(row);
                }
            );


            // ------------------------------------------------
            // 학생 행 재정렬
            //
            // 학생별 상태 갱신이 모두 끝난 후
            // 서버가 내려준 순서대로 DOM을 다시 배치한다.
            // ------------------------------------------------

            if (tableBody) {

                orderedRows.forEach(
                    (row) => {

                        tableBody.appendChild(
                            row
                        );
                    }
                );
            }

        }
        catch (error) {

            console.warn(
                "Course 코드 모니터링 갱신 실패",
                error
            );

        }
        finally {

            monitorRefreshing = false;
        }
    }


    async function refreshOpenSource() {

        if (
            !modal.classList.contains("is-open") ||
            !currentUserId ||
            currentContestId <= 0 ||
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
                String(currentContestId)
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

            modalSource.textContent =
                data.source || "";

            modalMeta.textContent =
                data.language_name +
                " · 마지막 저장 " +
                data.updated_at;

            currentUpdatedAt =
                data.updated_at || "";

        }
        catch (error) {

            console.warn(
                "열린 학생 코드 갱신 실패",
                error
            );

        }
        finally {

            sourceRefreshing = false;
        }
    }


    document.addEventListener(
        "click",
        (event) => {

            const button =
                event.target.closest(
                    ".course-code-open"
                );

            if (!button) {
                return;
            }

            const row =
                button.closest(
                    ".course-code-monitor-row"
                );

            if (!row) {
                return;
            }

            const userId =
                row.dataset.userId || "";

            const contestId =
                Number(
                    button.dataset.contestId
                );

            const problemId =
                Number(
                    button.dataset.problemId
                );

            if (
                !userId ||
                !Number.isInteger(contestId) ||
                contestId <= 0 ||
                !Number.isInteger(problemId) ||
                problemId <= 0
            ) {
                return;
            }

            loadCurrentCode(
                userId,
                contestId,
                problemId
            );
        }
    );


    modalClose.addEventListener(
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


    refreshMonitor();

    setInterval(
        refreshMonitor,
        5000
    );

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
