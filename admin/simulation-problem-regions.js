(function () {
    "use strict";

    const form = document.getElementById("simulation-bank-form");
    const raw = document.getElementById("bank-regions");
    const scoring = document.getElementById("bank-scoring_mode");
    const mode = document.getElementById("bank-simulation_mode");
    const maximum = document.getElementById("bank-max-score");
    const output = document.querySelector(".bank-preview-output");
    if (!form || !raw || !scoring || !mode || !maximum || !output) return;

    let initial;
    try {
        initial = JSON.parse(raw.value);
        if (!Array.isArray(initial) || initial.length > 20 ||
            initial.some(item => !item || typeof item !== "object" ||
                Array.isArray(item) ||
                !["id", "x", "y", "width", "height", "max_score"]
                    .every(key => Object.prototype.hasOwnProperty.call(item, key)))) {
            throw new Error("영역 설정 형식 확인 필요");
        }
    } catch (error) {
        const warning = document.createElement("p");
        warning.setAttribute("role", "alert");
        warning.textContent =
            "영역 설정을 표로 불러오지 못했습니다. 아래 JSON을 확인해 주세요.";
        raw.before(warning);
        return;
    }

    const panel = document.createElement("div");
    panel.className = "bank-regions-panel";
    panel.innerHTML = `
        <h3>영역별 배점</h3>
        <p>좌표는 왼쪽 위가 (0, 0)인 400×400 캔버스 기준입니다.</p>
        <p>영역은 최대 20개이며 서로 겹칠 수 없습니다.
           정답 도형 포함 여부와 경계 여백은 저장 후 서버에서 검사합니다.</p>
        <div class="bank-regions-scroll">
            <table class="bank-regions-table">
                <caption>채점 영역과 배점 입력</caption>
                <thead><tr>
                    <th scope="col">영역 ID</th><th scope="col">이름</th>
                    <th scope="col">X</th><th scope="col">Y</th>
                    <th scope="col">너비</th><th scope="col">높이</th>
                    <th scope="col">배점</th><th scope="col">삭제</th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary">영역 추가</button>
        <p class="bank-regions-summary" role="status" aria-live="polite"></p>
    `;
    raw.before(panel);

    const rawLabel = form.querySelector('label[for="bank-regions"]');
    if (rawLabel) rawLabel.hidden = true;
    raw.hidden = true;

    const body = panel.querySelector("tbody");
    const addButton = panel.querySelector("button");
    const summary = panel.querySelector(".bank-regions-summary");

    const controls = document.createElement("div");
    controls.className = "bank-region-controls";
    const label = document.createElement("label");
    const toggle = document.createElement("input");
    toggle.type = "checkbox";
    toggle.checked = true;
    toggle.id = "bank-region-overlay-toggle";
    label.append(toggle, document.createTextNode(" 채점 영역 표시"));
    const overlayNotice = document.createElement("p");
    overlayNotice.setAttribute("role", "status");
    controls.append(label, overlayNotice);
    output.before(controls);

    const svgNamespace = "http://www.w3.org/2000/svg";
    const overlay = document.createElementNS(svgNamespace, "svg");
    overlay.classList.add("bank-region-overlay");
    overlay.setAttribute("viewBox", "0 0 400 400");
    overlay.setAttribute("preserveAspectRatio", "none");
    overlay.setAttribute("aria-hidden", "true");
    overlay.setAttribute("hidden", "");
    output.appendChild(overlay);

    function active() {
        return mode.value === "drawing" && scoring.value === "regions";
    }

    function cents(value) {
        if (typeof value !== "string" ||
            !/^(?:0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?$/.test(value)) return null;
        const parts = value.split(".");
        return Number(parts[0]) * 100 + Number((parts[1] || "").padEnd(2, "0"));
    }

    function scoreText(value) {
        return Math.floor(value / 100) + "." +
            String(value % 100).padStart(2, "0");
    }

    function integer(value) {
        return /^(?:0|[1-9][0-9]{0,3})$/.test(value) ? Number(value) : null;
    }

    function createRow(item) {
        const row = document.createElement("tr");
        row.regionOriginal = Object.assign({}, item);

        ["id", "label", "x", "y", "width", "height", "max_score"].forEach(key => {
            const cell = document.createElement("td");
            const input = document.createElement("input");
            input.className = "admin-form-input";
            input.dataset.regionField = key;
            input.value = item[key] === undefined ? "" : String(item[key]);
            input.setAttribute("aria-label", {
                id: "영역 ID", label: "영역 이름", x: "X 좌표", y: "Y 좌표",
                width: "영역 너비", height: "영역 높이", max_score: "영역 배점"
            }[key]);

            if (key === "id" || key === "label") {
                input.type = "text";
                if (key === "label") input.maxLength = 80;
            } else {
                input.type = "number";
                input.min = key === "max_score" ? "0.01" :
                    (key === "width" || key === "height" ? "1" : "0");
                input.max = key === "max_score" ? "99999.99" : "400";
                input.step = key === "max_score" ? "0.01" : "1";
            }
            input.required = key !== "label";
            input.addEventListener("input", refresh);
            cell.appendChild(input);
            row.appendChild(cell);
        });

        const cell = document.createElement("td");
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "admin-btn admin-btn-secondary";
        remove.textContent = "삭제";
        remove.addEventListener("click", function () {
            row.remove();
            refresh();
        });
        cell.appendChild(remove);
        row.appendChild(cell);
        body.appendChild(row);
    }

    function items() {
        return Array.from(body.rows).map(row => {
            const item = Object.assign({}, row.regionOriginal);
            row.querySelectorAll("input").forEach(input => {
                const key = input.dataset.regionField;
                const value = input.value.trim();
                item[key] = ["x", "y", "width", "height"].includes(key)
                    ? integer(value) : value;
            });
            if (item.label === "") delete item.label;
            return item;
        });
    }

    function geometry(item) {
        return ["x", "y", "width", "height"].every(key =>
            Number.isInteger(item[key])) &&
            item.x >= 0 && item.y >= 0 &&
            item.width > 0 && item.height > 0 &&
            item.x + item.width <= 400 && item.y + item.height <= 400;
    }

    function validate(list) {
        if (!list.length) return "채점 영역을 하나 이상 추가해 주세요.";
        const ids = new Set();
        let total = 0;
        for (const item of list) {
            if (typeof item.id !== "string" || !item.id.trim() || ids.has(item.id)) {
                return "영역 ID는 비어 있지 않아야 하며 서로 달라야 합니다.";
            }
            ids.add(item.id);
            if (!geometry(item)) {
                return "좌표·크기를 정수로 입력하고 영역을 400×400 안에 배치해 주세요.";
            }
            const points = cents(item.max_score);
            if (points === null || points <= 0) {
                return "영역 배점은 소수 둘째 자리까지의 양수로 입력해 주세요.";
            }
            total += points;
        }
        for (let i = 0; i < list.length; i += 1) {
            for (let j = i + 1; j < list.length; j += 1) {
                const a = list[i], b = list[j];
                if (a.x < b.x + b.width && b.x < a.x + a.width &&
                    a.y < b.y + b.height && b.y < a.y + a.height) {
                    return "채점 영역이 서로 겹칩니다. 좌표나 크기를 조정해 주세요.";
                }
            }
        }
        const expected = cents(maximum.value);
        if (expected === null || expected <= 0) return "전체 배점을 확인해 주세요.";
        if (total !== expected) {
            return "영역 배점 합계 " + scoreText(total) +
                "점 / 전체 배점 " + scoreText(expected) + "점: 합계를 맞춰 주세요.";
        }
        return "";
    }

    function draw(list) {
        // 숨김 상태뿐 아니라 기존 도형도 지워 잔상이 남지 않도록 한다.
        overlay.replaceChildren();
        const show = active() && toggle.checked;
        overlay.toggleAttribute("hidden", !show);
        if (!show) return;

        const colors = ["#b91c1c", "#1d4ed8", "#047857", "#7e22ce", "#a16207"];
        list.filter(geometry).forEach((item, index) => {
            const color = colors[index % colors.length];
            const rect = document.createElementNS(svgNamespace, "rect");
            rect.setAttribute("x", item.x);
            rect.setAttribute("y", item.y);
            rect.setAttribute("width", item.width);
            rect.setAttribute("height", item.height);
            rect.setAttribute("fill", color);
            rect.setAttribute("fill-opacity", "0.06");
            rect.setAttribute("stroke", color);
            rect.setAttribute("stroke-width", "1.5");
            rect.setAttribute("vector-effect", "non-scaling-stroke");

            const text = document.createElementNS(svgNamespace, "text");
            text.setAttribute("x", Math.min(item.x + 3, 360));
            text.setAttribute("y", Math.min(item.y + 14, 396));
            text.setAttribute("fill", color);
            text.textContent = (item.label || item.id) + " · " + item.max_score + "점";
            overlay.append(rect, text);
        });
    }

    function refresh() {
        const list = items();
        raw.value = JSON.stringify(list);
        const enabled = active();
        body.querySelectorAll("input, button").forEach(element => {
            element.disabled = !enabled;
        });
        addButton.disabled = !enabled || list.length >= 20;
        controls.hidden = !enabled;

        const issue = validate(list);
        summary.textContent = !enabled
            ? "점수 산정 방식을 영역별 배점으로 선택하면 사용합니다."
            : (issue || list.length + "개 영역 · 배점 합계 일치. 도형 포함 여부는 서버 검사에서 확인합니다.");
        summary.classList.toggle("bank-regions-error", enabled && Boolean(issue));
        overlayNotice.textContent = !toggle.checked
            ? "채점 영역 표시를 껐습니다."
            : "편집 중인 영역 설정입니다. 저장된 목표 그림에는 영향을 주지 않습니다.";
        draw(list);
        return issue;
    }

    addButton.addEventListener("click", function () {
        if (!active() || body.rows.length >= 20) return;
        const ids = new Set(items().map(item => item.id));
        let index = 1;
        while (ids.has("region_" + index)) index += 1;
        createRow({
            id: "region_" + index, label: "영역 " + index,
            x: 10, y: 10, width: 100, height: 100, max_score: "10.00"
        });
        refresh();
    });

    initial.forEach(createRow);
    [scoring, mode, toggle].forEach(element => {
        element.addEventListener("change", refresh);
    });
    maximum.addEventListener("input", refresh);

    form.addEventListener("submit", function (event) {
        const issue = refresh();
        if (!active() || !issue) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        summary.scrollIntoView({block: "center"});
    }, true);

    refresh();
})();
