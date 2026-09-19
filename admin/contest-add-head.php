<?php

$contest_form_css_path =
    __DIR__ . '/contest-form.css';

$contest_form_css_version =
    is_file($contest_form_css_path)
    ? filemtime($contest_form_css_path)
    : '1';

?>

<link
    rel="stylesheet"
    href="contest-form.css?v=<?php
        echo rawurlencode(
            (string)$contest_form_css_version
        );
    ?>">

<?php
require_once(
    __DIR__ . '/tinymce.php'
);
?>
