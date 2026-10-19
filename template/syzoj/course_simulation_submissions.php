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

                                                <?php
                                                $grade_status = isset(
                                                    $submission['grading_status']
                                                ) ? (string)$submission['grading_status'] : '';

                                                $grade_labels = array(
                                                    'pending' => '채점 대기',
                                                    'running' => '비교 중',
                                                    'graded' => '비교 완료',
                                                    'error' => '실행 오류',
                                                );

                                                $grade_label = $grade_status === ''
                                                    ? '채점 요청 없음'
                                                    : (
                                                        isset($grade_labels[$grade_status])
                                                        ? $grade_labels[$grade_status]
                                                        : '상태 확인 필요'
                                                    );
                                                ?>

                                                <div class="ui small message">
                                                    <div class="header">
                                                        <?php echo $simulation_escape(
                                                            $grade_label
                                                        ); ?>
                                                    </div>

                                                    <?php if ($grade_status !== '') { ?>
                                                        <p>
                                                            채점 <?php echo intval(
                                                                $submission['grading_no']
                                                            ); ?>회차
                                                            <?php if (isset(
                                                                $submission['grade_reference_no']
                                                            )) { ?>
                                                                · 정답 버전
                                                                <?php echo $simulation_escape(
                                                                    $submission['grade_reference_no']
                                                                ); ?>
                                                            <?php } ?>
                                                        </p>
                                                    <?php } ?>

                                                    <?php if ($grade_status === 'graded') { ?>
                                                        <p>
                                                            <?php
                                                            foreach (array(
                                                                'grade_foreground_match' => '전경 일치율',
                                                                'grade_foreground_overlap' => '전경 겹침률',
                                                            ) as $metric_key => $metric_label) {
                                                                $metric_value = isset($submission[$metric_key])
                                                                    ? $submission[$metric_key]
                                                                    : null;

                                                                $metric_text = '확인 불가';

                                                                if (
                                                                    is_numeric($metric_value) &&
                                                                    is_finite((float)$metric_value) &&
                                                                    (float)$metric_value >= 0 &&
                                                                    (float)$metric_value <= 100
                                                                ) {
                                                                    $metric_text = number_format(
                                                                        (float)$metric_value,
                                                                        3,
                                                                        '.',
                                                                        ''
                                                                    ) . '%';
                                                                }
                                                                ?>
                                                                <span class="ui basic label">
                                                                    <?php echo $simulation_escape(
                                                                        $metric_label . ': ' . $metric_text
                                                                    ); ?>
                                                                </span>
                                                            <?php } ?>
                                                        </p>
                                                        <?php
                                                        $tolerance_value =
                                                            $submission['grade_tolerance_coverage'] ?? null;
                                                        $tolerance_radius =
                                                            $submission['grade_tolerance_radius'] ?? null;

                                                        $tolerance_valid = (
                                                            is_numeric($tolerance_value) &&
                                                            is_finite((float)$tolerance_value) &&
                                                            (float)$tolerance_value >= 0 &&
                                                            (float)$tolerance_value <= 100 &&
                                                            ctype_digit((string)$tolerance_radius) &&
                                                            intval($tolerance_radius) >= 0 &&
                                                            intval($tolerance_radius) <= 10
                                                        );
                                                        ?>

                                                        <p>
                                                            <?php if ($tolerance_valid) { ?>
                                                                <span class="ui blue basic label">
                                                                    허용 오차 충족률:
                                                                    <?php echo $simulation_escape(
                                                                        number_format(
                                                                            (float)$tolerance_value,
                                                                            3,
                                                                            '.',
                                                                            ''
                                                                        ) . '%'
                                                                    ); ?>
                                                                </span>

                                                                <span class="ui basic label">
                                                                    위치 허용 반경:
                                                                    <?php echo intval(
                                                                        $tolerance_radius
                                                                    ); ?>픽셀
                                                                </span>
                                                            <?php } elseif (
                                                                $tolerance_value === null &&
                                                                $tolerance_radius === null
                                                            ) { ?>
                                                                <span class="ui basic label">
                                                                    허용 오차 비교: 미산출
                                                                </span>
                                                            <?php } else { ?>
                                                                <span class="ui basic label">
                                                                    허용 오차 정보 확인 필요
                                                                </span>
                                                            <?php } ?>
                                                        </p>

                                                        <p>
                                                            그림 비교 지표입니다.
                                                            공식 점수는 아직 산정하지 않습니다.
                                                        </p>
                                                    <?php } elseif ($grade_status === 'error') { ?>
                                                        <p>
                                                            코드 실행 또는 그림 비교 처리 중
                                                            오류가 발생했습니다.
                                                        </p>
                                                    <?php } ?>
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
