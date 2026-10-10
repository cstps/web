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
            차시 수정
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
                독립 차시 관리
            </div>

            <p>
                차시 제목, 설명, 공개 상태를 수정합니다.
                Contest와 Activity의 설정은 변경하지 않습니다.
            </p>
        </div>


        <form
            class="ui form"
            method="post"
            action="course_lesson_update.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="lesson_id"
                value="<?php
                    echo intval($view_lesson['lesson_id']);
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
                        echo intval($view_lesson['lesson_no']);
                    ?>"
                    <?php
                    if ($view_linked_contest_count > 0) {
                        echo 'readonly';
                    }
                    ?>
                    required
                >

                <?php if ($view_linked_contest_count > 0) { ?>

                    <div class="ui small message">
                        이 차시에는 Contest
                        <?php echo intval($view_linked_contest_count); ?>개가
                        연결되어 있어 차시 번호를 변경할 수 없습니다.
                    </div>

                <?php } else { ?>

                    <div class="ui pointing basic label">
                        수업 안에서 중복되지 않는 차시 번호를
                        입력하세요.
                    </div>

                <?php } ?>

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
                    value="<?php
                        echo htmlspecialchars(
                            (string)$view_lesson['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
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
                ><?php
                    echo htmlspecialchars(
                        (string)$view_lesson['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>

            </div>


            <div class="field">

                <label for="lesson_visible">
                    차시 공개 상태
                </label>

                <select
                    id="lesson_visible"
                    name="visible"
                    class="ui dropdown"
                >
                    <option
                        value="0"
                        <?php
                        if (intval($view_lesson['visible']) === 0) {
                            echo 'selected';
                        }
                        ?>
                    >
                        비공개
                    </option>

                    <option
                        value="1"
                        <?php
                        if (intval($view_lesson['visible']) === 1) {
                            echo 'selected';
                        }
                        ?>
                    >
                        공개
                    </option>
                </select>

                <div class="ui small message">
                    차시를 공개해도 개별 Activity와 Contest가
                    비공개라면 학생에게 표시되지 않습니다.
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
