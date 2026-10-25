(function () {
    "use strict";

    const config = document.getElementById("drawproblem-submit-config");
    const source = document.getElementById("drawproblem-source");
    const button = document.getElementById("drawproblem-submit");
    const status = document.getElementById("drawproblem-submit-status");

    if (!config || !source || !button || !status) return;

    function validId(value) {
        return typeof value === "string" &&
            value.length >= 1 && value.length <= 19 &&
            value.charAt(0) !== "0" &&
            !/[^0-9]/.test(value) &&
            (value.length < 19 || value <= "9223372036854775807");
    }

    function validToken(value) {
        return typeof value === "string" &&
            value.length === 64 &&
            !/[^0-9a-f]/.test(value);
    }

    let token = config.dataset.token;
    let pending = null;
    let inFlight = false;
    let blocked = false;

    if (!validId(config.dataset.problemId) ||
        !validId(config.dataset.versionId) ||
        !validToken(token) ||
        typeof config.dataset.performanceContext !== "string") {
        button.disabled = true;
        status.textContent =
            "제출 정보를 확인하지 못했습니다. 코드를 보관한 뒤 화면을 다시 열어 주세요.";
        return;
    }

    button.addEventListener("click", async function () {
        if (blocked || inFlight) return;

        if (pending === null) {
            const code = source.value;

            if (!code.trim() ||
                code.includes("\0") ||
                new TextEncoder().encode(code).length > 200000) {
                status.textContent =
                    "제출할 코드는 비어 있지 않아야 하며 200,000바이트 이하여야 합니다.";
                return;
            }

            pending = {source: code, token: token};
        }

        // 응답이 유실되면 편집기의 현재 값 대신 같은 요청을 다시 보낸다.
        const attempt = pending;
        inFlight = true;
        button.disabled = true;
        status.textContent = "제출 기록을 저장·확인하는 중입니다.";

        const controller = new AbortController();
        const timer = setTimeout(function () {
            controller.abort();
        }, 12000);

        try {
            const body = new URLSearchParams();
            body.set("problem_id", config.dataset.problemId);
            body.set("version_id", config.dataset.versionId);
            body.set("performance_context", config.dataset.performanceContext);
            body.set("submission_token", attempt.token);
            body.set("source_code", attempt.source);

            const response = await fetch("/drawproblem_submit.php", {
                method: "POST",
                credentials: "same-origin",
                cache: "no-store",
                redirect: "error",
                headers: {"Accept": "application/json"},
                body: body,
                signal: controller.signal
            });

            const result = await response.json();

            if (!response.ok || !result || result.ok !== true) {
                if ([401, 403, 404, 409].includes(response.status)) {
                    blocked = true;
                } else if (response.status === 400) {
                    // 입력 검사 실패는 저장되지 않았으므로 수정 후 재시도한다.
                    pending = null;
                }

                status.textContent = result &&
                    typeof result.message === "string"
                    ? result.message.slice(0, 500)
                    : "제출 결과를 확인하지 못했습니다. 현재 코드를 보관해 주세요.";
                return;
            }

            if (!validId(result.submission_id) ||
                !Number.isInteger(result.submission_no) ||
                result.submission_no < 1 ||
                result.submission_no > 2147483647 ||
                typeof result.reused !== "boolean" ||
                !(result.grade_id === null || validId(result.grade_id))) {
                throw new Error("제출 응답 형식 확인 실패");
            }

            pending = null;
            config.dataset.latestSubmissionId = result.submission_id;

            let message = "제출 " + result.submission_no + "번을 저장했습니다.";
            if (result.grade_id !== null) {
                message += " 자동 채점을 요청했습니다.";
            }
            if (source.value !== attempt.source) {
                message += " 이후 편집한 내용은 아직 제출되지 않았습니다.";
            }

            if (validToken(result.next_submission_token)) {
                token = result.next_submission_token;
                config.dataset.token = token;
            } else {
                blocked = true;
                message +=
                    " 다음 제출 전에는 코드를 보관하고 화면을 다시 열어 주세요.";
            }

            status.textContent = message;

            document.dispatchEvent(new CustomEvent("drawproblem:submitted", {
                detail: {problemId: config.dataset.problemId}
            }));

        } catch (error) {
            status.textContent =
                "제출 응답을 확인하지 못했습니다. 코드는 그대로 유지됩니다. " +
                "‘제출 확인 다시 시도’를 누르면 이전 요청의 저장 여부를 확인합니다.";
        } finally {
            clearTimeout(timer);
            inFlight = false;
            button.disabled = blocked;
            button.textContent = pending !== null && !blocked
                ? "제출 확인 다시 시도"
                : "정식 제출";
        }
    });
})();
