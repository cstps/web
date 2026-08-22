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
            href="my_course_list.php"
            class="ui small basic button"
        >
            <i class="left arrow icon"></i>
            내 수업 목록
        </a>


        <h1 class="ui header">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </h1>


        <div class="course-page-description">

            <?php
            echo intval(
                $view_course['school_year']
            );
            ?>학년도

            ·

            <?php
            $semester =
                intval($view_course['semester']);

            if ($semester === 1) {

                echo '1학기';

            }
            elseif ($semester === 2) {

                echo '2학기';

            }
            elseif ($semester > 0) {

                echo $semester.'학기';

            }
            else {

                echo '학기 구분 없음';
            }
            ?>


            <?php
            if (!empty($view_course['school'])) {
            ?>

                ·

                <?php
                echo htmlspecialchars(
                    $view_course['school'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            <?php
            }
            ?>

        </div>

    </div>


    <?php
    // ========================================================
    // 종료된 Course 안내
    // ========================================================

    if (intval($view_course['status']) !== 1) {
    ?>

        <div class="ui grey message">

            <div class="header">
                종료된 수업입니다.
            </div>

            <p>
                새로운 문제를 제출할 수 없으며 기존 학습 기록만
                확인할 수 있습니다.
            </p>

        </div>

    <?php
    }
    ?>


    <?php
    if (!empty($view_course['description'])) {
    ?>

        <div class="ui segment">

            <?php
            echo nl2br(
                htmlspecialchars(
                    $view_course['description'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
            ?>

        </div>

    <?php
    }


    // ========================================================
    // 전체 해결률 계산
    // ========================================================

    $total_problem_count =
        intval($view_summary['problem_count']);

    $total_solved_count =
        intval($view_summary['solved_count']);

    $total_progress_percent = 0;


    if ($total_problem_count > 0) {

        $total_progress_percent =
            intval(
                round(
                    (
                        $total_solved_count /
                        $total_problem_count
                    ) * 100
                )
            );

        if ($total_progress_percent > 100) {
            $total_progress_percent = 100;
        }
    }
    ?>


    <!-- ======================================================
         전체 학습 요약
         ====================================================== -->

    <h3 class="ui dividing header">
        나의 학습 현황
    </h3>


    <div class="ui four statistics">

        <div class="statistic">

            <div class="value">
                <?php
                echo intval(
                    $view_summary['lesson_count']
                );
                ?>
            </div>

            <div class="label">
                공개 차시
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php
                echo intval(
                    $view_summary['ongoing_lesson_count']
                );
                ?>
            </div>

            <div class="label">
                진행 중
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <?php
                echo $total_solved_count;
                ?>

                /

                <?php
                echo $total_problem_count;
                ?>

            </div>

            <div class="label">
                문제 해결
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $view_summary['submission_count']
                );
                ?>

            </div>

            <div class="label">
                제출
            </div>

        </div>

    </div>


    <div class="ui small progress">

        <div
            class="bar"
            style="width: <?php
                echo $total_progress_percent;
            ?>%;"
        ></div>

        <div class="label">

            전체 문제 해결률

            <?php echo $total_progress_percent; ?>%

        </div>

    </div>


    <!-- ======================================================
         차시 목록
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;"
    >
        수업 차시
    </h3>


    <?php
    if (empty($view_contests)) {
    ?>

        <div class="ui message">
            현재 공개된 차시가 없습니다.
        </div>

    <?php
    }
    else {
    ?>

        <div class="course-list">

            <?php
            foreach ($view_contests as $contest) {

                $contest_id =
                    intval($contest['contest_id']);

                $lesson_no =
                    intval($contest['lesson_no']);

                $lesson_status =
                    isset($contest['lesson_status'])
                        ? $contest['lesson_status']
                        : 'ended';

                $problem_count =
                    intval($contest['problem_count']);

                $solved_count =
                    intval($contest['solved_count']);

                $submission_count =
                    intval($contest['submission_count']);


                // ------------------------------------------------
                // 차시별 해결률
                // ------------------------------------------------

                $lesson_progress_percent = 0;

                if ($problem_count > 0) {

                    $lesson_progress_percent =
                        intval(
                            round(
                                (
                                    $solved_count /
                                    $problem_count
                                ) * 100
                            )
                        );

                    if ($lesson_progress_percent > 100) {
                        $lesson_progress_percent = 100;
                    }
                }


                // ------------------------------------------------
                // 차시 상태 표시
                // ------------------------------------------------

                switch ($lesson_status) {

                    case 'upcoming':

                        $status_label =
                            '시작 전';

                        $status_class =
                            'blue';

                        break;


                    case 'ongoing':

                        $status_label =
                            '진행 중';

                        $status_class =
                            'green';

                        break;


                    default:

                        $status_label =
                            '종료';

                        $status_class =
                            'grey';

                        break;
                }
            ?>


                <div class="ui fluid card course-card">

                    <div class="content">

                        <div class="header">

                            <?php echo $lesson_no; ?>차시

                            ·

                            <?php
                            echo htmlspecialchars(
                                isset($contest['title'])
                                    ? $contest['title']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>


                        <div class="meta">

                            <span class="ui tiny <?php
                                echo $status_class;
                            ?> label">

                                <?php echo $status_label; ?>

                            </span>

                            대회 번호:

                            <?php echo $contest_id; ?>

                        </div>


                        <div class="course-meta">

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

                                <i class="clock icon"></i>

                                최근 활동:

                                <strong>

                                    <?php
                                    if (!empty($contest['last_activity'])) {

                                        echo htmlspecialchars(
                                            $contest['last_activity'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );

                                    }
                                    else {

                                        echo '없음';
                                    }
                                    ?>

                                </strong>

                            </span>

                        </div>


                        <div class="description">

                            <div>

                                <strong>시작:</strong>

                                <?php
                                echo htmlspecialchars(
                                    $contest['start_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </div>


                            <div>

                                <strong>종료:</strong>

                                <?php
                                echo htmlspecialchars(
                                    $contest['end_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </div>

                        </div>


                        <div class="ui tiny progress">

                            <div
                                class="bar"
                                style="width: <?php
                                    echo $lesson_progress_percent;
                                ?>%;"
                            ></div>

                            <div class="label">

                                차시 문제 해결률

                                <?php
                                echo $lesson_progress_percent;
                                ?>%

                            </div>

                        </div>


                        <div class="course-actions">

                            <?php
                            if (
                                intval($view_course['status']) === 1 &&
                                $lesson_status === 'ongoing'
                            ) {
                            ?>

                                <a
                                    class="ui small green button"
                                    href="contest.php?cid=<?php
                                        echo $contest_id;
                                    ?>"
                                >
                                    <i class="play icon"></i>
                                    학습하기
                                </a>

                            <?php
                            }
                            elseif (
                                intval($view_course['status']) === 1 &&
                                $lesson_status === 'ended'
                            ) {
                            ?>

                                <a
                                    class="ui small blue button"
                                    href="contest.php?cid=<?php
                                        echo $contest_id;
                                    ?>"
                                >
                                    차시 보기
                                </a>

                            <?php
                            }
                            elseif (
                                intval($view_course['status']) === 1 &&
                                $lesson_status === 'upcoming'
                            ) {
                            ?>

                                <button
                                    type="button"
                                    class="ui small disabled button"
                                    disabled
                                >
                                    시작 전
                                </button>

                            <?php
                            }
                            else {
                            ?>

                                <button
                                    type="button"
                                    class="ui small disabled button"
                                    disabled
                                >
                                    수업 종료
                                </button>

                            <?php
                            }


                            if ($submission_count > 0) {
                            ?>

                                <a
                                    class="ui small basic blue button"
                                    href="status.php?cid=<?php
                                        echo $contest_id;
                                    ?>&user_id=<?php
                                        echo rawurlencode($user_id);
                                    ?>"
                                >
                                    <i class="history icon"></i>
                                    내 제출·해결과정
                                </a>

                            <?php
                            }
                            ?>

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