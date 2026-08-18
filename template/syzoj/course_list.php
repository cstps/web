<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<div class="course-page">

    <div class="course-page-header">

        <h1 class="ui header">
            수업 관리
        </h1>

        <div class="course-page-description">
            담당하고 있는 수업과 학생, 수업 차시를 확인할 수 있습니다.
        </div>

    </div>

    <?php
    if ($view_can_create_course) {
    ?>

        <div class="course-actions">

            <a
                class="ui teal button"
                href="course_add.php"
            >
                <i class="plus icon"></i>
                새 수업 만들기
            </a>

        </div>

    <?php
    }
    ?>

    <?php
    // ========================================================
    // 수업이 없는 경우
    // ========================================================
    if (empty($view_courses)) {
    ?>

        <div class="ui info message">

            <div class="header">
                등록된 수업이 없습니다.
            </div>

            <p>
                현재 접근할 수 있는 수업이 없습니다.
            </p>

        </div>

    <?php
    }
    else {
    ?>


        <div class="course-list">

            <?php
            foreach ($view_courses as $course) {

                $course_id =
                    intval($course['course_id']);

                $course_status =
                    intval($course['status']);

                $course_role =
                    isset($course['course_role'])
                        ? $course['course_role']
                        : '';

                $student_count =
                    intval($course['student_count']);

                $contest_count =
                    intval($course['contest_count']);


                // ------------------------------------------------
                // 역할 표시명
                // ------------------------------------------------

                switch ($course_role) {

                    case 'administrator':
                        $role_label = '관리자';
                        break;

                    case 'owner':
                        $role_label = '책임교사';
                        break;

                    case 'teacher':
                        $role_label = '담당교사';
                        break;

                    case 'assistant':
                        $role_label = '보조교사';
                        break;

                    default:
                        $role_label = '-';
                        break;
                }


                // ------------------------------------------------
                // 학기 표시
                // ------------------------------------------------

                $semester =
                    intval($course['semester']);

                if ($semester == 1) {
                    $semester_label = '1학기';
                }
                elseif ($semester == 2) {
                    $semester_label = '2학기';
                }
                elseif ($semester > 0) {
                    $semester_label = $semester.'학기';
                }
                else {
                    $semester_label = '학기 구분 없음';
                }


                // ------------------------------------------------
                // Course 상태
                // ------------------------------------------------

                $card_class =
                    ($course_status == 1)
                        ? ''
                        : ' inactive';
            ?>


                <div class="ui fluid card course-card<?php echo $card_class; ?>">

                    <div class="content">

                        <div class="header">

                            <?php
                            echo htmlspecialchars(
                                $course['course_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>


                        <div class="meta">

                            <?php echo intval($course['school_year']); ?>학년도

                            ·

                            <?php echo htmlspecialchars(
                                $semester_label,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>

                            <?php
                            if (!empty($course['school'])) {
                            ?>

                                ·

                                <?php
                                echo htmlspecialchars(
                                    $course['school'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            <?php
                            }
                            ?>

                        </div>


                        <?php
                        if (!empty($course['description'])) {
                        ?>

                            <div class="description">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $course['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                );
                                ?>

                            </div>

                        <?php
                        }
                        ?>


                        <div class="course-meta">

                            <span class="course-meta-item">

                                <i class="user icon"></i>

                                역할:
                                <strong>
                                    <?php echo htmlspecialchars(
                                        $role_label,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </strong>

                            </span>


                            <span class="course-meta-item">

                                <i class="users icon"></i>

                                학생:
                                <strong>
                                    <?php echo $student_count; ?>
                                </strong>명

                            </span>


                            <span class="course-meta-item">

                                <i class="list alternate icon"></i>

                                차시:
                                <strong>
                                    <?php echo $contest_count; ?>
                                </strong>개

                            </span>


                            <span class="course-meta-item">

                                상태:

                                <?php
                                if ($course_status == 1) {
                                ?>

                                    <span class="ui tiny green label">
                                        운영 중
                                    </span>

                                <?php
                                }
                                else {
                                ?>

                                    <span class="ui tiny grey label">
                                        종료
                                    </span>

                                <?php
                                }
                                ?>

                            </span>

                        </div>


                        <div class="course-actions">

                            <a
                                class="ui small blue button"
                                href="course_view.php?course_id=<?php echo $course_id; ?>"
                            >
                                수업 보기
                            </a>

                        </div>

                    </div>

                </div>


            <?php
            }
            ?>

        </div>


    <?php
    }
    ?>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>