(function () {
    "use strict";
    const channel = new URL(location.href).searchParams.get("channel");
    if (!/^[a-f0-9]{64}$/.test(channel || "")) return;
    let worker = null;
    let initialized = false;

    function report(type, data, transfer) {
        parent.postMessage(
            Object.assign({ type: type, channel: channel }, data),
            "https://1024.kr",
            transfer || []
        );
    }

    function cleanup() {
        if (worker) worker.terminate();
        worker = null;
    }

    function workerMain() {
        let initialized = false;
        self.addEventListener("message", async function (event) {
            if (initialized) return;
            initialized = true;
            const request = event.data;

            function fail(error) {
                self.postMessage({
                    type: "error",
                    message: String(error && error.message || error).slice(0, 2000)
                });
            }

            try {
                const base =
                    "https://1024.kr/template/syzoj/js/vendor/pyodide-314.0.7/";
                const loader = await import(base + "pyodide.mjs");
                const python = await loader.loadPyodide({
                    indexURL: base,
                    stdout: function () {},
                    stderr: function () {}
                });

                self.postMessage({ type: "started" });
                python.runPython(request.runtime);
                python.globals.set("_poc_source", request.code);
                python.runPython("_poc_start(_poc_source)");
                python.globals.delete("_poc_source");

                let frame = 0;

                function sendFrame() {
                    const value = python.runPython("_poc_pixels()");
                    let pixels;
                    try {
                        pixels = new Uint8ClampedArray(
                            value && typeof value.toJs === "function"
                                ? value.toJs() : value
                        );
                    } finally {
                        if (value && typeof value.destroy === "function") value.destroy();
                    }
                    if (pixels.length !== 400 * 400 * 4) {
                        throw new Error("출력 그림 크기가 올바르지 않습니다.");
                    }
                    self.postMessage({
                        type: "frame", frame: frame, pixels: pixels.buffer
                    }, [pixels.buffer]);
                }

                if (!request.animated) {
                    frame = 1;
                    python.runPython("_poc_step(1)");
                    sendFrame();
                    self.postMessage({ type: "complete" });
                    return;
                }

                sendFrame();
                function tick() {
                    try {
                        frame += 1;
                        python.runPython("_poc_step(" + frame + ")");
                        sendFrame();
                        if (frame >= request.frames) {
                            self.postMessage({ type: "complete" });
                        } else {
                            setTimeout(tick, 1000 / 15);
                        }
                    } catch (error) {
                        fail(error);
                    }
                }
                setTimeout(tick, 0);
            } catch (error) {
                fail(error);
            }
        });
    }

    window.addEventListener("message", function (event) {
        if (event.source !== parent || event.origin !== "https://1024.kr") return;
        const data = event.data;
        if (!data || data.channel !== channel) return;

        if (data.type === "stop") {
            cleanup();
            return;
        }
        if (initialized || data.type !== "start") return;
        if (typeof data.runtime !== "string" || data.runtime.length > 100000 ||
            typeof data.code !== "string" ||
            new TextEncoder().encode(data.code).length > 200000 ||
            !Number.isInteger(data.frames) || data.frames < 1 || data.frames > 600 ||
            typeof data.animated !== "boolean") return;

        initialized = true;
        try {
            const url = "data:text/javascript;charset=utf-8," +
                encodeURIComponent("(" + workerMain.toString() + ")();");
            worker = new Worker(url, { type: "module", credentials: "omit" });

            worker.addEventListener("message", function (event) {
                const message = event.data;
                if (!message || typeof message !== "object") return;
                if (message.type === "frame") {
                    if (!(message.pixels instanceof ArrayBuffer) ||
                        message.pixels.byteLength !== 400 * 400 * 4 ||
                        !Number.isInteger(message.frame) ||
                        message.frame < 0 || message.frame > data.frames) {
                        report("error", { message: "그림 전달 형식 오류" });
                        cleanup();
                        return;
                    }
                    report("frame", {
                        frame: message.frame, pixels: message.pixels
                    }, [message.pixels]);
                } else if (message.type === "started") {
                    report("started");
                } else if (message.type === "complete") {
                    report("complete");
                    cleanup();
                } else if (message.type === "error") {
                    report("error", {
                        message: String(message.message || "실행 오류").slice(0, 2000)
                    });
                    cleanup();
                }
            });

            worker.addEventListener("error", function (event) {
                report("error", {
                    message: event.message || "격리 Worker 실행 실패"
                });
                cleanup();
            });

            worker.postMessage({
                runtime: data.runtime, code: data.code,
                frames: data.frames, animated: data.animated
            });
        } catch (error) {
            report("error", { message: String(error) });
            cleanup();
        }
    });

    window.addEventListener("pagehide", cleanup);
    report("ready");
})();
