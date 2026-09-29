<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_manage_admin_system_settings()) {
    http_response_code(403);
    exit("시스템 설정을 관리할 권한이 없습니다.");
}

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);

$setting_rows =
    pdo_query(
        "
        SELECT
            id,
            exam_mode,
            register
        FROM setting
        ORDER BY id
        LIMIT 1
        "
    );

if (
    $setting_rows === false ||
    !isset($setting_rows[0])
) {
    http_response_code(500);
    exit("시스템 설정을 불러올 수 없습니다.");
}

$setting =
    $setting_rows[0];

$setting_id =
    (int)$setting["id"];

$exam_mode =
    (int)$setting["exam_mode"] === 1
    ? 1
    : 0;

$register_enabled =
    (int)$setting["register"] === 1
    ? 1
    : 0;

$saved =
    isset($_GET["saved"]) &&
    (string)$_GET["saved"] === "1";

$template_name =
    isset($OJ_TEMPLATE)
    ? (string)$OJ_TEMPLATE
    : "";

$ce_penalty =
    isset($OJ_CE_PENALTY)
    ? (string)$OJ_CE_PENALTY
    : "";

$language_mask =
    isset($OJ_LANGMASK)
    ? (string)$OJ_LANGMASK
    : "";

$escape =
    function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            "UTF-8"
        );
    };

$admin_page_title =
    "시스템 설정";

$admin_active_menu =
    "system_settings";

require_once(
    __DIR__ . "/admin-layout-start.php"
);
?>

<div class="admin-page">

    <div class="admin-page-header">

        <div>
            <h1 class="admin-page-title">
                시스템 설정
            </h1>

            <div class="admin-page-description">
                수행평가 모드와 회원가입 허용 여부를 관리합니다.
            </div>
        </div>

    </div>

    <?php if ($saved) { ?>
        <div
            class="admin-card"
            role="status">
            시스템 설정을 저장했습니다.
        </div>
    <?php } ?>

    <form
        method="post"
        action="setdb_change.php">

        <?php
        require(
            __DIR__ .
            "/../include/set_post_key.php"
        );
        ?>

        <input
            type="hidden"
            name="setting_id"
            value="<?php echo $setting_id; ?>">

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    1
                </span>

                <div>
                    <div class="admin-form-card-title">
                        운영 설정
                    </div>

                    <div class="admin-form-card-desc">
                        변경할 항목을 선택한 후 저장합니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label
                        class="admin-form-label"
                        for="setting-exam-mode">
                        수행평가 모드
                    </label>

                    <select
                        id="setting-exam-mode"
                        name="exam_mode"
                        class="admin-form-input"
                        required>
                        <option
                            value="0"
                            <?php
                            echo $exam_mode === 0
                                ? "selected"
                                : "";
                            ?>>
                            사용 안 함
                        </option>
                        <option
                            value="1"
                            <?php
                            echo $exam_mode === 1
                                ? "selected"
                                : "";
                            ?>>
                            사용
                        </option>
                    </select>

                    <div class="admin-form-help">
                        수행평가 전용 운영 모드를 설정합니다.
                    </div>

                </div>

                <div class="admin-form-field">

                    <label
                        class="admin-form-label"
                        for="setting-register">
                        회원가입
                    </label>

                    <select
                        id="setting-register"
                        name="register"
                        class="admin-form-input"
                        required>
                        <option
                            value="0"
                            <?php
                            echo $register_enabled === 0
                                ? "selected"
                                : "";
                            ?>>
                            허용 안 함
                        </option>
                        <option
                            value="1"
                            <?php
                            echo $register_enabled === 1
                                ? "selected"
                                : "";
                            ?>>
                            허용
                        </option>
                    </select>

                    <div class="admin-form-help">
                        신규 사용자 회원가입 가능 여부를 설정합니다.
                    </div>

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
                        현재 서버 설정
                    </div>

                    <div class="admin-form-card-desc">
                        설정 파일에서 불러온 읽기 전용 정보입니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        기본 템플릿
                    </label>

                    <input
                        type="text"
                        class="admin-form-input"
                        value="<?php echo $escape($template_name); ?>"
                        readonly>

                </div>

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        컴파일 오류 감점
                    </label>

                    <input
                        type="text"
                        class="admin-form-input"
                        value="<?php echo $escape($ce_penalty); ?>"
                        readonly>

                </div>

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        언어 마스크
                    </label>

                    <input
                        type="text"
                        class="admin-form-input"
                        value="<?php echo $escape($language_mask); ?>"
                        readonly>

                </div>

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        설정 번호
                    </label>

                    <input
                        type="text"
                        class="admin-form-input"
                        value="<?php echo $setting_id; ?>"
                        readonly>

                </div>

            </div>

        </div>

        <div class="admin-form-actions">

            <button
                type="submit"
                class="admin-btn admin-btn-primary">
                설정 저장
            </button>

        </div>

    </form>

</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
