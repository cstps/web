(function () {
    "use strict";

    const config = document.getElementById("drawproblem-draft-config");
    const source = document.getElementById("drawproblem-source");
    const button = document.getElementById("drawproblem-draft-save");
    const status = document.getElementById("drawproblem-draft-status");
    if (!config || !source || !button || !status) return;

    function validRevision(value) {
        return typeof value === "string" &&
            /^(0|[1-9][0-9]{0,19})$/.test(value) &&
            (value.length < 20 || value <= "18446744073709551615");
    }

    let revision = config.dataset.revision;
    let inFlight = false;
    let blocked = false;

    if (!validRevision(revision)) {
        button.disabled = true;
        status.textContent = "초안 버전을 확인하지 못했습니다. 코드를 보관한 뒤 화면을 다시 열어 주세요.";
        return;
    }

    source.addEventListener("input", function () {
        if (!blocked && !inFlight) {
            status.textContent = "저장하지 않은 수정 내용이 있습니다.";
        }
    });

    button.addEventListener("click", async function () {
        if (blocked || inFlight) return;

        const snapshot = source.value;
        if (new TextEncoder().encode(snapshot).length > 200000) {
            status.textContent = "코드는 200,000바이트 이하여야 합니다.";
            return;
        }

        inFlight = true;
        button.disabled = true;
        status.textContent = "코드를 저장하는 중입니다.";

        const controller = new AbortController();
        const timer = setTimeout(function () {
            controller.abort();
        }, 8000);

        try {
            const body = new URLSearchParams();
            body.set("problem_id", config.dataset.problemId);
            body.set("version_id", config.dataset.versionId);
            body.set("performance_context", config.dataset.performanceContext);
            body.set("draft_token", config.dataset.token);
            body.set("expected_revision", revision);
            body.set("source_code", snapshot);

            const response = await fetch("/drawproblem_draft_save.php", {
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
                }
                status.textContent = result &&
                    typeof result.message === "string"
                    ? result.message.slice(0, 500)
                    : "저장하지 못했습니다. 현재 코드를 복사해 보관해 주세요.";
                return;
            }

            if (!validRevision(result.revision) || result.revision === "0") {
                throw new Error("저장 응답 버전 확인 실패");
            }

            revision = result.revision;
            config.dataset.revision = revision;
            status.textContent = source.value === snapshot
                ? "코드를 저장했습니다."
                : "요청한 코드는 저장됐습니다. 추가 수정 내용은 다시 저장해 주세요.";
        } catch (error) {
            status.textContent =
                "저장 응답을 확인하지 못했습니다. 코드를 보관한 뒤 다시 저장해 주세요.";
        } finally {
            clearTimeout(timer);
            inFlight = false;
            button.disabled = blocked;
        }
    });
})();
