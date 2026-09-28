<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_view_admin_users()) {
    http_response_code(403);
    exit("사용자 정보를 볼 권한이 없습니다.");
}

$user_id =
    isset($_GET["uid"])
    ? trim((string)$_GET["uid"])
    : "";

$return_page =
    isset($_GET["page"])
    ? (int)$_GET["page"]
    : 1;

$return_keyword =
    isset($_GET["keyword"])
    ? trim((string)$_GET["keyword"])
    : "";

if (
    $user_id === "" ||
    strlen($user_id) > 48
) {
    http_response_code(400);
    exit("사용자 ID가 올바르지 않습니다.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

$user_rows =
    pdo_query(
        "
        SELECT
            users.user_id,
            users.nick,
            users.email,
            users.school,
            users.defunct,
            EXISTS (
                SELECT 1
                FROM privilege
                WHERE privilege.user_id = users.user_id
                  AND privilege.rightstr IN (
                      'administrator',
                      'contest_creator',
                      'problem_editor',
                      'system_config_manager'
                  )
            ) AS protected_password
        FROM users
        WHERE users.user_id = ?
        LIMIT 1
        ",
        $user_id
    );

if ($user_rows === false) {
    http_response_code(500);
    exit("사용자 정보를 불러올 수 없습니다.");
}

if (!isset($user_rows[0])) {
    http_response_code(404);
    exit("사용자를 찾을 수 없습니다.");
}

$user =
    $user_rows[0];

$can_manage_users =
    oj_can_manage_admin_users();

$is_admin =
    oj_is_admin();

$is_protected_password =
    (int)$user["protected_password"] === 1;

$can_change_password =
    $is_admin ||
    !$is_protected_password;

$return_query =
    array(
        "page" => (string)$return_page
    );

if ($return_keyword !== "") {
    $return_query["keyword"] =
        $return_keyword;
}

$return_url =
    "user_list.php?" .
    http_build_query($return_query);

$escape =
    function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    };

$admin_page_title =
    "사용자 관리";

$admin_active_menu =
    "user_list";

require_once(
    __DIR__ . "/admin-layout-start.php"
);
?>

<div class="admin-page">

    <div class="admin-page-header">

        <div>
            <h1 class="admin-page-title">
                사용자 관리
            </h1>

            <div class="admin-page-description">
                사용자 정보와 비밀번호를 권한에 따라 관리합니다.
            </div>
        </div>

        <a
            href="<?php echo $escape($return_url); ?>"
            class="admin-btn admin-btn-secondary">
            사용자 목록
        </a>

    </div>

    <div class="admin-card">

        <div class="admin-form-grid-2">

            <div class="admin-form-field">
                <label class="admin-form-label">
                    사용자 ID
                </label>

                <input
                    type="text"
                    class="admin-form-input"
                    value="<?php echo $escape($user["user_id"]); ?>"
                    readonly>
            </div>

            <div class="admin-form-field">
                <label class="admin-form-label">
                    이메일
                </label>

                <input
                    type="text"
                    class="admin-form-input"
                    value="<?php echo $escape($user["email"]); ?>"
                    readonly>
            </div>

        </div>

        <div class="admin-form-field">
            <label class="admin-form-label">
                계정 상태
            </label>

            <span class="admin-status <?php
                echo (string)$user["defunct"] === "N"
                    ? "admin-status-public"
                    : "admin-status-private";
            ?>">
                <?php
                echo (string)$user["defunct"] === "N"
                    ? "사용 가능"
                    : "사용 중지";
                ?>
            </span>
        </div>

    </div>

    <?php if ($can_manage_users) { ?>

        <form
            method="post"
            action="user_update.php">

            <?php
            require(
                __DIR__ .
                "/../include/set_post_key.php"
            );
            ?>

            <input
                type="hidden"
                name="user_id"
                value="<?php echo $escape($user["user_id"]); ?>">

            <input
                type="hidden"
                name="return_page"
                value="<?php echo $return_page; ?>">

            <input
                type="hidden"
                name="return_keyword"
                value="<?php echo $escape($return_keyword); ?>">

            <div class="admin-form-card">

                <div class="admin-form-card-header">

                    <span class="admin-form-step">
                        1
                    </span>

                    <div>
                        <div class="admin-form-card-title">
                            기본 정보
                        </div>

                        <div class="admin-form-card-desc">
                            별명과 학교 정보를 수정합니다.
                        </div>
                    </div>

                </div>

                <div class="admin-form-grid-2">

                    <div class="admin-form-field">

                        <label
                            class="admin-form-label"
                            for="user-nick">
                            별명
                        </label>

                        <input
                            type="text"
                            id="user-nick"
                            name="nick"
                            class="admin-form-input"
                            maxlength="20"
                            value="<?php echo $escape($user["nick"]); ?>">

                    </div>

                    <div class="admin-form-field">

                        <label
                            class="admin-form-label"
                            for="user-school">
                            학교
                        </label>

                        <input
                            type="text"
                            id="user-school"
                            name="school"
                            class="admin-form-input"
                            maxlength="20"
                            value="<?php echo $escape($user["school"]); ?>">

                    </div>

                </div>

            </div>

            <div class="admin-form-actions">

                <a
                    href="<?php echo $escape($return_url); ?>"
                    class="admin-btn admin-btn-secondary">
                    취소
                </a>

                <button
                    type="submit"
                    class="admin-btn admin-btn-primary">
                    기본 정보 저장
                </button>

            </div>

        </form>

    <?php } ?>

    <?php if ($can_change_password) { ?>

        <form
            method="post"
            action="user_password_update.php"
            autocomplete="off">

            <?php
            require(
                __DIR__ .
                "/../include/set_post_key.php"
            );
            ?>

            <input
                type="hidden"
                name="user_id"
                value="<?php echo $escape($user["user_id"]); ?>">

            <input
                type="hidden"
                name="return_page"
                value="<?php echo $return_page; ?>">

            <input
                type="hidden"
                name="return_keyword"
                value="<?php echo $escape($return_keyword); ?>">

            <div class="admin-form-card">

                <div class="admin-form-card-header">

                    <span class="admin-form-step">
                        <?php echo $can_manage_users ? "2" : "1"; ?>
                    </span>

                    <div>
                        <div class="admin-form-card-title">
                            비밀번호 변경
                        </div>

                        <div class="admin-form-card-desc">
                            새 비밀번호를 두 번 입력합니다.
                        </div>
                    </div>

                </div>

                <div class="admin-form-grid-2">

                    <div class="admin-form-field">

                        <label
                            class="admin-form-label"
                            for="user-password">
                            새 비밀번호
                        </label>

                        <input
                            type="password"
                            id="user-password"
                            name="password"
                            class="admin-form-input"
                            maxlength="200"
                            autocomplete="new-password"
                            required>

                    </div>

                    <div class="admin-form-field">

                        <label
                            class="admin-form-label"
                            for="user-password-confirm">
                            새 비밀번호 확인
                        </label>

                        <input
                            type="password"
                            id="user-password-confirm"
                            name="password_confirm"
                            class="admin-form-input"
                            maxlength="200"
                            autocomplete="new-password"
                            required>

                    </div>

                </div>

            </div>

            <div class="admin-form-actions">

                <a
                    href="<?php echo $escape($return_url); ?>"
                    class="admin-btn admin-btn-secondary">
                    취소
                </a>

                <button
                    type="submit"
                    class="admin-btn admin-btn-primary">
                    비밀번호 변경
                </button>

            </div>

        </form>

    <?php } else { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    1
                </span>

                <div>
                    <div class="admin-form-card-title">
                        비밀번호 변경 제한
                    </div>

                    <div class="admin-form-card-desc">
                        이 사용자는 보호 권한을 가지고 있습니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">
                administrator, contest_creator, problem_editor 또는
                system_config_manager 권한 사용자의 비밀번호는
                최고관리자만 변경할 수 있습니다.
            </div>

        </div>

    <?php } ?>

</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
