<?php

require_once(__DIR__ . "/admin-init.php");

if (
    !isset($admin_page_title) ||
    trim((string)$admin_page_title) === ""
) {
    $admin_page_title = "관리자";
}

if (!isset($admin_active_menu)) {
    $admin_active_menu = "";
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>

    <?php
    require(__DIR__ . "/admin-head.php");
    ?>

    <?php
    if (
        isset($admin_page_head_file) &&
        is_string($admin_page_head_file) &&
        is_file($admin_page_head_file)
    ) {
        require_once($admin_page_head_file);
    }
    ?>
    
    <title><?php
            echo htmlspecialchars(
                $admin_page_title . " - 1024.kr 관리자",
                ENT_QUOTES,
                "UTF-8"
            );
            ?></title>

</head>

<body class="admin-layout-page">

    <?php
    require(__DIR__ . "/admin-bar.php");
    ?>

    <div class="admin-layout">

        <aside
            class="admin-sidebar"
            id="admin-sidebar"
            aria-label="관리자 메뉴">

            <?php
            require(__DIR__ . "/menu2.php");
            ?>

        </aside>

        <main
            class="admin-main"
            id="admin-main-content"
            tabindex="-1">