(function () {
    "use strict";

    const form = document.getElementById("simulation-bank-form");
    const profile = document.getElementById("bank-execution_profile");
    const mode = document.getElementById("bank-simulation_mode");
    const message = document.getElementById("simulation-bank-message");
    if (!form || !profile || !mode || !message) return;

    const editors = [];

    function currentMode() {
        return profile.value === "python-draw-poc-v1"
            ? "ace/mode/python"
            : "ace/mode/javascript";
    }

    if (window.ace) {
        form.querySelectorAll(".simulation-bank-source").forEach(function (source) {
            const container = document.getElementById(source.dataset.editorId);
            if (!container) return;
            let editor = null;

            try {
                container.hidden = false;
                editor = window.ace.edit(container);
                editor.setTheme("ace/theme/chrome");
                editor.session.setMode(currentMode());
                editor.session.setUseWorker(false);
                editor.session.setUseWrapMode(true);
                editor.setOptions({
                    fontSize: "14px",
                    showPrintMargin: false,
                    tabSize: 4,
                    useSoftTabs: true
                });
                editor.setValue(source.value, -1);
                editor.textInput.getElement().setAttribute(
                    "aria-label",
                    source.getAttribute("aria-label")
                );
                editor.session.on("change", function () {
                    source.value = editor.getValue();
                });

                source.hidden = true;
                editors.push({source: source, editor: editor});
                editor.resize();

                if (window.ResizeObserver) {
                    const observer = new ResizeObserver(function () {
                        editor.resize();
                    });
                    observer.observe(container);
                }
            } catch (error) {
                source.hidden = false;
                container.hidden = true;
                if (editor) editor.destroy();
            }
        });
    }

    profile.addEventListener("change", function () {
        editors.forEach(function (item) {
            item.editor.session.setMode(currentMode());
        });
    });

    form.addEventListener("submit", function (event) {
        editors.forEach(function (item) {
            item.source.value = item.editor.getValue();
        });

        const required = ["starter_code"];
        if (mode.value === "drawing") required.push("reference_source");

        for (const name of required) {
            const source = form.elements.namedItem(name);
            if (!source || !source.value.trim()) {
                event.preventDefault();
                message.textContent = name === "starter_code"
                    ? "학생에게 제공할 기본 코드를 입력해 주세요."
                    : "그림 문제의 교사용 정답 코드를 입력해 주세요.";
                message.hidden = false;

                const item = editors.find(function (entry) {
                    return entry.source === source;
                });
                if (item) item.editor.focus();
                else if (source) source.focus();
                return;
            }
        }

        message.hidden = true;
    });
})();
