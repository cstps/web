<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_view_admin_users()) {
    http_response_code(403);
    exit("사용자 목록을 볼 권한이 없습니다.");
}

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);

$can_manage_users =
    oj_can_manage_admin_users();

$can_create_users =
    oj_can_create_admin_users();

$current_user_id =
    isset($_SESSION[$OJ_NAME . "_user_id"])
    ? (string)$_SESSION[$OJ_NAME . "_user_id"]
    : "";

$keyword =
    isset($_GET["keyword"])
    ? trim((string)$_GET["keyword"])
    : "";

if (strlen($keyword) > 200) {
    http_response_code(400);
    exit("검색어는 200바이트 이하로 입력해 주세요.");
}

$page =
    isset($_GET["page"])
    ? (int)$_GET["page"]
    : 1;

if ($page < 1) {
    $page = 1;
}

$per_page = 25;

if ($keyword !== "") {
    $search_keyword =
        "%" . $keyword . "%";

    $count_rows =
        pdo_query(
            "
            SELECT COUNT(*) AS ids
            FROM users
            WHERE user_id LIKE ?
               OR nick LIKE ?
               OR email LIKE ?
               OR school LIKE ?
            ",
            $search_keyword,
            $search_keyword,
            $search_keyword,
            $search_keyword
        );
} else {
    $count_rows =
        pdo_query(
            "
            SELECT COUNT(*) AS ids
            FROM users
            "
        );
}

if (
    $count_rows === false ||
    !isset($count_rows[0])
) {
    http_response_code(500);
    exit("사용자 수를 확인할 수 없습니다.");
}

$total_count =
    (int)$count_rows[0]["ids"];

$total_pages =
    max(
        1,
        (int)ceil(
            $total_count /
            $per_page
        )
    );

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset =
    ($page - 1) *
    $per_page;

if ($keyword !== "") {
    $user_rows =
        pdo_query(
            "
            SELECT
                user_id,
                nick,
                school,
                accesstime,
                reg_time,
                defunct,
                (
                    SELECT
                        CASE
                            WHEN aua.action = 'create'
                            THEN aua.actor_user_id
                            ELSE ''
                        END
                    FROM admin_user_audit AS aua
                    WHERE aua.user_id =
                        users.user_id
                    ORDER BY aua.id DESC
                    LIMIT 1
                ) AS creation_actor_id
            FROM users
            WHERE user_id LIKE ?
               OR nick LIKE ?
               OR email LIKE ?
               OR school LIKE ?
            ORDER BY reg_time DESC, user_id DESC
            LIMIT " .
            (int)$offset .
            ", " .
            (int)$per_page,
            $search_keyword,
            $search_keyword,
            $search_keyword,
            $search_keyword
        );
} else {
    $user_rows =
        pdo_query(
            "
            SELECT
                user_id,
                nick,
                school,
                accesstime,
                reg_time,
                defunct,
                (
                    SELECT
                        CASE
                            WHEN aua.action = 'create'
                            THEN aua.actor_user_id
                            ELSE ''
                        END
                    FROM admin_user_audit AS aua
                    WHERE aua.user_id =
                        users.user_id
                    ORDER BY aua.id DESC
                    LIMIT 1
                ) AS creation_actor_id
            FROM users
            ORDER BY reg_time DESC, user_id DESC
            LIMIT " .
            (int)$offset .
            ", " .
            (int)$per_page
        );
}

if ($user_rows === false) {
    http_response_code(500);
    exit("사용자 목록을 불러올 수 없습니다.");
}

$page_group_size = 5;

$page_group_start =
    ((int)(($page - 1) / $page_group_size) *
        $page_group_size) +
    1;

$page_group_end =
    min(
        $total_pages,
        $page_group_start +
        $page_group_size -
        1
    );

$updated =
    isset($_GET["updated"]) &&
    (string)$_GET["updated"] === "1";

$edited =
    isset($_GET["edited"]) &&
    (string)$_GET["edited"] === "1";

$password_updated =
    isset($_GET["password_updated"]) &&
    (string)$_GET["password_updated"] === "1";

$privilege_added =
    isset($_GET["privilege_added"]) &&
    (string)$_GET["privilege_added"] === "1";

$user_deleted =
    isset($_GET["user_deleted"]) &&
    (string)$_GET["user_deleted"] === "1";

$escape =
    function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    };

$page_url =
    function ($target_page) use ($keyword) {
        $query =
            array(
                "page" => (string)$target_page
            );

        if ($keyword !== "") {
            $query["keyword"] =
                $keyword;
        }

        return
            "user_list.php?" .
            http_build_query($query);
    };

$user_edit_url =
    function ($user_id) use (
        $page,
        $keyword
    ) {
        $query =
            array(
                "uid" => (string)$user_id,
                "page" => (string)$page
            );

        if ($keyword !== "") {
            $query["keyword"] =
                $keyword;
        }

        return
            "user_edit.php?" .
            http_build_query($query);
    };

