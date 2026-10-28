(function () {
    "use strict";

    const source = document.getElementById("starter_code");
    const container = document.getElementById(
        "simulation-starter-ace-editor"
    );
    const message = document.getElementById(
        "simulation-starter-editor-message"
    );

    if (!source || !source.form || !container || !message) {
        return;
    }

    const isPython = source.dataset.sourceLanguage === "python";
    const languageLabel = isPython ? "Python" : "p5.js";
    const mode = document.getElementById("simulation_mode");

    function syncCanvasLimits() {
        const fixed = isPython || (mode && mode.value === "drawing");

        ["canvas_width", "canvas_height"].forEach(function (id) {
            const input = document.getElementById(id);
            if (!input) return;
            input.min = fixed ? "400" : "100";
            input.max = fixed ? "400" : "2000";
            input.readOnly = Boolean(fixed);
            if (fixed) input.value = "400";
        });
    }

    syncCanvasLimits();
    if (mode) mode.addEventListener("change", syncCanvasLimits);

    if (typeof window.ace === "undefined") return;

    const form = source.form;
    const wasRequired = source.required;
    const label = document.querySelector('label[for="starter_code"]');
    let editor = null;

    function syncSource() {
        source.value = editor.getValue();
    }

    function clearMessage() {
        message.textContent = "";
        message.hidden = true;
    }

    try {
        window.ace.config.set("basePath", "/ace");
        container.hidden = false;

        editor = window.ace.edit(container);
        editor.setTheme("ace/theme/chrome");
        editor.session.setMode(isPython ? "ace/mode/python" : "ace/mode/javascript");
        editor.session.setUseWorker(false);
        editor.session.setUseWrapMode(true);

        editor.setOptions({
            fontSize: "14px",
            showPrintMargin: false,
            showLineNumbers: true,
            showGutter: true,
            useSoftTabs: true,
            tabSize: 4,
            enableBasicAutocompletion: false,
            enableLiveAutocompletion: false
        });

        editor.setReadOnly(source.readOnly || source.disabled);
        editor.setValue(source.value, -1);
        editor.textInput.getElement().setAttribute(
            "aria-label", "기본 " + languageLabel + " 코드"
        );

        editor.session.on("change", function () {
            syncSource();
            clearMessage();
        });

        // 숨겨진 필수 입력란의 검증은 제출 처리에서 수행한다.
        source.required = false;
        source.hidden = true;
        editor.resize();

    } catch (error) {
        source.hidden = false;
        source.required = wasRequired;
        container.hidden = true;

        if (editor) {
            try {
                editor.destroy();
            } catch (cleanupError) {
                console.error("기본 코드 편집기 정리 실패:", cleanupError);
            }
        }

        console.error("기본 코드 편집기 초기화 실패:", error);
        return;
    }

    if (label) {
        label.addEventListener("click", function (event) {
            event.preventDefault();
            editor.focus();
        });
    }

    form.addEventListener("submit", function (event) {
        syncSource();

        let error = "";
        if (!source.value.trim()) {
            error = "기본 " + languageLabel + " 코드를 입력해 주세요.";
        } else if (new TextEncoder().encode(source.value).length > 200000) {
            error = "기본 코드는 200,000바이트 이하여야 합니다.";
        }

        if (error) {
            event.preventDefault();
            message.textContent = error;
            message.hidden = false;
            editor.focus();
        }
    });

    // 마우스로 높이를 변경하면 편집기 표시 영역도 갱신한다.
    if (typeof ResizeObserver !== "undefined") {
        const resizeObserver = new ResizeObserver(function () {
            editor.resize();
        });
        resizeObserver.observe(container);
    }

    window.addEventListener("resize", function () {
        editor.resize();
    });
})();
