(function () {
    "use strict";

    const mode = document.getElementById("reference-scoring-mode");
    const section = document.getElementById("reference-regions");
    const rows = document.getElementById("reference-region-rows");
    const add = document.getElementById("reference-region-add");
    const message = document.getElementById("reference-region-message");
    const total = document.getElementById("reference-score");

    if (!mode || !section || !rows || !add || !message ||
        !total || !mode.form) {
        return;
    }

    let nextId = 1;

    function inputs(row) {
        const result = {};
        row.querySelectorAll("[data-region-key]").forEach(function (input) {
            result[input.dataset.regionKey] = input;
        });
        return result;
    }

    function reindex() {
        const used = new Set();

        rows.querySelectorAll("tr").forEach(function (row) {
            const fields = inputs(row);
            if (fields.id.value) {
                used.add(fields.id.value);
            }
        });

        rows.querySelectorAll("tr").forEach(function (row, index) {
            const fields = inputs(row);

            if (!fields.id.value) {
                let id;
                do {
                    id = "region_" + nextId++;
                } while (used.has(id));
                fields.id.value = id;
                used.add(id);
            }

            Object.keys(fields).forEach(function (key) {
                fields[key].name = "regions[" + index + "][" + key + "]";
            });
        });

        add.disabled = rows.children.length >= 20;

        rows.querySelectorAll("[data-region-remove]").forEach(function (button) {
            button.disabled = rows.children.length <= 1;
        });
    }

    function scoreUnits(value) {
        if (!/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/.test(value)) {
            return null;
        }

        const parts = value.split(".");
        const units = Number(parts[0]) * 100 +
            Number(((parts[1] || "") + "00").slice(0, 2));

        return units > 0 && units <= 9999999 ? units : null;
    }

    function showSum() {
        if (mode.value !== "regions") {
            message.textContent = "";
            return;
        }

        let sum = 0;
        let valid = true;

        rows.querySelectorAll("tr").forEach(function (row) {
            const units = scoreUnits(inputs(row).max_score.value);
            if (units === null) {
                valid = false;
            } else {
                sum += units;
            }
        });

        const maximum = scoreUnits(total.value);

        if (!valid || maximum === null) {
            message.textContent =
                "각 영역의 배점과 전체 배점을 입력해 주세요. 최대 20개 영역을 설정할 수 있습니다.";
            return;
        }

        message.textContent =
            "영역 배점 합계: " + (sum / 100).toFixed(2) +
            "점 / 전체 배점: " + (maximum / 100).toFixed(2) +
            "점" + (sum === maximum ? " · 일치합니다." : " · 합계를 맞춰 주세요.");
    }

    function updateMode() {
        const enabled = mode.value === "regions";
        section.disabled = !enabled;
        section.hidden = !enabled;
        reindex();
        showSum();
    }

    add.addEventListener("click", function () {
        if (rows.children.length >= 20) {
            return;
        }

        const row = rows.firstElementChild.cloneNode(true);
        const fields = inputs(row);

        fields.id.value = "";
        fields.label.value = "";
        fields.x.value = "0";
        fields.y.value = "0";
        fields.width.value = "100";
        fields.height.value = "100";
        fields.max_score.value = "";

        rows.appendChild(row);
        reindex();
        showSum();
        fields.label.focus();
    });

    rows.addEventListener("click", function (event) {
        const button = event.target.closest("[data-region-remove]");
        if (!button || rows.children.length <= 1) {
            return;
        }

        button.closest("tr").remove();
        reindex();
        showSum();
    });

    function checkRegions() {
        const rectangles = [];
        let sum = 0;

        for (const row of rows.querySelectorAll("tr")) {
            const fields = inputs(row);
            const name = fields.label.value.trim();

            if (!name || Array.from(name).length > 80 ||
                /[\u0000-\u001f\u007f]/.test(name)) {
                return "영역 이름은 1~80자로 입력해 주세요.";
            }

            const rectangle = {};

            for (const key of ["x", "y", "width", "height"]) {
                const value = fields[key].value;
                if (!/^(0|[1-9][0-9]*)$/.test(value)) {
                    return "영역 좌표와 크기는 정수로 입력해 주세요.";
                }
                rectangle[key] = Number(value);
            }

            if (rectangle.width <= 0 || rectangle.height <= 0 ||
                rectangle.x + rectangle.width > 400 ||
                rectangle.y + rectangle.height > 400) {
                return "각 영역은 400×400 캔버스 안에 있어야 합니다.";
            }

            for (const previous of rectangles) {
                if (rectangle.x < previous.x + previous.width &&
                    previous.x < rectangle.x + rectangle.width &&
                    rectangle.y < previous.y + previous.height &&
                    previous.y < rectangle.y + rectangle.height) {
                    return "영역이 서로 겹칩니다. 좌표와 크기를 확인해 주세요.";
                }
            }
            rectangles.push(rectangle);

            const units = scoreUnits(fields.max_score.value);
            if (units === null) {
                return "각 영역의 배점은 0보다 큰 소수 둘째 자리 이내 숫자로 입력해 주세요.";
            }
            sum += units;
        }

        if (rectangles.length < 1 || rectangles.length > 20) {
            return "영역은 1~20개 설정해 주세요.";
        }

        if (sum !== scoreUnits(total.value)) {
            return "영역별 배점 합계가 전체 배점과 일치해야 합니다.";
        }

        return "";
    }

    mode.form.addEventListener("submit", function (event) {
        if (mode.value !== "regions") {
            return;
        }

        reindex();
        const error = checkRegions();

        if (error) {
            event.preventDefault();
            message.textContent = error;
            message.scrollIntoView({block: "center"});
        }
    });

    mode.addEventListener("change", updateMode);
    rows.addEventListener("input", showSum);
    total.addEventListener("input", showSum);

    updateMode();
})();
