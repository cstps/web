<?php
require_once(dirname(__DIR__, 2) . '/include/simulation_description.inc.php');
include("template/$OJ_TEMPLATE/header.php");

function simulation_edit_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<link
    rel="stylesheet"
    href="template/<?php echo simulation_edit_escape($OJ_TEMPLATE); ?>/css/course.css"
>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_view.php?course_id=<?php echo intval($course_id); ?>"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            시뮬레이션 편집
        </h1>

        <div class="course-page-description">
            <?php echo simulation_edit_escape($view_course['course_name']); ?>
        </div>

    </div>

    <div class="ui segment">

        <h3 class="ui dividing header">
            <?php echo intval($view_lesson['lesson_no']); ?>차시
            —
            <?php echo simulation_edit_escape($view_lesson['title']); ?>
        </h3>

        <div class="ui info message">
            <div class="header">
                <?php echo simulation_edit_escape($view_simulation_language_label); ?> 시뮬레이션 설정
            </div>
            <p>
                기본 코드와 학생의 편집·제출 허용을 설정합니다.
            </p>
        </div>

        <?php if (
            $view_simulation_execution['execution_engine'] === 'p5js' &&
            $view_simulation['simulation_mode'] === 'drawing' &&
            intval($view_simulation['canvas_width']) === 400 &&
            intval($view_simulation['canvas_height']) === 400
        ): ?>
            <p>
                <a class="ui button"
                   href="course_simulation_reference.php?activity_id=<?= intval($activity_id) ?>">
                    교사용 정답 관리
                </a>
            </p>
        <?php endif; ?>

        <form
            class="ui form"
            method="post"
            action="course_simulation_update.php"
            accept-charset="UTF-8"
        >

            <?php
            ob_start();
            include("./csrf.php");
            echo ob_get_clean();
            ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="activity_id"
                value="<?php echo intval($view_activity['activity_id']); ?>"
            >

            <?php foreach ($view_simulation_execution as $key => $value): ?>
                <input type="hidden"
                       name="expected_<?php echo simulation_edit_escape($key); ?>"
                       value="<?php echo simulation_edit_escape($value); ?>">
            <?php endforeach; ?>

            <div class="required field">
                <label for="simulation_title">
                    활동 제목
                </label>

                <input
                    type="text"
                    id="simulation_title"
                    name="title"
                    maxlength="255"
                    required
                    value="<?php echo simulation_edit_escape($view_activity['title']); ?>"
                >
            </div>

            <div class="field">
                <label for="simulation_description">
                    활동 설명
                </label>

                <textarea
                    id="simulation_description"
                    name="description"
                    rows="4"
                    maxlength="65535"
                ><?php echo simulation_edit_escape(simulation_description_html($view_activity['description'] ?? '')); ?></textarea>
            </div>

            <div class="ui divider"></div>

            <div class="required field">
                <label for="simulation_mode">
                    시뮬레이션 유형
                </label>

                <select
                    id="simulation_mode"
                    name="simulation_mode"
                    required
                >
                    <option
                        value="free"
                        <?php echo $view_simulation['simulation_mode'] === 'free' ? 'selected' : ''; ?>
                    >
                        일반 시뮬레이션
                    </option>

                    <option
                        value="drawing"
                        <?php echo $view_simulation['simulation_mode'] === 'drawing' ? 'selected' : ''; ?>
                    >
                        그림 문제
                    </option>
                </select>

                <div class="ui small message">
                    정답 목표 그림이 준비된 그림 문제는 정식 제출 시 자동 채점합니다.
                    Python 활동과 그림 문제는 400×400 캔버스를 사용합니다.
                </div>
            </div>

            <div class="two fields">

                <div class="required field">
                    <label for="canvas_width">
                        캔버스 너비 (px)
                    </label>

                    <input
                        type="number"
                        id="canvas_width"
                        name="canvas_width"
                        min="<?php echo $view_simulation_fixed_canvas ? 400 : 100; ?>"
                        max="<?php echo $view_simulation_fixed_canvas ? 400 : 2000; ?>"
                        <?php echo $view_simulation_fixed_canvas ? 'readonly' : ''; ?>
                        step="1"
                        required
                        value="<?php echo intval($view_simulation['canvas_width']); ?>"
                    >
                </div>

                <div class="required field">
                    <label for="canvas_height">
                        캔버스 높이 (px)
                    </label>

                    <input
                        type="number"
                        id="canvas_height"
                        name="canvas_height"
                        min="<?php echo $view_simulation_fixed_canvas ? 400 : 100; ?>"
                        max="<?php echo $view_simulation_fixed_canvas ? 400 : 2000; ?>"
                        <?php echo $view_simulation_fixed_canvas ? 'readonly' : ''; ?>
                        step="1"
                        required
                        value="<?php echo intval($view_simulation['canvas_height']); ?>"
                    >
                </div>

            </div>

            <div class="required field">
                <label for="starter_code">
                    기본 <?php echo simulation_edit_escape($view_simulation_language_label); ?> 코드
                </label>

                <div
                    id="simulation-starter-ace-editor"
                    aria-label="기본 <?php echo simulation_edit_escape($view_simulation_language_label); ?> 코드 편집기"
                    style="width:100%;height:260px;min-height:160px;max-height:80vh;resize:vertical;overflow:hidden;border:1px solid #cbd5e1;border-radius:4px;"
                    hidden
                ></div>

                <p
                    id="simulation-starter-editor-message"
                    role="alert"
                    hidden
                ></p>

                <textarea
                    id="starter_code"
                    name="starter_code"
                    data-source-language="<?php echo simulation_edit_escape($view_simulation_execution['source_language']); ?>"
                    rows="15"
                    spellcheck="false"
                    required
                    style="font-family: monospace; tab-size: 4;"
                ><?php echo simulation_edit_escape($view_simulation['starter_code']); ?></textarea>

                <div class="ui small message">
                    <?php echo $view_simulation_is_python ? 'Python' : 'JavaScript'; ?> 코드를 입력합니다.
                    이 입력 영역에서 코드를 실행하지는 않습니다.
                </div>
            </div>

            <div class="two fields">

                <div class="required field">
                    <label for="allow_edit">
                        학생 코드 편집
                    </label>

                    <select
                        id="allow_edit"
                        name="allow_edit"
                        required
                    >
                        <option
                            value="1"
                            <?php echo intval($view_simulation['allow_edit']) === 1 ? 'selected' : ''; ?>
                        >
                            허용
                        </option>

                        <option
                            value="0"
                            <?php echo intval($view_simulation['allow_edit']) === 0 ? 'selected' : ''; ?>
                        >
                            허용하지 않음
                        </option>
                    </select>
                </div>

                <div class="required field">
                    <label for="allow_submit">
                        학생 코드 제출
                    </label>

                    <select
                        id="allow_submit"
                        name="allow_submit"
                        required
                    >
                        <option
                            value="1"
                            <?php echo intval($view_simulation['allow_submit']) === 1 ? 'selected' : ''; ?>
                        >
                            허용
                        </option>

                        <option
                            value="0"
                            <?php echo intval($view_simulation['allow_submit']) === 0 ? 'selected' : ''; ?>
                        >
                            허용하지 않음
                        </option>
                    </select>
                </div>

            </div>

            <div class="required field">
                <label for="simulation_visible">
                    활동 공개 상태
                </label>

                <select
                    id="simulation_visible"
                    name="visible"
                    required
                >
                    <option
                        value="0"
                        <?php echo intval($view_activity['visible']) === 0 ? 'selected' : ''; ?>
                    >
                        비공개
                    </option>

                    <option
                        value="1"
                        <?php echo intval($view_activity['visible']) === 1 ? 'selected' : ''; ?>
                    >
                        공개
                    </option>
                </select>

                <div class="ui small message">
                    차시가 비공개 상태이면 활동을 공개해도
                    학생에게 표시되지 않습니다.
                </div>
            </div>

            <div class="ui divider"></div>

            <button
                type="submit"
                class="ui primary button"
            >
                <i class="save icon"></i>
                변경 사항 저장
            </button>

            <a
                class="ui basic button"
                href="course_view.php?course_id=<?php echo intval($course_id); ?>"
            >
                취소
            </a>

        </form>

    </div>

</div>

<script src="/tinymce/tinymce.min.js?v=8.9.0"></script>
<script src="/template/syzoj/js/course-simulation-description.js?v=1"></script>
<script src="/ace/ace.js"></script>
<script src="/ace/mode-<?php echo $view_simulation_is_python ? 'python' : 'javascript'; ?>.js"></script>
<script src="/ace/theme-chrome.js"></script>
<script src="/template/syzoj/js/course-simulation-edit.js?v=3"></script>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
