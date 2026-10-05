<?php

require_once(
    dirname(__FILE__) .
    "/include/db_info.inc.php"
);

$footer_file =
    dirname(__FILE__) .
    "/template/" .
    $OJ_TEMPLATE .
    "/footer.php";

if (file_exists($footer_file)) {
    require_once($footer_file);
}

?>
