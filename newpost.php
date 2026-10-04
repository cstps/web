<?php

require_once("discuss_func.inc.php");

if (
    !isset(
        $_SESSION[$OJ_NAME . '_user_id']
    )
) {
    header("Location: loginpage.php");
    exit;
}

$pid =
    isset($_GET['pid'])
        ? intval($_GET['pid'])
        : '';

require_once(
    "template/$OJ_TEMPLATE/newpost.php"
);
