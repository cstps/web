(function () {
    "use strict";

    const config = document.getElementById("drawproblem-history-config");
    const content = document.getElementById("drawproblem-history-content");
    const status = document.getElementById("drawproblem-history-status");
    const button = document.getElementById("drawproblem-history-refresh");

    if (!config || !content || !status || !button) return;

    const problemId = config.dataset.problemId;
    const performanceContext = config.dataset.performanceContext;

    let hasPending = config.dataset.hasPending === "1";
    let wantsRefresh = false;
    let inFlight = false;
    let stopped = false;
    let blocked = false;
    let suspended = false;
    let failures = 0;
    let delay = 2000;
    let startedAt = Date.now();
    let timer = null;
    let controller = null;
    let lastHtml = content.innerHTML;

    function schedule(wait) {
        clearTimeout(timer);

        if (!stopped && !blocked && !suspended &&
            !document.hidden && !inFlight &&
            (hasPending || wantsRefresh)) {
            timer = setTimeout(poll, wait);
        }
    }

    function requestRefresh() {
        if (blocked) return;
        stopped = false;
        failures = 0;
        delay = 2000;
        startedAt = Date.now();
        wantsRefresh = true;
        schedule(0);
    }

    function blockHistory(message) {
        blocked = true;
        clearTimeout(timer);
        content.replaceChildren();
        lastHtml = "";
        status.textContent = message;
        button.disabled = true;
    }

    function updateHtml(html) {
        if (html === lastHtml) return false;

        const expanded = new Set();
        content.querySelectorAll("tr[data-submission-id]").forEach(function (row) {
            if (row.querySelector("details[open]")) {
                expanded.add(row.dataset.submissionId);
            }
        });

        // 본인 권한 검사 후 서버가 생성한 이력 영역만 교체한다.
        content.innerHTML = html;
        lastHtml = html;

        content.querySelectorAll("tr[data-submission-id]").forEach(function (row) {
            if (expanded.has(row.dataset.submissionId)) {
                const details = row.querySelector("details");
                if (details) details.open = true;
            }
        });

        return true;
    }

    async function poll() {
        if (stopped || blocked || suspended || document.hidden ||
            inFlight || !(hasPending || wantsRefresh)) return;

        if (Date.now() - startedAt >= 600000) {
            stopped = true;
            status.textContent =
                "자동 확인을 잠시 중단했습니다. 다시 확인 버튼을 눌러 주세요.";
            return;
        }

        wantsRefresh = false;
        inFlight = true;
        button.disabled = true;
        controller = new AbortController();

        const requestTimer = setTimeout(function () {
            if (controller) controller.abort();
        }, 8000);

        try {
            const query = new URLSearchParams({
                problem_id: problemId,
                performance_context: performanceContext
            });

            const response = await fetch(
                "/drawproblem_grade_status.php?" + query.toString(),
                {
                    credentials: "same-origin",
                    cache: "no-store",
                    redirect: "error",
                    headers: {"Accept": "application/json"},
                    signal: controller.signal
                }
            );

            if ([401, 403, 404, 409].includes(response.status)) {
                blockHistory(
                    response.status === 409
                        ? "수행모드 기준이 변경됐습니다. 현재 코드를 보관한 뒤 화면을 다시 열어 주세요."
                        : "로그인 상태 또는 문제 조회 권한이 변경됐습니다. 현재 코드를 보관한 뒤 화면을 다시 열어 주세요."
                );
                return;
            }

            if (!response.ok) throw new Error("제출 기록 조회 실패");

            const result = await response.json();

            if (!result || result.ok !== true ||
                result.problem_id !== problemId ||
                result.performance_context !== performanceContext ||
                typeof result.has_pending !== "boolean" ||
                typeof result.html !== "string" ||
                new TextEncoder().encode(result.html).length > 262144) {
                throw new Error("제출 기록 응답 형식 확인 실패");
            }

            const changed = updateHtml(result.html);
            hasPending = result.has_pending;
            failures = 0;
            delay = changed ? 2000 : Math.min(10000, delay + 1000);

            status.textContent = hasPending
                ? "채점 결과를 자동으로 확인하고 있습니다."
                : "제출 기록과 채점 결과를 갱신했습니다.";

        } catch (error) {
            if (suspended || document.hidden) {
                wantsRefresh = true;
            } else {
                failures += 1;
                delay = Math.min(10000, delay * 2);
                wantsRefresh = true;

                if (failures >= 3) {
                    stopped = true;
                    status.textContent =
                        "제출 기록을 갱신하지 못했습니다. 다시 확인 버튼을 눌러 주세요.";
                }
            }
        } finally {
            clearTimeout(requestTimer);
            controller = null;
            inFlight = false;
            button.disabled = blocked;
            schedule(delay);
        }
    }

    button.addEventListener("click", requestRefresh);

    document.addEventListener("drawproblem:submitted", function (event) {
        if (event.detail && event.detail.problemId === problemId) {
            requestRefresh();
        }
    });

    document.addEventListener("visibilitychange", function () {
        clearTimeout(timer);
        if (!document.hidden) schedule(500);
    });

    window.addEventListener("pagehide", function () {
        suspended = true;
        clearTimeout(timer);
        if (controller) controller.abort();
    });

    window.addEventListener("pageshow", function (event) {
        suspended = false;
        if (event.persisted && !blocked) {
            wantsRefresh = true;
        }
        schedule(500);
    });

    if (hasPending) {
        status.textContent = "채점 결과를 자동으로 확인하고 있습니다.";
    }
    schedule(800);
})();
