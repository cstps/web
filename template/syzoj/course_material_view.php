<?php
include("template/$OJ_TEMPLATE/header.php");

$material_title = htmlspecialchars(
    (string)$view_activity['title'],
    ENT_QUOTES,
    'UTF-8'
);

$material_description = htmlspecialchars(
    (string)($view_activity['description'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);

$lesson_title = htmlspecialchars(
    (string)$view_activity['lesson_title'],
    ENT_QUOTES,
    'UTF-8'
);
?>

<div class="ui main container"
     style="margin-top:2rem;margin-bottom:3rem;">

    <nav class="ui small breadcrumb" aria-label="현재 위치">

        <a
            class="section"
            href="<?php
                echo $is_manager
                    ? 'course_view.php?course_id=' . $course_id
                    : 'my_course_view.php?course_id=' . $course_id;
            ?>"
        >
            <i class="home icon"></i>
            <?php echo $is_manager ? '수업 관리' : '내 수업'; ?>
        </a>

        <i class="right chevron icon divider"></i>

        <span class="active section">
            <i class="book icon"></i>
            학습 자료
        </span>

    </nav>

    <div class="ui segment"
         style="margin-top:1.5rem;">

        <div class="ui small blue label">
            학습 자료
        </div>

        <div class="ui small basic label">
            <?php echo $lesson_title; ?>
        </div>

        <h2 class="ui dividing header">
            <?php echo $material_title; ?>
        </h2>

        <?php if ($material_description !== '') { ?>

            <div class="ui message">
                <?php
                echo nl2br($material_description);
                ?>
            </div>

        <?php } ?>

        <div class="ui divider"></div>

        <?php if ($view_material_html !== '') { ?>

            <div class="course-material-content">
                <?php
                // 서버에서 정화한 HTML만 출력한다.
                echo $view_material_html;
                ?>
            </div>

        <?php } else { ?>

            <div class="ui info message">
                아직 등록된 상세 학습 자료가 없습니다.
            </div>

        <?php } ?>

        <?php if (!empty($view_material_files)) { ?>

            <div class="ui divider"></div>

            <h3 class="ui dividing header">
                <i class="paperclip icon"></i>
                첨부파일
            </h3>

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
                            </div>

                            <div class="description">

                                <a
                                    class="ui mini basic blue button"
                                    href="course_material_file_download.php?file_id=<?php
                                        echo intval($file['file_id']);
                                    ?>"
                                >
                                    <i class="download icon"></i>
                                    다운로드
                                </a>

                            </div>

                        </div>

                    </div>

                <?php } ?>

            </div>

        <?php } ?>

        <div class="ui divider"></div>

        <div class="ui segment">

            <div class="ui stackable two column grid middle aligned">

                <div class="column">

                    <div class="ui small header">
                        학습 자료를 모두 확인하셨나요?
                    </div>

                    <p class="ui small text">
                        수업으로 돌아가 다른 활동을 확인할 수 있습니다.
                    </p>

                </div>

                <div class="right aligned column">

                    <a
                        class="ui basic button"
                        href="<?php
                            echo $is_manager
                                ? 'course_view.php?course_id=' . $course_id
                                : 'my_course_view.php?course_id=' . $course_id;
                        ?>"
                    >
                        <i class="arrow left icon"></i>
                        수업으로 돌아가기
                    </a>

                    <?php if ($is_manager) { ?>

                        <a
                            class="ui teal button"
                            href="course_activity_edit.php?activity_id=<?php
                                echo intval($view_activity['activity_id']);
                            ?>"
                        >
                            <i class="edit icon"></i>
                            학습 자료 수정
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
