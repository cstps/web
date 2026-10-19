(function () {
    "use strict";

    function start() {
        var table = document.getElementById("simulation-submission-history");
        if (!table || table.dataset.gradeRefreshStarted === "1") return;
        var activityId = table.dataset.activityId || "";
        if (!/^[1-9][0-9]*$/.test(activityId)) return;

        var rows = new Map();
        table.querySelectorAll("tr[data-submission-id]").forEach(function (row) {
            var id = row.dataset.submissionId || "";
            if (/^[1-9][0-9]*$/.test(id) &&
                row.querySelector("[data-grade-status]") &&
                row.querySelector("[data-grade-score]")) {
                rows.set(id, row);
            }
        });
        if (rows.size === 0) return;
        table.dataset.gradeRefreshStarted = "1";

        var timer = null;
        var inFlight = false;
        var enabled = true;
        var suspended = false;
        var failures = 0;
        var delay = 2000;
        var startedAt = Date.now();
        var controller = null;
        var notice = document.createElement("p");
        notice.className = "ui small message";
        notice.setAttribute("role", "status");
        notice.hidden = true;
        table.insertAdjacentElement("afterend", notice);

        function hasPending() {
            return Array.from(rows.values()).some(function (row) {
                return row.dataset.gradeState === "pending" ||
                    row.dataset.gradeState === "running";
            });
        }

        function stop(message, retry) {
            enabled = false;
            clearTimeout(timer);
            notice.textContent = message;
            notice.hidden = false;
            if (retry) {
                var button = document.createElement("button");
                button.type = "button";
                button.className = "ui small basic button";
                button.textContent = "다시 확인";
                button.addEventListener("click", function () {
                    enabled = true;
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
            if (enabled && !suspended && !document.hidden &&
                !inFlight && hasPending()) {
                timer = setTimeout(poll, wait);
            }
        }

        function presentation(item) {
            var states = {
                "": "채점 기록 없음",
                pending: "채점 대기",
                running: "채점 중",
                graded: "비교 완료",
                error: "처리 오류"
            };
            if (!Object.prototype.hasOwnProperty.call(states, item.grading_status)) {
                throw new Error("잘못된 채점 상태");
            }
            var scoreText = "-";
            var label = states[item.grading_status];
            var empty = item.score === null && item.max_score === null;
            if (!empty) {
                var pattern = /^(?:0|[1-9][0-9]{0,4})\.[0-9]{2}$/;
                if (typeof item.score !== "string" ||
                    typeof item.max_score !== "string" ||
                    !pattern.test(item.score) || !pattern.test(item.max_score) ||
                    Number(item.max_score) <= 0 ||
                    Number(item.score) > Number(item.max_score) ||
                    item.grading_status !== "graded") {
                    throw new Error("잘못된 점수");
                }
                label = "채점 완료";
                scoreText = item.score + " / " + item.max_score + "점";
            } else if (item.grading_status === "graded") {
                scoreText = "미산정";
            }
            return {label: label, score: scoreText};
        }

        async function poll() {
            if (!enabled || suspended || document.hidden ||
                inFlight || !hasPending()) return;
            if (Date.now() - startedAt >= 600000) {
                stop("자동 확인을 잠시 중단했습니다.", true);
                return;
            }

            inFlight = true;
            controller = new AbortController();
            var requestTimer = setTimeout(function () {
                if (controller) controller.abort();
            }, 8000);

            try {
                var response = await fetch(
                    "/course_simulation_grade_status.php?activity_id=" +
                    encodeURIComponent(activityId),
                    {
                        credentials: "same-origin",
                        cache: "no-store",
                        redirect: "error",
                        headers: {"Accept": "application/json"},
                        signal: controller.signal
                    }
                );
                if (response.status === 401 || response.status === 403) {
                    stop("로그인 또는 활동 접근 권한을 확인해 주세요.", false);
                    return;
                }
                if (!response.ok) throw new Error("조회 실패");
                var data = await response.json();
                if (!data || data.ok !== true ||
                    !Array.isArray(data.submissions) ||
                    data.submissions.length > 10) {
                    throw new Error("잘못된 조회 결과");
                }

                var updates = [];
                var returned = new Set();
                data.submissions.forEach(function (item) {
                    if (!item || typeof item.submission_id !== "string" ||
                        !/^[1-9][0-9]*$/.test(item.submission_id) ||
                        typeof item.grading_status !== "string" ||
                        returned.has(item.submission_id)) {
                        throw new Error("잘못된 제출 정보");
                    }
                    returned.add(item.submission_id);
                    var view = presentation(item);
                    if (rows.has(item.submission_id)) {
                        updates.push({
                            row: rows.get(item.submission_id),
                            item: item,
                            view: view
                        });
                    }
                });

                var missing = Array.from(rows.entries()).some(function (entry) {
                    var state = entry[1].dataset.gradeState;
                    return (state === "pending" || state === "running") &&
                        !returned.has(entry[0]);
                });
                if (missing) {
                    stop(
                        "제출 목록이 변경됐습니다. 코드를 보관한 뒤 화면을 새로고침해 주세요.",
                        false
                    );
                    return;
                }

                var changed = false;
                updates.forEach(function (update) {
                    var row = update.row;
                    var statusCell = row.querySelector("[data-grade-status]");
                    var scoreCell = row.querySelector("[data-grade-score]");
                    if (row.dataset.gradeState !== update.item.grading_status ||
                        statusCell.textContent.trim() !== update.view.label ||
                        scoreCell.textContent.trim() !== update.view.score) {
                        changed = true;
                    }
                    row.dataset.gradeState = update.item.grading_status;
                    statusCell.textContent = update.view.label;
                    scoreCell.textContent = update.view.score;
                });
                failures = 0;
                delay = changed ? 2000 : Math.min(10000, delay + 1000);
                if (!hasPending()) {
                    notice.textContent = "채점 결과가 갱신되었습니다.";
                    notice.hidden = false;
                }
            } catch (error) {
                if (!suspended && !document.hidden) {
                    failures += 1;
                    delay = Math.min(10000, delay * 2);
                    if (failures >= 3) {
                        stop(
                            "채점 결과를 확인하지 못했습니다. 다시 시도해 주세요.",
                            true
                        );
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
        document.addEventListener("DOMContentLoaded", start, {once: true});
    } else {
        start();
    }
})();
