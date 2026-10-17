(function () {
    "use strict";

    const source = document.getElementById("reference-source");
    const container = document.getElementById("reference-ace-editor");
    const message = document.getElementById("reference-editor-message");
    const label = document.getElementById("reference-source-label");

    if (!source || !container || !message || !source.form ||
        typeof window.ace === "undefined") {
        return;
    }

    const form = source.form;
    const wasRequired = source.required;
    let editor = null;

    function syncSource() {
        source.value = editor.getValue();
    }

    try {
        container.hidden = false;
        editor = window.ace.edit(container);
        editor.setTheme("ace/theme/chrome");
        editor.session.setMode("ace/mode/javascript");
        editor.session.setUseWorker(false);
        editor.session.setUseWrapMode(true);
        editor.setOptions({
            fontSize: "14px",
            showPrintMargin: false,
            showLineNumbers: true,
            useSoftTabs: true,
            tabSize: 4
        });
        editor.setValue(source.value, -1);

        const input = editor.textInput.getElement();
        input.setAttribute("aria-label", "교사용 정답 코드");

        editor.session.on("change", function () {
            syncSource();
            message.textContent = "";
            message.hidden = true;
        });

        // 숨겨진 필수 입력란 때문에 브라우저 검증이 막히지 않게 한다.
        // 빈 코드 검사는 아래 제출 처리와 서버에서 수행한다.
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
                console.error("편집기 정리 실패:", cleanupError);
            }
        }
        console.error("정답 코드 편집기 초기화 실패:", error);
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
        if (source.value.trim() === "") {
            event.preventDefault();
            message.textContent = "교사용 정답 코드를 입력해 주세요.";
            message.hidden = false;
            editor.focus();
        }
    });

    window.addEventListener("resize", function () {
        editor.resize();
    });
})();
