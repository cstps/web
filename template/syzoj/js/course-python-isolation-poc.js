(function () {
    "use strict";

    const status = document.getElementById("isolation-status");
    const output = document.getElementById("isolation-output");
    const host = document.getElementById("isolation-frame-host");
    const stopButton = document.getElementById("isolation-stop");
    let frame = null;
    let channel = "";
    let timer = null;

    function stop(message) {
        clearTimeout(timer);
        timer = null;
        if (frame) frame.remove();
        frame = null;
        channel = "";
        stopButton.disabled = true;
        status.textContent = message;
    }

    document.getElementById("isolation-start").addEventListener("click", function () {
        stop("검사를 준비합니다.");
        output.textContent = "";

        channel = Array.from(crypto.getRandomValues(new Uint8Array(32)))
            .map(function (value) {
                return value.toString(16).padStart(2, "0");
            }).join("");

        frame = document.createElement("iframe");
        frame.setAttribute("sandbox", "allow-scripts");
        frame.setAttribute("referrerpolicy", "no-referrer");
        frame.title = "Python 격리 검사";
        frame.hidden = true;
        frame.src = "/admin/simulation_python_isolation_frame.php?channel=" +
            encodeURIComponent(channel);
        host.appendChild(frame);

        stopButton.disabled = false;
        status.textContent = "격리 프레임에서 Python 실행 환경을 확인하는 중입니다.";
        timer = setTimeout(function () {
            stop("검사 시간이 초과됐습니다. 브라우저 콘솔을 확인해 주세요.");
        }, 60000);
    });

    window.addEventListener("message", function (event) {
        if (!frame || event.source !== frame.contentWindow ||
            event.origin !== "null") return;

        const data = event.data;
        if (!data || data.channel !== channel) return;

        if (data.type === "progress") {
            status.textContent = String(data.message).slice(0, 300);
        } else if (data.type === "result") {
            if (!data.checks || typeof data.checks !== "object") return;

            const lines = [];
            let passed = true;
            Object.entries(data.checks).forEach(function (entry) {
                if (entry[1] !== true) passed = false;
                lines.push(entry[0] + ": " +
                    (entry[1] === true ? "정상" : "실패"));
            });
            lines.push("Python 버전: " + String(data.version || ""));
            lines.push("계산 결과: " + String(data.result || ""));
            output.textContent = lines.join("\n");
            stop(passed
                ? "격리 기본 검사 완료. 프레임·Worker 정리 완료."
                : "격리 검사 실패 항목이 있습니다.");
        } else if (data.type === "error") {
            output.textContent = String(data.message).slice(0, 2000);
            stop("격리 환경 실행 오류");
        }
    });

    stopButton.addEventListener("click", function () {
        stop("격리 프레임과 실행을 중지했습니다.");
    });
    window.addEventListener("pagehide", function () {
        stop("화면을 떠나 실행을 종료했습니다.");
    });
})();
