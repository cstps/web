<?php
include("template/$OJ_TEMPLATE/header.php");

$default_title =
    $view_course['course_name']." - ".
    intval($lesson_no)."차시";

$default_start_time =
    date('Y-m-d\TH:i');

$default_end_time =
    date(
        'Y-m-d\TH:i',
        strtotime('+7 days')
    );
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<style>

.course-lang-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.course-lang-option {
    position: relative;
    cursor: pointer;
}

.course-lang-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.course-lang-option span {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 38px;
    padding: 0 15px;

    border: 1px solid #d4d4d5;
    border-radius: 4px;

    background: #ffffff;

    font-weight: 600;
    cursor: pointer;

    transition: 0.15s;
}

.course-lang-option input:checked + span {
    background: #00b5ad;
    border-color: #00b5ad;
    color: #ffffff;
}

.course-lang-option input:focus + span {
    outline: 2px solid rgba(0, 181, 173, 0.25);
}

</style>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_contest_add.php?course_id=<?php
                echo intval($course_id);
            ?>"
        >
            <i class="left arrow icon"></i>
            차시 추가로 돌아가기
        </a>

        <h1 class="ui header">
            문제 직접 선택
        </h1>

        <div class="course-page-description">
            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
            · <?php echo intval($lesson_no); ?>차시
        </div>

    </div>


    <div class="ui segment">

        <form
            id="problem-search-form"
            class="ui form"
            method="get"
            action="course_problem_select.php"
        >

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="lesson_no"
                value="<?php echo intval($lesson_no); ?>"
            >

            <input
                type="hidden"
                id="search-selected-ids"
                name="selected_ids"
                value="<?php
                    echo htmlspecialchars(
                        $view_selected_ids_text,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >

            <div class="fields">

                <div class="twelve wide field">

                    <label>
                        문제 번호·제목·출처 검색
                    </label>

                    <input
                        type="text"
                        id="problem-search-input"
                        name="search"
                        value="<?php
                            echo htmlspecialchars(
                                $view_search,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        placeholder="예: 1187, 함수, Python"
                    >

                </div>

                <div class="four wide field">

                    <label>&nbsp;</label>

                    <button
                        type="submit"
                        class="ui blue button"
                    >
                        <i class="search icon"></i>
                        검색
                    </button>

                    <button
                        type="button"
                        id="clear-search-button"
                        class="ui basic button"
                    >
                        초기화
                    </button>

                </div>

            </div>

        </form>

    </div>
        <div class="ui segment">

        <h3 class="ui header">
            문제 검색 결과
        </h3>

        <div class="ui small info message">
            한 번에 최대 50개까지 표시됩니다.
            검색을 다시 해도 선택한 문제는 유지됩니다.
        </div>

        <div class="course-table-wrap">

            <table class="ui celled selectable table">

                <thead>

                    <tr>
                        <th class="center aligned">선택</th>
                        <th>번호</th>
                        <th>문제 제목</th>
                        <th>출처</th>
                        <th>상태</th>
                        <th>정답 / 제출</th>
                    </tr>

                </thead>

                <tbody>

                <?php
                if (empty($view_problem_rows)) {
                ?>

                    <tr>
                        <td
                            colspan="6"
                            class="center aligned"
                        >
                            검색된 문제가 없습니다.
                        </td>
                    </tr>

                <?php
                }
                else {

                    foreach ($view_problem_rows as $problem) {

                        $problem_id =
                            intval($problem['problem_id']);

                        $is_selected =
                            isset(
                                $view_selected_problem_ids[
                                    $problem_id
                                ]
                            );
                ?>

                    <tr>

                        <td class="center aligned">

                            <input
                                type="checkbox"
                                class="problem-checkbox"
                                value="<?php echo $problem_id; ?>"
                                <?php
                                echo $is_selected
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                        </td>

                        <td>
                            <?php echo $problem_id; ?>
                        </td>

                        <td>

                            <a
                                href="problem.php?id=<?php
                                    echo $problem_id;
                                ?>"
                                target="_blank"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $problem['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </a>

                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                isset($problem['source'])
                                    ? $problem['source']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </td>

                        <td>

                            <?php
                            if (
                                strtoupper(
                                    trim($problem['defunct'])
                                ) === 'Y'
                            ) {
                            ?>

                                <span class="ui tiny grey label">
                                    숨김
                                </span>

                            <?php
                            }
                            else {
                            ?>

                                <span class="ui tiny green label">
                                    공개
                                </span>

                            <?php
                            }
                            ?>

                        </td>

                        <td>
                            <?php
                            echo intval($problem['accepted']);
                            ?>
                            /
                            <?php
                            echo intval($problem['submit']);
                            ?>
                        </td>

                    </tr>

                <?php
                    }
                }
                ?>

                </tbody>

            </table>

        </div>

    </div>
        <div class="ui segment">

        <h3 class="ui header">
            선택한 문제와 차시 설정
        </h3>

        <div class="ui blue message">

            선택 문제:
            <strong id="selected-problem-count">0</strong>개

            <div id="selected-problem-list"></div>

            <button
                type="button"
                id="clear-selected-button"
                class="ui tiny basic button"
            >
                선택 모두 해제
            </button>

        </div>


        <form
            id="direct-create-form"
            class="ui form"
            method="post"
            action="course_contest_direct_create.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="lesson_no"
                value="<?php echo intval($lesson_no); ?>"
            >

            <div id="selected-problem-inputs"></div>


            <div class="field">

                <label>
                    차시 제목
                </label>

                <input
                    type="text"
                    name="contest_title"
                    maxlength="100"
                    value="<?php
                        echo htmlspecialchars(
                            $default_title,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    required
                >

            </div>


            <div class="two fields">

                <div class="field">

                    <label>
                        시작 시간
                    </label>

                    <input
                        type="datetime-local"
                        name="start_time"
                        value="<?php
                            echo $default_start_time;
                        ?>"
                        required
                    >

                </div>

                <div class="field">

                    <label>
                        종료 시간
                    </label>

                    <input
                        type="datetime-local"
                        name="end_time"
                        value="<?php
                            echo $default_end_time;
                        ?>"
                        required
                    >

                </div>

            </div>


            <!-- ==================================================
            제출 가능 언어
            =================================================== -->

            <div class="field">

                <label>
                    제출 가능 언어
                </label>

                <?php

                // --------------------------------------------------------
                // Course에서 사용할 제출 언어
                //
                // Python3이 있으면 Python3을 우선 사용하고
                // 화면에는 Python으로 표시한다.
                // --------------------------------------------------------

                $course_language_specs = array(

                    array(
                        'label' => 'C++',
                        'aliases' => array(
                            'C++'
                        )
                    ),

                    array(
                        'label' => 'Python',
                        'aliases' => array(
                            'Python3',
                            'Python'
                        )
                    ),

                    array(
                        'label' => 'JavaScript',
                        'aliases' => array(
                            'JavaScript',
                            'Java Script',
                            'Node.js'
                        )
                    ),

                    array(
                        'label' => 'Java',
                        'aliases' => array(
                            'Java'
                        )
                    )

                );


                $course_language_options =
                    array();


                foreach (
                    $course_language_specs
                    as $language_spec
                ) {

                    foreach (
                        $language_spec['aliases']
                        as $language_alias
                    ) {

                        $language_id =
                            array_search(
                                $language_alias,
                                $language_name,
                                true
                            );


                        if ($language_id !== false) {

                            $course_language_options[
                                intval($language_id)
                            ] =
                                $language_spec['label'];

                            break;
                        }
                    }
                }

                ?>


                <div class="course-lang-options">

                    <?php
                    foreach (
                        $course_language_options
                        as $language_id => $display_name
                    ) {
                    ?>

                        <label class="course-lang-option">

                            <input
                                type="checkbox"
                                name="lang[]"
                                value="<?php
                                    echo intval($language_id);
                                ?>"
                            >

                            <span>
                                <?php
                                echo htmlspecialchars(
                                    $display_name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </span>

                        </label>

                    <?php
                    }
                    ?>

                </div>


                <div
                    class="ui pointing basic label"
                    style="margin-top:8px;"
                >
                    하나 이상의 언어를 선택하세요.
                </div>

            </div>


            <div class="ui warning message">

                Course 차시는 처음에는 숨김 상태로 생성됩니다.
                생성 후 수업 화면에서 문제 구성과 설정을 확인한 다음
                학생에게 공개하세요.

            </div>


            <button
                type="submit"
                class="ui blue button"
            >
                <i class="plus icon"></i>
                선택 문제로 차시 생성
            </button>

        </form>

    </div>

</div>
<script>
(function () {

    var initialSelected =
        <?php
        echo json_encode(
            array_map(
                'strval',
                array_keys($view_selected_problem_ids)
            )
        );
        ?>;

    var selectedProblems =
        new Set(initialSelected);

    var checkboxes =
        document.querySelectorAll(
            '.problem-checkbox'
        );

    var searchForm =
        document.getElementById(
            'problem-search-form'
        );

    var searchSelectedInput =
        document.getElementById(
            'search-selected-ids'
        );

    var createForm =
        document.getElementById(
            'direct-create-form'
        );

    var selectedInputs =
        document.getElementById(
            'selected-problem-inputs'
        );

    var selectedCount =
        document.getElementById(
            'selected-problem-count'
        );

    var selectedList =
        document.getElementById(
            'selected-problem-list'
        );


    function synchronizeSelectedProblems() {

        var selectedArray =
            Array.from(selectedProblems);

        searchSelectedInput.value =
            selectedArray.join(',');

        selectedCount.textContent =
            selectedArray.length;

        selectedList.textContent =
            selectedArray.length > 0
                ? '선택 순서: ' +
                    selectedArray.join(', ')
                : '선택된 문제가 없습니다.';

        selectedInputs.innerHTML = '';


        selectedArray.forEach(function (problemId) {

            var input =
                document.createElement('input');

            input.type = 'hidden';
            input.name = 'problem_ids[]';
            input.value = problemId;

            selectedInputs.appendChild(input);
        });
    }


    checkboxes.forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            function () {

                var problemId =
                    String(this.value);

                if (this.checked) {
                    selectedProblems.add(problemId);
                } else {
                    selectedProblems.delete(problemId);
                }

                synchronizeSelectedProblems();
            }
        );
    });


    searchForm.addEventListener(
        'submit',
        function () {

            synchronizeSelectedProblems();
        }
    );


    document.getElementById(
        'clear-search-button'
    ).addEventListener(
        'click',
        function () {

            document.getElementById(
                'problem-search-input'
            ).value = '';

            synchronizeSelectedProblems();
            searchForm.submit();
        }
    );


    document.getElementById(
        'clear-selected-button'
    ).addEventListener(
        'click',
        function () {

            selectedProblems.clear();

            checkboxes.forEach(
                function (checkbox) {
                    checkbox.checked = false;
                }
            );

            synchronizeSelectedProblems();
        }
    );


    createForm.addEventListener(
        'submit',
        function (event) {

            synchronizeSelectedProblems();

            if (selectedProblems.size === 0) {

                event.preventDefault();

                alert(
                    '최소 한 개 이상의 문제를 선택하세요.'
                );

                return;
            }


            var selectedLanguages =
                createForm.querySelectorAll(
                    'input[name="lang[]"]:checked'
                );

            if (selectedLanguages.length === 0) {

                event.preventDefault();

                alert(
                    '제출 가능 언어를 하나 이상 선택하세요.'
                );

                return;
            }


            if (
                !confirm(
                    '선택한 문제로 Course 전용 차시를 생성하시겠습니까?'
                )
            ) {
                
                event.preventDefault();
            }
        }
    );


    synchronizeSelectedProblems();

})();
</script>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>