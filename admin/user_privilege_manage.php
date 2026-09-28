<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("사용자 권한을 관리할 권한이 없습니다.");
}

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
            defunct
        FROM users
        WHERE user_id = ?
        LIMIT 1
        ",
        $user_id
    );

if (
    $user_rows === false ||
    !isset($user_rows[0])
) {
    http_response_code(404);
    exit("사용자를 찾을 수 없습니다.");
}

$user =
    $user_rows[0];

$privilege_rows =
    pdo_query(
        "
        SELECT
            rightstr,
            valuestr
        FROM privilege
        WHERE user_id = ?
          AND defunct = 'N'
          AND (
              rightstr IN (
                  'administrator',
                  'problem_editor',
                  'source_browser',
                  'contest_creator',
                  'password_setter',
                  'system_config_manager',
                  'printer',
                  'balloon'
              )
              OR rightstr REGEXP '^[cs][0-9]+$'
          )
        ORDER BY rightstr
        ",
        $user_id
    );

if ($privilege_rows === false) {
    http_response_code(500);
    exit("사용자 권한을 불러올 수 없습니다.");
}

$privilege_labels =
    array(
        "administrator" =>
            "최고관리자",
        "problem_editor" =>
            "문제 관리자",
        "source_browser" =>
            "전체 소스 열람",
        "contest_creator" =>
            "대회 관리자",
        "password_setter" =>
            "비밀번호 관리자",
        "system_config_manager" =>
            "시스템 설정 관리자",
        "printer" =>
            "출력 권한",
        "balloon" =>
            "풍선 권한"
    );

$privilege_label =
    function ($rightstr) use (
        $privilege_labels
    ) {
        if (
            isset(
                $privilege_labels[$rightstr]
            )
        ) {
            return
                $privilege_labels[$rightstr];
        }

        if (
            preg_match(
                "/^c([0-9]+)$/",
                $rightstr,
                $matches
            )
        ) {
            return
                "대회 " .
                $matches[1] .
                " 참가";
        }

        if (
            preg_match(
                "/^s([0-9]+)$/",
                $rightstr,
                $matches
            )
        ) {
            return
                "문제 " .
                $matches[1] .
                " 소스 보기";
        }

        return $rightstr;
    };

$special_privileges =
    array();

$contest_privileges =
    array();

$source_privileges =
    array();

foreach ($privilege_rows as $privilege) {
    $rightstr =
        (string)$privilege["rightstr"];

    if (
        preg_match(
            "/^c[0-9]+$/",
            $rightstr
        )
    ) {
        $contest_privileges[] =
            $privilege;
    } elseif (
        preg_match(
            "/^s[0-9]+$/",
            $rightstr
        )
    ) {
        $source_privileges[] =
            $privilege;
    } else {
        $special_privileges[] =
            $privilege;
    }
}

$privilege_groups =
    array(
        array(
            "title" => "특별 권한",
            "rows" =>
                $special_privileges
        ),
        array(
            "title" => "대회 참가 권한",
            "rows" =>
                $contest_privileges
        ),
        array(
            "title" => "문제 소스 보기 권한",
            "rows" =>
                $source_privileges
        )
    );

