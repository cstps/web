(() => {
    "use strict";
    const form = document.getElementById("frmSolution");
    const button = document.getElementById("test-run-button");
    if (!form || !button) return;
    const el = id => document.getElementById("test-run-" + id);
    const retry = el("retry");
    let busy = false;
    let solutionId = null;

    // 요청별 제한 시간. 서버의 실행 시간 제한과는 별도로 처리한다.
    async function request(url, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);
        try {
            return await fetch(url, {
                ...options,
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal
            }).then(async response => ({
                response,
                text: await response.text()
            }));
        } finally {
            clearTimeout(timer);
        }
    }

    function requestError(text) {
        if (!text.trim().startsWith("<")) {
            return text.trim().slice(0, 250) || "실행 요청에 실패했습니다.";
        }
        const page = new DOMParser().parseFromString(text, "text/html");
        const message = page.querySelector(".negative.message, .error.message, [role='alert']");
        return message && message.textContent.trim()
            ? message.textContent.trim().slice(0, 250)
            : "실행 요청이 거부되었습니다. 로그인, 인증번호와 제출 간격을 확인해 주세요.";
    }

    function render(data) {
        el("status").textContent = data.status;
        el("time").textContent = data.time_ms + " ms";
        el("memory").textContent = data.memory_kb + " KB";
        if (!data.completed) return;
        el("stdout").textContent = data.stdout || "출력이 없습니다.";
        el("stderr").textContent = data.stderr || "오류 출력이 없습니다.";
        el("compile-box").hidden = data.result !== 11;
        el("compile").textContent = data.compile_error || "컴파일 오류 메시지가 없습니다.";
        const warnings = [];
        if (data.stdout_truncated) warnings.push("표준 출력은 앞부분 64 KiB까지 표시합니다.");
        if (data.stderr_truncated) warnings.push("오류 출력은 앞부분 64 KiB까지 표시합니다.");
        if (data.compile_error_truncated) warnings.push("컴파일 오류 메시지 일부가 생략되었습니다.");
        if (data.stdout_invalid_utf8 || data.stderr_invalid_utf8 || data.compile_error_invalid_utf8) {
            warnings.push("UTF-8로 표시할 수 없는 바이트가 있어 일부 문자가 대체되었습니다.");
        }
        if (data.message) warnings.push(data.message);
        el("warnings").textContent = warnings.join("\n");
        el("warnings").hidden = warnings.length === 0;
    }

    async function poll() {
        const deadline = Date.now() + 120000;
        while (Date.now() < deadline) {
            const url = new URL("test_run_status.php", location.href);
            url.searchParams.set("solution_id", solutionId);
            const { response, text } = await request(url);
            let data;
            try { data = JSON.parse(text); }
            catch { throw new Error("결과 응답을 확인할 수 없습니다. 잠시 후 다시 확인해 주세요."); }
            if (!response.ok || data.ok !== true) {
                throw new Error(data.message || "결과 조회에 실패했습니다.");
            }
            render(data);
            el("message").textContent = "실행 번호 #" + solutionId + " · " + data.status;
            if (data.completed) return;
            await new Promise(resolve => setTimeout(resolve, 2000));
        }
        throw new Error("결과 조회 대기 시간이 지났습니다. 결과 다시 확인을 눌러 주세요.");
    }

    async function run(checkOnly) {
        if (busy) return;
        busy = true;
        button.disabled = true;
        retry.disabled = true;
        retry.hidden = true;
        el("results").hidden = false;
        try {
            if (!checkOnly) {
                solutionId = null;
                el("status").textContent = "실행 요청 중";
                el("time").textContent = "—";
                el("memory").textContent = "—";
                el("stdout").textContent = "";
                el("stderr").textContent = "";
                el("compile-box").hidden = true;
                el("warnings").hidden = true;
                el("message").textContent = "실행을 요청하고 있습니다.";
                const source = window.editor && typeof window.editor.getValue === "function"
                    ? window.editor.getValue()
                    : document.getElementById("source").value;
                if (typeof source !== "string" || !source.trim()) {
                    throw new Error("코드를 입력해 주세요.");
                }
                const data = new FormData(form);
                data.set("source", source);
                ["encoded_submit", "reverse2"].forEach(name => data.delete(name));

                // 원래 문제의 코드 템플릿과 대회 설정을 적용하는 시험 실행 경로
                const key = data.has("id") ? "id" : "cid";
                const target = Number(data.get(key));
                if (!Number.isInteger(target) || target < 0 || target > 2147483647 || (key === "cid" && target === 0)) {
                    throw new Error("실행 대상 번호가 올바르지 않습니다.");
                }
                data.set(key, String(-target));
                if (key === "id") {
                    data.delete("cid");
                    data.delete("pid");
                }
                const url = new URL(form.action, location.href);
                url.searchParams.set("ajax", "1");
                const { response, text } = await request(url, { method: "POST", body: data });
                if (!response.ok || !/^[1-9][0-9]*$/.test(text.trim())) {
                    throw new Error(requestError(text));
                }
                solutionId = text.trim();
            }
            await poll();
        } catch (error) {
            el("message").textContent = error.name === "AbortError"
                ? "서버 응답 시간이 초과됐습니다. 잠시 후 다시 확인해 주세요."
                : error.message;
            retry.hidden = solutionId === null;
        } finally {
            busy = false;
            button.disabled = false;
            retry.disabled = false;
        }
    }
    button.addEventListener("click", () => run(false));
    retry.addEventListener("click", () => run(true));
    button.disabled = false;
})();
