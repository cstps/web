<?php
include("template/$OJ_TEMPLATE/header.php");

$simulation_escape = function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$activity_id = intval($view_activity['activity_id']);

$activity_title = $simulation_escape(
    $view_activity['title']
);

$lesson_title = $simulation_escape(
    $view_activity['lesson_title']
);
?>

<link rel="stylesheet" href="/template/syzoj/css/course-simulation-submission-history.css?v=1">

<div class="ui main container"
     style="margin-top:2rem;margin-bottom:3rem;">

    <div class="ui segment">

        <h2 class="ui header">
            <i class="tasks icon"></i>
            <div class="content">
                시뮬레이션 제출 현황
                <div class="sub header">
                    <?php echo $activity_title; ?>
                </div>
            </div>
        </h2>

        <p>
            <strong>차시:</strong>
            <?php echo $lesson_title; ?>
        </p>

        <div class="ui three stackable statistics">

            <div class="statistic">
                <div class="value">
                    <?php echo intval($view_total_students); ?>
                </div>
                <div class="label">
                    전체 수강생
                </div>
            </div>

            <div class="statistic">
                <div class="value">
                    <?php echo intval($view_submitted_students); ?>
                </div>
                <div class="label">
                    제출 학생
                </div>
            </div>

            <div class="statistic">
                <div class="value">
                    <?php echo intval($view_not_submitted_students); ?>
                </div>
                <div class="label">
                    미제출 학생
                </div>
            </div>

        </div>

        <div class="ui divider"></div>

        <h3 class="ui header">
            <i class="users icon"></i>
            학생별 제출 현황
        </h3>

        <?php if (empty($view_students)) { ?>

            <div class="ui message">
                현재 활성 수강생이 없습니다.
            </div>

        <?php } else { ?>

            <div style="overflow-x:auto;">

                <table class="ui celled compact table">

                    <thead>
                        <tr>
                            <th>학생 ID</th>
                            <th>학번</th>
                            <th>제출 횟수</th>
                            <th>최근 제출</th>
                            <th>최근 제출 점수</th>
                            <th>제출 기록</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($view_students as $student) { ?>

                        <?php
                        $student_id = (string)$student['user_id'];

                        $student_history =
                            $view_submission_history[$student_id]
                            ?? array();

                        $submission_count = intval(
                            $student['submission_count']
                        );

                        // 제출 기록은 제출 회차 내림차순으로 조회되어 있다.
                        $latest_submission_for_summary =
                            $student_history[0] ?? null;
                        $latest_submission_grade_text = '-';

                        if (is_array($latest_submission_for_summary)) {
                            $latest_grade_state = (string)(
                                $latest_submission_for_summary['grading_status']
                                ?? ''
                            );
                            $latest_grade_score =
                                $latest_submission_for_summary['grade_score']
                                ?? null;
                            $latest_grade_max =
                                $latest_submission_for_summary['grade_max_score']
                                ?? null;

                            $latest_grade_valid = (
                                $latest_grade_state === 'graded' &&
                                is_numeric($latest_grade_score) &&
                                is_numeric($latest_grade_max) &&
                                is_finite((float)$latest_grade_score) &&
                                is_finite((float)$latest_grade_max) &&
                                (float)$latest_grade_score >= 0 &&
                                (float)$latest_grade_max > 0 &&
                                (float)$latest_grade_score <=
                                    (float)$latest_grade_max
                            );

                            if ($latest_grade_valid) {
                                $latest_submission_grade_text =
                                    number_format(
                                        (float)$latest_grade_score, 2, '.', ''
                                    ) . ' / ' .
                                    number_format(
                                        (float)$latest_grade_max, 2, '.', ''
                                    ) . '점';
                            } elseif (
                                $latest_grade_state === 'graded' &&
                                $latest_grade_score === null &&
                                $latest_grade_max === null
                            ) {
                                $latest_submission_grade_text = '미산정';
                            } else {
                                $latest_grade_labels = array(
                                    '' => '채점 기록 없음',
                                    'pending' => '채점 대기',
                                    'running' => '채점 중',
                                    'error' => '처리 오류',
                                );

                                $latest_submission_grade_text =
                                    $latest_grade_labels[$latest_grade_state]
                                    ?? '점수 확인 필요';
                            }
                        }
                        ?>

                        <tr>
                            <td>
                                <?php echo $simulation_escape(
                                    $student_id
                                ); ?>
                            </td>

                            <td>
                                <?php echo $simulation_escape(
                                    $student['student_no'] ?? ''
                                ); ?>
                            </td>

                            <td>
                                <?php if ($submission_count > 0) { ?>
                                    <span class="ui teal label">
                                        <?php echo $submission_count; ?>회
                                    </span>
                                <?php } else { ?>
                                    <span class="ui basic label">
                                        미제출
                                    </span>
                                <?php } ?>
                            </td>

                            <td>
                                <?php if ($submission_count > 0) { ?>

                                    <?php echo $simulation_escape(
                                        $student['latest_at']
                                    ); ?>

                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>

                            <td
                                class="simulation-latest-grade"
                                data-latest-submission-id="<?php
                                    echo $simulation_escape(
                                        (string)(
                                            $latest_submission_for_summary['submission_id']
                                            ?? ''
                                        )
                                    );
                                ?>"
                            >
                                <?php echo $simulation_escape(
                                    $latest_submission_grade_text
                                ); ?>
                            </td>

                            <td>
                                <?php if (!empty($student_history)) { ?>

                                    <details
                                        class="simulation-student-history"
                                        data-student-id="<?php echo $simulation_escape(
                                            $student_id
                                        ); ?>"
                                    >
                                        <summary>
                                            제출 기록 보기
                                        </summary>

                                        <div class="ui divider"></div>

                                        <?php foreach (
                                            $student_history as $submission
                                        ) { ?>

                                            <?php
                                            $submission_id = intval(
                                                $submission['submission_id']
                                            );

                                            $code_id =
                                                'simulation-submission-code-' .
                                                $submission_id;
                                            ?>

                                            <div class="ui segment">

                                                <div class="ui small header">
                                                    <?php echo intval(
                                                        $submission['submission_no']
                                                    ); ?>회차

                                                    <div class="sub header">
                                                        <?php echo $simulation_escape(
                                                            $submission['submitted_at']
                                                        ); ?>
                                                    </div>
                                                </div>

                                                <div
    class="simulation-grade-result"
    data-activity-id="<?php echo intval($activity_id); ?>"
    data-submission-id="<?php echo $simulation_escape(
        (string)$submission['submission_id']
    ); ?>"
    data-grade-state="<?php echo $simulation_escape(
        (string)($submission['grading_status'] ?? '')
    ); ?>"
>
    <?php require(
        __DIR__ . '/course_simulation_grade_result.php'
    ); ?>
</div>

                                                <textarea
                                                    id="<?php echo $code_id; ?>"
                                                    class="ui fluid"
                                                    rows="12"
                                                    readonly
                                                    spellcheck="false"
                                                    aria-label="<?php
                                                        echo $submission_id;
                                                    ?>번 제출 코드"
                                                    style="width:100%;box-sizing:border-box;font-family:monospace;"
                                                ><?php echo $simulation_escape(
                                                    $submission['source_code']
                                                ); ?></textarea>

                                                <?php if ($view_regrade_reference_no > 0) { ?>
    <form
        class="ui form simulation-regrade-form"
        method="post"
        action="course_simulation_regrade.php"
    >
        <?php echo $view_regrade_csrf_html; ?>

        <input type="hidden" name="activity_id"
            value="<?php echo intval($activity_id); ?>">
        <input type="hidden" name="submission_id"
            value="<?php echo $simulation_escape(
                (string)$submission['submission_id']
            ); ?>">
        <input type="hidden" name="reference_no"
            value="<?php echo $view_regrade_reference_no; ?>">

        <button
            type="submit"
            class="ui mini basic orange button"
            <?php if (in_array(
                $submission['grading_status'] ?? '',
                array('pending', 'running'),
                true
            )) { echo 'disabled'; } ?>
        >
            정답 <?php echo $view_regrade_reference_no; ?>버전으로
            재채점 요청
        </button>
    </form>
<?php } ?>

<button
                                                    type="button"
                                                    class="ui mini basic button simulation-copy-code"
                                                    data-code-id="<?php
                                                        echo $code_id;
                                                    ?>"
                                                >
                                                    <i class="copy icon"></i>
                                                    코드 복사
                                                </button>

                                            </div>

                                        <?php } ?>

                                    </details>

                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

        <div class="ui info message">
            현재 활성 수강생의 정식 제출 기록만 표시합니다.
            임시 저장 코드는 포함되지 않으며,
            제출 코드는 이 화면에서 실행되지 않습니다.
        </div>

        <a
            class="ui basic button"
            href="course_simulation_view.php?activity_id=<?php
                echo $activity_id;
            ?>"
        >
            <i class="arrow left icon"></i>
            시뮬레이션으로 돌아가기
        </a>

    </div>

</div>

<script src="/template/syzoj/js/course-simulation-submissions.js?v=2"></script>
<script src="/template/syzoj/js/course-simulation-submission-history.js?v=1" defer></script>
<script src="/template/syzoj/js/course-simulation-teacher-grade-status.js?v=2" defer></script>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