$privilege_removed =
    isset($_GET["privilege_removed"]) &&
    (string)$_GET["privilege_removed"] === "1";

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
    "사용자 권한 관리";

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
                사용자 권한 관리
            </h1>

            <div class="admin-page-description">
                사용자에게 특별 권한, 대회 참가 권한 또는
                문제 소스 보기 권한을 부여합니다.
            </div>
        </div>

        <a
            href="<?php echo $escape($list_url); ?>"
            class="admin-btn admin-btn-secondary">
            사용자 목록
        </a>

    </div>

    <?php if ($privilege_removed) { ?>

        <div
            class="admin-card"
            role="status">
            사용자 권한을 제거했습니다.
        </div>

    <?php } ?>

    <div class="admin-card">

        <div class="admin-form-grid-2">

            <div>
                <div class="admin-form-label">
                    사용자 ID
                </div>

                <div>
                    <?php echo $escape($user["user_id"]); ?>
                </div>
            </div>

            <div>
                <div class="admin-form-label">
                    별명
                </div>

                <div>
                    <?php
                    echo $escape(
                        $user["nick"]
                    );
                    ?>
                </div>
            </div>

        </div>

    </div>

    <div class="admin-form-card">

        <div class="admin-form-card-header">

            <span class="admin-form-step">
                1
            </span>

            <div>
                <div class="admin-form-card-title">
                    현재 관리 가능한 권한
                </div>

                <div class="admin-form-card-desc">
                    이 화면에서 추가할 수 있는 활성 권한입니다.
                </div>
            </div>

        </div>

        <?php if (count($privilege_rows) === 0) { ?>

            <div class="admin-form-field">
                현재 표시할 활성 권한이 없습니다.
            </div>

        <?php } else { ?>

            <div class="admin-privilege-groups">

                <?php foreach ($privilege_groups as $group) { ?>

                    <section class="admin-privilege-group">

                        <div class="admin-privilege-group-header">

                            <span>
                                <?php
                                echo $escape(
                                    $group["title"]
                                );
                                ?>
                            </span>

                            <span class="admin-privilege-count">
                                <?php
                                echo number_format(
                                    count($group["rows"])
                                );
                                ?>개
                            </span>

                        </div>

                        <div class="admin-privilege-scroll">

                            <?php if (count($group["rows"]) === 0) { ?>

                                <div class="admin-privilege-empty">
                                    등록된 권한이 없습니다.
                                </div>

                            <?php } else { ?>

                                <?php foreach ($group["rows"] as $privilege) { ?>

                                    <div class="admin-privilege-item">

                                        <span class="admin-privilege-name">
                                            <?php
                                            echo $escape(
                                                $privilege_label(
                                                    (string)$privilege["rightstr"]
                                                )
                                            );
                                            ?>
                                        </span>

                                        <div class="admin-privilege-item-actions">

                                            <code class="admin-privilege-code">
                                                <?php
                                                echo $escape(
                                                    $privilege["rightstr"]
                                                );
                                                ?>
                                            </code>

                                            <form
                                                method="post"
                                                action="user_privilege_delete.php"
                                                class="admin-privilege-remove-form"
                                                onsubmit="return confirm('이 권한을 제거하시겠습니까?');">

                                                <?php
                                                require(
                                                    __DIR__ .
                                                    "/../include/set_post_key.php"
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?php
                                                    echo $escape($user_id);
                                                    ?>">

                                                <input
                                                    type="hidden"
                                                    name="rightstr"
                                                    value="<?php
                                                    echo $escape(
                                                        $privilege["rightstr"]
                                                    );
                                                    ?>">

                                                <input
                                                    type="hidden"
                                                    name="return_page"
                                                    value="<?php
                                                    echo $return_page;
                                                    ?>">

                                                <input
                                                    type="hidden"
                                                    name="return_keyword"
                                                    value="<?php
                                                    echo $escape(
                                                        $return_keyword
                                                    );
                                                    ?>">

                                                <button
                                                    type="submit"
                                                    class="admin-privilege-remove">
                                                    제거
                                                </button>

                                            </form>

                                        </div>

                                    </div>

                                <?php } ?>

                            <?php } ?>

                        </div>

                    </section>

                <?php } ?>

            </div>

        <?php } ?>

    </div>

    <div class="admin-form-card">

        <div class="admin-form-card-header">

            <span class="admin-form-step">
                2
            </span>

            <div>
                <div class="admin-form-card-title">
                    특별 권한 추가
                </div>

                <div class="admin-form-card-desc">
                    사이트 운영에 필요한 특별 권한을 부여합니다.
                </div>
            </div>

        </div>

        <form
            method="post"
            action="user_privilege_add.php">

                <input
                    type="hidden"
                    name="return_target"
                    value="manage">

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
                name="privilege_type"
                value="special">

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
                    for="special-right">
                    특별 권한
                </label>

                <select
                    id="special-right"
                    name="special_right"
                    class="admin-form-input"
                    required>
                    <option value="problem_editor">
                        문제 관리자
                    </option>
                    <option value="source_browser">
                        전체 소스 열람
                    </option>
                    <option value="contest_creator">
                        대회 관리자
                    </option>
                    <option value="password_setter">
                        비밀번호 관리자
                    </option>
                    <option value="system_config_manager">
                        시스템 설정 관리자
                    </option>
                    <option value="printer">
                        출력 권한
                    </option>
                    <option value="balloon">
                        풍선 권한
                    </option>
                    <option value="administrator">
                        최고관리자
                    </option>
                </select>

            </div>

            <div class="admin-form-actions">
                <button
                    type="submit"
                    class="admin-btn admin-btn-primary">
                    특별 권한 추가
                </button>
            </div>

        </form>

    </div>

    <div class="admin-form-grid-2">

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    3
                </span>

                <div>
                    <div class="admin-form-card-title">
                        대회 참가 권한
                    </div>

                    <div class="admin-form-card-desc">
                        참가할 대회 번호를 입력합니다.
                    </div>
                </div>

            </div>

            <form
                method="post"
                action="user_privilege_add.php">

                    <input
                        type="hidden"
                        name="return_target"
                        value="manage">

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
                    name="privilege_type"
                    value="contest">

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
                        for="contest-id">
                        대회 번호
                    </label>

                    <input
                        type="number"
                        id="contest-id"
                        name="target_id"
                        class="admin-form-input"
                        min="1"
                        max="2147483647"
                        required>

                </div>

                <div class="admin-form-actions">
                    <button
                        type="submit"
                        class="admin-btn admin-btn-primary">
                        참가 권한 추가
                    </button>
                </div>

            </form>

        </div>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    4
                </span>

                <div>
                    <div class="admin-form-card-title">
                        문제 소스 보기 권한
                    </div>

                    <div class="admin-form-card-desc">
                        열람할 문제 번호를 입력합니다.
                    </div>
                </div>

            </div>

            <form
                method="post"
                action="user_privilege_add.php">

                    <input
                        type="hidden"
                        name="return_target"
                        value="manage">

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
                    name="privilege_type"
                    value="source">

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
                        for="source-problem-id">
                        문제 번호
                    </label>

                    <input
                        type="number"
                        id="source-problem-id"
                        name="target_id"
                        class="admin-form-input"
                        min="1"
                        max="2147483647"
                        required>

                </div>

                <div class="admin-form-actions">
                    <button
                        type="submit"
                        class="admin-btn admin-btn-primary">
                        소스 보기 권한 추가
                    </button>
                </div>

            </form>

        </div>

    </div>

</div>

<?php

require(
    __DIR__ . "/admin-layout-end.php"
);

?>
