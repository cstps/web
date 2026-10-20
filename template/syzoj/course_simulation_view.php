<?php

include("template/$OJ_TEMPLATE/header.php");

$simulation_escape = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$course_url = $is_manager
    ? 'course_view.php?course_id=' . intval($course_id)
    : 'my_course_view.php?course_id=' . intval($course_id);

$can_edit_code = intval($view_simulation['allow_edit']) === 1;

$simulation_mode = (string)$view_simulation['simulation_mode'];

$canvas_width = intval($view_simulation['canvas_width']);
$canvas_height = intval($view_simulation['canvas_height']);

?>

<link
    rel="stylesheet"
    href="template/<?php echo $simulation_escape($OJ_TEMPLATE); ?>/css/course.css"
>

<style>
.simulation-action-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin: 16px 0 20px;
}

.simulation-action-toolbar .course-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.simulation-action-toolbar .ui.button {
    margin: 0;
}

.simulation-action-toolbar #simulation-draft-form {
    margin: 0;
}

@media (max-width: 600px) {
    .simulation-action-toolbar,
    .simulation-action-toolbar .course-actions {
        width: 100%;
    }
}
</style>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="<?php echo $simulation_escape($course_url); ?>"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            <?php echo $simulation_escape($view_activity['title']); ?>
        </h1>

        <div class="course-page-description">
            <?php echo $simulation_escape($view_activity['lesson_title']); ?>
        </div>

    </div>

    <div class="ui segment">

        <?php if (!empty($view_activity['description'])) { ?>

            <div class="ui message">
                <?php
                echo nl2br(
                    $simulation_escape($view_activity['description'])
                );
                ?>
            </div>

        <?php } ?>

        <?php if ($simulation_mode === 'drawing') { ?>
            <div class="ui segment">
                <h3 class="ui header">목표 완성 그림</h3>

                <?php if (
                    is_array($view_simulation_target) &&
                    intval($view_simulation_target['target_ready']) === 1
                ) { ?>
                    <p>
                        문제 설명과 아래 그림을 참고하여 코드를 작성하세요.
                    </p>

                    <img
                        class="ui bordered image"
                        src="<?php echo $simulation_escape(
                            '/course_simulation_reference_image.php?activity_id=' .
                            intval($activity_id)
                        ); ?>"
                        alt="목표 완성 그림"
                        width="<?php echo intval(
                            $view_simulation_target['canvas_width']
                        ); ?>"
                        height="<?php echo intval(
                            $view_simulation_target['canvas_height']
                        ); ?>"
                        decoding="async"
                    >
                <?php } else { ?>
                    <p>목표 그림이 아직 준비되지 않았습니다.</p>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="ui small labels">

            <span class="ui blue label">
                p5.js 2.3.4
            </span>

            <span class="ui basic label">
                <?php
                echo $simulation_mode === 'drawing'
                    ? '그림 문제'
                    : '일반 시뮬레이션';
                ?>
            </span>

            <span class="ui basic label">
                <?php echo $canvas_width; ?>
                ×
                <?php echo $canvas_height; ?>
            </span>

        </div>

        <?php if ($simulation_mode === 'drawing') { ?>

            <div class="ui warning message">
                그림 문제 자동채점은 현재 개발 중입니다.
                이 화면에서는 아직 점수를 산출하지 않습니다.
            </div>

        <?php } ?>

        <div class="ui divider"></div>

        <div class="ui stackable two column grid">

            <div class="column">

                <h3 class="ui header">
                    <i class="code icon"></i>
                    코드 편집기
                </h3>

                <div class="field">

                    <label for="simulation-code">
                        p5.js JavaScript 코드
                    </label>

                    <div
                        id="simulation-ace-editor"
                        aria-label="p5.js JavaScript 코드 편집기"
                        style="display:none;width:100%;height:420px;min-height:300px;border:1px solid #ddd;"
                    ></div>

                    <textarea
                        id="simulation-code"
                        name="source_code"
                        form="simulation-draft-form"
                        aria-label="p5.js JavaScript 코드"
                        rows="24"
                        spellcheck="false"
                        <?php echo $can_edit_code ? '' : 'readonly'; ?>
                        style="width:100%;box-sizing:border-box;font-family:monospace;font-size:14px;line-height:1.5;tab-size:4;"
                    ><?php
                        echo $simulation_escape(
                            $view_draft_code !== null
                                ? $view_draft_code
                                : $view_simulation['starter_code']
                        );
                    ?></textarea>

                </div>

                <textarea
                    id="simulation-starter-code"
                    hidden
                    aria-hidden="true"
                ><?php
                    echo $simulation_escape(
                        $view_simulation['starter_code']
                    );
                ?></textarea>

                <?php if (!$can_edit_code) { ?>

                    <div class="ui small message">
                        교사가 코드 수정을 허용하지 않은 활동입니다.
                    </div>

                <?php } ?>

            </div>

            <div class="column">

                <h3 class="ui header">
                    <i class="desktop icon"></i>
                    실행 결과
                </h3>

                <div class="ui segment" style="overflow-x:auto;">

                    <iframe
                        id="simulation-frame"
                        title="p5.js 시뮬레이션 실행 결과"
                        sandbox="allow-scripts"
                        referrerpolicy="no-referrer"
                        style="display:block;width:100%;height:auto;aspect-ratio:<?php echo $canvas_width; ?> / <?php echo $canvas_height; ?>;border:0;background:#fff;"
                    ></iframe>

                </div>

                <p
                    id="simulation-status"
                    class="ui small message"
                    role="status"
                    aria-live="polite"
                >
                    실행 준비 중입니다.
                </p>

            </div>

        </div>

        <div class="ui divider"></div>

        <div class="simulation-action-toolbar">

        <div class="course-actions">

            <button
                type="button"
                id="simulation-run"
                class="ui primary button"
                disabled
            >
                <i class="play icon"></i>
                코드 실행
            </button>

            <button
                type="button"
                id="simulation-stop"
                class="ui basic red button"
                disabled
            >
                <i class="stop icon"></i>
                실행 중지
            </button>

            <button
                type="button"
                id="simulation-reset"
                class="ui basic button"
                disabled
            >
                <i class="undo icon"></i>
                기본 코드 복원
            </button>

        </div>

        <?php
        $can_save_draft = (
            $is_student &&
            $can_edit_code &&
            intval($view_activity['course_status']) === 1 &&
            intval($view_activity['lesson_visible']) === 1 &&
            intval($view_activity['activity_visible']) === 1
        );
        ?>

        <?php if ($can_save_draft) { ?>

            <?php
            $autosave_session_key = $OJ_NAME . '_simulation_autosave';
            $autosave_owner = (string)$_SESSION[$OJ_NAME . '_user_id'];
            $autosave_settings = $_SESSION[$autosave_session_key] ?? null;

            if (
                !is_array($autosave_settings) ||
                !isset(
                    $autosave_settings['user_id'],
                    $autosave_settings['token']
                ) ||
                $autosave_settings['user_id'] !== $autosave_owner ||
                !is_string($autosave_settings['token']) ||
                !preg_match('/^[a-f0-9]{64}$/D', $autosave_settings['token'])
            ) {
                $autosave_settings = array(
                    'user_id' => $autosave_owner,
                    'token' => bin2hex(random_bytes(32)),
                );
                $_SESSION[$autosave_session_key] = $autosave_settings;
            }
            ?>

            <div
                id="simulation-autosave-config"
                data-revision="<?php echo $simulation_escape($view_draft_revision); ?>"
                data-activity-id="<?php echo intval($activity_id); ?>"
                data-editor-id="simulation-code"
                data-token="<?php echo $simulation_escape(
                    $autosave_settings['token']
                ); ?>"
                hidden
            ></div>

            <p id="simulation-autosave-status" role="status">
                자동 저장 대기
            </p>


            <form
                id="simulation-draft-form"
                class="ui form"
                method="post"
                action="course_simulation_draft_save.php"
            >

                <input
                    id="simulation-draft-revision"
                    type="hidden"
                    name="expected_revision"
                    value="<?php echo $simulation_escape(
                        $view_draft_revision
                    ); ?>"
                >

                <?php
                ob_start();
                include("./csrf.php");
                echo ob_get_clean();
                ?>

                <input
                    type="hidden"
                    name="activity_id"
                    value="<?php echo intval($activity_id); ?>"
                >

                <button
                    type="submit"
                    class="ui teal button"
                >
                    <i class="save icon"></i>
                    코드 임시 저장
                </button>
            </form>

        <?php } ?>

        <?php
        $can_submit_code = (
            $is_student &&
            intval($view_simulation['allow_submit']) === 1 &&
            intval($view_activity['course_status']) === 1 &&
            intval($view_activity['lesson_visible']) === 1 &&
            intval($view_activity['activity_visible']) === 1
        );
        ?>

        <?php if ($can_submit_code) { ?>

            <form
                id="simulation-submit-form"
                class="ui form"
                method="post"
                action="course_simulation_submit.php"
            >
                <?php
                ob_start();
                include("./csrf.php");
                echo ob_get_clean();
                ?>

                <input
                    type="hidden"
                    name="activity_id"
                    value="<?php echo intval($activity_id); ?>"
                >

                <textarea
                    id="simulation-submit-code"
                    name="source_code"
                    hidden
                ></textarea>

                <button
                    type="submit"
                    class="ui primary button"
                >
                    <i class="send icon"></i>
                    코드 제출
                </button>
            </form>

        <?php } ?>

        </div>

        <?php if ($is_student) { ?>

            <div class="ui segment">

                <h3 class="ui header">
                    <i class="history icon"></i>
                    나의 코드 제출 이력
                </h3>

                <?php if (empty($view_simulation_submissions)) { ?>

                    <div class="ui message">
                        아직 제출한 코드가 없습니다.
                    </div>

                <?php } else { ?>

                    <p>
                        최근 제출 기록
                        <?php echo count($view_simulation_submissions); ?>건
                        (최대 10건 표시)
                    </p>

                    <table
                        id="simulation-submission-history"
                        class="ui compact unstackable table"
                        data-activity-id="<?php echo intval($activity_id); ?>"
                    >

                        <thead>
                            <tr>
                                <th>제출 번호</th>
                                <th>제출 일시</th>
                                <th>채점 상태</th>
                                <th>점수</th>
                                <th>기능</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach (
                                $view_simulation_submissions as $submission
                            ) { ?>

                                <?php
                                $history_grade_status = (string)(
                                    $submission['grading_status'] ?? ''
                                );
                                $history_score =
                                    $submission['grade_score'] ?? null;
                                $history_max_score =
                                    $submission['grade_max_score'] ?? null;

                                $history_score_valid = (
                                    $history_score !== null &&
                                    $history_max_score !== null &&
                                    is_numeric($history_score) &&
                                    is_numeric($history_max_score) &&
                                    is_finite((float)$history_score) &&
                                    is_finite((float)$history_max_score) &&
                                    (float)$history_score >= 0 &&
                                    (float)$history_max_score > 0 &&
                                    (float)$history_score <=
                                        (float)$history_max_score
                                );

                                $history_status_labels = array(
                                    '' => '채점 기록 없음',
                                    'pending' => '채점 대기',
                                    'running' => '채점 중',
                                    'graded' => $history_score_valid
                                        ? '채점 완료'
                                        : '비교 완료',
                                    'error' => '처리 오류',
                                );

                                $history_status_label =
                                    $history_status_labels[$history_grade_status]
                                    ?? '상태 확인 필요';

                                $history_id =
                                    'simulation-history-code-' .
                                    intval($submission['submission_id']);
                                ?>

                                <tr
                                    data-submission-id="<?php echo intval(
                                        $submission['submission_id']
                                    ); ?>"
                                    data-grade-state="<?php echo $simulation_escape(
                                        $history_grade_status
                                    ); ?>"
                                >

                                    <td>
                                        <?php
                                        echo intval(
                                            $submission['submission_no']
                                        );
                                        ?>회차
                                    </td>

                                    <td>
                                        <?php
                                        echo $simulation_escape(
                                            (string)$submission['submitted_at']
                                        );
                                        ?>
                                    </td>

                                    <td data-grade-status>
                                        <?php echo $simulation_escape(
                                            $history_status_label
                                        ); ?>
                                    </td>

                                    <td data-grade-score>
                                        <?php if (
                                            $history_grade_status === 'graded' &&
                                            $history_score_valid
                                        ) { ?>
                                            <?php echo $simulation_escape(
                                                number_format(
                                                    (float)$history_score,
                                                    2,
                                                    '.',
                                                    ''
                                                ) .
                                                ' / ' .
                                                number_format(
                                                    (float)$history_max_score,
                                                    2,
                                                    '.',
                                                    ''
                                                )
                                            ); ?>점
                                        <?php } elseif (
                                            $history_grade_status === 'graded' &&
                                            $history_score === null &&
                                            $history_max_score === null
                                        ) { ?>
                                            미산정
                                        <?php } elseif (
                                            $history_grade_status === 'graded'
                                        ) { ?>
                                            확인 필요
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>

                                    <td>

                                        <?php if ($can_edit_code) { ?>

                                            <button
                                                type="button"
                                                class="ui small basic teal button simulation-history-load"
                                                data-source-id="<?php
                                                    echo $history_id;
                                                ?>"
                                            >
                                                <i class="code icon"></i>
                                                코드 불러오기
                                            </button>

                                            <textarea
                                                id="<?php echo $history_id; ?>"
                                                hidden
                                            ><?php echo $simulation_escape(
                                                (string)$submission['source_code']
                                            ); ?></textarea>

                                        <?php } else { ?>

                                            <span class="ui small grey text">
                                                코드 편집 불가
                                            </span>

                                        <?php } ?>

                                    </td>

                                </tr>

                            <?php } ?>

                        </tbody>

                    </table>
                    <script
                        src="/template/syzoj/js/course-simulation-grade-status.js?v=1"
                        defer
                    ></script>

                    <p class="ui small message">
                        이전 코드를 불러와도 기존 제출 기록과
                        임시 저장 데이터는 변경되지 않습니다.
                    </p>

                <?php } ?>

            </div>

        <?php } ?>

        <?php if ($view_successful_submission !== null) { ?>

            <div
                class="ui positive message"
                role="status"
            >
                <div class="header">
                    <i class="check circle icon"></i>
                    코드 제출이 완료되었습니다.
                </div>

                <p>
                    제출 번호:
                    <strong><?php
                        echo intval(
                            $view_successful_submission['submission_no']
                        );
                    ?>회차</strong>

                    / 제출 시각:
                    <?php
                        echo $simulation_escape(
                            (string)$view_successful_submission['submitted_at']
                        );
                    ?>
                </p>
            </div>

        <?php } ?>

        <div class="ui small info message">
            JavaScript 코드는 격리된 실행 영역에서 동작합니다.
            코드를 임시 저장하거나 정식 제출할 수 있으며,
            이전 제출 기록도 확인할 수 있습니다.
        </div>

    </div>

</div>

<script src="/ace/ace.js"></script>
<script src="/ace/mode-javascript.js"></script>
<script src="/ace/theme-chrome.js"></script>
<script src="/template/syzoj/js/course-simulation-runner.js?v=8"></script>
<script src="/template/syzoj/js/course-simulation-autosave.js?v=4" defer></script>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
