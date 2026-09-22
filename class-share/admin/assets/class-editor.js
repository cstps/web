(function () {
    "use strict";

    function initializeClassEditors() {
        if (typeof window.tinymce === "undefined") {
            console.error("TinyMCE를 불러오지 못했습니다.");
            return;
        }

        var editors =
            document.querySelectorAll(
                "textarea.class-share-rich-editor"
            );

        if (editors.length === 0) {
            return;
        }

        window.tinymce.init({
            selector:
                "textarea.class-share-rich-editor",

            license_key: "gpl",
            height: 360,
            min_height: 260,
            menubar: false,
            branding: false,
            promotion: false,
            resize: true,
            toolbar_mode: "sliding",

            plugins:
                "autolink charmap code fullscreen " +
                "link lists preview searchreplace " +
                "table wordcount",

            block_formats:
                "문단=p; 제목 2=h2; 제목 3=h3; " +
                "제목 4=h4; 인용=blockquote; 코드=pre",
            toolbar:
                "undo redo | blocks | " +
                "bold italic underline strikethrough | " +
                "bullist numlist | " +
                "blockquote link table | " +
                "removeformat | code fullscreen preview",

            formats: {
                underline: {
                    inline: "u"
                },

                strikethrough: {
                    inline: "s"
                }
            },

            valid_elements:
                "p,br,strong,b,em,i,u,s,sub,sup," +
                "h2,h3,h4,ul,ol,li,blockquote," +
                "a[href|title|target|rel]," +
                "table,thead,tbody,tfoot,tr," +
                "th[colspan|rowspan|scope]," +
                "td[colspan|rowspan],caption," +
                "pre,code,hr",

            invalid_elements:
                "script,style,iframe,frame,frameset," +
                "object,embed,applet,svg,math," +
                "form,input,button,textarea,select,option," +
                "img,video,audio,source,link,meta,base",

            entity_encoding: "raw",
            relative_urls: false,
            remove_script_host: false,
            convert_urls: true,

            setup: function (editor) {
                editor.on(
                    "keydown",
                    function (event) {
                        if (
                            !(event.ctrlKey || event.metaKey) ||
                            event.key !== "Enter"
                        ) {
                            return;
                        }

                        event.preventDefault();
                        editor.save();

                        var form =
                            editor.getElement().form;

                        if (!form) {
                            return;
                        }

                        if (
                            typeof form.requestSubmit ===
                            "function"
                        ) {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                );
            }
        });
    }

    if (
        document.readyState === "loading"
    ) {
        document.addEventListener(
            "DOMContentLoaded",
            initializeClassEditors
        );
    } else {
        initializeClassEditors();
    }
}());
