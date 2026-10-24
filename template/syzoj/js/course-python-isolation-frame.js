(function () {
    "use strict";

    const channel = new URL(location.href).searchParams.get("channel");
    if (!/^[a-f0-9]{64}$/.test(channel || "")) return;

    function report(type, data) {
        parent.postMessage(
            Object.assign({ type: type, channel: channel }, data),
            "https://1024.kr"
        );
    }

    function denied(action) {
        try {
            action();
            return false;
        } catch (error) {
            return error.name === "SecurityError";
        }
    }

    const frameChecks = {
        "프레임 출처 분리": self.origin === "null",
        "부모 화면 접근 차단": denied(function () {
            return parent.document.documentElement;
        }),
        "쿠키 읽기 차단": denied(function () {
            return document.cookie;
        }),
        "로컬 저장소 접근 차단": denied(function () {
            return localStorage.length;
        })
    };

    function workerMain() {
        const runtimeURL =
            "https://1024.kr/template/syzoj/js/vendor/pyodide-314.0.7/";
        const violations = [];

        self.addEventListener("securitypolicyviolation", function (event) {
            violations.push({
                directive: event.effectiveDirective,
                uri: event.blockedURI
            });
        });

        async function blockedByPolicy(url) {
            let rejected = false;
            try {
                await fetch(url, {
                    credentials: "include",
                    cache: "no-store"
                });
            } catch (error) {
                rejected = true;
            }
            await new Promise(function (resolve) {
                setTimeout(resolve, 100);
            });

            return rejected && violations.some(function (event) {
                return event.directive === "connect-src" &&
                    event.uri.startsWith(new URL(url).origin);
            });
        }

        (async function () {
            try {
                self.postMessage({
                    type: "progress",
                    message: "격리 Worker에서 로컬 Pyodide를 불러오는 중입니다."
                });

                const loader = await import(runtimeURL + "pyodide.mjs");
                const python = await loader.loadPyodide({
                    indexURL: runtimeURL,
                    stdout: function () {},
                    stderr: function () {}
                });

                const result = python.runPython("sum(range(1000000))");
                const version = python.runPython(
                    "import sys\nsys.version.split()[0]"
                );

                const checks = {
                    "Worker 출처 분리": self.origin === "null",
                    "Worker의 DOM 접근 없음": typeof document === "undefined",
                    "Python 계산": result === 499999500000,
                    "사이트 API 방향 통신 차단":
                        await blockedByPolicy("https://1024.kr/"),
                    "외부 사이트 통신 차단":
                        await blockedByPolicy("https://example.invalid/")
                };

                self.postMessage({
                    type: "result",
                    checks: checks,
                    version: String(version),
                    result: String(result)
                });
            } catch (error) {
                self.postMessage({
                    type: "error",
                    message: String(error && error.stack || error).slice(0, 2000)
                });
            }
        })();
    }

    let worker = null;
    let workerURL = null;

    function cleanup() {
        if (worker) worker.terminate();
        worker = null;
        workerURL = null;
    }

    try {
        // data URL로 시작해 격리 프레임의 blob 모듈 로딩 문제를 검증한다.
        workerURL = "data:text/javascript;charset=utf-8," +
            encodeURIComponent("(" + workerMain.toString() + ")();");

        worker = new Worker(workerURL, {
            type: "module",
            credentials: "omit"
        });

        worker.addEventListener("message", function (event) {
            const data = event.data;
            if (!data || typeof data !== "object") return;

            if (data.type === "result") {
                report("result", {
                    checks: Object.assign({}, frameChecks, data.checks),
                    version: data.version,
                    result: data.result
                });
                cleanup();
            } else if (data.type === "progress") {
                report("progress", { message: data.message });
            } else if (data.type === "error") {
                report("error", { message: data.message });
                cleanup();
            }
        });

        worker.addEventListener("error", function (event) {
            report("error", {
                message: event.message ||
                    "격리 Worker를 불러오지 못했습니다. 콘솔을 확인해 주세요."
            });
            cleanup();
        });
    } catch (error) {
        report("error", { message: String(error) });
        cleanup();
    }

    window.addEventListener("pagehide", cleanup);
})();
