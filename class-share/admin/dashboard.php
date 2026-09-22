<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

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

$role_name =
    class_share_admin_is_super_admin(
        $admin
    )
    ? '최고 관리자'
    : '학교 관리자';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>수업나눔 관리자</title>

    <link
        rel="stylesheet"
        href="/class-share/admin/assets/admin.css">
</head>

<body>
    <main class="admin-login-page">
        <section
            class="admin-login-card"
            aria-labelledby="dashboard-title">

            <div class="admin-login-brand">
                <div
                    class="admin-login-logo"
                    aria-hidden="true">
                    나
                </div>

                <div>
                    <h1 id="dashboard-title">
                        수업나눔 관리자
                    </h1>

                    <p>
                        관리자 인증이 완료되었습니다.
                    </p>
                </div>
            </div>

            <div class="admin-field">
                <label>관리자 이름</label>

                <p>
                    <?php
                    echo class_share_escape(
                        $admin['display_name']
                    );
                    ?>
                </p>
            </div>

            <div class="admin-field">
                <label>관리자 아이디</label>

                <p>
                    <?php
                    echo class_share_escape(
                        $admin['login_id']
                    );
                    ?>
                </p>
            </div>

            <div class="admin-field">
                <label>권한</label>

                <p>
                    <?php
                    echo class_share_escape(
                        $role_name
                    );
                    ?>
                </p>
            </div>

            <p class="admin-login-note">
                학교·수업·공지·신청자 관리 기능은
                다음 단계에서 연결합니다.
            </p>

            <form
                method="post"
                action="/class-share/admin/logout.php">

                <?php
                echo class_share_admin_csrf_input();
                ?>

                <button
                    type="submit"
                    class="admin-login-button">

                    로그아웃
                </button>
            </form>
        </section>
    </main>
</body>
</html>
