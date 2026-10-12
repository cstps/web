(function () {
    "use strict";

    function initializeCourseMaterialEditor() {
        var textarea = document.getElementById("material_content_html");

        if (!textarea) {
            return;
        }

        if (typeof window.tinymce === "undefined") {
            console.error("TinyMCE를 불러오지 못했습니다.");
            return;
        }

        window.tinymce.init({
            target: textarea,
            license_key: "gpl",
            height: 420,
            min_height: 300,
            menubar: false,
            branding: false,
            promotion: false,
            resize: true,

            plugins:
                "autolink charmap code fullscreen link lists " +
                "preview searchreplace table wordcount",

            toolbar:
                "undo redo | blocks | bold italic underline | " +
                "bullist numlist | link table | " +
                "removeformat | code fullscreen preview",

            paste_data_images: false,
            convert_urls: false,

            setup: function (editor) {
                editor.on("change keyup", function () {
                    editor.save();
                });
            }
        });

        var form = textarea.form;

        if (form) {
            form.addEventListener("submit", function () {
                var editor = window.tinymce.get("material_content_html");

                if (editor) {
                    editor.save();
                }
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initializeCourseMaterialEditor
        );
    } else {
        initializeCourseMaterialEditor();
    }
})();
