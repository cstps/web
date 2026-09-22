<?php

if (
    !isset($admin) ||
    !is_array($admin)
) {
    http_response_code(500);
    exit('관리자 정보가 없습니다.');
}

$page_title =
    isset($page_title)
    ? (string)$page_title
    : '수업나눔 관리';

$active_menu =
    isset($active_menu)
    ? (string)$active_menu
    : '';

header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "style-src 'self'; " .
    "img-src 'self' data:; " .
    "script-src 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "object-src 'none'"
);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        <?php
        echo class_share_escape(
            $page_title
        );
        ?>
        · 수업나눔 관리
    </title>

    <link
        rel="stylesheet"
        href="/class-share/admin/assets/admin.css">
</head>

<body class="admin-body">
    <header class="admin-topbar">
        <a
            class="admin-brand"
            href="/class-share/admin/dashboard.php">

            <span
                class="admin-brand-logo"
                aria-hidden="true">
                나
            </span>

            <span>
                수업나눔 관리
            </span>
        </a>

        <div class="admin-account">
            <span class="admin-account-name">
                <?php
                echo class_share_escape(
                    $admin['display_name']
                );
                ?>
            </span>

            <form
                method="post"
                action="/class-share/admin/logout.php">

                <?php
                echo class_share_admin_csrf_input();
                ?>

                <button
                    type="submit"
                    class="admin-logout-button">
                    로그아웃
                </button>
            </form>
        </div>
    </header>

    <div class="admin-shell">
        <aside class="admin-sidebar">
            <nav aria-label="관리자 메뉴">
                <a
                    class="admin-menu-link<?php
                    echo
                        $active_menu === 'dashboard'
                        ? ' active'
                        : '';
                    ?>"
                    href="/class-share/admin/dashboard.php"
                    <?php
                    if ($active_menu === 'dashboard') {
                        echo 'aria-current="page"';
                    }
                    ?>>

                    대시보드
                </a>

                <?php
                if (
                    class_share_admin_is_super_admin(
                        $admin
                    )
                ) {
                    ?>
                    <a
                        class="admin-menu-link<?php
                        echo
                            $active_menu === 'schools'
                            ? ' active'
                            : '';
                        ?>"
                        href="/class-share/admin/schools.php"
                        <?php
                        if ($active_menu === 'schools') {
                            echo 'aria-current="page"';
                        }
                        ?>>

                        학교 관리
                    </a>
                <?php } ?>
            </nav>
        </aside>

        <main class="admin-content">
            <div class="admin-page-heading">
                <h1>
                    <?php
                    echo class_share_escape(
                        $page_title
                    );
                    ?>
                </h1>
            </div>
