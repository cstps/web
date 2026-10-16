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

            try {
                if (
                    navigator.clipboard &&
                    navigator.clipboard.writeText
                ) {
                    await navigator.clipboard.writeText(
                        codeElement.value
                    );
                } else {
                    codeElement.focus();
                    codeElement.select();

                    if (!document.execCommand("copy")) {
                        throw new Error("copy failed");
                    }
                }

                button.textContent = "복사 완료";
            } catch (error) {
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
