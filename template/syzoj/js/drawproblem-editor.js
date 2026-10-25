(function () {
    "use strict";

    function start() {
        const source = document.getElementById("drawproblem-source");
        const box = document.getElementById("drawproblem-ace-editor");
        const message = document.getElementById("drawproblem-editor-message");
        if (!source || !box || !message || box.dataset.started === "1") return;

        function fallback() {
            box.hidden = true;
            source.hidden = false;
            message.textContent =
                "코드 편집기를 불러오지 못해 기본 코드 입력 영역을 표시합니다.";
            message.hidden = false;
        }

        if (!window.ace || typeof window.ace.edit !== "function") {
            fallback();
            return;
        }

        const language = box.dataset.language;
        if (!["javascript", "python"].includes(language)) {
            fallback();
            return;
        }

        let editor = null;
        try {
            editor = window.ace.edit(box);
            editor.session.setUseWorker(false);
            editor.session.setMode("ace/mode/" + language);
            editor.setTheme("ace/theme/chrome");
            editor.setOptions({
                fontSize: "14px",
                tabSize: 4,
                useSoftTabs: true,
                wrap: true,
                showPrintMargin: false
            });
            editor.setReadOnly(source.readOnly);
            editor.setValue(source.value, -1);

            editor.session.on("change", function () {
                source.value = editor.getValue();
                source.dispatchEvent(new Event("input", {bubbles: true}));
            });

            editor.textInput.getElement().setAttribute(
                "aria-label",
                source.readOnly ? "문제 기본 코드 읽기" : "문제 풀이 코드 작성"
            );

            source.hidden = true;
            box.hidden = false;
            box.dataset.started = "1";

            message.textContent = source.readOnly
                ? "이 문제의 코드는 읽기 전용입니다."
                : "코드를 수정할 수 있습니다. 화면을 떠나기 전에 작성한 코드를 복사해 보관해 주세요.";
            message.hidden = false;

            requestAnimationFrame(function () {
                editor.resize();
            });

            if (typeof ResizeObserver === "function") {
                const observer = new ResizeObserver(function () {
                    editor.resize();
                });
                observer.observe(box);
            }

            window.addEventListener("resize", function () {
                editor.resize();
            });
        } catch (error) {
            if (editor) {
                source.value = editor.getValue();
                editor.destroy();
            }
            fallback();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start, {once: true});
    } else {
        start();
    }
})();
