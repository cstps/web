<?php
require_once(
    "template/$OJ_TEMPLATE/header.php"
);
?>

<style>
.course-performance-dashboard {
    max-width: 1500px;
    margin: 24px auto;
    padding: 0 16px;
}

.course-performance-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.course-performance-head h2 {
    margin: 0;
}

.course-performance-sub {
    margin-top: 6px;
    color: #666;
}

.course-performance-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.course-performance-lessons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 18px;
}

.course-performance-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

.course-performance-summary-item {
    min-width: 120px;
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
}

.course-performance-summary-label {
    color: #666;
    font-size: 12px;
}

.course-performance-summary-value {
    margin-top: 3px;
    font-size: 20px;
    font-weight: bold;
}

.course-performance-table-wrap {
    overflow-x: auto;
}

.course-performance-table {
    min-width: 900px;
}

.course-performance-problem {
    min-width: 78px;
    text-align: center;
}

.course-performance-problem-link {
    display: block;
    text-decoration: none;
}

@media (max-width: 700px) {
    .course-performance-dashboard {
        padding: 0 8px;
    }
}
</style>


<div class="course-performance-dashboard">

    <div class="course-performance-head">

        <div>
            <h2>
                수행평가 현황
            </h2>

            <div class="course-performance-sub">
                <?php
                echo htmlspecialchars(
                    isset($view_course['course_name'])
                        ? $view_course['course_name']
                        : '',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>
        </div>

        <div class="course-performance-actions">

            <a
                class="ui small basic button"
                href="course_list.php"
            >
                <i class="list icon"></i>
                수업 목록
            </a>

            <a
                class="ui small basic button"
                href="course_code_monitor.php?course_id=<?php
                    echo intval($course_id);
                ?>"
            >
                <i class="code icon"></i>
                학생 코드 모니터링
            </a>

        </div>

    </div>


    <?php if (count($view_contests) > 0) { ?>

        <div class="course-performance-lessons">

            <?php foreach ($view_contests as $contest) {

                $contest_id =
                    intval($contest['contest_id']);

                $lesson_no =
                    intval($contest['lesson_no']);

                $is_selected =
                    $contest_id ===
                    intval($selected_contest_id);
            ?>

                <a
                    class="ui small <?php
                        echo $is_selected
                            ? 'blue'
                            : 'basic';
                    ?> button"
                    href="course_performance_dashboard.php?course_id=<?php
                        echo intval($course_id);
                    ?>&contest_id=<?php
                        echo $contest_id;
                    ?>"
                >
                    <?php
                    if ($lesson_no > 0) {
                        echo $lesson_no . '차시';
                    } else {
                        echo '차시';
                    }
                    ?>

                    <?php
                    if (
                        isset($contest['title']) &&
                        trim($contest['title']) !== ''
                    ) {
                        echo ' · ' .
                            htmlspecialchars(
                                $contest['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                    }
                    ?>
                </a>

            <?php } ?>

        </div>

    <?php } ?>


    <?php if ($view_selected_contest !== null) { ?>

        <?php
        $problem_count =
            count($contest_problems);

        $solved_total =
            0;

        $submit_total =
            0;

        $ai_total =
            0;

        foreach ($student_matrix as $student) {

            $solved_total +=
                isset($student['solved_count'])
                    ? intval($student['solved_count'])
                    : 0;

            $submit_total +=
                isset($student['total_submit'])
                    ? intval($student['total_submit'])
                    : 0;

            $ai_total +=
                isset($student['total_ai'])
                    ? intval($student['total_ai'])
                    : 0;
        }
        ?>

        <div class="course-performance-summary">

            <div class="course-performance-summary-item">
                <div class="course-performance-summary-label">
                    학생
                </div>
                <div class="course-performance-summary-value">
                    <?php echo intval($total_student_count); ?>
                </div>
            </div>

            <div class="course-performance-summary-item">
                <div class="course-performance-summary-label">
                    문제
                </div>
                <div class="course-performance-summary-value">
                    <?php echo intval($problem_count); ?>
                </div>
            </div>

            <div class="course-performance-summary-item">
                <div class="course-performance-summary-label">
                    총 제출
                </div>
                <div class="course-performance-summary-value">
                    <?php echo intval($submit_total); ?>
                </div>
            </div>

            <div class="course-performance-summary-item">
                <div class="course-performance-summary-label">
                    AI 활용 기록
                </div>
                <div class="course-performance-summary-value">
                    <?php echo intval($ai_total); ?>
                </div>
            </div>

        </div>


        <div class="course-performance-table-wrap">

            <table
                class="ui celled compact table course-performance-table"
            >

                <thead>
                    <tr>

                        <th>
                            학생
                        </th>

                        <?php
                        foreach (
                            $contest_problems
                            as
                            $num => $problem
                        ) {
                        ?>

                            <th class="center aligned">
                                <?php
                                echo htmlspecialchars(
                                    $problem['label'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </th>

                        <?php } ?>

                        <th class="center aligned">
                            해결
                        </th>

                        <th class="center aligned">
                            제출
                        </th>

                        <th class="center aligned">
                            AI
                        </th>

                        <th class="center aligned">
                            관리
                        </th>

                    </tr>
                </thead>


                <tbody>

                    <?php
                    if (count($student_matrix) === 0) {
                    ?>

                        <tr>
                            <td
                                colspan="<?php
                                    echo count($contest_problems) + 5;
                                ?>"
                                class="center aligned"
                            >
                                등록된 활성 학생이 없습니다.
                            </td>
                        </tr>

                    <?php
                    } else {

                        foreach (
                            $student_matrix
                            as
                            $user_id => $student
                        ) {

                            $student_problems =
                                isset($student['problems']) &&
                                is_array($student['problems'])
                                    ? $student['problems']
                                    : array();

                            $student_solved =
                                isset($student['solved_count'])
                                    ? intval(
                                        $student['solved_count']
                                    )
                                    : 0;

                            $student_submit =
                                isset($student['total_submit'])
                                    ? intval(
                                        $student['total_submit']
                                    )
                                    : 0;

                            $student_ai =
                                isset($student['total_ai'])
                                    ? intval(
                                        $student['total_ai']
                                    )
                                    : 0;
                    ?>

                        <tr>

                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $user_id,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </strong>

                                <?php
                                if (
                                    isset($student['nick']) &&
                                    trim($student['nick']) !== '' &&
                                    trim($student['nick']) !== $user_id
                                ) {
                                ?>
                                    <div style="color:#777; font-size:12px;">
                                        <?php
                                        echo htmlspecialchars(
                                            $student['nick'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </div>
                                <?php } ?>

                            </td>


                            <?php
                            foreach (
                                $contest_problems
                                as
                                $num => $problem
                            ) {

                                $status_text =
                                    '미제출';

                                $label_class =
                                    'grey';

                                $latest_solution_id =
                                    0;

                                if (
                                    isset(
                                        $student_problems[$num]
                                    )
                                ) {

                                    $process =
                                        $student_problems[$num];

                                    $submit_count =
                                        intval(
                                            $process['submit_count']
                                        );

                                    $ever_accepted =
                                        intval(
                                            $process['ever_accepted']
                                        );

                                    $latest_solution_id =
                                        intval(
                                            $process['latest_solution_id']
                                        );

                                    if ($ever_accepted === 1) {

                                        $status_text =
                                            '해결';

                                        $label_class =
                                            'green';

                                    } elseif ($submit_count >= 2) {

                                        $status_text =
                                            '반복 미해결';

                                        $label_class =
                                            'red';

                                    } elseif ($submit_count > 0) {

                                        $status_text =
                                            '진행 중';

                                        $label_class =
                                            'orange';
                                    }
                                }
                            ?>

                                <td
                                    class="course-performance-problem"
                                    title="<?php
                                        echo htmlspecialchars(
                                            isset($problem['title'])
                                                ? $problem['title']
                                                : '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                                    <?php
                                    if ($latest_solution_id > 0) {
                                    ?>

                                        <a
                                            class="course-performance-problem-link"
                                            href="solution_process_view.php?sid=<?php
                                                echo $latest_solution_id;
                                            ?>&course_id=<?php
                                                echo intval($course_id);
                                            ?>"
                                        >
                                            <span
                                                class="ui tiny <?php
                                                    echo $label_class;
                                                ?> label"
                                            >
                                                <?php
                                                echo $status_text;
                                                ?>
                                            </span>
                                        </a>

                                    <?php
                                    } else {
                                    ?>

                                        <span
                                            class="ui tiny grey basic label"
                                        >
                                            미제출
                                        </span>

                                    <?php } ?>

                                </td>

                            <?php } ?>


                            <td class="center aligned">
                                <?php
                                echo $student_solved .
                                    '/' .
                                    intval($problem_count);
                                ?>
                            </td>

                            <td class="center aligned">
                                <?php echo $student_submit; ?>
                            </td>

                            <td class="center aligned">
                                <?php echo $student_ai; ?>
                            </td>

                            <td class="center aligned">

                                <a
                                    class="ui tiny teal basic button"
                                    href="course_student_view.php?course_id=<?php
                                        echo intval($course_id);
                                    ?>&user_id=<?php
                                        echo urlencode($user_id);
                                    ?>"
                                >
                                    상세
                                </a>

                            </td>

                        </tr>

                    <?php
                        }
                    }
                    ?>

                </tbody>

            </table>

        </div>


    <?php } elseif (count($view_contests) === 0) { ?>

        <div class="ui message">
            이 수업에 연결된 활성 차시가 없습니다.
        </div>

    <?php } ?>

</div>


<?php
require_once(
    "template/$OJ_TEMPLATE/footer.php"
);
?>
