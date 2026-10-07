(() => {
    "use strict";
    const form = document.getElementById("frmSolution");
    const button = document.getElementById("test-run-button");
    const traceButton =
        document.getElementById("test-run-trace-button");

    if (!form || !button) return;
    const el = id => document.getElementById("test-run-" + id);
    const retry = el("retry");
    let busy = false;
    let solutionId = null;

    let traceSteps = [];
    let traceIndex = -1;
    let traceMarkerId = null;
    let traceError = null;

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

    function renderTraceStep() {
        const panel = el("trace-panel");

        if (!panel) {
            return;
        }

        if (
            !Array.isArray(traceSteps) ||
            traceSteps.length === 0 ||
            traceIndex < 0 ||
            traceIndex >= traceSteps.length
        ) {
            panel.hidden = true;
            return;
        }

        const step = traceSteps[traceIndex] || {};

        panel.hidden = false;

        const isErrorStep =
            traceError &&
            Number.isInteger(traceError.line) &&
            traceError.line === step.line;

        const errorElement =
            el("trace-error");

        if (errorElement) {
            if (isErrorStep) {
                const errorType =
                    typeof traceError.type === "string"
                        ? traceError.type
                        : "Error";

                const errorMessage =
                    typeof traceError.message === "string"
                        ? traceError.message
                        : "";

                errorElement.textContent =
                    errorType +
                    (
                        errorMessage !== ""
                            ? ": " + errorMessage
                            : ""
                    );

                errorElement.hidden = false;
            }
            else {
                errorElement.textContent = "";
                errorElement.hidden = true;
            }
        }

        el("trace-position").textContent =
            "단계 " +
            (traceIndex + 1) +
            " / " +
            traceSteps.length;

        el("trace-line").textContent =
            Number.isInteger(step.line)
                ? String(step.line)
                : "—";

        if (
            window.editor &&
            typeof window.editor.gotoLine === "function" &&
            Number.isInteger(step.line) &&
            step.line > 0
        ) {
            const aceEditor = window.editor;

            if (traceMarkerId !== null) {
                aceEditor.session.removeMarker(
                    traceMarkerId
                );

                traceMarkerId = null;
            }

            const Range =
                ace.require(
                    "ace/range"
                ).Range;

            traceMarkerId =
                aceEditor.session.addMarker(
                    new Range(
                        step.line - 1,
                        0,
                        step.line - 1,
                        1
                    ),
                    isErrorStep
                        ? "execution-error-line"
                        : "execution-current-line",
                    "fullLine"
                );

            aceEditor.gotoLine(
                step.line,
                0,
                true
            );

            aceEditor.scrollToLine(
                step.line - 1,
                true,
                true,
                function () {}
            );
        }

        const variablesElement =
            el("trace-variables");

        variablesElement.textContent = "";

        const variables =
            step.variables &&
            typeof step.variables === "object"
                ? step.variables
                : {};

        const names = Object.keys(variables);

        const previousStep =
            traceIndex > 0
                ? traceSteps[traceIndex - 1] || {}
                : {};

        const previousVariables =
            previousStep.variables &&
            typeof previousStep.variables === "object"
                ? previousStep.variables
                : {};

        if (names.length === 0) {
            variablesElement.textContent =
                "표시할 변수가 없습니다.";
        }
        else {
            const table =
                document.createElement("table");

            table.className =
                "ui very basic compact table";

            const tbody =
                document.createElement("tbody");

            names.forEach(name => {
                const tr =
                    document.createElement("tr");

                const nameCell =
                    document.createElement("td");

                const valueCell =
                    document.createElement("td");

                nameCell.textContent = name;
                valueCell.textContent =
                    String(variables[name]);

                const isNewVariable =
                    !Object.prototype.hasOwnProperty.call(
                        previousVariables,
                        name
                    );

                const isChangedVariable =
                    !isNewVariable &&
                    String(previousVariables[name]) !==
                        String(variables[name]);

                if (
                    traceIndex > 0 &&
                    (
                        isNewVariable ||
                        isChangedVariable
                    )
                ) {
                    tr.classList.add(
                        "test-run-trace-variable-changed"
                    );
                }

                tr.appendChild(nameCell);
                tr.appendChild(valueCell);
                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            variablesElement.appendChild(table);
        }

        el("trace-stdout").textContent =
            typeof step.stdout === "string" &&
            step.stdout !== ""
                ? step.stdout
                : "아직 출력이 없습니다.";

        const prev = el("trace-prev");
        const next = el("trace-next");
        const last = el("trace-last");

        if (prev) {
            prev.disabled = traceIndex <= 0;
        }

        if (next) {
            next.disabled =
                traceIndex >= traceSteps.length - 1;
        }

        if (last) {
            last.disabled =
                traceIndex >= traceSteps.length - 1;
        }
    }


    function loadTrace(data) {
        traceSteps = [];
        traceIndex = -1;
        traceError = null;

        const panel = el("trace-panel");

        const normalOutput =
            document.getElementById(
                "test-run-normal-output"
            );

        if (
            !data ||
            data.trace_available !== true ||
            !data.trace ||
            !Array.isArray(data.trace.steps) ||
            data.trace.steps.length === 0
        ) {
            if (panel) {
                panel.hidden = true;
            }

            if (normalOutput) {
                normalOutput.hidden = false;
            }

            return;
        }

        if (normalOutput) {
            normalOutput.hidden = true;
        }

        traceSteps = data.trace.steps;

        traceError =
            data.trace.error &&
            typeof data.trace.error === "object"
                ? data.trace.error
                : null;

        traceIndex = 0;

        renderTraceStep();
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

        if (data.trace_truncated) {
            warnings.push(
                "단계적 실행 내용이 많아 일부 단계 또는 상태가 생략되었습니다."
            );
        }
        if (data.stdout_invalid_utf8 || data.stderr_invalid_utf8 || data.compile_error_invalid_utf8) {
            warnings.push("UTF-8로 표시할 수 없는 바이트가 있어 일부 문자가 대체되었습니다.");
        }
        if (data.message) warnings.push(data.message);
        el("warnings").textContent = warnings.join("\n");
        el("warnings").hidden = warnings.length === 0;

        loadTrace(data);
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

    async function run(checkOnly, mode = "normal") {
        if (busy) return;
        busy = true;
        button.disabled = true;

        if (traceButton) {
            traceButton.disabled = true;
        }

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

                data.set(
                    "test_run_mode",
                    mode === "trace"
                        ? "trace"
                        : "normal"
                );

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

            if (traceButton) {
                traceButton.disabled = false;
            }

            retry.disabled = false;
        }
    }
    button.addEventListener(
        "click",
        () => run(false, "normal")
    );

    if (traceButton) {
        traceButton.addEventListener(
            "click",
            () => run(false, "trace")
        );

        traceButton.disabled = false;
    }

    retry.addEventListener(
        "click",
        () => run(true, "normal")
    );

    const tracePrev = el("trace-prev");
    const traceNext = el("trace-next");
    const traceLast = el("trace-last");

    if (tracePrev) {
        tracePrev.addEventListener(
            "click",
            () => {
                if (traceIndex > 0) {
                    traceIndex--;
                    renderTraceStep();
                }
            }
        );
    }

    if (traceNext) {
        traceNext.addEventListener(
            "click",
            () => {
                if (
                    traceIndex >= 0 &&
                    traceIndex < traceSteps.length - 1
                ) {
                    traceIndex++;
                    renderTraceStep();
                }
            }
        );
    }

    if (traceLast) {
        traceLast.addEventListener(
            "click",
            () => {
                if (traceSteps.length > 0) {
                    traceIndex =
                        traceSteps.length - 1;

                    renderTraceStep();
                }
            }
        );
    }

    button.disabled = false;
})();
