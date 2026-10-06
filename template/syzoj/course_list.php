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

                $visible_contest_count =
                    isset($course['visible_contest_count'])
                        ? intval($course['visible_contest_count'])
                        : 0;

                $clipboard_block_enabled =
                    isset($course['block_code_clipboard']) &&
                    intval($course['block_code_clipboard']) === 1;

                $performance_mode_enabled =
                    isset($course['performance_session_count']) &&
                    intval($course['performance_session_count']) > 0;

                // --------------------------------------------
                // Course 역할별 관리 권한
                // --------------------------------------------

                $can_edit_course =
                    (
                        $course_role === 'administrator' ||
                        $course_role === 'owner'
                    );

                $can_manage_teachers =
                    $can_edit_course;

                $can_manage_students =
                    (
                        $course_role === 'administrator' ||
                        $course_role === 'owner' ||
                        $course_role === 'teacher'
                    );

                $can_manage_contests =
                    $can_manage_students;

                $can_manage_performance =
                    $can_manage_students;


                $hidden_contest_count =
                    max(
                        0,
                        $contest_count - $visible_contest_count
                    );


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

                                전체 차시:
                                <strong>
                                    <?php echo $contest_count; ?>
                                </strong>개

                            </span>


                            <span class="course-meta-item">

                                <i class="eye icon"></i>

                                공개:
                                <strong>
                                    <?php echo $visible_contest_count; ?>
                                </strong>개

                            </span>


                            <span class="course-meta-item">

                                <i class="eye slash icon"></i>

                                숨김:
                                <strong>
                                    <?php echo $hidden_contest_count; ?>
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
                                <i class="eye icon"></i>
                                수업 보기
                            </a>


                            <?php
                            if ($can_manage_students) {
                            ?>

                                <a
                                    class="ui small blue basic button"
                                    href="course_students.php?course_id=<?php echo $course_id; ?>"
                                >
                                    <i class="users icon"></i>
                                    학생 관리
                                </a>

                            <?php
                            }
                            ?>


                            <?php
                            if ($can_manage_teachers) {
                            ?>

                                <a
                                    class="ui small teal basic button"
                                    href="course_teachers.php?course_id=<?php echo $course_id; ?>"
                                >
                                    <i class="user tie icon"></i>
                                    교사 관리
                                </a>

                            <?php
                            }
                            ?>


                            <?php
                            if ($can_manage_contests) {
                            ?>

                                <a
                                    class="ui small basic button"
                                    href="course_code_monitor.php?course_id=<?php echo $course_id; ?>"
                                    title="이 수업 학생들의 작성 중인 코드를 확인합니다."
                                >
                                    <i class="code icon"></i>
                                    학생 코드 모니터링
                                </a>

                                <a
                                    class="ui small violet basic button"
                                    href="course_performance_dashboard.php?course_id=<?php echo $course_id; ?>"
                                    title="학생별 문제 수행 현황과 해결 과정을 확인합니다."
                                >
                                    <i class="tasks icon"></i>
                                    수행평가 현황
                                </a>

                            <?php
                            }
                            ?>


                            <?php
                            if ($can_edit_course) {
                            ?>

                                <a
                                    class="ui small basic button"
                                    href="course_edit.php?course_id=<?php echo $course_id; ?>"
                                >
                                    <i class="edit icon"></i>
                                    수업 정보 수정
                                </a>

                            <?php
                            }
                            ?>


                            <?php
                            if ($can_manage_performance) {
                            ?>

                                <form
                                    method="post"
                                    action="course_clipboard_setting.php"
                                    style="display:inline;"
                                >

                                    <?php echo $view_csrf_input; ?>

                                    <input
                                        type="hidden"
                                        name="course_id"
                                        value="<?php echo $course_id; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="return_to"
                                        value="course_list"
                                    >
                                    <input
                                        type="hidden"
                                        name="block_code_clipboard"
                                        value="<?php
                                        echo $clipboard_block_enabled
                                            ? '0'
                                            : '1';
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="ui small <?php
                                        echo $clipboard_block_enabled
                                            ? 'orange'
                                            : 'basic';
                                        ?> button"
                                        title="<?php
                                        echo $clipboard_block_enabled
                                            ? '활성화됨: 복사, 잘라내기, 붙여넣기, 드래그앤드롭을 제한합니다.'
                                            : '클릭하면 이 수업의 코드 작성 화면에서 복사·붙여넣기를 제한합니다.';
                                        ?>"
                                        onclick="return confirm(
                                            '<?php
                                            echo $clipboard_block_enabled
                                                ? '복붙금지모드를 해제하시겠습니까?'
                                                : '복붙금지모드를 활성화하시겠습니까?';
                                            ?>'
                                        );"
                                    >
                                        <i class="<?php
                                        echo $clipboard_block_enabled
                                            ? 'lock'
                                            : 'unlock';
                                        ?> icon"></i>

                                        복붙금지모드:
                                        <?php
                                        echo $clipboard_block_enabled
                                            ? 'ON'
                                            : 'OFF';
                                        ?>
                                    </button>

                                </form>

                            <?php
                            }
                            ?>


                            <?php
                            if ($can_manage_performance) {

                                if ($performance_mode_enabled) {
                            ?>

                                    <form
                                        method="post"
                                        action="/course_performance_end.php"
                                        style="display:inline;"
                                        onsubmit="return confirm(
                                            '현재 수행모드를 종료하시겠습니까?'
                                        );"
                                    >

                                        <?php echo $view_csrf_input; ?>

                                        <input
                                            type="hidden"
                                            name="course_id"
                                            value="<?php echo $course_id; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="return_to"
                                        value="course_list"
                                    >

                                        <button
                                            type="submit"
                                            class="ui small red button"
                                            title="수행모드 진행 중: 기존 제출·해결과정 열람과 코드 복사·붙여넣기를 제한합니다."
                                        >
                                            <i class="shield alternate icon"></i>
                                            수행모드: ON
                                        </button>

                                    </form>

                            <?php
                                }
                                elseif ($course_status === 1) {
                            ?>

                                    <form
                                        method="post"
                                        action="/course_performance_start.php"
                                        style="display:inline;"
                                        onsubmit="return confirm(
                                            '이 수업의 활성 학생 전체를 수행모드로 전환하시겠습니까?'
                                        );"
                                    >

                                        <?php echo $view_csrf_input; ?>

                                        <input
                                            type="hidden"
                                            name="course_id"
                                            value="<?php echo $course_id; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="return_to"
                                        value="course_list"
                                    >

                                        <button
                                            type="submit"
                                            class="ui small basic button"
                                            title="활성 수강생의 기존 제출·해결과정 열람과 코드 복사·붙여넣기를 제한합니다."
                                        >
                                            <i class="shield alternate icon"></i>
                                            수행모드: OFF
                                        </button>

                                    </form>

                            <?php
                                }
                            }
                            ?>


                            <?php
                            if ($can_edit_course) {
                            ?>

                                <form
                                    method="post"
                                    action="course_status.php"
                                    style="display:inline;"
                                    onsubmit="return confirm(
                                        '<?php
                                        echo $course_status === 1
                                            ? '이 수업을 종료하시겠습니까?'
                                            : '이 수업을 다시 시작하시겠습니까?';
                                        ?>'
                                    );"
                                >

                                    <?php echo $view_csrf_input; ?>

                                    <input
                                        type="hidden"
                                        name="course_id"
                                        value="<?php echo $course_id; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="return_to"
                                        value="course_list"
                                    >
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="<?php
                                        echo $course_status === 1
                                            ? '0'
                                            : '1';
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="ui small <?php
                                        echo $course_status === 1
                                            ? 'red basic'
                                            : 'green basic';
                                        ?> button"
                                    >
                                        <i class="<?php
                                        echo $course_status === 1
                                            ? 'stop'
                                            : 'play';
                                        ?> icon"></i>

                                        <?php
                                        echo $course_status === 1
                                            ? '수업 종료'
                                            : '수업 재개';
                                        ?>
                                    </button>

                                </form>

                            <?php
                            }
                            ?>

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