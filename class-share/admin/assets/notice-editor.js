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
                "image link lists preview searchreplace " +
                "table wordcount",

            block_formats:
                "문단=p; 제목 2=h2; 제목 3=h3; " +
                "제목 4=h4; 인용=blockquote; 코드=pre",
            toolbar:
                "undo redo | blocks | " +
                "bold italic underline strikethrough | " +
                "alignleft aligncenter alignright | " +
                "bullist numlist | " +
                "blockquote link image table hr | " +
                "removeformat | code fullscreen preview",

            formats: {
                // 이미지를 포함하는 문단에 정렬을 적용합니다.
                alignleft: {
                    selector: "p,h2,h3,h4,li,blockquote",
                    styles: { textAlign: "left" }
                },

                aligncenter: {
                    selector: "p,h2,h3,h4,li,blockquote",
                    styles: { textAlign: "center" }
                },

                alignright: {
                    selector: "p,h2,h3,h4,li,blockquote",
                    styles: { textAlign: "right" }
                },

                underline: {
                    inline: "u"
                },

                strikethrough: {
                    inline: "s"
                }
            },

            valid_elements:
                "p[style|class],br,strong,b,em,i,u,s,sub,sup," +
                "h2[style|class],h3[style|class],h4[style|class],ul,ol," +
                "li[style|class],blockquote[style|class]," +
                "a[href|title|target|rel]," +
                "table,thead,tbody,tfoot,tr," +
                "th[colspan|rowspan|scope]," +
                "td[colspan|rowspan],caption," +
                "pre,code,hr,img[src|alt|title|width|height|class]",

            invalid_elements:
                "script,style,iframe,frame,frameset," +
                "object,embed,applet,svg,math," +
                "form,input,button,textarea,select,option," +
                "video,audio,source,link,meta,base",

            entity_encoding: "raw",
            relative_urls: false,
            remove_script_host: true,
            convert_urls: true,

            content_style:
                "img { max-width: 100%; height: auto; } " +
                ".cs-notice-align-left { text-align: left; } " +
                ".cs-notice-align-center { text-align: center; } " +
                ".cs-notice-align-right { text-align: right; }",
            image_title: true,
            automatic_uploads: true,
            paste_data_images: false,

            // 행사 번호와 관리자 보안키를 함께 전송합니다.
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    var textarea = document.getElementById("content");
                    var form = textarea ? textarea.form : null;
                    var eventInput = form
                        ? form.elements.namedItem("event_id")
                        : null;
                    var csrfInput = form
                        ? form.elements.namedItem("csrf_token")
                        : null;

                    if (!eventInput || !csrfInput) {
                        reject("행사 정보 또는 보안키가 없습니다. 새로고침해 주세요.");
                        return;
                    }

                    if (blobInfo.blob().size > 5 * 1024 * 1024) {
                        reject("이미지는 5MB 이하로 올려 주세요.");
                        return;
                    }

                    var data = new FormData();
                    data.append("file", blobInfo.blob(), blobInfo.filename());
                    data.append("event_id", eventInput.value);
                    data.append("csrf_token", csrfInput.value);

                    var xhr = new XMLHttpRequest();
                    xhr.open(
                        "POST",
                        "/class-share/admin/notice_image_upload.php"
                    );
                    xhr.timeout = 60000;

                    xhr.upload.onprogress = function (event) {
                        if (event.lengthComputable) {
                            progress(event.loaded / event.total * 100);
                        }
                    };

                    xhr.onerror = function () {
                        reject("이미지 전송 중 연결이 끊겼습니다.");
                    };

                    xhr.ontimeout = function () {
                        reject("이미지 전송 시간이 초과되었습니다.");
                    };

                    xhr.onload = function () {
                        var response;

                        try {
                            response = JSON.parse(xhr.responseText);
                        } catch (error) {
                            reject(
                                "업로드 응답을 확인할 수 없습니다. "
                                + "관리자 로그인 상태를 확인해 주세요."
                            );
                            return;
                        }

                        if (xhr.status < 200 || xhr.status >= 300) {
                            reject(
                                response && typeof response.error === "string"
                                    ? response.error
                                    : "이미지를 올리지 못했습니다."
                            );
                            return;
                        }

                        if (
                            !response ||
                            typeof response.location !== "string" ||
                            !/^\/class-share\/uploads\/notices\/[1-9][0-9]*\/[a-f0-9]{32}\.(jpg|png|gif|webp)$/.test(
                                response.location
                            )
                        ) {
                            reject("이미지 저장 주소가 올바르지 않습니다.");
                            return;
                        }

                        resolve(response.location);
                    };

                    xhr.send(data);
                });
            },

            setup: function (editor) {
                // 이미지 업로드를 끝낸 다음 공지를 저장합니다.
                editor.on("init", function () {
                    var form = editor.getElement().form;
                    if (!form) {
                        return;
                    }

                    var uploading = false;
                    var readyToSubmit = false;

                    form.addEventListener("submit", function (event) {
                        if (readyToSubmit) {
                            editor.save();
                            return;
                        }

                        event.preventDefault();

                        if (uploading) {
                            return;
                        }

                        uploading = true;
                        var submitter = event.submitter;

                        editor.uploadImages().then(function () {
                            var images = editor.getBody().querySelectorAll("img");

                            for (var index = 0; index < images.length; index++) {
                                var src = images[index].getAttribute("src") || "";
                                if (/^(blob:|data:)/i.test(src)) {
                                    throw new Error(
                                        "업로드되지 않은 이미지가 있습니다. "
                                        + "해당 이미지를 다시 올려 주세요."
                                    );
                                }
                            }

                            editor.save();
                            readyToSubmit = true;

                            if (!form.reportValidity()) {
                                uploading = false;
                                return;
                            }

                            // 제출 이벤트를 다시 발생시키지 않고 POST로 저장합니다.
                            HTMLFormElement.prototype.submit.call(form);

                            readyToSubmit = false;
                            uploading = false;
                        }).catch(function (error) {
                            uploading = false;
                            readyToSubmit = false;
                            window.alert(
                                error && error.message
                                    ? error.message
                                    : String(error || "이미지를 올리지 못했습니다.")
                            );
                        });
                    });
                });

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
