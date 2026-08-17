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
            href="course_view.php?course_id=<?php echo intval($course_id); ?>"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            차시 추가
        </h1>

        <div class="course-page-description">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>

    </div>


    <div class="ui segment">

        <form
            class="ui form"
            method="get"
            action="course_contest_problem_select.php"
        >

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >


            <div class="field">

                <label>
                    차시 번호
                </label>

                <input
                    type="number"
                    name="lesson_no"
                    min="1"
                    value="<?php echo intval($view_next_lesson_no); ?>"
                    required
                >

            </div>


            <div class="field">

                <label>
                    원본 대회 번호
                </label>

                <input
                    type="number"
                    name="source_contest_id"
                    min="1"
                    placeholder="예: 2916"
                    required
                >

                <div class="ui pointing basic label">
                    기존 대회의 문제 구성을 가져오기 위한 대회 번호를 입력합니다.
                </div>

            </div>


            <button
                type="submit"
                class="ui teal button"
            >
                <i class="arrow right icon"></i>
                문제 선택으로 이동
            </button>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>