(function () {
    "use strict";

    const source = document.getElementById("reference-source");
    const runButton = document.getElementById("reference-preview-run");
    const stopButton = document.getElementById("reference-preview-stop");
    const status = document.getElementById("reference-preview-status");
    let frame = document.getElementById("reference-preview-frame");

    if (!source || !runButton || !stopButton || !status || !frame) {
        return;
    }

    const libraryUrl =
        "https://1024.kr/template/syzoj/js/vendor/p5.min.js";

    let runId = 0;
    let errorReported = false;
    let running = false;

    function setStatus(message) {
        status.textContent = message;
    }

    function stopPreview(showMessage) {
        runId += 1;
        running = false;
        frame.onload = null;

        // 이전 실행의 메시지와 구분하도록 프레임 자체를 교체한다.
        const replacement = frame.cloneNode(false);
        replacement.removeAttribute("srcdoc");
        replacement.src = "about:blank";
        frame.replaceWith(replacement);
        frame = replacement;

        stopButton.disabled = true;
        if (showMessage) {
            setStatus("미리보기 실행을 중지했습니다.");
        }
    }

    function buildDocument(code, id) {
        const safeCode = code.replace(/<\/script/gi, "<\\/script");

        const errorHandler = `
            window.addEventListener("error", function (event) {
                parent.postMessage({
                    type: "reference-preview-error",
                    runId: ${id},
                    message: String(event.message || "실행 오류")
                }, "*");
            });
            window.addEventListener("unhandledrejection", function (event) {
                parent.postMessage({
                    type: "reference-preview-error",
                    runId: ${id},
                    message: String(event.reason || "비동기 실행 오류")
                }, "*");
            });
        `;

        return [
            "<!doctype html>",
            '<html lang="ko"><head>',
            '<meta charset="utf-8">',
            '<meta http-equiv="Content-Security-Policy" ',
            'content="default-src \'none\'; ',
            'script-src https://1024.kr \'unsafe-inline\'; ',
            'style-src \'unsafe-inline\'; ',
            'img-src data: blob:; ',
            'connect-src \'none\'; font-src \'none\'; ',
            'media-src \'none\'; frame-src \'none\'; ',
            'object-src \'none\'; worker-src \'none\'; ',
            'form-action \'none\'; base-uri \'none\'">',
            '<meta name="viewport" content="width=device-width,initial-scale=1">',
            "<style>",
            "html,body,main{margin:0;padding:0;width:100%;}",
            "canvas.p5Canvas{display:block;width:100%!important;height:auto!important;}",
            "</style></head><body>",
            "<script>", errorHandler, "</script>",
            '<script src="' + libraryUrl + '"></script>',
            "<script>", safeCode, "</script>",
            "</body></html>"
        ].join("\n");
    }

    runButton.addEventListener("click", function () {
        // Ace 편집 내용은 기존 편집기에서 이 입력란에 동기화한다.
        const code = source.value;

        if (!code.trim()) {
            setStatus("미리보기로 실행할 정답 코드를 입력해 주세요.");
            return;
        }

        if (new TextEncoder().encode(code).length > 200000) {
            setStatus("코드 크기는 200,000바이트 이하여야 합니다.");
            return;
        }

        stopPreview(false);
        const currentRun = runId;
        errorReported = false;
        running = true;

        frame.onload = function () {
            if (currentRun !== runId || errorReported || !running) {
                return;
            }
            setStatus("실행 프레임을 불러왔습니다. 아래 그림을 확인해 주세요.");
        };

        stopButton.disabled = false;
        setStatus("정답 코드 미리보기를 실행하는 중입니다.");
        frame.srcdoc = buildDocument(code, currentRun);
    });

    stopButton.addEventListener("click", function () {
        stopPreview(true);
    });

    window.addEventListener("message", function (event) {
        if (!running || event.source !== frame.contentWindow) {
            return;
        }

        const data = event.data;
        if (
            !data ||
            typeof data !== "object" ||
            data.type !== "reference-preview-error" ||
            data.runId !== runId
        ) {
            return;
        }

        errorReported = true;
        setStatus(
            "실행 오류: " +
            String(data.message || "알 수 없는 오류").slice(0, 300)
        );
    });

    window.addEventListener("pagehide", function () {
        stopPreview(false);
    });
})();
