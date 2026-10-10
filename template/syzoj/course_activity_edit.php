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
            학습 활동 수정
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
                일반 학습 활동 수정
            </div>

            <p>
                활동 유형, 제목, 설명, 공개 상태를 수정합니다.
                소속 Lesson과 다른 Activity의 정보는 변경하지 않습니다.
            </p>

            <p>
                이 화면에서는 Contest Activity를 수정할 수 없습니다.
            </p>
        </div>

        <form
            class="ui form"
            method="post"
            action="course_activity_update.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="activity_id"
                value="<?php
                    echo intval($view_activity['activity_id']);
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
                            <?php
                            if (
                                $view_activity['activity_type'] === $type
                            ) {
                                echo 'selected';
                            }
                            ?>
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
                    value="<?php
                        echo htmlspecialchars(
                            (string)$view_activity['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
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
                ><?php
                    echo htmlspecialchars(
                        (string)$view_activity['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>

            </div>

            <div class="field">

                <label for="activity_visible">
                    활동 공개 상태
                </label>

                <select
                    id="activity_visible"
                    name="visible"
                    class="ui dropdown"
                    required
                >

                    <option
                        value="0"
                        <?php
                        if (
                            intval($view_activity['visible']) === 0
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        비공개
                    </option>

                    <option
                        value="1"
                        <?php
                        if (
                            intval($view_activity['visible']) === 1
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        공개
                    </option>

                </select>

                <div class="ui small message">
                    활동을 공개하더라도 소속 Lesson이 비공개라면
                    학생에게 표시되지 않습니다.
                </div>

            </div>

            <div class="ui divider"></div>

            <button
                type="submit"
                class="ui primary button"
            >
                <i class="save icon"></i>
                변경 사항 저장
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
