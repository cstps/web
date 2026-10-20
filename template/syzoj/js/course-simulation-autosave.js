(function () {
    "use strict";
    function start() {
        const config = document.getElementById("simulation-autosave-config");
        const status = document.getElementById("simulation-autosave-status");
        if (!config || !status || config.dataset.started === "1") return;
        const editor = document.getElementById(config.dataset.editorId);

        // 자동 저장 안내를 코드 편집기 바로 아래에 배치한다.
        if (editor) {
            const aceContainer = document.getElementById(
                "simulation-ace-editor"
            );
            const statusAnchor =
                aceContainer && aceContainer.style.display === "block"
                    ? aceContainer
                    : editor;

            statusAnchor.insertAdjacentElement("afterend", status);
        }

        const revisionField = document.getElementById("simulation-draft-revision");
        const activity = config.dataset.activityId;
        const token = config.dataset.token;
        let revision = config.dataset.revision;
        function validRevision(value) {
            return typeof value === "string" &&
                /^(0|[1-9][0-9]{0,19})$/.test(value) &&
                (value.length < 20 || value <= "18446744073709551615");
        }
        if (!editor || editor.readOnly) return;
        if (!revisionField || !validRevision(revision) ||
            !/^[1-9][0-9]*$/.test(activity) || !/^[a-f0-9]{64}$/.test(token)) {
            status.textContent = "저장 설정을 확인하지 못했습니다. 코드를 보관한 뒤 화면을 새로고침해 주세요.";
            return;
        }
        config.dataset.started = "1";
        revisionField.value = revision;
        let saved = editor.value;
        let timer = null, busy = null;
        let changedAt = 0, lastAttempt = 0, failures = 0;
        let paused = false, locked = false, held = false;
        let suspended = false, navigating = false;
        let lockedMessage = "";
        let replayForm = null, replayEvent = null;

        const retry = document.createElement("button");
        retry.type = "button";
        retry.className = "ui mini basic button";
        retry.textContent = "다시 저장";
        retry.addEventListener("click", function () {
            if (locked) return;
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
        function dirty() { return editor.value !== saved; }
        function schedule(wait) {
            clearTimeout(timer);
            if (!dirty() || busy || paused || locked || held || suspended ||
                navigating || document.hidden || navigator.onLine === false) return;
            timer = setTimeout(function () { save(false); },
                Math.max(wait, 5000 - (Date.now() - lastAttempt), 0));
        }
        async function sourceHash(bytes) {
            const digest = await window.crypto.subtle.digest("SHA-256", bytes);
            return Array.from(new Uint8Array(digest)).map(function (byte) {
                return byte.toString(16).padStart(2, "0");
            }).join("");
        }
        function save(force) {
            if (busy) return busy;
            if (locked || navigating || suspended ||
                (!force && (!dirty() || paused || held || document.hidden)) ||
                navigator.onLine === false) {
                if (locked) show(lockedMessage, false);
                else if (navigator.onLine === false)
                    show("연결이 끊겼습니다. 현재 코드는 아직 저장되지 않았습니다.", true);
                return Promise.resolve(false);
            }
            const code = editor.value;
            const expectedRevision = revision;
            const bytes = new TextEncoder().encode(code);
            if (bytes.length > 200000) {
                show("200KB를 초과해 저장하지 못했습니다. 코드 크기를 줄여 주세요.", false);
                return Promise.resolve(false);
            }
            lastAttempt = Date.now();
            show("코드 자동 저장 중", false);
            busy = (async function () {
                try {
                    const expectedHash = await sourceHash(bytes);
                    const body = new URLSearchParams();
                    body.set("activity_id", activity);
                    body.set("source_code", code);
                    body.set("autosave_token", token);
                    body.set("expected_revision", expectedRevision);
                    const response = await fetch("/course_simulation_draft_autosave.php", {
                        method: "POST", credentials: "same-origin",
                        cache: "no-store", redirect: "error",
                        headers: { "Accept": "application/json" }, body: body
                    });
                    if (response.status === 401 || response.status === 403) {
                        locked = true;
                        lockedMessage = "자동 저장 권한을 확인하지 못했습니다. 코드를 복사해 보관한 뒤 다시 로그인해 주세요.";
                        show(lockedMessage, false);
                        return false;
                    }
                    const result = await response.json();
                    if (response.status === 409 && result &&
                        (result.code === "draft_conflict" ||
                         result.code === "revision_required")) {
                        locked = true;
                        lockedMessage = result.message ||
                            "다른 탭에서 코드가 변경됐습니다. 현재 코드를 복사해 보관해 주세요.";
                        show(lockedMessage, false);
                        return false;
                    }
                    if (!response.ok || !result || result.ok !== true ||
                        result.activity_id !== activity ||
                        result.source_sha256 !== expectedHash ||
                        !validRevision(result.revision) || result.revision === "0") {
                        throw new Error("저장 결과 확인 실패");
                    }
                    revision = result.revision;
                    config.dataset.revision = revision;
                    revisionField.value = revision;
                    saved = code;
                    failures = 0;
                    if (!dirty()) changedAt = 0;
                    show(dirty() ? "추가 변경 내용 저장 대기" : "코드 자동 저장 완료", false);
                    return true;
                } catch (error) {
                    failures += 1;
                    paused = failures >= 3;
                    show(paused
                        ? "자동 저장을 잠시 중단했습니다. 다시 저장을 눌러 주세요."
                        : "자동 저장에 실패했습니다. 잠시 후 다시 시도합니다.", paused);
                    return false;
                }
            })().finally(function () {
                busy = null;
                schedule(failures ? Math.min(5000 * Math.pow(2, failures - 1), 30000) : 1500);
            });
            return busy;
        }
        editor.addEventListener("input", function () {
            if (!dirty()) {
                changedAt = 0;
                clearTimeout(timer);
                if (!busy) show(locked ? lockedMessage : "저장된 코드와 같습니다.", false);
                return;
            }
            if (!changedAt) changedAt = Date.now();
            if (locked) { show(lockedMessage, false); return; }
            if (!paused && !busy && !held) show("변경 내용 자동 저장 대기", false);
            schedule(Math.min(1500, Math.max(0, 5000 - (Date.now() - changedAt))));
        });

        const forms = ["simulation-draft-form", "simulation-submit-form"]
            .map(function (id) { return document.getElementById(id); })
            .filter(Boolean);
        forms.forEach(function (form) {
            form.addEventListener("submit", function (event) {
                if (replayForm === form) { replayEvent = event; return; }
                if (event.defaultPrevented) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                if (held) return;
                held = true;
                clearTimeout(timer);
                const draftRequest = form.id === "simulation-draft-form";
                const submitter = event.submitter;
                const controls = [];
                forms.forEach(function (item) {
                    item.querySelectorAll('button[type="submit"], input[type="submit"]')
                        .forEach(function (button) {
                            controls.push([button, button.disabled]);
                            button.disabled = true;
                        });
                });
                function restoreControls() {
                    controls.forEach(function (entry) { entry[0].disabled = entry[1]; });
                }
                const waiting = busy;
                (async function () {
                    try {
                        if (waiting) {
                            show("진행 중인 자동 저장을 마친 뒤 요청합니다.", false);
                            await waiting;
                        }
                        if (draftRequest) {
                            if (locked) { show(lockedMessage, false); return; }
                            paused = false;
                            failures = 0;
                            const ok = await save(true);
                            if (ok && !dirty()) show("코드 임시 저장 완료", false);
                        } else {
                            // 최초 제출 이벤트가 끝난 다음 제출을 다시 요청한다.
                            await new Promise(function (resolve) {
                                window.setTimeout(resolve, 0);
                            });

                            restoreControls();
                            navigating = true;
                            replayForm = form;
                            replayEvent = null;
                            if (submitter && submitter.form === form) form.requestSubmit(submitter);
                            else form.requestSubmit();
                            if (!replayEvent || replayEvent.defaultPrevented) {
                                navigating = false;
                                show("제출되지 않았습니다. 입력 내용을 확인해 주세요.", false);
                            }
                        }
                    } catch (error) {
                        navigating = false;
                        show("요청에 실패했습니다. 현재 코드는 화면에 유지됩니다.", !locked);
                    } finally {
                        replayForm = null;
                        replayEvent = null;
                        restoreControls();
                        held = false;
                        schedule(1500);
                    }
                })();
            }, true);
        });
        document.addEventListener("visibilitychange", function () {
            clearTimeout(timer);
            if (!document.hidden) schedule(500);
        });
        window.addEventListener("offline", function () {
            clearTimeout(timer);
            if (dirty()) show("연결이 끊겼습니다. 변경 내용은 아직 저장되지 않았습니다.", !locked);
        });
        window.addEventListener("online", function () {
            if (locked) return;
            paused = false; failures = 0; schedule(500);
        });
        window.addEventListener("beforeunload", function (event) {
            if (!navigating && (dirty() || busy)) {
                event.preventDefault(); event.returnValue = "";
            }
        });
        window.addEventListener("pagehide", function () {
            suspended = true; clearTimeout(timer);
        });
        window.addEventListener("pageshow", function () {
            suspended = false; navigating = false; schedule(500);
        });
        show("변경 시 자동 저장됩니다.", false);
    }
    if (document.readyState === "loading")
        document.addEventListener("DOMContentLoaded", start, { once: true });
    else start();
})();
