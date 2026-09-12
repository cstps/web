<?php

if (!isset($admin_css_version)) {

    $admin_css_path =
        __DIR__ . "/admin.css";

    $admin_css_version =
        file_exists($admin_css_path)
        ? filemtime($admin_css_path)
        : "1";
}
?>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1">

<link
    rel="stylesheet"
    href="../include/hoj.css"
    type="text/css">

<?php
require_once(
    __DIR__ .
    "/../template/" .
    $OJ_TEMPLATE .
    "/css.php"
);
?>

<link
    rel="stylesheet"
    href="admin.css?v=<?php
                        echo rawurlencode((string)$admin_css_version);
                        ?>"
    type="text/css">

<script src="../template/syzoj/jquery.min.js"></script>

<script>
    $(function() {

        $("form").each(function() {

            var $form = $(this);

            if ($form.find(".admin-csrf-fields").length > 0) {
                return;
            }

            $("<div>", {
                    "class": "admin-csrf-fields"
                })
                .appendTo($form)
                .load("../csrf.php");
        });

    });
</script>