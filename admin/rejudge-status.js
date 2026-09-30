(() => {
    "use strict";

    const panel = document.getElementById("rejudge-live-status");
    if (!panel) return;

    const output = panel.querySelector("[data-status-text]");
    const retry = panel.querySelector("button");
    let timer = null;
    let attempts = 0;
    let busy = false;

    async function poll() {
        if (busy) return;
        busy = true;
        retry.disabled = true;

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);

        try {
            const url = new URL("rejudge_status.php", window.location.href);
            url.searchParams.set("token", panel.dataset.token);

            const response = await fetch(url, {
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal
            });

            if (!response.ok) throw new Error("조회 실패");

            const data = await response.json();
            const results = data.distribution
                .map(item => `${item.label} (${item.code}): ${item.count}건`)
                .join(" / ");

            output.textContent =
                `전체 ${data.total}건 · 대기/채점 중 ${data.pending}건 · ` +
                `처리 종료 ${data.finished}건 · 수동 확인 ${data.manual}건` +
                (data.missing ? ` · 조회되지 않는 제출 ${data.missing}건` : "") +
                (results ? `\n${results}` : "");

            attempts++;

            if (!data.settled && attempts < 60) {
                timer = setTimeout(poll, 2000);
            } else if (!data.settled) {
                output.textContent +=
                    "\n자동 조회를 잠시 멈췄습니다. ‘상태 다시 조회’를 눌러 확인하세요.";
            }
        } catch (error) {
            output.textContent =
                "상태를 조회하지 못했습니다. ‘상태 다시 조회’를 눌러 주세요.";
        } finally {
            clearTimeout(timeout);
            busy = false;
            retry.disabled = false;
        }
    }

    retry.addEventListener("click", () => {
        clearTimeout(timer);
        attempts = 0;
        poll();
    });

    window.addEventListener("pagehide", () => clearTimeout(timer));
    poll();
})();
