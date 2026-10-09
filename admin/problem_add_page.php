<?php
require_once("../include/code_template_functions.inc.php");

require_once(__DIR__ . "/admin-init.php");
require_once(__DIR__ . "/../include/const.inc.php");


// ============================================================
// 문제 생성 권한
// ============================================================

if (!oj_can_create_admin_problems()) {

    http_response_code(403);

    exit("문제를 생성할 권한이 없습니다.");
}


// ============================================================
// 캐시 방지
// ============================================================

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);


// ============================================================
// 관리자 공통 레이아웃
// ============================================================

$admin_page_title = "새 문제 만들기";
$admin_active_menu = "problem_add";

$admin_page_head_file =
    __DIR__ . "/tinymce.php";

require(
    __DIR__ . "/admin-layout-start.php"
);

?>

<div class="admin-page">

    <div class="admin-page-header">

        <div>
            <h1 class="admin-page-title">
                새 문제 만들기
            </h1>

            <div class="admin-page-description">
                문제 내용과 채점 조건, 문제 재사용 정책을 설정합니다.
            </div>
        </div>

        <a
            href="problem_list.php"
            class="admin-btn admin-btn-secondary">
            문제 목록
        </a>

    </div>


    <?php
    $enabled_template_languages =
        oj_get_enabled_template_languages(
            $language_name,
            $language_ext,
            $OJ_LANGMASK
        );
    ?>


    <form
        method="POST"
        id="problemAdd"
        action="problem_add.php"
        onsubmit="do_submit()">

        <input
            type="hidden"
            name="problem_id"
            value="New Problem">


        <!-- =====================================================
             1. 기본 정보
             ===================================================== -->

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
                        문제 제목과 실행 제한을 설정합니다.
                    </div>
                </div>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_TITLE; ?>
                </label>

                <input
                    class="admin-form-input"
                    type="text"
                    name="title"
                    required>

            </div>


            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Time_Limit; ?>
                    </label>

                    <div class="admin-form-unit">

                        <input
                            class="admin-form-input"
                            type="number"
                            min="0.001"
                            max="300"
                            step="0.001"
                            name="time_limit"
                            value="1"
                            required>

                        <span class="admin-form-unit-label">
                            sec
                        </span>

                    </div>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Memory_Limit; ?>
                    </label>

                    <div class="admin-form-unit">

                        <input
                            class="admin-form-input"
                            type="number"
                            min="1"
                            max="1024"
                            step="1"
                            name="memory_limit"
                            value="128"
                            required>

                        <span class="admin-form-unit-label">
                            MB
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             2. 문제 내용
             ===================================================== -->

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    2
                </span>

                <div>
                    <div class="admin-form-card-title">
                        문제 내용
                    </div>

                    <div class="admin-form-card-desc">
                        학생에게 표시할 문제 설명과 입출력 예제를 작성합니다.
                    </div>
                </div>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_Description; ?>
                </label>

                <textarea
                    class="tinymce-editor"
                    rows="13"
                    name="description"
                    cols="80"></textarea>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_Input; ?>
                </label>

                <textarea
                    class="tinymce-editor"
                    rows="13"
                    name="input"
                    cols="80"></textarea>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_Output; ?>
                </label>

                <textarea
                    class="tinymce-editor"
                    rows="13"
                    name="output"
                    cols="80"></textarea>

            </div>


            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Sample_Input; ?>
                    </label>

                    <textarea
                        class="admin-form-textarea admin-code-textarea"
                        rows="9"
                        name="sample_input"></textarea>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Sample_Output; ?>
                    </label>

                    <textarea
                        class="admin-form-textarea admin-code-textarea"
                        rows="9"
                        name="sample_output"></textarea>

                </div>

            </div>


            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Test_Input; ?>
                    </label>

                    <div class="admin-form-help">
                        <?php echo $MSG_HELP_MORE_TESTDATA_LATER; ?>
                    </div>

                    <textarea
                        class="admin-form-textarea admin-code-textarea"
                        rows="9"
                        name="test_input"></textarea>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Test_Output; ?>
                    </label>

                    <div class="admin-form-help">
                        <?php echo $MSG_HELP_MORE_TESTDATA_LATER; ?>
                    </div>

                    <textarea
                        class="admin-form-textarea admin-code-textarea"
                        rows="9"
                        name="test_output"></textarea>

                </div>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_HINT; ?>
                </label>

                <textarea
                    class="tinymce-editor"
                    rows="13"
                    name="hint"
                    cols="80"></textarea>

            </div>

        </div>


        <!-- =====================================================
             3. 채점 및 문제 관리
             ===================================================== -->

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    3
                </span>

                <div>
                    <div class="admin-form-card-title">
                        채점 및 문제 관리
                    </div>

                    <div class="admin-form-card-desc">
                        채점 방식과 문제의 사용 정책을 설정합니다.
                    </div>
                </div>

            </div>


            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_SPJ; ?>
                    </label>

                    <div class="admin-choice-group">

                        <label class="admin-choice">

                            <input
                                type="radio"
                                name="spj"
                                value="0"
                                checked>

                            <span>
                                사용 안 함
                            </span>

                        </label>


                        <label class="admin-choice">

                            <input
                                type="radio"
                                name="spj"
                                value="1">

                            <span>
                                사용
                            </span>

                        </label>

                    </div>

                    <div class="admin-form-help">
                        <?php echo $MSG_HELP_SPJ; ?>
                    </div>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        다른 대회에서 문제 사용
                    </label>

                    <div class="admin-choice-group">

                        <label class="admin-choice">

                            <input
                                type="radio"
                                name="allow_reuse"
                                value="1"
                                checked>

                            <span>
                                재사용 허용
                            </span>

                        </label>


                        <label class="admin-choice">

                            <input
                                type="radio"
                                name="allow_reuse"
                                value="0">

                            <span>
                                재사용 제한
                            </span>

                        </label>

                    </div>

                    <div class="admin-form-help">
                        재사용 제한 시 다른 사용자가 이 문제를 새로운 대회나
                        수업 차시에 추가할 수 없습니다.
                    </div>

                </div>

            </div>


            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_SOURCE; ?>
                    </label>

                    <input
                        class="admin-form-input"
                        type="text"
                        name="source"
                        placeholder="예: 정보올림피아드//수행평가">

                    <div class="admin-form-help">
                        여러 출처는 // 로 구분합니다.
                    </div>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Creator; ?>
                    </label>

                    <textarea
                        class="admin-form-textarea"
                        name="creator"
                        rows="2"></textarea>

                    <div class="admin-form-help">
                        표시할 출제자 이름입니다. 비워 두면 현재 등록자 ID가 표시됩니다. 이 입력은 문제 관리 권한에 영향을 주지 않습니다.
                    </div>

                </div>

            </div>


            <div class="admin-form-field admin-form-field-small">

                <label class="admin-form-label">
                    <?php echo $MSG_PRO_POINT; ?>
                </label>

                <div class="admin-form-unit admin-form-unit-small">

                    <input
                        class="admin-form-input"
                        type="number"
                        min="1"
                        max="300"
                        step="1"
                        name="pro_point"
                        value="1">

                    <span class="admin-form-unit-label">
                        점
                    </span>

                </div>

            </div>

        </div>


        <!-- =====================================================
             4. 코드 템플릿 및 제한
             ===================================================== -->

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    4
                </span>

                <div>
                    <div class="admin-form-card-title">
                        코드 템플릿 및 제한
                    </div>

                    <div class="admin-form-card-desc">
                        함수 작성형 문제 또는 특정 코드 사용 제한이 필요한 경우 설정합니다.
                    </div>
                </div>

            </div>


            <style>
                .admin-template-language-tabs {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 10px;
                    margin-bottom: 16px;
                }

                .admin-template-language-tab {
                    border: 1px solid #d0d7de;
                    background: #ffffff;
                    border-radius: 6px;
                    padding: 8px 14px;
                    cursor: pointer;
                    font-size: 14px;
                }

                .admin-template-language-tab.active {
                    font-weight: 600;
                    border-color: #0969da;
                    background: #f0f6ff;
                }

                .admin-template-language-panel {
                    display: none;
                }

                .admin-template-language-panel.active {
                    display: block;
                }
            </style>


            <button
                type="button"
                class="admin-code-toggle"
                id="codeTemplateToggle"
                onclick="toggleCodeTemplate()"
                aria-expanded="false"
                aria-controls="codeTemplateContent">

                <span id="codeTemplateToggleText">
                    코드 템플릿 설정 펼치기
                </span>

                <span id="codeTemplateArrow">
                    ▼
                </span>

            </button>


            <div
                id="codeTemplateContent"
                class="admin-code-template-content">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        언어별 코드 템플릿
                    </label>

                    <div class="admin-form-help">
                        현재 사이트에서 활성화된 언어별로
                        Front Code와 Rear Code를 관리합니다.
                    </div>

                    <div class="admin-template-language-tabs">

                        <?php
                        $template_language_index = 0;

                        foreach (
                            $enabled_template_languages
                            as $language_id => $language_label
                        ) {
                        ?>

                            <button
                                type="button"
                                class="admin-template-language-tab <?php
                                    echo $template_language_index === 0
                                        ? 'active'
                                        : '';
                                ?>"
                                data-template-language="<?php
                                    echo intval($language_id);
                                ?>">
                                <?php
                                echo htmlspecialchars(
                                    $language_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </button>

                        <?php
                            $template_language_index++;
                        }
                        ?>

                    </div>


                    <?php
                    $template_language_index = 0;

                    foreach (
                        $enabled_template_languages
                        as $language_id => $language_label
                    ) {
                        $language_id =
                            intval($language_id);
                    ?>

                        <div
                            class="admin-template-language-panel <?php
                                echo $template_language_index === 0
                                    ? 'active'
                                    : '';
                            ?>"
                            data-template-panel="<?php
                                echo $language_id;
                            ?>">

                            <div class="admin-form-field">

                                <label class="admin-form-label">
                                    <?php
                                    echo htmlspecialchars(
                                        $language_label,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                    Front Code
                                </label>

                                <textarea
                                    class="admin-form-textarea admin-code-textarea"
                                    rows="8"
                                    name="template_front[<?php
                                        echo $language_id;
                                    ?>]"></textarea>

                            </div>


                            <div class="admin-form-field">

                                <label class="admin-form-label">
                                    <?php
                                    echo htmlspecialchars(
                                        $language_label,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                    Rear Code
                                </label>

                                <textarea
                                    class="admin-form-textarea admin-code-textarea"
                                    rows="8"
                                    name="template_rear[<?php
                                        echo $language_id;
                                    ?>]"></textarea>

                            </div>

                        </div>

                    <?php
                        $template_language_index++;
                    }
                    ?>

                </div>


<div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_BAN_CODE; ?>
                    </label>

                    <input
                        class="admin-form-input"
                        type="text"
                        name="ban_code"
                        placeholder="예: for/if">

                    <div class="admin-form-help">
                        여러 금지 코드는 / 로 구분해서 입력합니다.
                    </div>

                </div>

            </div>

        </div>


        <div class="admin-form-actions">

            <a
                href="problem_list.php"
                class="admin-btn admin-btn-secondary">
                취소
            </a>

            <?php
            require_once(
                __DIR__ . "/../include/set_post_key.php"
            );
            ?>

            <button
                type="submit"
                name="submit"
                class="admin-btn admin-btn-primary">
                <?php echo $MSG_SAVE; ?>
            </button>

        </div>

    </form>

</div>


<script>
    function toggleCodeTemplate() {

        var content =
            document.getElementById(
                "codeTemplateContent"
            );

        var button =
            document.getElementById(
                "codeTemplateToggle"
            );

        var text =
            document.getElementById(
                "codeTemplateToggleText"
            );

        var arrow =
            document.getElementById(
                "codeTemplateArrow"
            );

        if (!content) {
            return;
        }

        var isOpen =
            content.classList.contains(
                "open"
            );

        if (isOpen) {

            content.classList.remove(
                "open"
            );

            if (text) {
                text.textContent =
                    "코드 템플릿 설정 펼치기";
            }

            if (arrow) {
                arrow.textContent = "▼";
            }

            if (button) {
                button.setAttribute(
                    "aria-expanded",
                    "false"
                );
            }

        } else {

            content.classList.add(
                "open"
            );

            if (text) {
                text.textContent =
                    "코드 템플릿 설정 접기";
            }

            if (arrow) {
                arrow.textContent = "▲";
            }

            if (button) {
                button.setAttribute(
                    "aria-expanded",
                    "true"
                );
            }

        }
    }


    document.addEventListener(
        "DOMContentLoaded",
        function () {

            var templateLanguageTabsInitialized = true;

            var tabs =
                document.querySelectorAll(
                    ".admin-template-language-tab"
                );

            var panels =
                document.querySelectorAll(
                    ".admin-template-language-panel"
                );

            tabs.forEach(function (tab) {

                tab.addEventListener(
                    "click",
                    function () {

                        var languageId =
                            this.getAttribute(
                                "data-template-language"
                            );

                        tabs.forEach(function (item) {
                            item.classList.remove("active");
                        });

                        panels.forEach(function (panel) {
                            panel.classList.remove("active");
                        });

                        this.classList.add("active");

                        var target =
                            document.querySelector(
                                '.admin-template-language-panel' +
                                '[data-template-panel="' +
                                languageId +
                                '"]'
                            );

                        if (target) {
                            target.classList.add("active");
                        }
                    }
                );
            });
        }
    );


    function do_submit() {

        if (
            typeof(window.tinymce) !== "undefined"
        ) {
            window.tinymce.triggerSave();
        }

        document.getElementById("problemAdd").target = "_self";
    }
</script>




<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>