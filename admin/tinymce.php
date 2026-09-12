<?php
// Shared TinyMCE configuration for HUSTOJ administrator forms.
?>
<script src="../tinymce/tinymce.min.js?v=8.9.0"></script>
<script>
    (function() {
        "use strict";

        function getPostKey() {
            var input = document.querySelector('input[name="postkey"]');
            return input ? input.value : "";
        }

        function uploadImage(blobInfo, progress) {
            return new Promise(function(resolve, reject) {
                var postKey = getPostKey();

                if (!postKey) {
                    reject("보안 키를 찾을 수 없습니다. 페이지를 새로 고친 뒤 다시 시도하세요.");
                    return;
                }

                var xhr = new XMLHttpRequest();
                xhr.open("POST", "tinymce_upload_image.php", true);
                xhr.withCredentials = true;

                xhr.upload.onprogress = function(event) {
                    if (event.lengthComputable) {
                        progress(event.loaded / event.total * 100);
                    }
                };

                xhr.onerror = function() {
                    reject("이미지 업로드 중 네트워크 오류가 발생했습니다.");
                };

                xhr.onload = function() {
                    var response;

                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (error) {
                        reject("서버가 올바른 업로드 응답을 반환하지 않았습니다.");
                        return;
                    }

                    if (xhr.status < 200 || xhr.status >= 300) {
                        reject(response.error || "이미지를 업로드하지 못했습니다.");
                        return;
                    }

                    if (!response.location || typeof response.location !== "string") {
                        reject("업로드된 이미지 주소가 응답에 없습니다.");
                        return;
                    }

                    resolve(response.location);
                };

                var formData = new FormData();
                formData.append("file", blobInfo.blob(), blobInfo.filename());
                formData.append("postkey", postKey);
                xhr.send(formData);
            });
        }

        function initializeEditors() {
            if (typeof window.tinymce === "undefined") {
                console.error("TinyMCE를 불러오지 못했습니다.");
                return;
            }

            window.tinymce.init({
                selector: "textarea.tinymce-editor",
                license_key: "gpl",
                height: 320,
                min_height: 240,
                menubar: false,
                branding: false,
                promotion: false,
                resize: true,
                toolbar_mode: "sliding",
                plugins: "autolink charmap code fullscreen image link lists preview searchreplace table wordcount",
                toolbar: "undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | removeformat | code fullscreen preview",
                automatic_uploads: true,
                paste_data_images: true,
                images_file_types: "jpg,jpeg,png,gif,webp",
                images_upload_handler: uploadImage,
                image_advtab: true,
                image_caption: true,
                relative_urls: false,
                remove_script_host: true,
                convert_urls: true,
                content_style: "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 15px; line-height: 1.65; } img { max-width: 100%; height: auto; }",
                setup: function(editor) {
                    editor.on("keydown", function(event) {
                        if (
                            !(event.ctrlKey || event.metaKey) ||
                            event.key !== "Enter"
                        ) {
                            return;
                        }

                        event.preventDefault();
                        editor.save();

                        var form = editor.getElement().form;
                        if (!form) {
                            return;
                        }

                        if (typeof form.requestSubmit === "function") {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    });
                }
            });
        }

        function waitForImageUploads(event) {
            var form = event.target;

            if (
                !form ||
                !form.querySelector ||
                !form.querySelector("textarea.tinymce-editor")
            ) {
                return;
            }

            if (form.getAttribute("data-tinymce-submit-ready") === "1") {
                form.removeAttribute("data-tinymce-submit-ready");
                return;
            }

            var editors = window.tinymce ?
                window.tinymce.get().filter(function(editor) {
                    return editor.getElement().form === form;
                }) : [];

            if (editors.length === 0) {
                return;
            }

            event.preventDefault();

            var submitter = event.submitter || null;
            var uploadTasks = editors.map(function(editor) {
                return editor.uploadImages();
            });

            Promise.all(uploadTasks)
                .then(function() {
                    editors.forEach(function(editor) {
                        editor.save();
                    });

                    form.setAttribute("data-tinymce-submit-ready", "1");

                    if (typeof form.requestSubmit === "function") {
                        if (submitter) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                    } else {
                        form.submit();
                    }
                })
                .catch(function(error) {
                    var message = error && error.message ?
                        error.message :
                        String(error || "이미지를 업로드하지 못했습니다.");
                    window.alert(message);
                });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", initializeEditors);
        } else {
            initializeEditors();
        }

        document.addEventListener("submit", waitForImageUploads, true);
    })();
</script>