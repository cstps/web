import { loadPyodide } from "./vendor/pyodide-314.0.7/pyodide.mjs";

let started = false;

self.addEventListener("message", async function (event) {
    if (started) return;

    const mode = event.data && event.data.mode;
    if (!["check", "loop", "static", "animation"].includes(mode)) return;
    started = true;

    function reportError(error) {
        self.postMessage({
            type: "error",
            message: String(error && error.message || error).slice(0, 1000)
        });
    }

    try {
        self.postMessage({ type: "loading" });

        const python = await loadPyodide({
            indexURL: new URL(
                "./vendor/pyodide-314.0.7/",
                import.meta.url
            ).href,
            stdout: function () {},
            stderr: function () {}
        });

        const version = python.runPython(
            "import sys\nsys.version.split()[0]"
        );

        self.postMessage({ type: "started", version: String(version) });

        if (mode === "loop") {
            python.runPython("while True:\n    pass");
            return;
        }

        if (mode === "check") {
            const result = python.runPython("sum(range(1000000))");
            if (result !== 499999500000) {
                throw new Error("Python 계산 결과가 예상값과 다릅니다.");
            }
            self.postMessage({ type: "complete", result: String(result) });
            return;
        }

        const response = await fetch(
            new URL("./course-python-drawing-poc.json?v=3", import.meta.url),
            { credentials: "omit", cache: "no-store" }
        );
        if (!response.ok) throw new Error("고정 그림 예제를 불러오지 못했습니다.");

        const bundle = await response.json();
        if (bundle.schema_version !== 1 ||
            typeof bundle.runtime !== "string" ||
            !bundle.samples ||
            typeof bundle.samples[mode] !== "string") {
            throw new Error("그림 실행 설정 형식이 올바르지 않습니다.");
        }

        python.runPython(bundle.runtime);
        python.globals.set("_poc_source", bundle.samples[mode]);
        python.runPython("_poc_start(_poc_source)");
        python.globals.delete("_poc_source");

        let verifiedFrames = 0;

        async function sendFrame(frame) {
            const value = python.runPython("_poc_pixels()");
            let pixels;
            try {
                const converted = value && typeof value.toJs === "function"
                    ? value.toJs()
                    : value;
                pixels = new Uint8ClampedArray(converted);
            } finally {
                if (value && typeof value.destroy === "function") value.destroy();
            }

            if (pixels.length !== 400 * 400 * 4) {
                throw new Error("그림 픽셀 크기가 올바르지 않습니다.");
            }

            const expectedByFrame = bundle.expected_pixels_sha256 &&
                bundle.expected_pixels_sha256[mode];
            const expectedHash = expectedByFrame &&
                expectedByFrame[String(frame)];

            if (typeof expectedHash === "string") {
                const digest = await crypto.subtle.digest(
                    "SHA-256", pixels
                );
                const actualHash = Array.from(new Uint8Array(digest))
                    .map(function (byte) {
                        return byte.toString(16).padStart(2, "0");
                    }).join("");

                if (actualHash !== expectedHash) {
                    throw new Error(
                        "서버·브라우저 픽셀 불일치: 프레임 " + frame +
                        "\n서버: " + expectedHash +
                        "\n브라우저: " + actualHash
                    );
                }
                verifiedFrames += 1;
            }

            self.postMessage({
                type: "frame",
                frame: frame,
                verifiedFrames: verifiedFrames,
                pixels: pixels.buffer
            }, [pixels.buffer]);
        }

        await sendFrame(0);

        if (mode === "static") {
            self.postMessage({ type: "complete", result: "정적 그림 생성 완료 / 서버 기준 픽셀 일치 " + verifiedFrames + "건" });
            return;
        }

        let frame = 0;
        async function tick() {
            try {
                frame += 1;
                python.runPython("_poc_step(" + frame + ")");
                await sendFrame(frame);

                if (frame >= 120) {
                    self.postMessage({
                        type: "complete",
                        result: "120프레임 실행 완료 / 서버 기준 픽셀 일치 " + verifiedFrames + "건"
                    });
                    return;
                }

                setTimeout(tick, 1000 / 15);
            } catch (error) {
                reportError(error);
            }
        }
        setTimeout(tick, 0);
    } catch (error) {
        reportError(error);
    }
});
