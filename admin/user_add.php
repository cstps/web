<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_create_admin_users()) {
    http_response_code(403);
    exit("사용자를 추가할 권한이 없습니다.");
}

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);

$flash_key =
    $OJ_NAME .
    "_admin_user_add_result";

$add_result =
    isset($_SESSION[$flash_key]) &&
    is_array($_SESSION[$flash_key])
    ? $_SESSION[$flash_key]
    : array();

unset(
    $_SESSION[$flash_key]
);

$success_ids =
    isset($add_result["success_ids"]) &&
    is_array($add_result["success_ids"])
    ? $add_result["success_ids"]
    : array();

$errors =
    isset($add_result["errors"]) &&
    is_array($add_result["errors"])
    ? $add_result["errors"]
    : array();

$escape =
    function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    };

$admin_page_title =
    "사용자 추가";

$admin_active_menu =
    "user_add";

require_once(
    __DIR__ . "/admin-layout-start.php"
);
?>

<div class="admin-page">

    <div class="admin-page-header">

        <div>
            <h1 class="admin-page-title">
                사용자 추가
            </h1>

            <div class="admin-page-description">
                한 명 또는 여러 명의 사용자 계정을 한 번에 추가합니다.
            </div>
        </div>

        <a
            href="user_list.php"
            class="admin-btn admin-btn-secondary">
            사용자 목록
        </a>

    </div>

    <?php if (count($success_ids) > 0) { ?>

        <div
            class="admin-form-card"
            role="status">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    ✓
                </span>

                <div>
                    <div class="admin-form-card-title">
                        <?php
                        echo number_format(
                            count($success_ids)
                        );
                        ?>명 추가 완료
                    </div>

                    <div class="admin-form-card-desc">
                        사용자 계정을 정상적으로 추가했습니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">
                <?php
                $display_success_ids =
                    array_slice(
                        $success_ids,
                        0,
                        100
                    );

                echo $escape(
                    implode(
                        ", ",
                        $display_success_ids
                    )
                );

                if (count($success_ids) > 100) {
                    echo " 외 " .
                        number_format(
                            count($success_ids) -
                            100
                        ) .
                        "명";
                }
                ?>
            </div>

        </div>

    <?php } ?>

    <?php if (count($errors) > 0) { ?>

        <div
            class="admin-form-card"
            role="alert">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    !
                </span>

                <div>
                    <div class="admin-form-card-title">
                        확인이 필요한 항목
                    </div>

                    <div class="admin-form-card-desc">
                        비밀번호는 화면에 다시 표시하지 않습니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">

                <ul>
                    <?php
                    foreach (
                        array_slice(
                            $errors,
                            0,
                            100
                        )
                        as
                        $error
                    ) {
                    ?>
                        <li>
                            <?php echo $escape($error); ?>
                        </li>
                    <?php } ?>
                </ul>

                <?php if (count($errors) > 100) { ?>
                    <div class="admin-form-help">
                        나머지
                        <?php
                        echo number_format(
                            count($errors) -
                            100
                        );
                        ?>개 오류는 입력 내용을 나누어 다시 확인해 주세요.
                    </div>
                <?php } ?>

            </div>

        </div>

    <?php } ?>

    <form
        method="post"
        action="user_add_save.php"
        autocomplete="off">

        <?php
        require(
            __DIR__ .
            "/../include/set_post_key.php"
        );
        ?>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    1
                </span>

                <div>
                    <div class="admin-form-card-title">
                        사용자 정보 입력
                    </div>

                    <div class="admin-form-card-desc">
                        사용자 한 명당 한 줄씩 입력합니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">

                <label
                    class="admin-form-label"
                    for="user-add-list">
                    사용자 목록
                </label>

                <textarea
                    id="user-add-list"
                    name="user_list"
                    class="admin-form-textarea admin-code-textarea"
                    rows="14"
                    maxlength="100000"
                    spellcheck="false"
                    required
                    placeholder="student01 password1 별명 경남온라인학교&#10;student02 password2 별명"></textarea>

                <div class="admin-form-help">
                    입력 형식:
                    사용자ID 비밀번호 별명 [학교명]
                </div>

            </div>

        </div>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    2
                </span>

                <div>
                    <div class="admin-form-card-title">
                        입력 규칙
                    </div>

                    <div class="admin-form-card-desc">
                        저장하기 전에 다음 조건을 확인해 주세요.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">

                <ul>
                    <li>
                        사용자 ID는 3~20자의 영문자와 숫자로 입력합니다.
                    </li>
                    <li>
                        비밀번호는 6자 이상 입력합니다.
                    </li>
                    <li>
                        별명은 20자 이하이며 공백 없이 입력합니다.
                    </li>
                    <li>
                        학교명은 선택 사항이며 공식 학교 목록의 이름을 사용합니다.
                    </li>
                    <li>
                        한 번에 최대 500명까지 추가할 수 있습니다.
                    </li>
                </ul>

            </div>

        </div>

        <div class="admin-form-actions">

            <button
                type="reset"
                class="admin-btn admin-btn-secondary">
                입력 지우기
            </button>

            <button
                type="submit"
                class="admin-btn admin-btn-primary">
                사용자 추가
            </button>

        </div>

    </form>

</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
