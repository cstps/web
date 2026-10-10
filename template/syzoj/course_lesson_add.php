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
            href="course_view.php?course_id=<?php
                echo intval($course_id);
            ?>"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            새 차시 만들기
        </h1>

        <div class="course-page-description">
            <?php
            echo htmlspecialchars(
                (string)$view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    </div>


    <div class="ui segment">

        <div class="ui info message">
            <div class="header">
                독립 차시 생성
            </div>

            <p>
                Contest 없이 차시를 먼저 생성합니다.
                생성 후 학습 활동을 추가할 수 있습니다.
            </p>

            <p>
                새 차시는 비공개 상태로 저장됩니다.
            </p>
        </div>


        <form
            class="ui form"
            method="post"
            action="course_lesson_create.php"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?php
                    echo htmlspecialchars(
                        (string)$view_csrf_token,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >


            <div class="field">

                <label for="lesson_no">
                    차시 번호
                </label>

                <input
                    type="number"
                    id="lesson_no"
                    name="lesson_no"
                    min="1"
                    max="2147483647"
                    value="<?php
                        echo intval($view_next_lesson_no);
                    ?>"
                    required
                >

                <div class="ui pointing basic label">
                    수업 안에서 중복되지 않는 번호를 입력하세요.
                </div>

            </div>


            <div class="required field">

                <label for="lesson_title">
                    차시 제목
                </label>

                <input
                    type="text"
                    id="lesson_title"
                    name="title"
                    maxlength="255"
                    placeholder="예: 반복문의 이해"
                    required
                >

            </div>


            <div class="field">

                <label for="lesson_description">
                    차시 설명
                </label>

                <textarea
                    id="lesson_description"
                    name="description"
                    rows="5"
                    placeholder="학습 목표 및 활동 안내를 입력하세요."
                ></textarea>

            </div>


            <div class="ui divider"></div>


            <button
                type="submit"
                class="ui primary button"
            >
                <i class="plus icon"></i>
                차시 생성
            </button>

            <a
                class="ui basic button"
                href="course_view.php?course_id=<?php
                    echo intval($course_id);
                ?>"
            >
                취소
            </a>

        </form>

    </div>

</div>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
