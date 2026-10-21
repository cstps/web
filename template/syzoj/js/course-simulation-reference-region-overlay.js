(function () {
    "use strict";

    const frame = document.getElementById("reference-preview-frame");
    const mode = document.getElementById("reference-scoring-mode");
    const rows = document.getElementById("reference-region-rows");
    const status = document.getElementById("reference-preview-status");

    if (!frame || !mode || !rows || !status) return;

    const box = frame.closest(".reference-preview-box");
    if (!box) return;

    const namespace = "http://www.w3.org/2000/svg";
    const overlay = document.createElementNS(namespace, "svg");
    overlay.classList.add("reference-region-overlay");
    overlay.setAttribute("viewBox", "0 0 400 400");
    overlay.setAttribute("preserveAspectRatio", "none");
    overlay.setAttribute("aria-hidden", "true");
    box.appendChild(overlay);

    const controls = document.createElement("div");
    controls.className = "reference-region-controls";

    const toggle = document.createElement("input");
    toggle.type = "checkbox";
    toggle.id = "reference-region-overlay-toggle";
    toggle.checked = true;

    const label = document.createElement("label");
    label.htmlFor = toggle.id;
    label.append(toggle, document.createTextNode(" 채점 영역 표시"));
    controls.appendChild(label);
    status.insertAdjacentElement("beforebegin", controls);

    const message = document.createElement("p");
    message.className = "reference-region-overlay-message";
    message.setAttribute("role", "status");
    message.setAttribute("aria-live", "polite");
    box.insertAdjacentElement("afterend", message);

    let selectedRow = null;
    let scheduled = false;

    function coordinate(input, positive) {
        const value = input ? input.value : "";
        if (!/^(0|[1-9][0-9]*)$/.test(value)) return null;

        const number = Number(value);
        if (!Number.isSafeInteger(number) ||
            number < (positive ? 1 : 0) || number > 400) {
            return null;
        }
        return number;
    }

    function collect() {
        const regions = [];
        let invalid = 0;

        Array.from(rows.children).forEach(function (row, index) {
            const fields = {};
            row.querySelectorAll("[data-region-key]").forEach(function (input) {
                fields[input.dataset.regionKey] = input;
            });

            const x = coordinate(fields.x, false);
            const y = coordinate(fields.y, false);
            const width = coordinate(fields.width, true);
            const height = coordinate(fields.height, true);

            if ([x, y, width, height].includes(null) ||
                x + width > 400 || y + height > 400) {
                invalid++;
                return;
            }

            const name = fields.label && fields.label.value.trim();
            const score = fields.max_score && fields.max_score.value;

            regions.push({
                row: row,
                x: x,
                y: y,
                width: width,
                height: height,
                label: name || "영역 " + (index + 1),
                score: /^[0-9]{1,5}(?:\.[0-9]{1,2})?$/.test(score || "")
                    ? score + "점" : "배점 미입력",
                overlapping: false
            });
        });

        for (let i = 0; i < regions.length; i++) {
            for (let j = i + 1; j < regions.length; j++) {
                const a = regions[i];
                const b = regions[j];

                if (a.x < b.x + b.width &&
                    b.x < a.x + a.width &&
                    a.y < b.y + b.height &&
                    b.y < a.y + a.height) {
                    a.overlapping = true;
                    b.overlapping = true;
                }
            }
        }

        return {regions: regions, invalid: invalid};
    }

    function render() {
        scheduled = false;
        const enabled = mode.value === "regions";

        controls.hidden = !enabled;
        message.hidden = !enabled;
        if (!enabled || !toggle.checked) {
            overlay.setAttribute("hidden", "");
        } else {
            overlay.removeAttribute("hidden");
        }
        overlay.replaceChildren();

        if (selectedRow && !rows.contains(selectedRow)) {
            selectedRow = null;
        }

        Array.from(rows.children).forEach(function (row) {
            row.classList.toggle(
                "reference-region-selected",
                enabled && row === selectedRow
            );
        });

        if (!enabled) return;

        const result = collect();

        result.regions.forEach(function (region) {
            const rectangle = document.createElementNS(namespace, "rect");
            rectangle.classList.add("reference-region-outline");

            if (region.row === selectedRow) {
                rectangle.classList.add("is-selected");
            }
            if (region.overlapping) {
                rectangle.classList.add("is-overlapping");
            }

            rectangle.setAttribute("x", region.x);
            rectangle.setAttribute("y", region.y);
            rectangle.setAttribute("width", region.width);
            rectangle.setAttribute("height", region.height);
            rectangle.setAttribute("vector-effect", "non-scaling-stroke");

            const caption = document.createElementNS(namespace, "text");
            caption.classList.add("reference-region-caption");
            caption.setAttribute("x", region.x + 3);
            caption.setAttribute("y", Math.min(region.y + 13, 397));

            const shortName = Array.from(region.label).slice(0, 16).join("");
            caption.textContent = shortName + " · " + region.score;

            overlay.append(rectangle, caption);
        });

        const overlapping = result.regions.filter(function (region) {
            return region.overlapping;
        }).length;

        let text = toggle.checked
            ? "400×400 기준으로 " + result.regions.length + "개 영역을 표시합니다."
            : "채점 영역 표시를 껐습니다.";

        if (result.invalid) {
            text += " 좌표 또는 크기를 확인할 영역: " + result.invalid + "개.";
        }
        if (overlapping) {
            text += " 겹치는 영역 " + overlapping + "개를 빨간색으로 표시합니다.";
        }

        text += " 도형 포함 여부와 여백의 최종 검사는 저장 후 서버에서 수행합니다.";
        message.textContent = text;
    }

    function schedule() {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(render);
    }

    rows.addEventListener("input", schedule);
    rows.addEventListener("change", schedule);
    rows.addEventListener("focusin", function (event) {
        const row = event.target.closest("tr");
        if (row && row.parentElement === rows) {
            selectedRow = row;
            schedule();
        }
    });

    mode.addEventListener("change", schedule);
    toggle.addEventListener("change", schedule);

    const observer = new MutationObserver(schedule);
    observer.observe(rows, {childList: true});

    render();
})();
