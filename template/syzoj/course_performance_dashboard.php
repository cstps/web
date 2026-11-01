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
    width: auto !important;
    min-width: 0;
}

.course-performance-problem {
    width: 88px;
    min-width: 88px;
    max-width: 88px;
    text-align: center;
    white-space: nowrap;
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

/* 차시 옆 활동 목록 */
.dashboard-activity-row {
    display: grid;
    grid-template-columns: 80px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
    padding: 12px 0;
    border-top: 1px solid #eee;
}
.dashboard-activity-lesson.ui.header {
    margin: 0;
    padding-top: 8px;
}
.dashboard-activity-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
}
.dashboard-activity-item {
    max-width: 100%;
}
.dashboard-activity-links .ui.button {
    margin: 0;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
    text-align: left;
}
@media (max-width: 600px) {
    .dashboard-activity-row {
        grid-template-columns: minmax(0, 1fr);
        gap: 8px;
    }
    .dashboard-activity-lesson.ui.header {
        padding-top: 0;
    }
}

</style>


<div class="course-performance-dashboard">

    <div class="course-performance-head">

        <div>
            <h2>
                수업 현황
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

            <?php if (intval($selected_contest_id) > 0) { ?>

                <a
                    class="ui small violet basic button"
                    href="contest_process.php?cid=<?php
                        echo intval($selected_contest_id);
                    ?>"
                    title="현재 선택한 차시의 학생별 문제 해결과정을 자세히 분석합니다."
                >
                    <i class="chart bar icon"></i>
                    차시 상세 분석
                </a>

            <?php } ?>

        </div>

    </div>


    <?php
    $dashboard_activity_groups = array();
    foreach ($view_contests as $dashboard_contest) {
        $number = (int)$dashboard_contest['lesson_no'];
        $dashboard_activity_groups[$number][] = array(
            'type' => 'contest',
            'id' => (int)$dashboard_contest['contest_id'],
            'title' => (string)($dashboard_contest['activity_title'] ?? $dashboard_contest['title']),
            'visible' => !empty($dashboard_contest['visible'])
        );
    }
    foreach ($dashboard_simulation['activities'] as $dashboard_activity) {
        $number = (int)$dashboard_activity['lesson_no'];
        $dashboard_activity_groups[$number][] = array(
            'type' => 'simulation',
            'id' => (int)$dashboard_activity['activity_id'],
            'title' => (string)$dashboard_activity['title'],
            'visible' => !empty($dashboard_activity['visible']) &&
                !empty($dashboard_activity['lesson_visible'])
        );
    }
    krsort($dashboard_activity_groups, SORT_NUMERIC);
    $dashboard_activity_escape = static function ($value) {
        return htmlspecialchars(
            (string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
        );
    };
    ?>
    <section class="ui segment" aria-labelledby="dashboard-activity-title">
        <h3 class="ui header" id="dashboard-activity-title">학습활동 선택</h3>
        <p>차시별 활동을 선택하면 아래에 해당 활동의 현황이 표시됩니다.</p>
        <?php if ($dashboard_simulation['error'] !== '' && $dashboard_activity_type !== 'simulation'): ?>
            <div class="ui warning message" role="alert"><?= $dashboard_activity_escape($dashboard_simulation['error']) ?></div>
        <?php endif; ?>

        <?php foreach ($dashboard_activity_groups as $dashboard_lesson_no => $dashboard_items): ?>
            <div class="dashboard-activity-row">
                <h4 class="ui header dashboard-activity-lesson"><?= $dashboard_lesson_no > 0 ? (int)$dashboard_lesson_no . '차시' : '차시 미배정' ?></h4>
                <div class="dashboard-activity-links">
                    <?php foreach ($dashboard_items as $dashboard_item):
                        $simulation = $dashboard_item['type'] === 'simulation';
                        $active = $dashboard_activity_type === $dashboard_item['type'] &&
                            $dashboard_item['id'] === ($simulation
                                ? (int)($dashboard_simulation['selected']['activity_id'] ?? 0)
                                : (int)$selected_contest_id);
                        $url = 'course_performance_dashboard.php?' . http_build_query(array(
                            'course_id' => (int)$course_id,
                            'activity_type' => $dashboard_item['type'],
                            $simulation ? 'simulation_activity_id' : 'contest_id' => $dashboard_item['id']
                        ));
                    ?>
                        <div class="dashboard-activity-item">
                            <a class="ui small <?= $simulation ? 'teal' : 'green' ?> <?= $active ? '' : 'basic' ?> button"
                               href="<?= $dashboard_activity_escape($url) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <?= $active ? '✓ ' : '' ?><?= $simulation ? '시뮬레이션' : '코딩 실습' ?> · <?= $dashboard_activity_escape($dashboard_item['title']) ?><?= $dashboard_item['visible'] ? '' : ' [비공개]' ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$dashboard_activity_groups && $dashboard_simulation['error'] === ''): ?>
            <p>조회할 활성 코딩 실습·시뮬레이션 활동이 없습니다.</p>
        <?php endif; ?>
    </section>

    <?php if ($dashboard_activity_type === 'simulation') {
        require(__DIR__ . '/course_dashboard_simulation.php');
    } ?>

    <?php if ($view_selected_contest !== null) { ?>
        <h3 class="ui header">코딩 실습 · <?= $dashboard_activity_escape(
            $view_selected_contest['activity_title'] ?? $view_selected_contest['title']
        ) ?></h3>

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

                            <th
                                class="center aligned course-performance-problem"
                                style="padding:0;"
                            >
                                <a
                                    href="problem.php?cid=<?php
                                        echo intval(
                                            $selected_contest_id
                                        );
                                    ?>&amp;pid=<?php
                                        echo intval(
                                            $problem['num']
                                        );
                                    ?>"
                                    target="_blank"
                                    title="<?php
                                        echo htmlspecialchars(
                                            isset($problem['title'])
                                                ? $problem['title']
                                                : '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                    style="
                                        display:block;
                                        width:100%;
                                        height:100%;
                                        padding:0.8em 0.6em;
                                        box-sizing:border-box;
                                    "
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $problem['label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </a>
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


                    </tr>
                </thead>


                <tbody>

                    <?php
                    if (count($student_matrix) === 0) {
                    ?>

                        <tr>
                            <td
                                colspan="<?php
                                    echo count($contest_problems) + 4;
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

                                <a
                                    href="course_student_view.php?course_id=<?php
                                        echo intval($course_id);
                                    ?>&user_id=<?php
                                        echo urlencode($user_id);
                                    ?>"
                                    target="_blank"
                                    title="학생 학습현황 보기"
                                >
                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $user_id,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </strong>
                                </a>

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
                                            target="_blank"
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


                        </tr>

                    <?php
                        }
                    }
                    ?>

                </tbody>

            </table>

        </div>


    <?php } elseif ($dashboard_activity_type === 'contest' && count($view_contests) === 0) { ?>

        <div class="ui message">
            이 수업에 연결된 활성 코딩 실습이 없습니다.
        </div>

    <?php } ?>

</div>


<?php
require_once(
    "template/$OJ_TEMPLATE/footer.php"
);
?>
