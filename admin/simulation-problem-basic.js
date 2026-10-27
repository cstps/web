(function () {
    "use strict";

    const form = document.getElementById("simulation-bank-form");
    if (!form) return;

    const workspace = form.querySelector(".bank-authoring-workspace");

    if (workspace) {
        const section = document.createElement("section");
        section.className = "admin-card bank-basic-settings";

        const heading = document.createElement("h2");
        heading.textContent = "실행 설정";
        section.appendChild(heading);

        [
            "execution_profile",
            "simulation_mode",
            "canvas_width",
            "canvas_height"
        ].forEach(function (name) {
            const control = form.elements.namedItem(name);
            const field = control && control.closest(".admin-form-field");
            if (field) section.appendChild(field);
        });

        const help = document.createElement("p");
        help.textContent =
            "그림 문제와 Python 실험은 400×400으로 설정합니다. 실행 설정 변경은 전체 내용 새 버전 저장으로 반영합니다.";
        section.appendChild(help);
        workspace.before(section);
    }

    const button = document.getElementById("bank-basic-save");
    if (!button) return;

    button.hidden = false;

    button.addEventListener("click", function () {
        // 오류 화면에서도 작성 중인 코드를 보존할 수 있도록 입력값만 동기화한다.
        if (window.tinymce) {
            window.tinymce.triggerSave();
        }

        if (window.ace) {
            form.querySelectorAll("textarea[data-editor-id]").forEach(function (source) {
                const box = document.getElementById(source.dataset.editorId);
                if (box && !box.hidden) {
                    source.value = window.ace.edit(box).getValue();
                }
            });
        }

        let action = form.elements.namedItem("save_action");

        if (!action) {
            action = document.createElement("input");
            action.type = "hidden";
            action.name = "save_action";
            form.appendChild(action);
        }

        action.value = "basic";
        button.disabled = true;

        try {
            // 기본 정보의 검증은 서버에서 처리하며 코드 미리보기 검사는 거치지 않는다.
            HTMLFormElement.prototype.submit.call(form);
        } finally {
            action.value = "version";
            button.disabled = false;
        }
    });
})();
