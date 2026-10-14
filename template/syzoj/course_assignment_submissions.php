<?php
include("template/$OJ_TEMPLATE/header.php");

$assignment_title = htmlspecialchars(
    (string)$view_activity['title'],
    ENT_QUOTES,
    'UTF-8'
);

$lesson_title = htmlspecialchars(
    (string)$view_activity['lesson_title'],
    ENT_QUOTES,
    'UTF-8'
);

$activity_id = intval($view_activity['activity_id']);
$course_id = intval($course_id);
?>

<div class="ui main container"
     style="margin-top:2rem;margin-bottom:3rem;">

    <div class="ui small breadcrumb">
        <a class="section"
           href="course_view.php?course_id=<?php echo $course_id; ?>">
            수업 관리
        </a>

        <i class="right chevron icon divider"></i>

        <a class="section"
           href="course_assignment_view.php?activity_id=<?php
               echo $activity_id;
           ?>">
            학습 과제
        </a>

        <i class="right chevron icon divider"></i>

        <span class="active section">
            제출 현황
        </span>
    </div>

    <div class="ui segment" style="margin-top:1.5rem;">

        <div class="ui small orange label">
            학습 과제
        </div>

        <div class="ui small basic label">
            <?php echo $lesson_title; ?>
        </div>

        <h2 class="ui dividing header">
            <?php echo $assignment_title; ?> — 제출 현황
        </h2>

        <div class="ui three small statistics">

            <div class="statistic">
                <div class="value">
                    <?php echo intval($view_total_students); ?>
                </div>
                <div class="label">전체 학생</div>
            </div>

            <div class="statistic">
                <div class="value">
                    <?php echo intval($view_submitted_students); ?>
                </div>
                <div class="label">제출 완료</div>
            </div>

            <div class="statistic">
                <div class="value">
                    <?php echo intval($view_not_submitted_students); ?>
                </div>
                <div class="label">미제출</div>
            </div>

        </div>

        <div class="ui divider"></div>

        <h3 class="ui header">학생별 제출 현황</h3>

        <?php if (empty($view_students)) { ?>

            <div class="ui info message">
                현재 활성 수강생이 없습니다.
            </div>

        <?php } else { ?>

            <div style="overflow-x:auto;">

                <table class="ui celled striped table">

                    <thead>
                        <tr>
                            <th>학번</th>
                            <th>아이디</th>
                            <th>제출 상태</th>
                            <th>제출 횟수</th>
                            <th>최근 제출 일시</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($view_students as $student) { ?>

                            <?php
                            $user_id = (string)$student['user_id'];

                            $student_no = htmlspecialchars(
                                (string)($student['student_no'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            $safe_user_id = htmlspecialchars(
                                $user_id,
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            $count = intval(
                                $student['submission_count']
                            );
                            ?>

                            <tr>
                                <td><?php echo $student_no; ?></td>
                                <td><?php echo $safe_user_id; ?></td>

                                <td>
                                    <?php if ($count > 0) { ?>
                                        <span class="ui mini green label">
                                            제출 완료
                                        </span>
                                    <?php } else { ?>
                                        <span class="ui mini grey label">
                                            미제출
                                        </span>
                                    <?php } ?>
                                </td>

                                <td><?php echo $count; ?>회</td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        (string)($student['latest_at'] ?? '-'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

            <div class="ui divider"></div>

            <h3 class="ui header">학생별 제출 답안</h3>

            <?php foreach ($view_students as $student) { ?>

                <?php
                $user_id = (string)$student['user_id'];

                $safe_user_id = htmlspecialchars(
                    $user_id,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $student_no = htmlspecialchars(
                    (string)($student['student_no'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );

                $submissions =
                    $view_submission_history[$user_id] ?? array();
                ?>

                <details class="ui segment">

                    <summary style="cursor:pointer;padding:0.5rem 0;">

                        <strong>
                            <?php echo $safe_user_id; ?>
                        </strong>

                        <?php if ($student_no !== '') { ?>
                            (<?php echo $student_no; ?>)
                        <?php } ?>

                        <span class="ui mini basic label">
                            <?php echo count($submissions); ?>건
                        </span>

                    </summary>

                    <?php if (empty($submissions)) { ?>

                        <div class="ui info message">
                            제출 기록이 없습니다.
                        </div>

                    <?php } else { ?>

                        <?php foreach ($submissions as $index => $submission) { ?>

                            <details
                                class="ui segment"
                                <?php echo $index === 0 ? 'open' : ''; ?>
                            >

                                <summary style="cursor:pointer;padding:0.5rem 0;">

                                    <strong>
                                        <?php
                                        echo intval(
                                            $submission['submission_no']
                                        );
                                        ?>차 제출
                                    </strong>

                                    <?php if ($index === 0) { ?>
                                        <span class="ui mini green label">
                                            최근 제출
                                        </span>
                                    <?php } ?>

                                    <span style="margin-left:0.5rem;">
                                        <?php
                                        echo htmlspecialchars(
                                            (string)$submission['submitted_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </span>

                                </summary>

                                <div
                                    class="ui secondary segment"
                                    style="white-space:pre-wrap;overflow-wrap:anywhere;"
                                ><?php
                                    echo htmlspecialchars(
                                        (string)$submission['content_text'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?></div>

                            <?php
                            $submission_files =
                                $view_submission_files[
                                    (string)$submission['submission_id']
                                ] ?? array();
                            ?>

                            <?php if (!empty($submission_files)) { ?>

                                <div class="ui small segment">

                                    <strong>첨부파일</strong>

                                    <?php foreach (
                                        $submission_files as $file
                                    ) { ?>

                                        <div style="margin-top:0.5rem;">

                                            <a
                                                class="ui small basic blue button"
                                                href="course_assignment_file_download.php?file_id=<?php
                                                    echo intval($file['file_id']);
                                                ?>"
                                            >
                                                <i class="download icon"></i>
                                                <?php
                                                echo htmlspecialchars(
                                                    (string)$file['original_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </a>

                                        </div>

                                    <?php } ?>

                                </div>

                            <?php } ?>


                            </details>

                        <?php } ?>

                    <?php } ?>

                </details>

            <?php } ?>

        <?php } ?>

        <div class="ui divider"></div>

        <a
            class="ui basic button"
            href="course_assignment_view.php?activity_id=<?php
                echo $activity_id;
            ?>"
        >
            <i class="arrow left icon"></i>
            과제로 돌아가기
        </a>

    </div>

</div>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
