<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$page_title =
    '대시보드';

$active_menu =
    'dashboard';

$role_name =
    class_share_admin_is_super_admin(
        $admin
    )
    ? '최고 관리자'
    : '학교 관리자';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<section class="admin-panel">
    <h2>
        관리자 로그인 정보
    </h2>

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
</section>

<section class="admin-panel admin-panel-spaced">

    <h2>
        수업나눔 관리
    </h2>

    <p class="admin-muted">
        학교를 등록한 후 학교별 행사, 수업,
        공지사항과 참관 신청내역을 관리할 수 있습니다.
    </p>

    <?php
    if (
        class_share_admin_is_super_admin(
            $admin
        )
    ) {
    ?>
        <p class="admin-muted">
            왼쪽의 ‘학교 관리’ 메뉴에서
            첫 번째 학교를 등록할 수 있습니다.
        </p>
    <?php } ?>
</section>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
