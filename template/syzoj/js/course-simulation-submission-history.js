(function () {
    "use strict";

    function start() {
        if (typeof HTMLDialogElement === "undefined" ||
            typeof HTMLDialogElement.prototype.showModal !== "function") {
            return;
        }

        const histories = Array.from(
            document.querySelectorAll(".simulation-student-history")
        );
        if (!histories.length) return;

        const dialog = document.createElement("dialog");
        dialog.className = "simulation-history-dialog";
        dialog.setAttribute("aria-labelledby", "simulation-history-dialog-title");

        function element(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text) node.textContent = text;
            return node;
        }

        function button(text, className) {
            const node = element("button", className, text);
            node.type = "button";
            return node;
        }

        const head = element("div", "simulation-history-dialog-head");
        const title = element("h3", "", "학생 제출 기록");
        title.id = "simulation-history-dialog-title";
        const close = button("닫기", "ui small basic button");
        head.append(title, close);

        const body = element("div", "simulation-history-dialog-body");
        const navigation = element("nav", "simulation-history-navigation");
        navigation.setAttribute("aria-label", "제출 회차 선택");
        const content = element("div", "simulation-history-content");
        body.append(navigation, content);

        const foot = element("div", "simulation-history-dialog-foot");
        const previous = button("이전 기록", "ui small basic button");
        const position = element("span");
        position.setAttribute("role", "status");
        const next = button("다음 기록", "ui small basic button");
        foot.append(previous, position, next);
        dialog.append(head, body, foot);
        document.body.appendChild(dialog);

        let current = null;

        function select(index) {
            if (!current ||
                index < 0 || index >= current.cards.length) return;

            current.selected = index;

            current.cards.forEach(function (card, cardIndex) {
                card.hidden = cardIndex !== index;
            });

            Array.from(navigation.children).forEach(function (item, itemIndex) {
                item.setAttribute(
                    "aria-current",
                    itemIndex === index ? "true" : "false"
                );
            });

            previous.disabled = index === 0;
            next.disabled = index === current.cards.length - 1;
            position.textContent =
                (index + 1) + " / " + current.cards.length + "개 기록";
            content.scrollTop = 0;
        }

        function prepare(history) {
            const cards = Array.from(history.children).filter(function (node) {
                return node.matches(".ui.segment");
            });

            cards.forEach(function (card) {
                card.classList.add("simulation-history-card");

                const textarea = card.querySelector("textarea");
                const copy = card.querySelector(".simulation-copy-code");

                if (textarea && !textarea.closest(".simulation-history-code")) {
                    const code = element("details", "simulation-history-code");
                    const summary = element("summary", "", "제출 코드 보기");
                    textarea.insertAdjacentElement("beforebegin", code);
                    code.append(summary, textarea);
                    if (copy) code.appendChild(copy);
                }
            });

            return cards;
        }

        histories.forEach(function (history) {
            const cards = prepare(history);
            if (!cards.length) return;

            const placeholder = document.createComment("학생 제출 기록 위치");
            history.insertAdjacentElement(
                "beforebegin",
                button("제출 기록 보기", "ui mini basic blue button")
            );
            const opener = history.previousElementSibling;
            history.parentNode.insertBefore(placeholder, history);
            history.hidden = true;

            const entry = {
                history: history,
                cards: cards,
                placeholder: placeholder,
                opener: opener,
                selected: 0
            };

            opener.addEventListener("click", function () {
                if (dialog.open) return;

                current = entry;
                title.textContent =
                    (history.dataset.studentId || "") + " · 제출 기록";
                navigation.replaceChildren();

                cards.forEach(function (card, index) {
                    const header = card.querySelector(".ui.small.header");
                    const date = header &&
                        header.querySelector(".sub.header");

                    const heading = header
                        ? Array.from(header.childNodes)
                            .filter(function (node) {
                                return node.nodeType === Node.TEXT_NODE;
                            })
                            .map(function (node) {
                                return node.textContent;
                            })
                            .join(" ").trim()
                        : "";

                    const item = button(
                        heading || (index + 1) + "번째 기록"
                    );

                    if (date) {
                        item.appendChild(
                            element("small", "", date.textContent.trim())
                        );
                    }

                    item.addEventListener("click", function () {
                        select(index);
                    });
                    navigation.appendChild(item);
                });

                history.hidden = false;
                history.open = true;
                content.appendChild(history);
                select(entry.selected);

                dialog.showModal();
                close.focus();
            });
        });

        close.addEventListener("click", function () {
            dialog.close();
        });

        previous.addEventListener("click", function () {
            if (current) select(current.selected - 1);
        });

        next.addEventListener("click", function () {
            if (current) select(current.selected + 1);
        });

        dialog.addEventListener("close", function () {
            if (!current) return;

            const entry = current;
            current = null;
            entry.history.hidden = true;
            entry.history.open = false;
            entry.placeholder.parentNode.insertBefore(
                entry.history,
                entry.placeholder.nextSibling
            );
            entry.opener.focus();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", start, {once: true});
    } else {
        start();
    }
})();
