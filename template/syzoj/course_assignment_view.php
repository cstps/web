<?php
include("template/$OJ_TEMPLATE/header.php");

$assignment_title = htmlspecialchars(
    (string)$view_activity['title'],
    ENT_QUOTES,
    'UTF-8'
);

$assignment_description = htmlspecialchars(
    (string)($view_activity['description'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$lesson_title = htmlspecialchars(
    (string)$view_activity['lesson_title'],
    ENT_QUOTES,
    'UTF-8'
);

$course_url = $is_manager
    ? 'course_view.php?course_id=' . intval($course_id)
    : 'my_course_view.php?course_id=' . intval($course_id);
?>

<div class="ui main container"
     style="margin-top:2rem;margin-bottom:3rem;">

    <nav class="ui small breadcrumb" aria-label="현재 위치">

        <a
            class="section"
            href="<?php echo htmlspecialchars(
                $course_url,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >
            <i class="home icon"></i>
            <?php echo $is_manager ? '수업 관리' : '내 수업'; ?>
        </a>

        <i class="right chevron icon divider"></i>

        <span class="active section">
            <i class="clipboard icon"></i>
            학습 과제
        </span>

    </nav>

    <div class="ui segment"
         style="margin-top:1.5rem;">

        <div class="ui small orange label">
            학습 과제
        </div>

        <div class="ui small basic label">
            <?php echo $lesson_title; ?>
        </div>

        <h2 class="ui dividing header">
            <?php echo $assignment_title; ?>
        </h2>

        <?php if ($assignment_description !== '') { ?>

            <div class="ui message">
                <?php echo nl2br($assignment_description); ?>
            </div>

        <?php } ?>

        <div class="ui divider"></div>

        <?php if ($view_assignment_html !== '') { ?>

            <div class="course-assignment-content">
                <?php
                // 서버에서 정제한 HTML만 출력한다.
                echo $view_assignment_html;
                ?>
            </div>

        <?php } else { ?>

            <div class="ui info message">
                아직 등록된 과제 상세 안내가 없습니다.
            </div>

        <?php } ?>

        <?php if (!$is_manager && $is_student) { ?>

            <div class="ui divider"></div>

            <h3 class="ui header">
                <i class="edit outline icon"></i>
                <div class="content">과제 제출</div>
            </h3>

            <?php if ($view_assignment_settings !== null) { ?>

                <div class="ui small message">

                    <?php
                    $due_at = $view_assignment_settings['due_at'];
                    ?>

                    <p>
                        <strong>제출 기한:</strong>
                        <?php
                        echo $due_at === null
                            ? '제한 없음'
                            : htmlspecialchars(
                                (string)$due_at,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>
                    </p>

                    <p>
                        <strong>재제출:</strong>
                        <?php
                        echo $view_assignment_settings['allow_resubmit'] === 1
                            ? '허용'
                            : '허용하지 않음';
                        ?>
                    </p>

                    <p>
                        <strong>기한 후 제출:</strong>
                        <?php
                        echo $view_assignment_settings['allow_late'] === 1
                            ? '허용'
                            : '허용하지 않음';
                        ?>
                    </p>

                </div>

            <?php } ?>

            <?php if ($view_latest_submission !== null) { ?>

                <div class="ui positive message">
                    <div class="header">최근 제출 기록</div>

                    <p>
                        제출 차수:
                        <?php
                        echo intval(
                            $view_latest_submission['submission_no']
                        );
                        ?>회
                    </p>

                    <p>
                        제출 시간:
                        <?php
                        echo htmlspecialchars(
                            (string)$view_latest_submission['submitted_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </p>
                </div>

            <?php } ?>

            <?php if ($view_can_submit) { ?>

                <form
                    class="ui form"
                    method="post"
                    action="course_assignment_submit.php"
                    enctype="multipart/form-data"
                    accept-charset="UTF-8"
                >

                    <?php
                    // 기존 Course 양식과 같은 CSRF 보안 필드.
                    include("./csrf.php");
                    ?>

                    <input
                        type="hidden"
                        name="activity_id"
                        value="<?php
                            echo intval($view_activity['activity_id']);
                        ?>"
                    >

                    <div class="required field">

                        <label for="assignment_content_text">
                            과제 답안
                        </label>

                        <textarea
                            id="assignment_content_text"
                            name="content_text"
                            rows="12"
                            maxlength="1048576"
                            required
                            placeholder="과제 답안을 입력하세요."
                        ></textarea>

                        <div class="ui small message">
                            텍스트 답안을 입력합니다.
                            최대 1MB까지 저장할 수 있습니다.
                            파일 첨부는 추후 제공됩니다.
                        </div>

                    </div>

                    <div class="field">
                        <label for="assignment_file">
                            첨부파일 (선택)
                        </label>

                        <input
                            type="file"
                            id="assignment_file"
                            name="assignment_file"
                        >

                        <div class="ui small message">
                            파일 첨부는 선택 사항입니다.
                            제출 1회당 파일 1개를 첨부할 수 있습니다.
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="ui orange button"
                    >
                        <i class="paper plane icon"></i>
                        <?php
                        echo $view_latest_submission === null
                            ? '과제 제출'
                            : '과제 재제출';
                        ?>
                    </button>

                </form>

            <?php } else { ?>

                <div class="ui warning message">
                    <?php
                    echo htmlspecialchars(
                        (string)$view_submit_message,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

            <?php } ?>

        <?php } ?>

        <?php if (!$is_manager && $is_student) { ?>

            <div class="ui divider"></div>

            <h3 class="ui header">
                <i class="history icon"></i>
                <div class="content">내 과제 제출 이력</div>
            </h3>

            <?php if (empty($view_submission_history)) { ?>

                <div class="ui info message">
                    아직 제출한 과제가 없습니다.
                </div>

            <?php } else { ?>

                <div class="ui segments">

                    <?php foreach (
                        $view_submission_history as $index => $submission
                    ) { ?>

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

                </div>

            <?php } ?>

        <?php } ?>

        <div class="ui divider"></div>

        <div class="ui segment">

            <div class="ui stackable two column grid middle aligned">

                <div class="column">

                    <div class="ui small header">
                        과제 내용을 확인하셨나요?
                    </div>

                    <p>
                        수업으로 돌아가 다른 활동을 확인할 수 있습니다.
                    </p>

                </div>

                <div class="right aligned column">

                    <a
                        class="ui basic button"
                        href="<?php echo htmlspecialchars(
                            $course_url,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >
                        <i class="arrow left icon"></i>
                        수업으로 돌아가기
                    </a>

                    <?php if ($is_manager) { ?>

                        <a
                            class="ui blue button"
                            href="course_assignment_submissions.php?activity_id=<?php
                                echo intval($view_activity['activity_id']);
                            ?>"
                        >
                            <i class="tasks icon"></i>
                            제출 현황
                        </a>

                        <a
                            class="ui orange button"
                            href="course_activity_edit.php?activity_id=<?php
                                echo intval($view_activity['activity_id']);
                            ?>"
                        >
                            <i class="edit icon"></i>
                            과제 수정
                        </a>

                    <?php } ?>

                </div>

            </div>

        </div>

    </div>

</div>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
