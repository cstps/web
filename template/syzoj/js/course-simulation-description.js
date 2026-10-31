(function () {
    'use strict';
    var field = document.getElementById('simulation_description');
    if (!field || !window.tinymce) return;
    tinymce.init({
        selector: '#simulation_description',
        license_key: 'gpl',
        height: 340,
        menubar: false,
        branding: false,
        promotion: false,
        plugins: 'autolink code fullscreen image link lists preview table',
        toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | removeformat | code fullscreen preview',
        paste_data_images: false,
        relative_urls: false,
        remove_script_host: true,
        content_style: 'body{font-family:sans-serif;font-size:15px;line-height:1.65}img{max-width:100%;height:auto}',
        setup: function (editor) {
            editor.on('change input undo redo', function () { editor.save(); });
        }
    });
    document.addEventListener('submit', function (event) {
        if (event.target === field.form) tinymce.triggerSave();
    }, true);
})();
