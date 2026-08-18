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
            href="course_view.php?course_id=<?php
                echo intval($course_id);
            ?>"
            class="ui small basic button"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>


        <h1 class="ui header">
            수업 정보 수정
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
            action="course_update.php"
        >

            <?php include("./csrf.php"); ?>


            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >


            <!-- ==================================================
                 수업명
                 ================================================== -->

            <div class="required field">

                <label>
                    수업명
                </label>

                <input
                    type="text"
                    name="course_name"
                    maxlength="100"
                    value="<?php
                        echo htmlspecialchars(
                            $view_course['course_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    required
                >

            </div>


            <!-- ==================================================
                 학교
                 ================================================== -->

            <div class="field">

                <label>
                    학교
                </label>

                <input
                    type="text"
                    name="school"
                    maxlength="100"
                    value="<?php
                        echo htmlspecialchars(
                            isset($view_course['school'])
                                ? $view_course['school']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                >

            </div>


            <div class="two fields">


                <!-- ==============================================
                     학년도
                     ============================================== -->

                <div class="required field">

                    <label>
                        학년도
                    </label>

                    <input
                        type="number"
                        name="school_year"
                        min="2000"
                        max="2100"
                        value="<?php
                            echo intval(
                                $view_course['school_year']
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- ==============================================
                     학기
                     ============================================== -->

                <div class="required field">

                    <label>
                        학기
                    </label>

                    <select
                        name="semester"
                        class="ui dropdown"
                        required
                    >

                        <option
                            value="1"
                            <?php
                            if (
                                intval(
                                    $view_course['semester']
                                ) === 1
                            ) {
                                echo 'selected';
                            }
                            ?>
                        >
                            1학기
                        </option>


                        <option
                            value="2"
                            <?php
                            if (
                                intval(
                                    $view_course['semester']
                                ) === 2
                            ) {
                                echo 'selected';
                            }
                            ?>
                        >
                            2학기
                        </option>

                    </select>

                </div>

            </div>


            <!-- ==================================================
                 설명
                 ================================================== -->

            <div class="field">

                <label>
                    수업 설명
                </label>

                <textarea
                    name="description"
                    rows="5"
                    maxlength="1000"
                ><?php
                    echo htmlspecialchars(
                        isset($view_course['description'])
                            ? $view_course['description']
                            : '',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>

            </div>


            <div style="margin-top:1.5rem;">

                <button
                    type="submit"
                    class="ui teal button"
                >
                    <i class="save icon"></i>
                    저장
                </button>


                <a
                    href="course_view.php?course_id=<?php
                        echo intval($course_id);
                    ?>"
                    class="ui basic button"
                >
                    취소
                </a>

            </div>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>