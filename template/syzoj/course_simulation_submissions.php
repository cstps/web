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

                            <td>
                                <?php if (!empty($student_history)) { ?>

                                    <details>
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

<script src="/template/syzoj/js/course-simulation-submissions.js?v=1"></script>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
