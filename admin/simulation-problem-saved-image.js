(function () {
    "use strict";

    const panel = document.getElementById("bank-saved-image");
    if (!panel) return;

    const id = panel.dataset.versionId;
    const button = document.getElementById("bank-saved-image-refresh");
    const status = document.getElementById("bank-saved-image-status");
    const generated = document.getElementById("bank-saved-image-generated");
    const region = document.getElementById("bank-saved-image-region");
    const link = document.getElementById("bank-saved-image-link");
    const image = document.getElementById("bank-saved-image-preview");

    if (!/^[1-9][0-9]{0,18}$/.test(id || "")) {
        button.disabled = true;
        return;
    }

    button.addEventListener("click", async function () {
        button.disabled = true;
        const controller = new AbortController();
        const timer = setTimeout(function () { controller.abort(); }, 8000);

        try {
            const response = await fetch(
                "simulation_problem_image_status.php?version_id=" +
                encodeURIComponent(id),
                {
                    credentials: "same-origin",
                    cache: "no-store",
                    redirect: "error",
                    headers: {"Accept": "application/json"},
                    signal: controller.signal
                }
            );

            if (!response.ok) throw new Error("조회 실패");
            const data = await response.json();
            const view = data && data.image;

            if (!data || data.ok !== true || !view ||
                view.version_id !== id ||
                !["missing", "pending", "running", "ready", "error", "invalid"]
                    .includes(view.state) ||
                typeof view.label !== "string" || view.label.length > 200 ||
                typeof view.generated_at !== "string" ||
                typeof view.region_label !== "string") {
                throw new Error("조회 결과 형식 오류");
            }

            status.textContent = view.label;
            generated.textContent = view.generated_at;
            region.textContent = view.region_label;

            if (view.state === "ready") {
                if (!/^[0-9a-f]{64}$/.test(view.png_sha256 || "")) {
                    throw new Error("이미지 해시 형식 오류");
                }

                const url = "simulation_problem_reference_image.php?version_id=" +
                    encodeURIComponent(id) + "&hash=" +
                    encodeURIComponent(view.png_sha256);

                link.href = url;
                image.src = url;
                link.hidden = false;
            } else {
                link.hidden = true;
                image.removeAttribute("src");
            }
        } catch (error) {
            status.textContent =
                "상태를 확인하지 못했습니다. 로그인 상태를 확인한 뒤 다시 눌러 주세요.";
        } finally {
            clearTimeout(timer);
            button.disabled = false;
        }
    });
})();
