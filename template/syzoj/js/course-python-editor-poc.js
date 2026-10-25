(function () {
    "use strict";

    const source = document.getElementById("editor-source");
    const aceBox = document.getElementById("editor-ace");
    const status = document.getElementById("editor-status");
    const output = document.getElementById("editor-output");
    const canvas = document.getElementById("editor-canvas");
    const context = canvas.getContext("2d");
    const stopButton = document.getElementById("editor-stop");
    let editor = null;
    let bundle = null;
    let active = null;

    if (window.ace) {
        try {
            ace.config.set("basePath", "/ace");
            aceBox.hidden = false;
            editor = ace.edit(aceBox);
            editor.setTheme("ace/theme/chrome");
            editor.session.setMode("ace/mode/python");
            editor.session.setUseWorker(false);
            editor.session.setUseWrapMode(true);
            editor.setOptions({
                fontSize: "14px",
                showPrintMargin: false,
                useSoftTabs: true,
                tabSize: 4
            });
            editor.setValue(source.value, -1);
            editor.session.on("change", function () {
                source.value = editor.getValue();
            });
            source.hidden = true;
            if (typeof ResizeObserver !== "undefined") {
                new ResizeObserver(function () {
                    editor.resize();
                }).observe(aceBox);
            }
        } catch (error) {
            if (editor) editor.destroy();
            editor = null;
            aceBox.hidden = true;
            source.hidden = false;
        }
    }

    function stop(message) {
        const previous = active;
        active = null;
        if (previous) {
            clearTimeout(previous.timer);
            previous.frame.contentWindow.postMessage({
                type: "stop", channel: previous.channel
            }, "*");
            setTimeout(function () { previous.frame.remove(); }, 50);
        }
        stopButton.disabled = true;
        status.textContent = message;
    }

    function guard(run, wait) {
        clearTimeout(run.timer);
        const remaining = run.deadline - Date.now();
        run.timer = setTimeout(function () {
            if (active === run) {
                stop("응답 시간이 초과되어 실행을 중지했습니다. 입력 코드는 유지됩니다.");
            }
        }, Math.max(0, Math.min(wait, remaining)));
    }

    function setCode(code) {
        source.value = code;
        if (editor) editor.setValue(code, -1);
    }

    fetch("/template/syzoj/js/course-python-drawing-poc.json?v=3", {
        credentials: "omit", cache: "no-store"
    }).then(function (response) {
        if (!response.ok) throw new Error("실행 설정 조회 실패");
        return response.json();
    }).then(function (data) {
        if (typeof data.runtime !== "string" || !data.samples) {
            throw new Error("실행 설정 형식 오류");
        }
        bundle = data;
        status.textContent = "코드를 입력하고 실행해 주세요.";
    }).catch(function (error) {
        status.textContent = "실행 설정을 불러오지 못했습니다.";
        output.textContent = String(error);
    });

    document.getElementById("editor-static").addEventListener("click", function () {
        if (!bundle) return;
        stop("정적 예제를 불러왔습니다.");
        setCode(bundle.samples.static);
        document.getElementById("editor-animated").checked = false;
    });
    document.getElementById("editor-animation").addEventListener("click", function () {
        if (!bundle) return;
        stop("움직임 예제를 불러왔습니다.");
        setCode(bundle.samples.animation);
        document.getElementById("editor-animated").checked = true;
    });

    document.getElementById("editor-run").addEventListener("click", function () {
        if (!bundle) {
            status.textContent = "실행 설정 로딩을 먼저 확인해 주세요.";
            return;
        }
        const code = editor ? editor.getValue() : source.value;
        const frames = Number(document.getElementById("editor-frames").value);
        if (!code.trim() || new TextEncoder().encode(code).length > 200000) {
            status.textContent = "코드는 비어 있지 않아야 하며 200KB 이하여야 합니다.";
            return;
        }
        if (!Number.isInteger(frames) || frames < 1 || frames > 600) {
            status.textContent = "최대 프레임은 1~600 사이의 정수여야 합니다.";
            return;
        }

        stop("실행을 준비합니다.");
        output.textContent = "";
        context.fillStyle = "white";
        context.fillRect(0, 0, 400, 400);

        const channel = Array.from(crypto.getRandomValues(new Uint8Array(32)))
            .map(function (v) { return v.toString(16).padStart(2, "0"); }).join("");
        const frame = document.createElement("iframe");
        frame.hidden = true;
        frame.title = "Python 그림 격리 실행";
        frame.setAttribute("sandbox", "allow-scripts");
        frame.setAttribute("referrerpolicy", "no-referrer");

        const run = {
            frame: frame, channel: channel, timer: null,
            deadline: Date.now() + 60000,
            initialized: false, lastFrame: -1,
            code: code, frames: frames,
            animated: document.getElementById("editor-animated").checked
        };
        active = run;
        frame.src = "/admin/simulation_python_editor_frame.php?channel=" + channel;
        document.getElementById("editor-frame-host").appendChild(frame);
        stopButton.disabled = false;
        status.textContent = "격리 실행 환경을 불러오는 중입니다.";
        guard(run, 60000);
    });

    window.addEventListener("message", function (event) {
        const run = active;
        if (!run || event.source !== run.frame.contentWindow ||
            event.origin !== "null") return;
        const data = event.data;
        if (!data || data.channel !== run.channel) return;

        if (data.type === "ready" && !run.initialized) {
            run.initialized = true;
            run.frame.contentWindow.postMessage({
                type: "start", channel: run.channel,
                runtime: bundle.runtime, code: run.code,
                frames: run.frames, animated: run.animated
            }, "*");
        } else if (data.type === "started") {
            run.deadline = Date.now() + 60000;
            guard(run, 5000);
            status.textContent = "Python 코드를 실행하는 중입니다.";
        } else if (data.type === "frame") {
            if (!Number.isInteger(data.frame) ||
                data.frame <= run.lastFrame ||
                data.frame > (run.animated ? run.frames : 1) ||
                !(data.pixels instanceof ArrayBuffer) ||
                data.pixels.byteLength !== 400 * 400 * 4) {
                stop("그림 응답 형식이 올바르지 않습니다.");
                return;
            }
            run.lastFrame = data.frame;
            context.putImageData(
                new ImageData(new Uint8ClampedArray(data.pixels), 400, 400),
                0, 0
            );
            output.textContent = "현재 프레임: " + data.frame;
            status.textContent = "그림 실행 중입니다.";
            guard(run, 5000);
        } else if (data.type === "complete") {
            stop("그림 실행 완료. 격리 실행을 정리했습니다.");
        } else if (data.type === "error") {
            output.textContent = String(data.message || "알 수 없는 오류").slice(0, 2000);
            stop("Python 실행 오류. 입력 코드는 유지됩니다.");
        }
    });

    stopButton.addEventListener("click", function () {
        stop("실행을 중지했습니다. 입력 코드는 유지됩니다.");
    });
    window.addEventListener("pagehide", function () {
        stop("실행을 종료했습니다.");
    });
})();
