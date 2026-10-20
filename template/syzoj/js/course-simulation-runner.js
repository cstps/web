(function () {
    "use strict";

    const editor = document.getElementById("simulation-code");
    const frame = document.getElementById("simulation-frame");
    const runButton = document.getElementById("simulation-run");
    const stopButton = document.getElementById("simulation-stop");
    const resetButton = document.getElementById("simulation-reset");
    const status = document.getElementById("simulation-status");

    if (
        !editor || !frame || !runButton ||
        !stopButton || !resetButton || !status
    ) {
        return;
    }

    // Ace 편집기는 화면 입력을 담당하고,
    // 기존 textarea는 실행·저장·제출 데이터를 유지한다.
    const aceContainer = document.getElementById(
        "simulation-ace-editor"
    );

    let aceEditor = null;

    if (aceContainer && typeof ace !== "undefined") {
        try {
            ace.config.set("basePath", "/ace");

            aceContainer.style.display = "block";

            aceEditor = ace.edit(aceContainer);

            aceEditor.setTheme("ace/theme/chrome");
            aceEditor.session.setMode("ace/mode/javascript");
            aceEditor.session.setUseWrapMode(true);

            aceEditor.setOptions({
                fontSize: "14px",
                showPrintMargin: false,
                showLineNumbers: true,
                showGutter: true,
                highlightActiveLine: true,
                enableBasicAutocompletion: false,
                enableLiveAutocompletion: false,
                useSoftTabs: true,
                tabSize: 4
            });

            aceEditor.setReadOnly(editor.readOnly);
            aceEditor.setValue(editor.value, -1);

            // 학생이 코드를 입력하면 textarea에도 즉시 반영한다.
            aceEditor.session.on("change", function () {
                editor.value = aceEditor.getValue();
                editor.dispatchEvent(new Event("input", { bubbles: true }));
            });

            editor.style.display = "none";
            aceEditor.resize();

        } catch (error) {
            console.error("Ace 초기화 실패:", error);

            // Ace 초기화에 실패해도 기존 편집 기능은 유지한다.
            aceContainer.style.display = "none";
            editor.style.display = "";
            aceEditor = null;
        }
    }

    // 기본 코드 및 이전 제출 코드 복원에 공통 사용
    function setEditorCode(code) {
        editor.value = code;

        if (aceEditor) {
            aceEditor.setValue(code, -1);
            aceEditor.focus();
        } else {
            editor.dispatchEvent(new Event("input", { bubbles: true }));
        }
    }

    const originalCode = document.getElementById(
        "simulation-starter-code"
    );

    const starterCode = originalCode
        ? originalCode.value
        : editor.value;
    const libraryUrl =
        "https://1024.kr/template/syzoj/js/vendor/p5.min.js";

    let runId = 0;
    let errorReported = false;

    function setStatus(message) {
        status.textContent = message;
    }

    function stopSimulation() {
        runId += 1;

        frame.onload = null;
        frame.removeAttribute("srcdoc");
        frame.src = "about:blank";

        stopButton.disabled = true;
        setStatus("실행이 중지되었습니다.");
    }

    function buildDocument(code, id) {
        // 학생 코드가 script 요소를 조기에 닫지 못하도록 처리한다.
        // 코드는 신뢰할 수 없는 입력으로 취급한다.
        const safeCode = code.replace(
            /<\/script/gi,
            "<\\/script"
        );

        const errorHandler = `
            window.addEventListener("error", function (event) {
                parent.postMessage({
                    type: "simulation-error",
                    runId: ${id},
                    message: String(event.message || "실행 오류")
                }, "*");
            });

            window.addEventListener(
                "unhandledrejection",
                function (event) {
                    parent.postMessage({
                        type: "simulation-error",
                        runId: ${id},
                        message: String(
                            event.reason || "비동기 실행 오류"
                        )
                    }, "*");
                }
            );
        `;

        return [
            "<!doctype html>",
            '<html lang="ko">',
            "<head>",
            '<meta charset="utf-8">',
            '<style>html,body,main{margin:0;padding:0;width:100%;}canvas.p5Canvas{display:block;width:100%!important;height:auto!important;}</style>',
            '<meta name="viewport" content="width=device-width,initial-scale=1">',

            '<meta http-equiv="Content-Security-Policy" ',
            'content="default-src \'none\'; ',
            'script-src https://1024.kr \'unsafe-inline\'; ',
            'style-src \'unsafe-inline\'; ',
            'img-src data: blob:; ',
            'connect-src \'none\'; ',
            'font-src \'none\'; ',
            'media-src \'none\'; ',
            'frame-src \'none\'; ',
            'object-src \'none\'; ',
            'worker-src \'none\'; ',
            'form-action \'none\'; ',
            'base-uri \'none\'">',

            "</head>",
            '<body style="margin:0;padding:0;">',

            "<script>",
            errorHandler,
            "</script>",

            '<script src="' + libraryUrl + '"></script>',

            "<script>",
            safeCode,
            "</script>",

            "</body>",
            "</html>"
        ].join("\n");
    }

    function runSimulation() {
        const code = editor.value;

        if (!code.trim()) {
            setStatus("실행할 코드를 입력해 주세요.");
            return;
        }

        if (code.length > 200000) {
            setStatus("코드 길이가 허용 범위를 초과했습니다.");
            return;
        }

        stopSimulation();

        const currentRun = runId;
        errorReported = false;

        setStatus("p5.js 코드를 실행하는 중입니다.");

        frame.onload = function () {
            if (currentRun !== runId || errorReported) {
                return;
            }

            setStatus(
                "실행 프레임 로드 완료 — 캔버스 출력을 확인해 주세요."
            );
        };

        stopButton.disabled = false;
        frame.srcdoc = buildDocument(code, currentRun);
    }

    window.addEventListener("message", function (event) {
        if (event.source !== frame.contentWindow) {
            return;
        }

        const data = event.data;

        if (!data || typeof data !== "object") {
            return;
        }

        if (
            data.type === "simulation-error" &&
            data.runId === runId
        ) {
            errorReported = true;

            setStatus(
                "코드 실행 오류: " +
                String(data.message || "알 수 없는 오류").slice(0, 300)
            );
        }
    });

    // 정식 제출 시 현재 편집기 코드를 전송한다.
    const submitForm = document.getElementById(
        "simulation-submit-form"
    );

    const submitCode = document.getElementById(
        "simulation-submit-code"
    );

    if (submitForm && submitCode) {
        submitForm.addEventListener("submit", function (event) {
            const code = editor.value;

            if (!code.trim() || new TextEncoder().encode(code).length > 200000) {
                event.preventDefault();
                setStatus("제출 코드는 비어 있지 않아야 하며 200KB 이하여야 합니다.");
                return;
            }

            submitCode.value = code;
        });
    }

    // 이전 제출 코드를 편집기에 불러온다.
    // DB의 기존 제출 및 임시 저장 기록은 변경하지 않는다.
    const historyButtons = document.querySelectorAll(
        ".simulation-history-load"
    );

    historyButtons.forEach(function (button) {
        button.addEventListener("click", function () {

            if (editor.readOnly) {
                setStatus("현재 코드를 편집할 수 없습니다.");
                return;
            }

            const sourceId = button.getAttribute(
                "data-source-id"
            );

            const sourceElement = document.getElementById(
                sourceId
            );

            if (!sourceElement) {
                setStatus("이전 제출 코드를 찾을 수 없습니다.");
                return;
            }

            setEditorCode(sourceElement.value);

            stopSimulation();

            setStatus(
                "이전 제출 코드를 불러왔습니다. " +
                "실행 버튼으로 확인할 수 있습니다."
            );
        });
    });

    runButton.addEventListener("click", runSimulation);

    stopButton.addEventListener("click", stopSimulation);

    resetButton.addEventListener("click", function () {
        if (editor.readOnly) {
            return;
        }

        setEditorCode(starterCode);
        stopSimulation();
        setStatus("기본 코드로 복원했습니다.");
    });

    runButton.disabled = false;
    stopButton.disabled = true;
    resetButton.disabled = editor.readOnly;

    setStatus("코드를 확인한 후 실행 버튼을 눌러 주세요.");
})();
