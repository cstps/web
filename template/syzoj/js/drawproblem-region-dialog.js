(function () {
    "use strict";

    const host = document.getElementById("drawproblem-history-content");

    if (!host || host.dataset.regionDialogStarted === "1" ||
        typeof HTMLDialogElement === "undefined" ||
        typeof HTMLDialogElement.prototype.showModal !== "function") return;

    host.dataset.regionDialogStarted = "1";
    host.classList.add("drawproblem-region-dialog-enabled");

    const dialog = document.createElement("dialog");
    dialog.id = "drawproblem-region-dialog";
    dialog.className = "drawproblem-region-dialog";
    dialog.setAttribute("aria-labelledby", "drawproblem-region-dialog-title");

    const head = document.createElement("div");
    head.className = "drawproblem-region-dialog-head";

    const title = document.createElement("h3");
    title.id = "drawproblem-region-dialog-title";

    const close = document.createElement("button");
    close.type = "button";
    close.className = "ui small basic button";
    close.textContent = "닫기";
    head.append(title, close);

    const body = document.createElement("div");
    body.className = "drawproblem-region-dialog-body";
    dialog.append(head, body);
    document.body.appendChild(dialog);

    let currentId = null;
    let opener = null;
    let lastMarkup = null;

    function findRow(id) {
        return Array.from(
            host.querySelectorAll("tr[data-submission-id]")
        ).find(function (row) {
            return row.dataset.submissionId === id;
        }) || null;
    }

    function prepareButtons() {
        host.querySelectorAll("details > summary").forEach(function (summary) {
            summary.setAttribute("aria-haspopup", "dialog");
            summary.setAttribute("aria-controls", dialog.id);
        });
    }

    function showResult(row) {
        const details = row.querySelector("details");
        const cells = row.cells;

        title.textContent =
            "제출 " + cells[0].textContent.trim() + "번 · 영역별 결과";

        if (!details) {
            const message = "현재 채점 상태: " + cells[3].textContent.trim();

            if (lastMarkup !== message) {
                body.textContent = message;
                lastMarkup = message;
            }
            return;
        }

        const markup = details.innerHTML;
        if (markup === lastMarkup) return;

        const scrollTop = body.scrollTop;
        const fragment = document.createDocumentFragment();

        // 권한 검사 후 서버가 생성한 결과 요소만 복사한다.
        Array.from(details.childNodes).forEach(function (node) {
            if (node.nodeType === Node.ELEMENT_NODE &&
                node.tagName === "SUMMARY") return;

            fragment.appendChild(node.cloneNode(true));
        });

        body.replaceChildren(fragment);
        body.scrollTop = scrollTop;
        lastMarkup = markup;
    }

    host.addEventListener("click", function (event) {
        const target = event.target instanceof Element
            ? event.target
            : null;
        const summary = target && target.closest("summary");

        if (!summary || !host.contains(summary)) return;

        const details = summary.parentElement;
        const row = summary.closest("tr[data-submission-id]");

        if (!details || details.tagName !== "DETAILS" || !row) return;

        event.preventDefault();

        if (dialog.open) return;

        details.open = false;
        currentId = row.dataset.submissionId;
        opener = summary;
        lastMarkup = null;
        showResult(row);
        body.scrollTop = 0;
        dialog.showModal();
        close.focus();
    });

    close.addEventListener("click", function () {
        dialog.close();
    });

    dialog.addEventListener("close", function () {
        const row = currentId ? findRow(currentId) : null;
        const currentOpener = row && row.querySelector("details > summary");
        const focusTarget = currentOpener ||
            (opener && opener.isConnected ? opener : null) ||
            document.getElementById("drawproblem-history-refresh");

        currentId = null;
        opener = null;
        lastMarkup = null;
        body.replaceChildren();

        if (focusTarget && !focusTarget.disabled) focusTarget.focus();
    });

    // 이력 표가 교체되어도 팝업은 유지하고 해당 제출 결과만 갱신한다.
    const observer = new MutationObserver(function () {
        prepareButtons();

        if (!dialog.open || currentId === null) return;

        // 권한 또는 수행 기준 변경으로 이력이 지워지면 팝업도 닫는다.
        if (!host.querySelector("tr[data-submission-id]")) {
            dialog.close();
            return;
        }

        const row = findRow(currentId);
        if (row) showResult(row);
    });

    observer.observe(host, {childList: true, subtree: true});
    prepareButtons();
})();
