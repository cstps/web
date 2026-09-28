<?php

require_once(
    __DIR__ . "/admin-init.php"
);

$is_admin_user_manager =
    oj_can_manage_admin_users();

$can_create_users =
    oj_can_create_admin_users();

if (
    !$is_admin_user_manager &&
    !$can_create_users
) {
    http_response_code(403);
    exit("사용자를 삭제할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/user_delete_functions.php"
);

$user_id =
    isset($_GET["uid"])
    ? trim((string)$_GET["uid"])
    : "";

$return_page =
    isset($_GET["return_page"])
    ? (int)$_GET["return_page"]
    : 1;

$return_keyword =
    isset($_GET["return_keyword"])
    ? trim((string)$_GET["return_keyword"])
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
            user_id,
            nick,
            school,
            email,
            defunct,
            reg_time,
            accesstime,
            submit,
            solved
        FROM users
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

if ($user_rows === false) {
    http_response_code(500);
    exit("사용자를 확인할 수 없습니다.");
}

if (!isset($user_rows[0])) {
    http_response_code(404);
    exit("사용자를 찾을 수 없습니다.");
}

$user =
    $user_rows[0];

$blockers =
    oj_admin_user_delete_blockers(
        $user_id
    );

if ($blockers === false) {
    http_response_code(500);
    exit("사용자 활동 기록을 확인할 수 없습니다.");
}

$has_administrator_right =
    oj_admin_user_has_administrator_right(
        $user_id
    );

if ($has_administrator_right === null) {
    http_response_code(500);
    exit("사용자 권한을 확인할 수 없습니다.");
}

$current_user_id =
    isset(
        $_SESSION[
            $OJ_NAME . "_user_id"
        ]
    )
    ? (string)$_SESSION[
        $OJ_NAME . "_user_id"
    ]
    : "";

$creation_actor_id =
    "";

$has_protected_delete_right =
    false;

if (!$is_admin_user_manager) {
    $creation_actor_id =
        oj_admin_user_creation_actor(
            $user_id
        );

    if ($creation_actor_id === null) {
        http_response_code(500);
        exit("사용자 생성 이력을 확인할 수 없습니다.");
    }

    $has_protected_delete_right =
        oj_admin_user_has_protected_delete_right(
            $user_id
        );

    if ($has_protected_delete_right === null) {
        http_response_code(500);
        exit("사용자 특별권한을 확인할 수 없습니다.");
    }
}

$is_current_user =
    $user_id === $current_user_id;

$is_own_created_user =
    $current_user_id !== "" &&
    $creation_actor_id === $current_user_id;

$has_delete_scope =
    $is_admin_user_manager ||
    (
        $can_create_users &&
        $is_own_created_user &&
        !$has_protected_delete_right
    );

$can_delete =
    $has_delete_scope &&
    !$is_current_user &&
    !$has_administrator_right &&
    count($blockers) === 0;

$list_query =
    array(
        "page" =>
            (string)$return_page
    );

if ($return_keyword !== "") {
    $list_query["keyword"] =
        $return_keyword;
}

$list_url =
    "user_list.php?" .
    http_build_query($list_query);

$escape =
    function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    };

