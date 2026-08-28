<?php
require_once("../include/db_info.inc.php");
require_once("../include/const.inc.php");
require_once("admin-header.php");

if (
    !(
        isset($_SESSION[$OJ_NAME.'_administrator']) ||
        isset($_SESSION[$OJ_NAME.'_contest_creator']) ||
        isset($_SESSION[$OJ_NAME.'_problem_editor'])
    )
) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

include_once("kindeditor.php");
?>

<html>
<head>
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Content-Language" content="zh-cn">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>새 문제 만들기</title>
</head>

<body>

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
            class="admin-btn admin-btn-secondary"
        >
            문제 목록
        </a>

    </div>


    <form
        method="POST"
        id="problemAdd"
        action="problem_add.php"
        onsubmit="do_submit()"
    >

        <input
            type="hidden"
            name="problem_id"
            value="New Problem"
        >


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
                    required
                >

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
                            required
                        >

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
                            required
                        >

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
                    class="kindeditor"
                    rows="13"
                    name="description"
                    cols="80"
                ></textarea>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_Input; ?>
                </label>

                <textarea
                    class="kindeditor"
                    rows="13"
                    name="input"
                    cols="80"
                ></textarea>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_Output; ?>
                </label>

                <textarea
                    class="kindeditor"
                    rows="13"
                    name="output"
                    cols="80"
                ></textarea>

            </div>


            <div class="admin-form-grid-2">

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Sample_Input; ?>
                    </label>

                    <textarea
                        class="admin-form-textarea admin-code-textarea"
                        rows="9"
                        name="sample_input"
                    ></textarea>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        <?php echo $MSG_Sample_Output; ?>
                    </label>

                    <textarea
                        class="admin-form-textarea admin-code-textarea"
                        rows="9"
                        name="sample_output"
                    ></textarea>

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
                        name="test_input"
                    ></textarea>

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
                        name="test_output"
                    ></textarea>

                </div>

            </div>


            <div class="admin-form-field">

                <label class="admin-form-label">
                    <?php echo $MSG_HINT; ?>
                </label>

                <textarea
                    class="kindeditor"
                    rows="13"
                    name="hint"
                    cols="80"
                ></textarea>

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
                                checked
                            >

                            <span>
                                사용 안 함
                            </span>

                        </label>


                        <label class="admin-choice">

                            <input
                                type="radio"
                                name="spj"
                                value="1"
                            >

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
                                checked
                            >

                            <span>
                                재사용 허용
                            </span>

                        </label>


                        <label class="admin-choice">

                            <input
                                type="radio"
                                name="allow_reuse"
                                value="0"
                            >

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
                        placeholder="예: 정보올림피아드//수행평가"
                    >

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
                        rows="2"
                    ></textarea>

                    <div class="admin-form-help">
                        실제 문제 출제자와 현재 등록자가 다른 경우에만 입력합니다.
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
                        value="1"
                    >

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


            <button
                type="button"
                class="admin-code-toggle"
                id="codeTemplateToggle"
                onclick="toggleCodeTemplate()"
                aria-expanded="false"
                aria-controls="codeTemplateContent"
            >

                <span id="codeTemplateToggleText">
                    코드 템플릿 설정 펼치기
                </span>

                <span id="codeTemplateArrow">
                    ▼
                </span>

            </button>


            <div
                id="codeTemplateContent"
                class="admin-code-template-content"
            >

                <div class="admin-form-field">

                    <label class="admin-form-label">
                        앞 코드 (Front Code)
                    </label>

                    <div class="admin-form-help">
                        학생이 작성한 코드 앞에 자동으로 추가되는 코드입니다.
                        언어별 구분은 기존 HUSTOJ 형식인
                        <code>//언어명//</code>을 사용합니다.
                    </div>

                    <?php
                    if ($OJ_ACE_EDITOR) {
                    ?>

                        <pre
                            class="admin-code-editor"
                            id="front_code"
                        ></pre>

                        <input
                            type="hidden"
                            id="front_code_source"
                            name="front_code"
                            value=""
                        >

                    <?php
                    } else {
                    ?>

                        <textarea
                            class="admin-form-textarea admin-code-textarea"
                            rows="10"
                            id="front_code"
                            name="front_code"
                        ></textarea>

                    <?php
                    }
                    ?>

                </div>


                <div class="admin-form-field">

                    <label class="admin-form-label">
                        뒤 코드 (Rear Code)
                    </label>

                    <div class="admin-form-help">
                        학생이 작성한 코드 뒤에 자동으로 추가되는 코드입니다.
                    </div>

                    <?php
                    if ($OJ_ACE_EDITOR) {
                    ?>

                        <pre
                            class="admin-code-editor"
                            id="rear_code"
                        ></pre>

                        <input
                            type="hidden"
                            id="rear_code_source"
                            name="rear_code"
                            value=""
                        >

                    <?php
                    } else {
                    ?>

                        <textarea
                            class="admin-form-textarea admin-code-textarea"
                            rows="10"
                            id="rear_code"
                            name="rear_code"
                        ></textarea>

                    <?php
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
                        placeholder="예: for/if"
                    >

                    <div class="admin-form-help">
                        여러 금지 코드는 / 로 구분해서 입력합니다.
                    </div>

                </div>

            </div>

        </div>


        <div class="admin-form-actions">

            <a
                href="problem_list.php"
                class="admin-btn admin-btn-secondary"
            >
                취소
            </a>

            <?php
            require_once("../include/set_post_key.php");
            ?>

            <button
                type="submit"
                name="submit"
                class="admin-btn admin-btn-primary"
            >
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

        // 접힌 상태에서 초기화된 ACE 편집기의 폭을 다시 계산
        window.setTimeout(
            function () {

                if (
                    typeof(editorFrontCode)
                    !== "undefined"
                ) {
                    editorFrontCode.resize();
                }

                if (
                    typeof(editorRearCode)
                    !== "undefined"
                ) {
                    editorRearCode.resize();
                }
            },
            0
        );
    }
}


function do_submit() {

    if (
        typeof(editorFrontCode) !== "undefined"
    ) {
        $("#front_code_source").val(
            editorFrontCode.getValue()
        );
    }

    if (
        typeof(editorRearCode) !== "undefined"
    ) {
        $("#rear_code_source").val(
            editorRearCode.getValue()
        );
    }

    document.getElementById("problemAdd").target = "_self";
}
</script>


<?php
if ($OJ_ACE_EDITOR) {
?>
<script src="../ace/ace.js"></script>
<script src="../ace/ext-language_tools.js"></script>

<script>
ace.require("../ace/ext/language_tools");

var editorFrontCode =
    ace.edit("front_code");

editorFrontCode.setTheme(
    "ace/theme/chrome"
);

editorFrontCode.session.setMode(
    "ace/mode/c_cpp"
);

editorFrontCode.setOptions({
    enableSnippets: true,
    enableLiveAutocompletion: false
});


var editorRearCode =
    ace.edit("rear_code");

editorRearCode.setTheme(
    "ace/theme/chrome"
);

editorRearCode.session.setMode(
    "ace/mode/c_cpp"
);

editorRearCode.setOptions({
    enableSnippets: true,
    enableLiveAutocompletion: false
});
</script>
<?php
}
?>

</body>
</html>