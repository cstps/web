<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$current_admin =
    class_share_admin_current();

if ($current_admin !== null) {
    header(
        'Location: /class-share/admin/dashboard.php',
        true,
        303
    );

    exit;
}

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

$error_message =
    isset(
        $_SESSION[
            'class_share_admin_login_error'
        ]
    )
    ? (string)$_SESSION[
        'class_share_admin_login_error'
    ]
    : '';

unset(
    $_SESSION[
        'class_share_admin_login_error'
    ]
);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>수업나눔 관리자 로그인</title>

    <link
        rel="stylesheet"
        href="/class-share/admin/assets/admin.css">
</head>

<body>
    <main class="admin-login-page">
        <section
            class="admin-login-card"
            aria-labelledby="login-title">

            <div class="admin-login-brand">
                <div
                    class="admin-login-logo"
                    aria-hidden="true">
                    나
                </div>

                <div>
                    <h1 id="login-title">
                        수업나눔 관리자
                    </h1>

                    <p>
                        OJ 계정과 분리된 관리자 로그인
                    </p>
                </div>
            </div>

            <?php if ($error_message !== '') { ?>
                <div
                    class="admin-error"
                    role="alert">

                    <?php
                    echo class_share_escape(
                        $error_message
                    );
                    ?>
                </div>
            <?php } ?>

            <form
                method="post"
                action="/class-share/admin/login_process.php">

                <?php
                echo class_share_admin_csrf_input();
                ?>

                <div class="admin-field">
                    <label for="login_id">
                        관리자 아이디
                    </label>

                    <input
                        type="text"
                        id="login_id"
                        name="login_id"
                        required
                        minlength="4"
                        maxlength="64"
                        pattern="[A-Za-z0-9._-]+"
                        autocomplete="username"
                        autofocus>
                </div>

                <div class="admin-field">
                    <label for="password">
                        비밀번호
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        maxlength="128"
                        autocomplete="current-password">
                </div>

                <button
                    type="submit"
                    class="admin-login-button">

                    로그인
                </button>
            </form>

            <p class="admin-login-note">
                학교별 수업과 신청내역을 관리하는
                별도 관리자 페이지입니다.
            </p>
        </section>
    </main>
</body>
</html>