$admin_page_title =
    "사용자 목록";

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
                사용자 목록
            </h1>

            <div class="admin-page-description">
                등록된 사용자를 검색하고 계정 정보를 관리합니다.
            </div>
        </div>

        <?php if ($can_create_users) { ?>
            <a
                href="user_add.php"
                class="admin-btn admin-btn-primary">
                사용자 추가
            </a>
        <?php } ?>

    </div>

    <?php if ($updated) { ?>
        <div
            class="admin-card"
            role="status">
            사용자 계정 상태를 변경했습니다.
        </div>
    <?php } ?>

    <?php if ($edited) { ?>
        <div
            class="admin-card"
            role="status">
            사용자 기본 정보를 수정했습니다.
        </div>
    <?php } ?>

    <?php if ($password_updated) { ?>
        <div
            class="admin-card"
            role="status">
            사용자 비밀번호를 변경했습니다.
        </div>
    <?php } ?>

    <?php if ($privilege_added) { ?>
        <div
            class="admin-card"
            role="status">
            사용자 권한을 저장했습니다.
        </div>
    <?php } ?>

    <?php if ($user_deleted) { ?>

        <div
            class="admin-card"
            role="status">
            사용자를 영구 삭제했습니다.
        </div>

    <?php } ?>

    <div class="admin-card admin-filter-card">

        <form
            action="user_list.php"
            method="get"
            class="admin-search-form">

            <div class="admin-search-input-wrap">

                <input
                    type="text"
                    name="keyword"
                    class="admin-search-input"
                    maxlength="200"
                    value="<?php echo $escape($keyword); ?>"
                    placeholder="사용자 ID, 별명, 이메일, 학교 검색">

                <button
                    type="submit"
                    class="admin-btn admin-btn-primary">
                    검색
                </button>

                <?php if ($keyword !== "") { ?>
                    <a
                        href="user_list.php"
                        class="admin-btn admin-btn-secondary">
                        초기화
                    </a>
                <?php } ?>

            </div>

        </form>

    </div>

    <div class="admin-card admin-table-card">

        <div class="admin-pagination-wrap">

            <div class="admin-pagination-info">
                전체 <?php echo number_format($total_count); ?>명
                ·
                <?php echo $page; ?> /
                <?php echo $total_pages; ?> 페이지
            </div>

            <?php if ($total_pages > 1) { ?>

                <nav
                    class="admin-pagination"
                    aria-label="사용자 목록 페이지">

                    <a
                        class="admin-page-link <?php
                        echo $page <= 1
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(1)
                        );
                        ?>"
                        title="첫 페이지">
                        «
                    </a>

                    <a
                        class="admin-page-link <?php
                        echo $page <= 1
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(
                                max(
                                    1,
                                    $page - 1
                                )
                            )
                        );
                        ?>"
                        title="이전 페이지">
                        ‹
                    </a>

                    <?php
                    for (
                        $page_number =
                            $page_group_start;
                        $page_number <=
                            $page_group_end;
                        $page_number++
                    ) {
                    ?>
                        <a
                            class="admin-page-link <?php
                            echo $page === $page_number
                                ? "active"
                                : "";
                            ?>"
                            href="<?php
                            echo $escape(
                                $page_url(
                                    $page_number
                                )
                            );
                            ?>">
                            <?php echo $page_number; ?>
                        </a>
                    <?php } ?>

                    <a
                        class="admin-page-link <?php
                        echo $page >= $total_pages
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(
                                min(
                                    $total_pages,
                                    $page + 1
                                )
                            )
                        );
                        ?>"
                        title="다음 페이지">
                        ›
                    </a>

                    <a
                        class="admin-page-link <?php
                        echo $page >= $total_pages
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(
                                $total_pages
                            )
                        );
                        ?>"
                        title="마지막 페이지">
                        »
                    </a>

                </nav>

            <?php } ?>

        </div>

        <div class="admin-table-wrap">

            <table class="admin-table admin-user-table">

                <thead>
                    <tr>
                        <th class="admin-user-col-id">
                            사용자 ID
                        </th>
                        <th class="admin-user-col-nick">
                            별명
                        </th>
                        <th class="admin-user-col-school">
                            학교
                        </th>
                        <th class="admin-user-col-date">
                            최근 로그인
                        </th>
                        <th class="admin-user-col-date">
                            가입일
                        </th>
                        <th class="admin-user-col-status">
                            상태
                        </th>
                        <th class="admin-user-col-manage">
                            관리
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($user_rows) === 0) { ?>
                        <tr>
                            <td colspan="7">
                                조건에 맞는 사용자가 없습니다.
                            </td>
                        </tr>
                    <?php } ?>

                    <?php foreach ($user_rows as $user) { ?>
                        <?php
                        $user_id =
                            (string)$user["user_id"];

                        $is_active =
                            (string)$user["defunct"] === "N";

                        $is_current_user =
                            $current_user_id !== "" &&
                            $user_id === $current_user_id;

                        $creation_actor_id =
                            isset(
                                $user["creation_actor_id"]
                            )
                            ? (string)$user[
                                "creation_actor_id"
                            ]
                            : "";

                        $can_delete_user =
                            $can_manage_users ||
                            (
                                $can_create_users &&
                                $current_user_id !== "" &&
                                $creation_actor_id ===
                                    $current_user_id
                            );
                        ?>

                        <tr>

                            <td
                                class="admin-user-text-cell"
                                title="<?php echo $escape($user_id); ?>">

                                <a
                                    href="../userinfo.php?user=<?php
                                    echo rawurlencode($user_id);
                                    ?>">
                                    <?php echo $escape($user_id); ?>
                                </a>

                            </td>

                            <td
                                class="admin-user-text-cell"
                                title="<?php echo $escape($user["nick"]); ?>">
                                <?php echo $escape($user["nick"]); ?>
                            </td>

                            <td
                                class="admin-user-text-cell"
                                title="<?php echo $escape($user["school"]); ?>">
                                <?php echo $escape($user["school"]); ?>
                            </td>

                            <td>
                                <?php
                                echo $user["accesstime"] !== null
                                    ? $escape($user["accesstime"])
                                    : "--";
                                ?>
                            </td>

                            <td>
                                <?php
                                echo $user["reg_time"] !== null
                                    ? $escape($user["reg_time"])
                                    : "--";
                                ?>
                            </td>

                            <td>

                                <?php
                                if (
                                    $can_manage_users &&
                                    !$is_current_user
                                ) {
                                ?>

                                    <form
                                        method="post"
                                        action="user_df_change.php"
                                        class="admin-status-form">

                                        <div class="admin-csrf-fields">
                                            <?php
                                            require(
                                                __DIR__ .
                                                "/../include/set_post_key.php"
                                            );
                                            ?>
                                        </div>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?php echo $escape($user_id); ?>">

                                        <input
                                            type="hidden"
                                            name="return_page"
                                            value="<?php echo $page; ?>">

                                        <input
                                            type="hidden"
                                            name="return_keyword"
                                            value="<?php echo $escape($keyword); ?>">

                                        <button
                                            type="submit"
                                            class="admin-status <?php
                                            echo $is_active
                                                ? "admin-status-public"
                                                : "admin-status-private";
                                            ?>"
                                            title="<?php
                                            echo $is_active
                                                ? "클릭하여 사용 중지"
                                                : "클릭하여 사용 가능으로 변경";
                                            ?>">
                                            <?php
                                            echo $is_active
                                                ? "사용 가능"
                                                : "사용 중지";
                                            ?>
                                        </button>

                                    </form>

                                <?php } else { ?>

                                    <span
                                        class="admin-status <?php
                                        echo $is_active
                                            ? "admin-status-public"
                                            : "admin-status-private";
                                        ?>"
                                        <?php if ($is_current_user) { ?>
                                            title="현재 로그인한 계정"
                                        <?php } ?>>
                                        <?php
                                        echo $is_active
                                            ? "사용 가능"
                                            : "사용 중지";
                                        ?>
                                    </span>

                                <?php } ?>

                            </td>

                            <td class="admin-manage-cell">

                                <div class="admin-row-actions">

                                    <a
                                        href="<?php
                                        echo $escape(
                                            $user_edit_url(
                                                $user_id
                                            )
                                        );
                                        ?>"
                                        class="admin-row-action">
                                        관리
                                    </a>

                                    <?php if ($can_manage_users) { ?>

                                        <a
                                            href="<?php
                                            echo $escape(
                                                "user_privilege_manage.php?" .
                                                http_build_query(
                                                    array(
                                                        "uid" =>
                                                            $user_id,
                                                        "return_page" =>
                                                            (string)$page,
                                                        "return_keyword" =>
                                                            $keyword
                                                    )
                                                )
                                            );
                                            ?>"
                                            class="admin-row-action">
                                            권한
                                        </a>

                                        <?php } ?>

                                        <?php if ($can_delete_user) { ?>

                                        <a
                                            href="<?php
                                            echo $escape(
                                                "user_delete_confirm.php?" .
                                                http_build_query(
                                                    array(
                                                        "uid" =>
                                                            $user_id,
                                                        "return_page" =>
                                                            (string)$page,
                                                        "return_keyword" =>
                                                            $keyword
                                                    )
                                                )
                                            );
                                            ?>"
                                            class="admin-row-action admin-row-action-danger">
                                            삭제
                                        </a>

                                    <?php } ?>

                                </div>

                            </td>

                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
