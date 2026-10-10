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
            학습 활동 추가
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

        <h3 class="ui dividing header">
            <?php echo intval($view_lesson['lesson_no']); ?>차시
            —
            <?php
            echo htmlspecialchars(
                (string)$view_lesson['title'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </h3>

        <div class="ui info message">

            <div class="header">
                일반 학습 활동
            </div>

            <p>
                현재 차시에 학습 자료 안내 또는
                학습 활동·과제 안내를 추가합니다.
            </p>

            <p>
                생성된 활동은 비공개 상태로 저장됩니다.
                파일 업로드와 과제 제출 기능은 아직 지원하지 않습니다.
            </p>

        </div>


        <form
            class="ui form"
            method="post"
            action="course_activity_create.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="lesson_id"
                value="<?php
                    echo intval($view_lesson['lesson_id']);
                ?>"
            >


            <div class="required field">

                <label for="activity_type">
                    활동 유형
                </label>

                <select
                    id="activity_type"
                    name="activity_type"
                    class="ui dropdown"
                    required
                >

                    <?php
                    foreach ($view_activity_types as $type => $label) {
                    ?>

                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    (string)$type,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >
                            <?php
                            echo htmlspecialchars(
                                (string)$label,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </option>

                    <?php } ?>

                </select>

            </div>


            <div class="required field">

                <label for="activity_title">
                    활동 제목
                </label>

                <input
                    type="text"
                    id="activity_title"
                    name="title"
                    maxlength="255"
                    placeholder="예: 반복문 학습 자료"
                    required
                >

            </div>


            <div class="field">

                <label for="activity_description">
                    활동 설명
                </label>

                <textarea
                    id="activity_description"
                    name="description"
                    rows="7"
                    placeholder="학생에게 안내할 학습 내용과 활동 방법을 입력하세요."
                ></textarea>

                <div class="ui pointing basic label">
                    학습 안내 내용을 입력할 수 있습니다.
                    실제 자료 첨부나 과제 제출 기능은 추후 제공됩니다.
                </div>

            </div>


            <div class="ui divider"></div>


            <button
                type="submit"
                class="ui primary button"
            >
                <i class="plus icon"></i>
                학습 활동 생성
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
