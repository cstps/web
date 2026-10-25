(function () {
    "use strict";

    const source = document.getElementById("bank-description");
    const form = document.getElementById("simulation-bank-form");
    if (!source || !form) return;

    let descriptionEditor = null;

    function syncDescription() {
        if (descriptionEditor && !descriptionEditor.removed) {
            descriptionEditor.save();
        }
    }

    // 기존 폼 검증보다 먼저 설명 내용을 입력란에 동기화한다.
    form.addEventListener("submit", syncDescription, true);

    if (!window.tinymce) {
        source.hidden = false;
        return;
    }

    const options = {
        target: source,
        base_url: "/tinymce",
        suffix: ".min",
        license_key: "gpl",
        height: 320,
        min_height: 240,
        menubar: false,
        branding: false,
        promotion: false,
        resize: true,
        toolbar_mode: "sliding",
        plugins: "lists link table code fullscreen preview",
        toolbar: "undo redo | blocks | bold italic underline strikethrough | " +
            "bullist numlist | link table | removeformat | code fullscreen preview",
        block_formats: "문단=p; 제목 2=h2; 제목 3=h3; 제목 4=h4; 코드=pre",
        paste_data_images: false,
        automatic_uploads: false,
        valid_elements: "p,br,strong,b,em,i,u,s,sub,sup,h2,h3,h4," +
            "ul,ol,li,blockquote,a[href|rel],table,thead,tbody,tfoot," +
            "tr,th,td,caption,pre,code,hr",
        invalid_elements: "script,style,iframe,object,embed,svg,math," +
            "form,input,button,textarea,select,img,video,audio",
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        content_style: "body{font-family:sans-serif;font-size:15px;line-height:1.65;}" +
            "table{border-collapse:collapse;}td,th{border:1px solid #cbd5e1;padding:6px;}",
        setup: function (editor) {
            descriptionEditor = editor;

            editor.on("change input undo redo", syncDescription);

            editor.on("keydown", function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
                    event.preventDefault();
                    syncDescription();
                    form.requestSubmit();
                }
            });
        }
    };



    window.tinymce.init(options).catch(function () {
        if (descriptionEditor) {
            try { descriptionEditor.remove(); } catch (error) {}
        }
        descriptionEditor = null;
        source.hidden = false;
        source.style.display = "";
    });
})();
