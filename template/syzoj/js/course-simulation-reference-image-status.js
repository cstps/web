(function () {
    "use strict";

    const root = document.getElementById("reference-image-status");
    if (!root) {
        return;
    }

    const activityId = root.dataset.activityId || "";
    const referenceNo = root.dataset.referenceNo || "";
    const activeStates = new Set(["pending", "running"]);
    let state = root.dataset.imageStatus || "";

    if (!/^[1-9][0-9]*$/.test(activityId) ||
        !/^[1-9][0-9]*$/.test(referenceNo) ||
        !activeStates.has(state)) {
        return;
    }

    const notice = document.createElement("p");
    notice.setAttribute("role", "status");
    notice.hidden = true;
    root.insertAdjacentElement("afterend", notice);

    let timer = null;
    let busy = false;
    let stopped = false;
    let failures = 0;
    let delay = 2000;
    let startedAt = Date.now();
    let controller = null;
    let suspended = false;

    function clearTimer() {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    function showNotice(text, retry) {
        notice.replaceChildren(document.createTextNode(text));
        notice.hidden = false;

        if (retry) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "ui mini basic button";
            button.textContent = "다시 확인";

            button.addEventListener("click", function () {
                stopped = false;
                failures = 0;
                delay = 2000;
                startedAt = Date.now();
                notice.hidden = true;
                schedule(0);
            });

            notice.appendChild(document.createTextNode(" "));
            notice.appendChild(button);
        }
    }

    function stop(text, retry) {
        stopped = true;
        clearTimer();
        if (text) {
            showNotice(text, retry);
        }
    }

    function schedule(wait) {
        clearTimer();

        if (stopped || suspended || document.hidden ||
            !activeStates.has(state)) {
            return;
        }

        timer = window.setTimeout(refresh, wait);
    }

    async function refresh() {
        timer = null;

        if (busy || stopped || suspended || document.hidden) {
            return;
        }

        if (Date.now() - startedAt >= 10 * 60 * 1000) {
            stop("자동 확인 시간이 지났습니다. 필요하면 다시 확인해 주세요.", true);
            return;
        }

        busy = true;
        controller = new AbortController();

        const timeout = window.setTimeout(function () {
            controller.abort();
        }, 10000);

        try {
            const url = new URL(
                "course_simulation_reference.php",
                window.location.href
            );
            url.searchParams.set("activity_id", activityId);
            url.searchParams.set("image_status", "1");

            const response = await fetch(url.toString(), {
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal
            });

            if (response.status === 401 || response.status === 403) {
                stop("로그인 상태 또는 정답 관리 권한을 확인해 주세요.", false);
                return;
            }

            if (!response.ok) {
                throw new Error("상태 조회 실패");
            }

            const data = await response.json();

            if (data.ok !== true ||
                typeof data.reference_no !== "string" ||
                typeof data.image_status !== "string" ||
                typeof data.html !== "string") {
                throw new Error("상태 응답 확인 실패");
            }

            if (data.reference_no !== referenceNo) {
                stop(
                    "다른 창에서 새 정답 버전을 저장했습니다. " +
                    "편집 중인 코드를 보관한 뒤 정답 관리 화면을 다시 열어 주세요.",
                    false
                );
                return;
            }

            if (!["pending", "running", "ready", "error", ""].includes(
                data.image_status
            )) {
                throw new Error("지원하지 않는 이미지 상태");
            }

            const fragment = document.createElement("template");
            fragment.innerHTML = data.html;

            const incoming = fragment.content.querySelector(
                "#reference-image-status"
            );

            if (!incoming ||
                incoming.dataset.referenceNo !== referenceNo ||
                incoming.dataset.activityId !== activityId ||
                incoming.dataset.imageStatus !== data.image_status) {
                throw new Error("상태 표시 정보 불일치");
            }

            root.className = incoming.className;
            root.replaceChildren(...incoming.childNodes);
            root.dataset.imageStatus = data.image_status;
            state = data.image_status;

            failures = 0;
            notice.hidden = true;
            delay = Math.min(10000, Math.round(delay * 1.4));

            if (!activeStates.has(state)) {
                stop("", false);
            }
        } catch (error) {
            if (suspended) {
                return;
            }

            failures++;
            delay = Math.min(10000, delay * 2);

            if (failures >= 3) {
                stop(
                    "상태를 자동으로 확인하지 못했습니다. " +
                    "편집 중인 코드는 유지됩니다.",
                    true
                );
            }
        } finally {
            window.clearTimeout(timeout);
            controller = null;
            busy = false;
            schedule(delay);
        }
    }

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            clearTimer();
        } else {
            schedule(0);
        }
    });

    window.addEventListener("pagehide", function () {
        suspended = true;
        clearTimer();
        if (controller) {
            controller.abort();
        }
    });

    window.addEventListener("pageshow", function () {
        suspended = false;
        schedule(0);
    });

    schedule(2000);
})();
