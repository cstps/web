(function () {
    "use strict";

    function start() {
        const elements = Array.from(
            document.querySelectorAll(".simulation-grade-result")
        );
        if (!elements.length) return;

        const activity = elements[0].dataset.activityId;
        if (!/^[1-9][0-9]*$/.test(activity)) return;

        const nodes = new Map();
        elements.forEach(function (node) {
            if (node.dataset.activityId === activity &&
                /^[1-9][0-9]*$/.test(node.dataset.submissionId)) {
                nodes.set(node.dataset.submissionId, node);
            }
        });

        let timer = null;
        let inFlight = false;
        let stopped = false;
        let suspended = false;
        let controller = null;
        let failures = 0;
        let delay = 2000;
        let startedAt = Date.now();
        let cursor = 0;

        const notice = document.createElement("p");
        notice.className = "ui small message";
        notice.setAttribute("role", "status");
        notice.hidden = true;
        const table = elements[0].closest("table");
        if (table) table.insertAdjacentElement("beforebegin", notice);

        function pendingNodes() {
            return Array.from(nodes.values()).filter(function (node) {
                return node.dataset.gradeState === "pending" ||
                    node.dataset.gradeState === "running";
            });
        }

        function stop(message, retry) {
            stopped = true;
            clearTimeout(timer);
            notice.textContent = message;
            notice.hidden = false;

            if (retry) {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "ui mini basic button";
                button.textContent = "다시 확인";
                button.addEventListener("click", function () {
                    stopped = false;
                    failures = 0;
                    delay = 2000;
                    startedAt = Date.now();
                    notice.hidden = true;
                    schedule(0);
                });
                notice.appendChild(document.createTextNode(" "));
                notice.appendChild(button);
            }
        }

        function schedule(wait) {
            clearTimeout(timer);
            if (!stopped && !suspended && !document.hidden &&
                !inFlight && pendingNodes().length) {
                timer = setTimeout(poll, wait);
            }
        }

        async function poll() {
            if (stopped || suspended || document.hidden || inFlight) return;

            const pending = pendingNodes();
            if (!pending.length) return;

            if (Date.now() - startedAt > 600000) {
                stop("자동 확인을 잠시 중단했습니다.", true);
                return;
            }

            cursor %= pending.length;
            const batch = pending.slice(cursor).concat(
                pending.slice(0, cursor)
            ).slice(0, 50);
            cursor = (cursor + batch.length) % pending.length;

            const ids = batch.map(function (node) {
                return node.dataset.submissionId;
            });
            const requested = new Set(ids);

            inFlight = true;
            controller = new AbortController();
            const requestTimer = setTimeout(function () {
                if (controller) controller.abort();
            }, 8000);

            try {
                const response = await fetch(
                    "/course_simulation_teacher_grade_status.php?activity_id=" +
                    encodeURIComponent(activity) + "&submission_ids=" +
                    encodeURIComponent(ids.join(",")),
                    {
                        credentials: "same-origin",
                        cache: "no-store",
                        redirect: "error",
                        headers: { "Accept": "application/json" },
                        signal: controller.signal
                    }
                );

                if (response.status === 401 || response.status === 403) {
                    stop("로그인 상태와 결과 조회 권한을 확인해 주세요.", false);
                    return;
                }
                if (!response.ok) throw new Error("채점 결과 조회 실패");

                const data = await response.json();
                if (!data || data.ok !== true ||
                    !Array.isArray(data.submissions) ||
                    data.submissions.length !== ids.length) {
                    throw new Error("채점 결과 목록 확인 실패");
                }

                const seen = new Set();
                const allowed = ["", "pending", "running", "graded", "error"];

                data.submissions.forEach(function (item) {
                    if (!item || typeof item.submission_id !== "string" ||
                        !requested.has(item.submission_id) ||
                        seen.has(item.submission_id) ||
                        !allowed.includes(item.grading_status) ||
                        typeof item.html !== "string" ||
                        item.html.length > 65536) {
                        throw new Error("채점 결과 형식 확인 실패");
                    }
                    seen.add(item.submission_id);
                });

                let changed = false;
                data.submissions.forEach(function (item) {
                    const node = nodes.get(item.submission_id);
                    if (node.dataset.gradeState !== item.grading_status) {
                        changed = true;
                    }

                    // 권한 검사 후 서버가 생성한 결과 표시 영역만 교체한다.
                    node.innerHTML = item.html;
                    node.dataset.gradeState = item.grading_status;

                    const segment = node.closest(".ui.segment");
                    const form = segment && segment.querySelector(
                        ".simulation-regrade-form"
                    );
                    const button = form && form.querySelector(
                        'button[type="submit"]'
                    );
                    if (button) {
                        button.disabled = item.grading_status === "pending" ||
                            item.grading_status === "running";
                    }
                });

                failures = 0;
                delay = changed ? 2000 : Math.min(delay + 1000, 10000);

                if (!pendingNodes().length) {
                    notice.textContent = "채점 결과가 갱신되었습니다.";
                    notice.hidden = false;
                }
            } catch (error) {
                if (!suspended && !document.hidden) {
                    failures += 1;
                    delay = Math.min(delay * 2, 10000);
                    if (failures >= 3) {
                        stop("채점 결과 자동 확인에 실패했습니다.", true);
                    }
                }
            } finally {
                clearTimeout(requestTimer);
                controller = null;
                inFlight = false;
                schedule(delay);
            }
        }

        document.addEventListener("visibilitychange", function () {
            clearTimeout(timer);
            if (!document.hidden) schedule(500);
        });
        window.addEventListener("pagehide", function () {
            suspended = true;
            clearTimeout(timer);
            if (controller) controller.abort();
        });
        window.addEventListener("pageshow", function () {
            suspended = false;
            schedule(500);
        });

        schedule(800);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start, { once: true });
    } else {
        start();
    }
})();
