<?php

$contest_list_css_path =
    __DIR__ . "/contest-list.css";

$contest_list_css_version =
    file_exists($contest_list_css_path)
    ? filemtime($contest_list_css_path)
    : "1";

?>

<link
    rel="stylesheet"
    href="contest-list.css?v=<?php
                                echo rawurlencode(
                                    (string)$contest_list_css_version
                                );
                                ?>">