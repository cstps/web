<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_contest_add.php?course_id=<?php echo intval($course_id); ?>"
        >
            <i class="left arrow icon"></i>
            이전으로
        </a>

        <h1 class="ui header">
            차시 문제 선택
        </h1>

        <div class="course-page-description">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

            ·

            <?php echo intval($lesson_no); ?>차시

        </div>

    </div>


    <div class="ui segment">

        <h3 class="ui header">
            원본 대회
        </h3>

        <div class="ui relaxed list">

            <div class="item">
                <strong>대회 번호:</strong>
                <?php echo intval($view_source_contest['contest_id']); ?>
            </div>

            <div class="item">
                <strong>대회 제목:</strong>

                <?php
                echo htmlspecialchars(
                    $view_source_contest['title'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

        </div>

    </div>


    <form
        class="ui form"
        method="post"
        action="course_contest_create.php"
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

        <input
            type="hidden"
            name="source_contest_id"
            value="<?php echo intval($source_contest_id); ?>"
        >


        <div class="ui segment">

            <h3 class="ui dividing header">
                가져올 문제 선택
            </h3>


            <div style="margin-bottom:1rem;">

                <button
                    type="button"
                    class="ui tiny basic button"
                    id="course-problem-select-all"
                >
                    전체 선택
                </button>

                <button
                    type="button"
                    class="ui tiny basic button"
                    id="course-problem-select-none"
                >
                    전체 해제
                </button>

            </div>


            <table class="ui celled striped table">

                <thead>

                    <tr>

                        <th class="center aligned">
                            선택
                        </th>

                        <th class="center aligned">
                            문제
                        </th>

                        <th class="center aligned">
                            문제 번호
                        </th>

                        <th>
                            제목
                        </th>

                        <th class="center aligned">
                            점수
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php
                foreach ($view_problems as $problem) {

                    $problem_label =
                        chr(
                            ord('A') +
                            intval($problem['num'])
                        );
                ?>

                    <tr>

                        <td class="center aligned">

                            <input
                                type="checkbox"
                                class="course-problem-checkbox"
                                name="problem_ids[]"
                                value="<?php
                                    echo intval($problem['problem_id']);
                                ?>"
                                checked
                            >

                        </td>


                        <td class="center aligned">

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $problem_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </strong>

                        </td>


                        <td class="center aligned">

                            <?php
                            echo intval(
                                $problem['problem_id']
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $problem['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </td>


                        <td class="center aligned">

                            <?php
                            if (
                                isset($problem['score']) &&
                                $problem['score'] !== null
                            ) {

                                echo intval(
                                    $problem['score']
                                );

                            }
                            else {

                                echo '-';
                            }
                            ?>

                        </td>

                    </tr>

                <?php
                }
                ?>

                </tbody>

            </table>

        </div>


        <div class="ui segment">

            <h3 class="ui dividing header">
                새 차시 기본 정보
            </h3>


            <div class="field">

                <label>
                    차시 제목
                </label>

                <input
                    type="text"
                    name="contest_title"
                    maxlength="100"
                    value="<?php
                        echo intval($lesson_no);
                    ?>차시 - <?php
                        echo htmlspecialchars(
                            $view_source_contest['title'],
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
                        required
                    >

                </div>

            </div>

        </div>


        <div style="margin-top:1rem;">

            <button
                type="submit"
                class="ui teal button"
            >
                <i class="arrow right icon"></i>
                선택한 문제로 차시 만들기
            </button>

        </div>

    </form>

</div>


<script>
(function() {

    var selectAllButton =
        document.getElementById(
            'course-problem-select-all'
        );

    var selectNoneButton =
        document.getElementById(
            'course-problem-select-none'
        );


    function getCheckboxes() {

        return document.querySelectorAll(
            '.course-problem-checkbox'
        );
    }


    if (selectAllButton) {

        selectAllButton.addEventListener(
            'click',
            function() {

                getCheckboxes().forEach(
                    function(checkbox) {

                        checkbox.checked = true;

                    }
                );

            }
        );
    }


    if (selectNoneButton) {

        selectNoneButton.addEventListener(
            'click',
            function() {

                getCheckboxes().forEach(
                    function(checkbox) {

                        checkbox.checked = false;

                    }
                );

            }
        );
    }

})();
</script>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>