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
            차시 수정
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
            method="post"
            action="course_contest_update.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="contest_id"
                value="<?php echo intval($contest_id); ?>"
            >


            <div class="field">

                <label>
                    차시 번호
                </label>

                <input
                    type="number"
                    name="lesson_no"
                    min="1"
                    value="<?php
                        echo intval($view_contest['lesson_no']);
                    ?>"
                    required
                >

            </div>


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
                            $view_contest['title'],
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
                            echo date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $view_contest['start_time']
                                )
                            );
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
                            echo date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $view_contest['end_time']
                                )
                            );
                        ?>"
                        required
                    >

                </div>

            </div>


            <div class="field">

                <label>
                    공개 상태
                </label>

                <select
                    name="visible"
                    class="ui dropdown"
                >

                    <option
                        value="1"
                        <?php
                        echo intval($view_contest['visible']) === 1
                            ? 'selected'
                            : '';
                        ?>
                    >
                        공개
                    </option>

                    <option
                        value="0"
                        <?php
                        echo intval($view_contest['visible']) === 0
                            ? 'selected'
                            : '';
                        ?>
                    >
                        숨김
                    </option>

                </select>

            </div>


            <?php
            if (!empty($view_contest['source_contest_id'])) {
            ?>

                <div class="ui info message">

                    원본 대회 번호:
                    <strong>
                        <?php
                        echo intval(
                            $view_contest['source_contest_id']
                        );
                        ?>
                    </strong>

                </div>

            <?php
            }
            ?>


            <button
                type="submit"
                class="ui teal button"
            >
                <i class="save icon"></i>
                수정 내용 저장
            </button>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>