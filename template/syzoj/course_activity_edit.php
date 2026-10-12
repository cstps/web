<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<?php if ($view_activity['activity_type'] === 'material') { ?>
<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course-material-editor-layout.css?v=1"
>
<?php } ?>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_view.php?course_id=<?php
                echo intval($course_id);
            ?>"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            학습 활동 수정
        </h1>

        <div class="course-page-description">
            <?php
            echo htmlspecialchars(
                (string)$view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    </div>

    <div class="ui segment">

        <h3 class="ui dividing header">
            <?php echo intval($view_lesson['lesson_no']); ?>차시
            —
            <?php
            echo htmlspecialchars(
                (string)$view_lesson['title'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </h3>

        <div class="ui info message">
            <div class="header">
                일반 학습 활동 수정
            </div>

            <p>
                활동 유형, 제목, 설명, 공개 상태를 수정합니다.
                소속 Lesson과 다른 Activity의 정보는 변경하지 않습니다.
            </p>

            <p>
                이 화면에서는 Contest Activity를 수정할 수 없습니다.
            </p>
        </div>

        <form
            class="ui form"
            method="post"
            action="course_activity_update.php"
        >

            <?php
            ob_start();
            include("./csrf.php");
            $course_csrf_input = ob_get_clean();
            echo $course_csrf_input;
            ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="activity_id"
                value="<?php
                    echo intval($view_activity['activity_id']);
                ?>"
            >

            <div class="required field">

                <label for="activity_type">
                    활동 유형
                </label>

                <select
                    id="activity_type"
                    name="activity_type"
                    class="ui dropdown"
                    required
                >

                    <?php
                    foreach ($view_activity_types as $type => $label) {
                    ?>

                        <option
                            value="<?php
                                echo htmlspecialchars(
                                    (string)$type,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            <?php
                            if (
                                $view_activity['activity_type'] === $type
                            ) {
                                echo 'selected';
                            }
                            ?>
                        >
                            <?php
                            echo htmlspecialchars(
                                (string)$label,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </option>

                    <?php } ?>

                </select>

            </div>

            <div class="required field">

                <label for="activity_title">
                    활동 제목
                </label>

                <input
                    type="text"
                    id="activity_title"
                    name="title"
                    maxlength="255"
                    value="<?php
                        echo htmlspecialchars(
                            (string)$view_activity['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    required
                >

            </div>

            <div class="field">

                <label for="activity_description">
                    활동 설명
                </label>

                <textarea
                    id="activity_description"
                    name="description"
                    rows="7"
                ><?php
                    echo htmlspecialchars(
                        (string)$view_activity['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>

            </div>

            <?php
            if ($view_activity['activity_type'] === 'material') {
            ?>

                <div class="field">

                    <label for="material_content_html">
                        학습 자료 상세 본문
                    </label>

                    <textarea
                        id="material_content_html"
                        name="material_content_html"
                        rows="15"
                    ><?php
                        echo htmlspecialchars(
                            (string)$view_material_html,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?></textarea>

                    <div class="ui small message">
                        학습 자료의 상세 내용을 작성하는 영역입니다.
                        서식 편집 기능은 다음 단계에서 연결합니다.
                    </div>

                </div>

            <?php } ?>

            <div class="field">

                <label for="activity_visible">
                    활동 공개 상태
                </label>

                <select
                    id="activity_visible"
                    name="visible"
                    class="ui dropdown"
                    required
                >

                    <option
                        value="0"
                        <?php
                        if (
                            intval($view_activity['visible']) === 0
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        비공개
                    </option>

                    <option
                        value="1"
                        <?php
                        if (
                            intval($view_activity['visible']) === 1
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        공개
                    </option>

                </select>

                <div class="ui small message">
                    활동을 공개하더라도 소속 Lesson이 비공개라면
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
                href="course_view.php?course_id=<?php
                    echo intval($course_id);
                ?>"
            >
                취소
            </a>

        </form>

        <?php if ($view_activity['activity_type'] === 'material') { ?>

            <div class="ui divider"></div>

            <h3 class="ui dividing header">
                <i class="paperclip icon"></i>
                첨부파일 관리
            </h3>

            <p>
                학습 자료에 필요한 문서, 이미지,
                프로그래밍 파일을 첨부할 수 있습니다.
            </p>

            <?php
            $material_file_count = count($view_material_files);
            ?>

            <h4 class="ui header">
                등록된 첨부파일
                (<?php echo $material_file_count; ?>/10)
            </h4>

            <?php if (empty($view_material_files)) { ?>

                <div class="ui info message">
                    등록된 첨부파일이 없습니다.
                </div>

            <?php } else { ?>

                <div class="ui relaxed divided list">

                    <?php foreach ($view_material_files as $file) { ?>

                        <div class="item">

                            <i class="large file outline middle aligned icon"></i>

                            <div class="content">

                                <div class="header">
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$file['original_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </div>

                                <div class="description">
                                    <?php
                                    echo number_format(
                                        intval($file['file_size']) / 1024,
                                        1
                                    );
                                    ?> KB

                                    · 등록일:
                                    <?php
                                    echo htmlspecialchars(
                                        (string)$file['created_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </div>

                                <form
                                    method="post"
                                    action="course_material_file_delete.php"
                                    class="ui form course-material-file-delete-form"
                                >
                                    <?php echo $course_csrf_input; ?>

                                    <input
                                        type="hidden"
                                        name="activity_id"
                                        value="<?php
                                            echo intval($view_activity['activity_id']);
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="file_id"
                                        value="<?php
                                            echo intval($file['file_id']);
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="ui mini basic red button"
                                    >
                                        <i class="trash icon"></i>
                                        파일 삭제
                                    </button>
                                </form>

                            </div>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>

            <form
                class="ui form"
                method="post"
                action="course_material_file_upload.php"
                enctype="multipart/form-data"
            >
                <?php echo $course_csrf_input; ?>

                <input
                    type="hidden"
                    name="activity_id"
                    value="<?php
                        echo intval($view_activity['activity_id']);
                    ?>"
                >

                <div class="field">
                    <label for="material_file">
                        첨부할 파일
                    </label>

                    <input
                        type="file"
                        id="material_file"
                        name="material_file"
                        accept=".pdf,.docx,.hwpx,.pptx,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.py,.ipynb,.c,.cpp,.java,.txt,.csv,.json,.zip"
                        required
                    >
                </div>

                <div class="ui small message">
                    파일당 최대 20MB,
                    학습 자료당 최대 10개까지 첨부할 수 있습니다.
                    한글 문서는 HWPX 형식만 지원합니다.
                </div>

                <button
                    type="submit"
                    class="ui teal button"
                >
                    <i class="upload icon"></i>
                    파일 업로드
                </button>

            </form>

        <?php } ?>

    </div>

</div>

<?php
if ($view_activity['activity_type'] === 'material') {
?>
<script src="/tinymce/tinymce.min.js?v=8.9.0"></script>
<script src="/template/syzoj/js/course-material-editor.js?v=1"></script>
<script src="/template/syzoj/js/course-material-file-delete.js?v=1"></script>
<?php
}
?>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
