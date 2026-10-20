(function () {
    "use strict";

    function start() {
        const config = document.getElementById("simulation-autosave-config");
        const status = document.getElementById("simulation-autosave-status");
        if (!config || !status || config.dataset.started === "1") return;
        const editor = document.getElementById(config.dataset.editorId);
        const activity = config.dataset.activityId;
        const token = config.dataset.token;
        if (!editor || editor.readOnly ||
            !/^[1-9][0-9]*$/.test(activity) ||
            !/^[a-f0-9]{64}$/.test(token)) return;

        config.dataset.started = "1";
        let saved = editor.value;
        let dirtySince = 0;
        let lastAttempt = 0;
        let timer = null;
        let pending = null;
        let failures = 0;
        let paused = false;
        let forbidden = false;
        let suspended = false;
        let manualWaiting = false;
        let navigating = false;
        let replayForm = null;
        let replayEvent = null;

        const retry = document.createElement("button");
        retry.type = "button";
        retry.className = "ui mini basic button";
        retry.textContent = "다시 저장";
        retry.addEventListener("click", function () {
            if (forbidden) return;
            paused = false;
            failures = 0;
            schedule(0);
        });

        function show(message, canRetry) {
            status.textContent = message;
            if (canRetry) {
                status.appendChild(document.createTextNode(" "));
                status.appendChild(retry);
            }
        }

        function dirty() {
            return editor.value !== saved;
        }

        function schedule(wait) {
            clearTimeout(timer);
            if (!dirty() || pending || paused || forbidden || suspended ||
                manualWaiting || navigating || document.hidden ||
                navigator.onLine === false) return;
            timer = setTimeout(save, Math.max(
                wait, 5000 - (Date.now() - lastAttempt), 0
            ));
        }

        async function hash(bytes) {
            const digest = await window.crypto.subtle.digest("SHA-256", bytes);
            return Array.from(new Uint8Array(digest)).map(function (byte) {
                return byte.toString(16).padStart(2, "0");
            }).join("");
        }

        function save() {
            if (pending || !dirty() || paused || forbidden || suspended ||
                manualWaiting || navigating || document.hidden ||
                navigator.onLine === false) return;

            const code = editor.value;
            const bytes = new TextEncoder().encode(code);
            if (bytes.length > 200000) {
                show("200KB를 초과해 자동 저장하지 못했습니다. 코드 크기를 줄여 주세요.", false);
                return;
            }

            lastAttempt = Date.now();
            show("코드 자동 저장 중", false);

            pending = (async function () {
                try {
                    const expectedHash = await hash(bytes);
                    const body = new URLSearchParams();
                    body.set("activity_id", activity);
                    body.set("source_code", code);
                    body.set("autosave_token", token);

                    const response = await fetch(
                        "/course_simulation_draft_autosave.php",
                        {
                            method: "POST",
                            credentials: "same-origin",
                            cache: "no-store",
                            redirect: "error",
                            headers: { "Accept": "application/json" },
                            body: body
                        }
                    );

                    if (response.status === 401 || response.status === 403) {
                        forbidden = true;
                        show("자동 저장 권한을 확인하지 못했습니다. 코드를 복사해 보관한 뒤 다시 로그인해 주세요.", false);
                        return;
                    }

                    const result = await response.json();
                    if (!response.ok || !result || result.ok !== true ||
                        result.activity_id !== activity ||
                        result.source_sha256 !== expectedHash) {
                        throw new Error("자동 저장 결과 확인 실패");
                    }

                    saved = code;
                    failures = 0;
                    if (!dirty()) {
                        dirtySince = 0;
                        show("코드 자동 저장 완료", false);
                    } else {
                        show("추가 변경 내용 저장 대기", false);
                    }
                } catch (error) {
                    failures += 1;
                    paused = failures >= 3;
                    if (navigator.onLine === false) {
                        show("연결이 끊겼습니다. 변경 내용은 아직 저장되지 않았습니다.", true);
                    } else {
                        show(
                            paused
                                ? "자동 저장을 잠시 중단했습니다. 다시 저장을 눌러 주세요."
                                : "자동 저장에 실패했습니다. 잠시 후 다시 시도합니다.",
                            paused
                        );
                    }
                }
            })().finally(function () {
                pending = null;
                schedule(failures ? Math.min(5000 * Math.pow(2, failures - 1), 30000) : 1500);
            });
        }

        editor.addEventListener("input", function () {
            if (!dirty()) {
                dirtySince = 0;
                clearTimeout(timer);
                if (!pending) show("저장된 코드와 같습니다.", false);
                return;
            }
            if (!dirtySince) dirtySince = Date.now();
            if (forbidden || paused || manualWaiting || navigating) return;
            if (navigator.onLine === false) {
                show("연결이 끊겼습니다. 변경 내용은 아직 저장되지 않았습니다.", true);
                return;
            }
            if (!pending) show("변경 내용 자동 저장 대기", false);
            schedule(Math.min(1500, Math.max(
                0, 5000 - (Date.now() - dirtySince)
            )));
        });

        ["simulation-draft-form", "simulation-submit-form"].forEach(function (id) {
            const form = document.getElementById(id);
            if (!form) return;
            form.addEventListener("submit", function (event) {
                if (replayForm === form) {
                    replayEvent = event;
                    return;
                }
                if (event.defaultPrevented) return;

                clearTimeout(timer);
                if (!pending && !manualWaiting) {
                    navigating = true;
                    Promise.resolve().then(function () {
                        if (event.defaultPrevented) {
                            navigating = false;
                            schedule(1500);
                        }
                    });
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                if (manualWaiting) return;

                manualWaiting = true;
                const submitter = event.submitter;
                const waiting = pending;
                show("진행 중인 자동 저장을 마친 뒤 요청합니다.", false);

                Promise.resolve(waiting).then(function () {
                    manualWaiting = false;
                    navigating = true;
                    replayForm = form;
                    replayEvent = null;
                    try {
                        if (submitter && submitter.form === form) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                        if (!replayEvent || replayEvent.defaultPrevented) {
                            navigating = false;
                            show("요청이 진행되지 않았습니다. 입력 내용을 확인해 주세요.", false);
                            schedule(1500);
                        }
                    } catch (error) {
                        navigating = false;
                        show("요청을 다시 실행해 주세요.", false);
                        schedule(1500);
                    } finally {
                        replayForm = null;
                        replayEvent = null;
                    }
                });
            }, true);
        });

        document.addEventListener("visibilitychange", function () {
            clearTimeout(timer);
            if (!document.hidden) schedule(500);
        });
        window.addEventListener("offline", function () {
            clearTimeout(timer);
            if (dirty()) show("연결이 끊겼습니다. 변경 내용은 아직 저장되지 않았습니다.", true);
        });
        window.addEventListener("online", function () {
            if (forbidden) return;
            paused = false;
            failures = 0;
            schedule(500);
        });
        window.addEventListener("beforeunload", function (event) {
            if (!navigating && (dirty() || pending)) {
                event.preventDefault();
                event.returnValue = "";
            }
        });
        window.addEventListener("pagehide", function () {
            suspended = true;
            clearTimeout(timer);
        });
        window.addEventListener("pageshow", function () {
            suspended = false;
            navigating = false;
            schedule(500);
        });

        show("변경 시 자동 저장됩니다.", false);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start, { once: true });
    } else {
        start();
    }
})();
