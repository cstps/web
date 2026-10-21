(function () {
    "use strict";

    const buttons = document.querySelectorAll(
        ".simulation-copy-code"
    );

    buttons.forEach(function (button) {
        button.addEventListener("click", async function () {
            const codeId = button.getAttribute("data-code-id");
            const codeElement = document.getElementById(codeId);

            if (!codeElement) {
                return;
            }

            const originalText = button.textContent;

            function revealCode() {
                let parent = codeElement.parentElement;
                while (parent) {
                    if (parent.tagName === "DETAILS") {
                        parent.open = true;
                    }
                    parent = parent.parentElement;
                }
            }

            try {
                if (
                    navigator.clipboard &&
                    navigator.clipboard.writeText
                ) {
                    await navigator.clipboard.writeText(
                        codeElement.value
                    );
                } else {
                    revealCode();
                    codeElement.focus();
                    codeElement.select();

                    if (!document.execCommand("copy")) {
                        throw new Error("코드 복사 실패");
                    }
                }

                button.textContent = "복사 완료";
            } catch (error) {
                revealCode();
                    codeElement.focus();
                codeElement.select();

                button.textContent =
                    "코드를 선택했습니다. Ctrl+C로 복사하세요.";
            }

            button.addEventListener(
                "blur",
                function () {
                    button.textContent = originalText;
                },
                { once: true }
            );
        });
    });
})();