$admin_page_title =
    "사용자 삭제 확인";

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
                사용자 삭제 확인
            </h1>

            <div class="admin-page-description">
                활동 기록이 없는 오등록 계정만 영구 삭제할 수 있습니다.
            </div>
        </div>

        <a
            href="<?php echo $escape($list_url); ?>"
            class="admin-btn admin-btn-secondary">
            사용자 목록
        </a>

    </div>

    <div class="admin-form-card">

        <div class="admin-form-card-header">

            <span class="admin-form-step">
                1
            </span>

            <div>
                <div class="admin-form-card-title">
                    계정 정보
                </div>

                <div class="admin-form-card-desc">
                    삭제하려는 사용자가 맞는지 확인합니다.
                </div>
            </div>

        </div>

        <div class="admin-form-grid-2">

            <div class="admin-form-field">
                <div class="admin-form-label">
                    사용자 ID
                </div>

                <div>
                    <?php echo $escape($user["user_id"]); ?>
                </div>
            </div>

            <div class="admin-form-field">
                <div class="admin-form-label">
                    별명
                </div>

                <div>
                    <?php echo $escape($user["nick"]); ?>
                </div>
            </div>

            <div class="admin-form-field">
                <div class="admin-form-label">
                    학교
                </div>

                <div>
                    <?php
                    echo $user["school"] !== ""
                        ? $escape($user["school"])
                        : "-";
                    ?>
                </div>
            </div>

            <div class="admin-form-field">
                <div class="admin-form-label">
                    계정 상태
                </div>

                <div>
                    <?php
                    echo (string)$user["defunct"] === "N"
                        ? "사용 가능"
                        : "사용 중지";
                    ?>
                </div>
            </div>

            <div class="admin-form-field">
                <div class="admin-form-label">
                    제출 / 해결
                </div>

                <div>
                    <?php
                    echo number_format(
                        (int)$user["submit"]
                    );
                    ?>
                    /
                    <?php
                    echo number_format(
                        (int)$user["solved"]
                    );
                    ?>
                </div>
            </div>

            <div class="admin-form-field">
                <div class="admin-form-label">
                    가입일시
                </div>

                <div>
                    <?php
                    echo $escape(
                        $user["reg_time"]
                    );
                    ?>
                </div>
            </div>

        </div>

    </div>

    <?php if ($is_current_user) { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-title">
                삭제할 수 없습니다.
            </div>

            <div class="admin-form-help">
                현재 로그인한 자기 계정은 삭제할 수 없습니다.
            </div>

        </div>

    <?php } elseif ($has_administrator_right) { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-title">
                삭제할 수 없습니다.
            </div>

            <div class="admin-form-help">
                활성 최고관리자 권한이 있는 계정은 삭제할 수 없습니다.
                먼저 권한 정책을 확인해 주세요.
            </div>

        </div>

    <?php } elseif (count($blockers) > 0) { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    !
                </span>

                <div>
                    <div class="admin-form-card-title">
                        활동 기록이 있어 삭제할 수 없습니다.
                    </div>

                    <div class="admin-form-card-desc">
                        계정을 영구 삭제하지 말고 사용자 목록에서
                        사용 중지 상태로 변경해 주세요.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">

                <div class="admin-form-label">
                    확인된 활동 기록
                </div>

                <ul>

                    <?php foreach ($blockers as $blocker) { ?>

                        <li>
                            <?php echo $escape($blocker); ?>
                        </li>

                    <?php } ?>

                </ul>

            </div>

        </div>

    <?php } elseif (
        !$is_admin_user_manager &&
        !$is_own_created_user
    ) { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-title">
                삭제할 수 없습니다.
            </div>

            <div class="admin-form-help">
                비밀번호 관리자는 자신이 사용자 추가 화면에서
                직접 추가한 계정만 삭제할 수 있습니다.
            </div>

        </div>

    <?php } elseif (
        !$is_admin_user_manager &&
        $has_protected_delete_right
    ) { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-title">
                삭제할 수 없습니다.
            </div>

            <div class="admin-form-help">
                활성 특별권한이 있는 계정은 비밀번호 관리자가
                삭제할 수 없습니다. 최고관리자가 권한을 확인해 주세요.
            </div>

        </div>

    <?php } elseif ($can_delete) { ?>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    2
                </span>

                <div>
                    <div class="admin-form-card-title">
                        영구 삭제
                    </div>

                    <div class="admin-form-card-desc">
                        삭제한 계정은 복구할 수 없습니다.
                        확인을 위해 사용자 ID를 다시 입력합니다.
                    </div>
                </div>

            </div>

            <form
                method="post"
                action="user_delete.php">

                <?php
                require(
                    __DIR__ .
                    "/../include/set_post_key.php"
                );
                ?>

                <input
                    type="hidden"
                    name="user_id"
                    value="<?php echo $escape($user_id); ?>">

                <input
                    type="hidden"
                    name="return_page"
                    value="<?php echo $return_page; ?>">

                <input
                    type="hidden"
                    name="return_keyword"
                    value="<?php echo $escape($return_keyword); ?>">

                <div class="admin-form-field">

                    <label
                        class="admin-form-label"
                        for="confirm-user-id">
                        사용자 ID 재입력
                    </label>

                    <input
                        type="text"
                        id="confirm-user-id"
                        name="confirm_user_id"
                        class="admin-form-input"
                        maxlength="48"
                        autocomplete="off"
                        required>

                    <div class="admin-form-help">
                        <?php echo $escape($user_id); ?>
                        를 정확히 입력해 주세요.
                    </div>

                </div>

                <div class="admin-form-actions">

                    <a
                        href="<?php echo $escape($list_url); ?>"
                        class="admin-btn admin-btn-secondary">
                        취소
                    </a>

                    <button
                        type="submit"
                        class="admin-btn admin-btn-danger">
                        사용자 영구 삭제
                    </button>

                </div>

            </form>

        </div>

    <?php } ?>

</div>

<?php

require(
    __DIR__ . "/admin-layout-end.php"
);

?>
