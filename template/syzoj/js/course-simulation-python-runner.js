(function () {
    "use strict";

    window.createCourseSimulationPythonRunner = function (options) {
        const frame = options.frame;
        const canvas = document.createElement("canvas");

        canvas.width = 400;
        canvas.height = 400;
        canvas.setAttribute("role", "img");
        canvas.setAttribute("aria-label", "Python 코드 실행 결과");
        canvas.style.cssText =
            "display:none;width:100%;max-width:400px;height:auto;background:#fff;";

        frame.insertAdjacentElement("afterend", canvas);
        let current = null;

        function release() {
            const entry = current;
            current = null;

            if (entry) {
                clearTimeout(entry.timer);
                entry.abort.abort();
            }

            frame.onload = null;
            frame.removeAttribute("srcdoc");
            frame.src = "about:blank";
            options.stopButton.disabled = true;
        }

        function fail(entry, message) {
            if (current !== entry) return;
            release();
            options.setStatus(String(message).slice(0, 500));
        }

        function deadline(entry, milliseconds) {
            clearTimeout(entry.timer);
            entry.timer = setTimeout(function () {
                fail(
                    entry,
                    "실행 제한 시간을 초과했습니다. 코드를 확인한 뒤 다시 실행해 주세요."
                );
            }, milliseconds);
        }

        function start(entry) {
            if (
                current !== entry ||
                !entry.ready ||
                entry.runtime === null ||
                entry.sent
            ) return;

            entry.sent = true;

            // 격리 프레임은 고유 출처이므로 대상 출처를 별도로 지정할 수 없다.
            // 수신 프레임에서는 부모 출처와 실행 식별자를 검사한다.
            frame.contentWindow.postMessage({
                type: "start",
                channel: entry.channel,
                runtime: entry.runtime,
                code: entry.code,
                frames: 1,
                animated: false
            }, "*");
        }

        function stop() {
            release();
            canvas.style.display = "none";
        }

        async function run(code) {
            stop();
            frame.style.display = "none";

            const random = crypto.getRandomValues(new Uint8Array(32));
            const channel = Array.from(random, function (value) {
                return value.toString(16).padStart(2, "0");
            }).join("");

            const entry = {
                channel: channel,
                code: code,
                ready: false,
                runtime: null,
                sent: false,
                received: false,
                timer: null,
                abort: new AbortController()
            };

            current = entry;
            deadline(entry, 45000);
            options.stopButton.disabled = false;
            options.setStatus("Python 실행 환경을 준비하는 중입니다.");

            try {
                const url = new URL(
                    "/course_simulation_python_frame.php",
                    location.origin
                );
                url.searchParams.set("activity_id", options.activityId);
                url.searchParams.set("channel", channel);
                frame.src = url.href;

                const response = await fetch(
                    "/template/syzoj/js/course-python-drawing-poc.json",
                    {
                        credentials: "omit",
                        signal: entry.abort.signal
                    }
                );

                if (!response.ok) {
                    throw new Error("Python 그림 함수를 불러오지 못했습니다.");
                }

                const bundle = await response.json();

                if (
                    !bundle ||
                    bundle.schema_version !== 1 ||
                    typeof bundle.runtime !== "string" ||
                    !bundle.runtime.trim() ||
                    new TextEncoder().encode(bundle.runtime).length > 100000
                ) {
                    throw new Error("Python 그림 함수 설정이 올바르지 않습니다.");
                }

                if (current !== entry) return;
                entry.runtime = bundle.runtime;
                start(entry);

            } catch (error) {
                if (current !== entry) return;

                fail(
                    entry,
                    "Python 실행 준비 오류: " +
                    String(error.message || error)
                );
            }
        }

        window.addEventListener("message", function (event) {
            const entry = current;

            if (
                !entry ||
                event.source !== frame.contentWindow ||
                event.origin !== "null"
            ) return;

            const data = event.data;

            if (
                !data ||
                typeof data !== "object" ||
                data.channel !== entry.channel
            ) return;

            if (data.type === "ready") {
                entry.ready = true;
                start(entry);
                return;
            }

            if (!entry.sent) return;

            if (data.type === "started") {
                deadline(entry, 10000);
                options.setStatus("Python 코드를 실행하는 중입니다.");

            } else if (data.type === "frame") {
                if (
                    data.frame !== 0 ||
                    !(data.pixels instanceof ArrayBuffer) ||
                    data.pixels.byteLength !== 400 * 400 * 4
                ) {
                    fail(
                        entry,
                        "Python 실행 결과의 그림 형식이 올바르지 않습니다."
                    );
                    return;
                }

                try {
                    const context = canvas.getContext("2d");

                    if (!context) {
                        throw new Error("그림 출력 영역을 준비하지 못했습니다.");
                    }

                    context.putImageData(
                        new ImageData(
                            new Uint8ClampedArray(data.pixels),
                            400,
                            400
                        ),
                        0,
                        0
                    );

                    entry.received = true;
                    canvas.style.display = "block";

                } catch (error) {
                    fail(
                        entry,
                        "그림 출력 오류: " + String(error.message || error)
                    );
                }

            } else if (data.type === "complete") {
                if (!entry.received) {
                    fail(entry, "Python 실행 결과의 그림이 없습니다.");
                    return;
                }

                release();
                options.setStatus("Python 코드 실행이 완료되었습니다.");

            } else if (data.type === "error") {
                fail(
                    entry,
                    "Python 코드 실행 오류: " +
                    String(data.message || "실행 오류")
                );
            }
        });

        window.addEventListener("pagehide", stop);

        return {
            run: run,
            stop: stop
        };
    };
})();
