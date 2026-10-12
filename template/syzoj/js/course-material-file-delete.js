"use strict";

document.addEventListener("DOMContentLoaded", function () {
    var forms = document.querySelectorAll(
        ".course-material-file-delete-form"
    );

    forms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.confirm(
                "이 첨부파일을 삭제하시겠습니까?"
            )) {
                event.preventDefault();
            }
        });
    });
});
