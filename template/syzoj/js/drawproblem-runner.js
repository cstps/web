(function () {
    "use strict";

    const form = document.getElementById("drawproblem-run-settings");
    const source = document.getElementById("drawproblem-source");
    const profile = document.getElementById("drawproblem-run-profile");
    const mode = document.getElementById("drawproblem-run-mode");
    const runButton = document.getElementById("drawproblem-run");
    const stopButton = document.getElementById("drawproblem-stop");
    const status = document.getElementById("drawproblem-run-status");
    const host = document.getElementById("drawproblem-run-host");
    const canvas = document.getElementById("drawproblem-run-canvas");
    const preview = document.getElementById("drawproblem-preview");

    if (!form || !source || !profile || !mode || !runButton ||
        !stopButton || !status || !host || !canvas || !preview) return;

    const context = canvas.getContext("2d");
    let active = null;
    let generation = 0;
    let verified = null;
    let bundlePromise = null;

    const section = source.closest("section");
    const aceBox = document.getElementById("drawproblem-ace-editor");
    if (section && aceBox) {
        const grid = document.createElement("div");
        grid.className = "drawproblem-reference-layout";
        const codePanel = document.createElement("div");
        codePanel.className = "drawproblem-reference-code";

        // 제목·저장 버튼·안내문·편집기를 함께 왼쪽 열로 옮긴다.
        const codeNodes = Array.from(section.childNodes).filter(function (node) {
            return node !== preview;
        });

        section.appendChild(grid);
        codeNodes.forEach(function (node) {
            codePanel.appendChild(node);
        });
        grid.append(codePanel, preview);

        // 기존 버튼 요소를 이동하므로 연결된 이벤트는 유지된다.
        const actions = document.createElement("div");
        actions.className = "drawproblem-preview-actions";
        preview.insertBefore(actions, runButton);
        actions.append(runButton, stopButton);

        const submitButton = document.getElementById("drawproblem-submit");
        const submitStatus = document.getElementById("drawproblem-submit-status");

        if (submitButton) {
            actions.appendChild(submitButton);
        }
        if (submitStatus) {
            preview.insertBefore(submitStatus, status.nextSibling);
        }

        // 상단 안내 영역과 실제 편집·출력 영역을 나눠 시작선을 맞춘다.
        const codeHeading = document.createElement("div");
        codeHeading.className =
            "drawproblem-panel-heading drawproblem-code-heading";
        const codeBody = document.createElement("div");
        codeBody.className = "drawproblem-code-body";
        const editorMessage =
            document.getElementById("drawproblem-editor-message");

        const bodyNodes = new Set([aceBox, source, editorMessage, form]);
        Array.from(codePanel.childNodes).forEach(function (node) {
            if (!bodyNodes.has(node)) codeHeading.appendChild(node);
        });

        codeBody.append(aceBox, source);
        if (editorMessage) codeBody.appendChild(editorMessage);
        codeBody.appendChild(form);
        codePanel.append(codeHeading, codeBody);

        const previewHeading = document.createElement("div");
        previewHeading.className =
            "drawproblem-panel-heading drawproblem-preview-heading";
        const previewBody = document.createElement("div");
        previewBody.className = "drawproblem-preview-body";
        const previewOutput =
            preview.querySelector(".drawproblem-preview-output");
        let outputStarted = false;

        Array.from(preview.childNodes).forEach(function (node) {
            if (node === previewOutput) outputStarted = true;
            (outputStarted ? previewBody : previewHeading).appendChild(node);
        });

        preview.append(previewHeading, previewBody);
    }

    function signature() {
        return JSON.stringify([
            source.value,
            profile.value,
            form.elements.namedItem("canvas_width").value,
            form.elements.namedItem("canvas_height").value
        ]);
    }

    function stopActive() {
        const run = active;
        active = null;
        if (run) {
            clearTimeout(run.timer);
            if (run.kind === "python" && run.frame.contentWindow) {
                run.frame.contentWindow.postMessage({
                    type: "stop", channel: run.channel
                }, "*");
                setTimeout(function () { run.frame.remove(); }, 50);
            } else {
                run.frame.remove();
            }
        }
        stopButton.disabled = true;
    }

    function fail(message) {
        generation += 1;
        verified = null;
        stopActive();
        status.textContent = message;
    }

    function guard(run, wait) {
        clearTimeout(run.timer);
        run.timer = setTimeout(function () {
            if (active === run) {
                fail("응답 시간이 초과됐습니다. 코드를 확인한 뒤 다시 실행해 주세요.");
            }
        }, Math.max(0, Math.min(wait, run.deadline - Date.now())));
    }

    function changed() {
        generation += 1;
        verified = null;
        stopActive();
        canvas.hidden = true;
        status.textContent =
            "코드가 변경됐습니다. 실행 버튼을 눌러 다시 확인해 주세요.";
    }

    function loadBundle() {
        if (!bundlePromise) {
            bundlePromise = fetch(
                "/template/syzoj/js/course-python-drawing-poc.json?v=3",
                {credentials: "omit", cache: "no-store"}
            ).then(function (response) {
                if (!response.ok) throw new Error("실행 설정 조회 실패");
                return response.json();
            }).then(function (data) {
                if (data.schema_version !== 1 ||
                    typeof data.runtime !== "string" ||
                    new TextEncoder().encode(data.runtime).length > 100000) {
                    throw new Error("실행 설정 형식 오류");
                }
                return data;
            }).catch(function (error) {
                bundlePromise = null;
                throw error;
            });
        }
        return bundlePromise;
    }

    function buildP5Document(code, channel) {
        const bootstrap = "(" + function (channel) {
            let failed = false;
            function report(type, message, extra) {
                parent.postMessage(Object.assign({
                    channel: channel,
                    type: type,
                    message: message
                }, extra || {}), "*");
            }
            window.addEventListener("error", function (event) {
                failed = true;
                report("error", String(event.message || "실행 파일을 불러오지 못했습니다."));
            });
            window.addEventListener("unhandledrejection", function (event) {
                failed = true;
                report("error", String(event.reason || "비동기 실행 오류"));
            });
            window.addEventListener("load", function () {
                const deadline = Date.now() + 5000;
                function inspect() {
                    if (failed) return;
                    const canvas = document.querySelector("canvas");
                    if (canvas) {
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                if (!failed) report("rendered", "", {
                                    width: canvas.width,
                                    height: canvas.height
                                });
                            });
                        });
                    } else if (Date.now() < deadline) {
                        requestAnimationFrame(inspect);
                    } else {
                        report("error", "결과 캔버스를 찾지 못했습니다.");
                    }
                }
                inspect();
            });
        }.toString() + ")(" + JSON.stringify(channel) + ");";

        const safeCode = code.replace(/<\/script/gi, "<\\/script");
        return [
            "<!doctype html><html lang=\"ko\"><head>",
            "<meta charset=\"utf-8\">",
            "<meta http-equiv=\"Content-Security-Policy\" content=\"",
            "default-src 'none'; ",
            "script-src https://1024.kr/template/syzoj/js/vendor/p5.min.js 'unsafe-inline'; ",
            "style-src 'unsafe-inline'; img-src data: blob:; ",
            "connect-src 'none'; worker-src 'none'; frame-src 'none'; ",
            "object-src 'none'; base-uri 'none'; form-action 'none'\">",
            "<style>html,body{margin:0;padding:0;background:white}",
            "canvas{display:block;max-width:100%;height:auto!important}</style>",
            "</head><body>",
            "<script>", bootstrap, "</script>",
            "<script src=\"https://1024.kr/template/syzoj/js/vendor/p5.min.js\"></script>",
            "<script>", safeCode, "</script>",
            "</body></html>"
        ].join("\n");
    }

    runButton.addEventListener("click", async function () {
        generation += 1;
        const current = generation;
        verified = null;
        stopActive();

        const code = source.value;
        if (!code.trim() || new TextEncoder().encode(code).length > 200000) {
            status.textContent = "코드는 비어 있지 않아야 하며 200KB 이하여야 합니다.";
            return;
        }

        if (
            profile.value === "python-draw-poc-v1" &&
            (form.elements.namedItem("canvas_width").value !== "400" ||
             form.elements.namedItem("canvas_height").value !== "400")
        ) {
            status.textContent = "현재 실행 화면는 400×400 캔버스를 지원합니다.";
            return;
        }

        const selectedProfile = profile.value;
        if (!["p5js-2.3.4", "python-draw-poc-v1"].includes(selectedProfile)) {
            status.textContent = "지원하지 않는 실행 설정입니다.";
            return;
        }

        const snapshot = signature();
        status.textContent = "실행 화면를 준비하는 중입니다.";
        canvas.hidden = true;
        host.replaceChildren();

        try {
            const bundle = selectedProfile === "python-draw-poc-v1"
                ? await loadBundle()
                : null;

            if (current !== generation || snapshot !== signature()) return;

            const channel = Array.from(
                crypto.getRandomValues(new Uint8Array(32))
            ).map(function (value) {
                return value.toString(16).padStart(2, "0");
            }).join("");

            const frame = document.createElement("iframe");
            frame.title = "교사용 코드 실행 결과";
            frame.setAttribute("sandbox", "allow-scripts");
            frame.setAttribute("referrerpolicy", "no-referrer");

            const run = {
                kind: bundle ? "python" : "javascript",
                frame: frame,
                channel: channel,
                signature: snapshot,
                code: code,
                bundle: bundle,
                initialized: false,
                lastFrame: -1,
                timer: null,
                deadline: Date.now() + 60000
            };
            active = run;

            if (bundle) {
                frame.hidden = true;
                frame.src = "/drawproblem_python_frame.php?channel=" + channel +
                    "&problem_id=" + encodeURIComponent(form.dataset.problemId) +
                    "&version_id=" + encodeURIComponent(form.dataset.versionId);
                context.fillStyle = "white";
                context.fillRect(0, 0, 400, 400);
                guard(run, 10000);
            } else {
                frame.srcdoc = buildP5Document(code, channel);
                guard(run, 10000);
            }

            host.appendChild(frame);
            stopButton.disabled = false;
            status.textContent = bundle
                ? "Python 실행 환경을 불러오는 중입니다."
                : "JavaScript 코드를 실행하는 중입니다.";
        } catch (error) {
            if (current === generation) {
                fail("미리보기 준비 실패: " + String(error.message || error).slice(0, 300));
            }
        }
    });

    window.addEventListener("message", function (event) {
        const run = active;
        if (!run || event.source !== run.frame.contentWindow ||
            event.origin !== "null") return;

        const data = event.data;
        if (!data || data.channel !== run.channel) return;

        if (data.type === "error") {
            fail("실행 오류: " + String(data.message || "알 수 없는 오류").slice(0, 1000));
            return;
        }

        if (run.kind === "javascript") {
            if (data.type !== "rendered") return;
            if (
                data.width !== Number(form.elements.namedItem("canvas_width").value) ||
                data.height !== Number(form.elements.namedItem("canvas_height").value)
            ) {
                fail("코드의 캔버스 크기를 문제의 캔버스 설정과 맞춰 주세요.");
                return;
            }
            clearTimeout(run.timer);
            verified = run.signature;
            status.textContent = "실행 결과가 표시됐습니다.";
            return;
        }

        if (data.type === "ready" && !run.initialized) {
            run.initialized = true;
            guard(run, 60000);
            run.frame.contentWindow.postMessage({
                type: "start",
                channel: run.channel,
                runtime: run.bundle.runtime,
                code: run.code,
                frames: 1,
                animated: false
            }, "*");
        } else if (data.type === "started") {
            run.deadline = Date.now() + 60000;
            guard(run, 5000);
            status.textContent = "Python 코드를 실행하는 중입니다.";
        } else if (data.type === "frame") {
            if (!run.initialized || run.lastFrame !== -1 ||
                data.frame !== 0 ||
                !(data.pixels instanceof ArrayBuffer) ||
                data.pixels.byteLength !== 400 * 400 * 4) {
                fail("그림 응답 형식이 올바르지 않습니다.");
                return;
            }
            run.lastFrame = 0;
            canvas.hidden = false;
            context.putImageData(
                new ImageData(new Uint8ClampedArray(data.pixels), 400, 400),
                0, 0
            );
            guard(run, 5000);
        } else if (data.type === "complete") {
            if (run.lastFrame !== 0) {
                fail("완성된 그림을 받지 못했습니다.");
                return;
            }
            verified = run.signature;
            stopActive();
            status.textContent =
                "Python 실행이 완료됐습니다.";
        }
    });

    source.addEventListener("input", changed);
    [profile, mode,
        form.elements.namedItem("canvas_width"),
        form.elements.namedItem("canvas_height")
    ].forEach(function (element) {
        element.addEventListener("change", changed);
    });

    stopButton.addEventListener("click", function () {
        generation += 1;
        stopActive();
        status.textContent = "실행을 중지했습니다.";
    });

    window.addEventListener("pagehide", function () {
        generation += 1;
        stopActive();
    });
})();
