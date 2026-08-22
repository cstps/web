<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>


<div class="course-page">

    <div class="course-page-header">

        <h1 class="ui header">
            내 수업
        </h1>

        <div class="course-page-description">
            수강 중인 수업과 학습 진행 상황을 확인할 수 있습니다.
        </div>

    </div>


    <?php
    // ========================================================
    // 수강 중인 Course가 없는 경우
    // ========================================================

    if (empty($view_courses)) {
    ?>

        <div class="ui info message">

            <div class="header">
                등록된 수업이 없습니다.
            </div>

            <p>
                현재 수강 중인 수업이 없습니다.
            </p>

        </div>

    <?php
    }
    else {
    ?>

        <div class="course-list">

            <?php
            foreach ($view_courses as $course) {

                $course_id =
                    intval($course['course_id']);

                $course_status =
                    intval($course['status']);

                $lesson_count =
                    intval($course['lesson_count']);

                $ongoing_lesson_count =
                    intval($course['ongoing_lesson_count']);

                $problem_count =
                    intval($course['problem_count']);

                $submission_count =
                    intval($course['submission_count']);

                $solved_count =
                    intval($course['solved_count']);


                // ------------------------------------------------
                // 학기 표시
                // ------------------------------------------------

                $semester =
                    intval($course['semester']);

                if ($semester === 1) {

                    $semester_label =
                        '1학기';

                }
                elseif ($semester === 2) {

                    $semester_label =
                        '2학기';

                }
                elseif ($semester > 0) {

                    $semester_label =
                        $semester.'학기';

                }
                else {

                    $semester_label =
                        '학기 구분 없음';
                }


                // ------------------------------------------------
                // 해결률 계산
                // ------------------------------------------------

                $progress_percent = 0;

                if ($problem_count > 0) {

                    $progress_percent =
                        intval(
                            round(
                                ($solved_count / $problem_count) * 100
                            )
                        );

                    if ($progress_percent > 100) {
                        $progress_percent = 100;
                    }
                }


                // ------------------------------------------------
                // 종료된 Course 카드 표시
                // ------------------------------------------------

                $card_class =
                    ($course_status === 1)
                        ? ''
                        : ' inactive';
            ?>


                <div class="ui fluid card course-card<?php echo $card_class; ?>">

                    <div class="content">

                        <div class="header">

                            <?php
                            echo htmlspecialchars(
                                $course['course_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>


                        <div class="meta">

                            <?php
                            echo intval(
                                $course['school_year']
                            );
                            ?>학년도

                            ·

                            <?php
                            echo htmlspecialchars(
                                $semester_label,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>


                            <?php
                            if (!empty($course['school'])) {
                            ?>

                                ·

                                <?php
                                echo htmlspecialchars(
                                    $course['school'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            <?php
                            }
                            ?>

                        </div>


                        <?php
                        if (!empty($course['description'])) {
                        ?>

                            <div class="description">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $course['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                );
                                ?>

                            </div>

                        <?php
                        }
                        ?>


                        <div class="course-meta">

                            <span class="course-meta-item">

                                <i class="list alternate icon"></i>

                                공개 차시:
                                <strong>
                                    <?php echo $lesson_count; ?>
                                </strong>개

                            </span>


                            <span class="course-meta-item">

                                <i class="check circle icon"></i>

                                문제 해결:
                                <strong>
                                    <?php echo $solved_count; ?>
                                </strong>
                                /
                                <?php echo $problem_count; ?>

                            </span>


                            <span class="course-meta-item">

                                <i class="paper plane icon"></i>

                                제출:
                                <strong>
                                    <?php echo $submission_count; ?>
                                </strong>회

                            </span>


                            <span class="course-meta-item">

                                상태:

                                <?php
                                if ($course_status === 1) {
                                ?>

                                    <span class="ui tiny green label">
                                        운영 중
                                    </span>

                                <?php
                                }
                                else {
                                ?>

                                    <span class="ui tiny grey label">
                                        종료
                                    </span>

                                <?php
                                }
                                ?>

                            </span>

                        </div>


                        <?php
                        if (
                            $course_status === 1 &&
                            $ongoing_lesson_count > 0
                        ) {
                        ?>

                            <div class="ui small teal message">

                                <i class="play circle icon"></i>

                                현재 진행 중인 차시가

                                <strong>
                                    <?php echo $ongoing_lesson_count; ?>
                                </strong>개 있습니다.

                            </div>

                        <?php
                        }
                        ?>


                        <div class="ui tiny progress">

                            <div
                                class="bar"
                                style="width: <?php echo $progress_percent; ?>%;"
                            ></div>

                            <div class="label">

                                문제 해결률

                                <?php echo $progress_percent; ?>%

                            </div>

                        </div>


                        <div class="course-actions">

                            <a
                                class="ui small blue button"
                                href="my_course_view.php?course_id=<?php echo $course_id; ?>"
                            >

                                <?php
                                if ($course_status === 1) {
                                ?>

                                    수업 들어가기

                                <?php
                                }
                                else {
                                ?>

                                    지난 수업 보기

                                <?php
                                }
                                ?>

                            </a>

                        </div>

                    </div>

                </div>

            <?php
            }
            ?>

        </div>

    <?php
    }
    ?>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>