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
            href="course_student_view.php?course_id=<?php
                echo intval($course_id);
            ?>&user_id=<?php
                echo urlencode($student_user_id);
            ?>"
        >
            <i class="left arrow icon"></i>
            학생 학습현황
        </a>

        <h1 class="ui header">
            학생 메모 수정
        </h1>

    </div>


    <div class="ui segment">

        <form
            class="ui form"
            method="post"
            action="course_student_memo_update.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="user_id"
                value="<?php
                    echo htmlspecialchars(
                        $student_user_id,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >

            <input
                type="hidden"
                name="memo_id"
                value="<?php echo intval($memo_id); ?>"
            >


            <div class="field">

                <label>
                    메모 대상
                </label>

                <select
                    name="contest_id"
                    class="ui dropdown"
                >

                    <option value="">
                        수업 전체
                    </option>

                    <?php
                    foreach ($view_contests as $contest) {

                        $cid =
                            intval($contest['contest_id']);

                        $selected =
                            intval($view_memo['contest_id']) === $cid;
                    ?>

                        <option
                            value="<?php echo $cid; ?>"
                            <?php echo $selected ? 'selected' : ''; ?>
                        >
                            <?php
                            echo intval(
                                $contest['lesson_no']
                            );
                            ?>차시 -
                            <?php
                            echo htmlspecialchars(
                                $contest['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </option>

                    <?php
                    }
                    ?>

                </select>

            </div>


            <div class="field">

                <label>
                    관찰 메모
                </label>

                <textarea
                    name="memo_text"
                    rows="6"
                    maxlength="5000"
                    required
                ><?php
                    echo htmlspecialchars(
                        $view_memo['memo_text'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>

            </div>


            <button
                type="submit"
                class="ui teal button"
            >
                수정 저장
            </button>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>