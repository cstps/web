<?php
require_once(dirname(__DIR__, 2) . '/include/simulation_description.inc.php');
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css">
<link rel="stylesheet" href="/template/syzoj/css/course-activity-heading.css?v=1">


<div class="course-page">

    <div class="course-page-header">

        <a
            href="course_list.php"
            class="ui small basic button">
            <i class="left arrow icon"></i>
            수업 목록
        </a>

        <h1 class="ui header">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </h1>


        <div class="course-page-description">

            <?php echo intval($view_course['school_year']); ?>학년도

            ·

            <?php
            $semester = intval($view_course['semester']);

            if ($semester == 1) {
                echo '1학기';
            } elseif ($semester == 2) {
                echo '2학기';
            } elseif ($semester > 0) {
                echo $semester . '학기';
            } else {
                echo '학기 구분 없음';
            }
            ?>

            <?php
            if (!empty($view_course['school'])) {
            ?>
                ·
                <?php
                echo htmlspecialchars(
                    $view_course['school'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            <?php
            }
            ?>

        </div>

    </div>


    <?php
    if (!empty($view_course['description'])) {
    ?>

        <div class="ui segment">

            <?php
            echo nl2br(
                htmlspecialchars(
                    $view_course['description'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
            ?>

        </div>

    <?php
    }
    ?>
    <!-- ======================================================
    요약
    ====================================================== -->

    <div class="ui three statistics">

        <div class="statistic">

            <div class="value">
                <?php echo intval($view_student_count); ?>
            </div>

            <div class="label">
                학생
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php echo count($view_teachers); ?>
            </div>

            <div class="label">
                담당 교사
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php echo count($view_contests); ?>
            </div>

            <div class="label">
                차시
            </div>

        </div>

    </div>


    <!-- ======================================================
         담당 교사
         ====================================================== -->

    <h3 class="ui dividing header">
        담당 교사
    </h3>


    <?php
    if (empty($view_teachers)) {
    ?>

        <div class="ui message">
            등록된 담당 교사가 없습니다.
        </div>

    <?php
    } else {
    ?>

        <table class="ui celled table">

            <thead>
                <tr>
                    <th>아이디</th>
                    <th>이름</th>
                    <th>소속</th>
                    <th>역할</th>
                </tr>
            </thead>

            <tbody>

                <?php
                foreach ($view_teachers as $teacher) {

                    switch ($teacher['role']) {

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
                    }
                ?>

                    <tr>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $teacher['user_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                isset($teacher['nick'])
                                    ? $teacher['nick']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                isset($teacher['school'])
                                    ? $teacher['school']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </td>

                        <td>
                            <?php echo $role_label; ?>
                        </td>

                    </tr>

                <?php
                }
                ?>

            </tbody>

        </table>

    <?php
    }
    ?>


    <!-- ======================================================
         연결 대회
         ====================================================== -->

    <h3 class="ui dividing header">
        수업 차시
    </h3>


    <?php
    if (
        $view_can_manage_contests &&
        intval($view_course['status']) === 1
    ) {
    ?>

        <div style="margin-bottom:1rem;">

            <a
                class="ui primary button"
                href="course_lesson_add.php?course_id=<?php
                                                        echo intval($course_id);
                                                        ?>">
                <i class="plus icon"></i>
                새 차시 만들기
            </a>

        </div>

    <?php
    }
    ?>




    <?php
    // --------------------------------------------------------
    // Lesson별 Activity 목록
    // 기존 Contest 관리 목록은 그대로 유지한다.
    // --------------------------------------------------------

    if ($view_can_manage_contests) {
    ?>

        <div class="ui segment" style="margin-top:2rem;">

            <h3 class="ui dividing header">
                차시별 학습 활동
            </h3>

            <?php
            if (
                empty($view_lesson_groups) &&
                empty($view_unassigned_activities)
            ) {
            ?>
                <div class="ui message">
                    등록된 학습 활동이 없습니다.
                </div>
            <?php
            }
            ?>

            <?php
            foreach ($view_lesson_groups as $group) {

                $lesson = $group['lesson'];
                $activities = $group['activities'];
            ?>

                <div class="course-lesson-card">

                    <h4 class="ui dividing header course-lesson-heading">
                        <?php echo intval($lesson['lesson_no']); ?>차시
                        —
                        <?php
                        echo htmlspecialchars(
                            (string)$lesson['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                        <span class="ui mini basic label">
                            활동 <?php echo count($activities); ?>개
                        </span>

                        <span class="ui mini basic label">
                            <?php
                            echo (
                                intval($lesson['status']) === 1 &&
                                intval($lesson['visible']) === 1
                            )
                                ? '차시 공개'
                                : '차시 비공개';
                            ?>
                        </span>

                        <?php
                        if (
                            $view_can_manage_contests &&
                            intval($view_course['status']) === 1 &&
                            intval($lesson['status']) === 1
                        ) {
                        ?>
                            <a
                                class="ui mini teal basic button"
                                href="course_lesson_edit.php?lesson_id=<?php
                                                                        echo intval($lesson['lesson_id']);
                                                                        ?>">
                                <i class="edit icon"></i>
                                차시 수정
                            </a>

                            <a
                                class="ui mini blue basic button"
                                href="course_activity_add.php?lesson_id=<?php
                                                                        echo intval($lesson['lesson_id']);
                                                                        ?>">
                                <i class="plus icon"></i>
                                학습 활동 추가
                            </a>
                        <?php } ?>
                    </h4>

                    <?php if (empty($activities)) { ?>

                        <p>등록된 활동이 없습니다.</p>

                    <?php } else { ?>

                        <div class="ui relaxed divided list course-lesson-activities">

                            <?php
                            $activity_count = count($activities);

                            foreach ($activities as $activity_index => $activity) {
                            ?>

                                <div class="item course-lesson-activity course-activity-item">
                                    <div class="content">

                                        <?php $course_activity_teacher = true; require(__DIR__ . '/course_activity_heading.php'); ?>
<div class="course-activity-management">

                                            <?php
                                            if (
                                                $view_can_manage_contests &&
                                                intval($view_course['status']) === 1 &&
                                                intval($lesson['status']) === 1 &&
                                                $activity_count > 1
                                            ) {
                                            ?>
                                                <div class="course-activity-move-controls">

                                                    <?php if ($activity_index > 0) { ?>
                                                        <form
                                                            method="post"
                                                            action="course_activity_move.php"
                                                            class="course-activity-move-form">
                                                            <?php echo $view_csrf_input; ?>

                                                            <input type="hidden"
                                                                name="course_id"
                                                                value="<?php echo intval($course_id); ?>">
                                                            <input type="hidden"
                                                                name="activity_id"
                                                                value="<?php echo intval($activity['activity_id']); ?>">
                                                            <input type="hidden"
                                                                name="direction"
                                                                value="up">

                                                            <button type="submit"
                                                                class="ui mini basic button">
                                                                <i class="arrow up icon"></i>
                                                                위로 이동
                                                            </button>
                                                        </form>
                                                    <?php } ?>

                                                    <?php if ($activity_index < $activity_count - 1) { ?>
                                                        <form
                                                            method="post"
                                                            action="course_activity_move.php"
                                                            class="course-activity-move-form">
                                                            <?php echo $view_csrf_input; ?>

                                                            <input type="hidden"
                                                                name="course_id"
                                                                value="<?php echo intval($course_id); ?>">
                                                            <input type="hidden"
                                                                name="activity_id"
                                                                value="<?php echo intval($activity['activity_id']); ?>">
                                                            <input type="hidden"
                                                                name="direction"
                                                                value="down">

                                                            <button type="submit"
                                                                class="ui mini basic button">
                                                                <i class="arrow down icon"></i>
                                                                아래로 이동
                                                            </button>
                                                        </form>
                                                    <?php } ?>

                                                </div>
                                            <?php } ?>

                                        <?php
                                        if (
                                            $activity['activity_type'] !== 'contest' &&
                                            false /* 수업 목록의 활동 설명 숨김 */
                                        ) {
                                        ?>
                                            <div class="description course-activity-description"><?php
                                                                                                    if ($activity['activity_type'] === 'simulation') {
        echo '<div style="white-space:normal;overflow-wrap:anywhere;overflow-x:auto;">';
        echo simulation_description_html($activity['description']);
        echo '</div>';
    } else {
        echo htmlspecialchars(
                                                                                                        (string)$activity['description'],
                                                                                                        ENT_QUOTES,
                                                                                                        'UTF-8'
                                                                                                    );
    }
                                                                                                    ?></div>
                                        <?php } ?>

                                        <?php
                                        // Contest Activity의 대회 설정 수정
                                        if (
                                            $view_can_manage_contests &&
                                            intval($view_course['status']) === 1 &&
                                            intval($lesson['status']) === 1 &&
                                            $activity['activity_type'] === 'contest' &&
                                            intval($activity['contest_id']) > 0
                                        ) {
                                        ?>
                                            <a
                                                class="ui mini teal basic button course-contest-action-button"
                                                href="course_contest_edit.php?course_id=<?php
                                                                                        echo intval($course_id);
                                                                                        ?>&amp;contest_id=<?php
                                                                echo intval($activity['contest_id']);
                                                                ?>">
                                                <i class="edit icon"></i>
                                                대회 설정 수정
                                            </a>
                                        <?php } ?>

                                        <?php
                                        // Course에서 생성한 Contest만 문제 구성 허용
                                        if (
                                            $view_can_manage_contests &&
                                            intval($view_course['status']) === 1 &&
                                            intval($lesson['status']) === 1 &&
                                            $activity['activity_type'] === 'contest' &&
                                            intval($activity['contest_id']) > 0 &&
                                            isset($activity['contest_link_type']) &&
                                            $activity['contest_link_type'] === 'created'
                                        ) {
                                        ?>
                                            <a
                                                class="ui mini orange basic button course-contest-action-button"
                                                href="course_contest_problem_edit.php?course_id=<?php
                                                                                                echo intval($course_id);
                                                                                                ?>&amp;contest_id=<?php
                                                                echo intval($activity['contest_id']);
                                                                ?>">
                                                <i class="tasks icon"></i>
                                                문제 구성
                                            </a>
                                        <?php } ?>

                                        <?php
                                        // Contest Activity 공개·숨김
                                        if (
                                            $view_can_manage_contests &&
                                            intval($view_course['status']) === 1 &&
                                            intval($lesson['status']) === 1 &&
                                            $activity['activity_type'] === 'contest' &&
                                            intval($activity['contest_id']) > 0
                                        ) {
                                            $contest_is_visible =
                                                intval($activity['contest_visible']) === 1;
                                        ?>
                                            <form
                                                method="post"
                                                action="course_contest_visibility.php"
                                                style="display:inline;">
                                                <?php echo $view_csrf_input; ?>

                                                <input
                                                    type="hidden"
                                                    name="course_id"
                                                    value="<?php echo intval($course_id); ?>">

                                                <input
                                                    type="hidden"
                                                    name="contest_id"
                                                    value="<?php echo intval($activity['contest_id']); ?>">

                                                <input
                                                    type="hidden"
                                                    name="visible"
                                                    value="<?php echo $contest_is_visible ? 0 : 1; ?>">

                                                <button
                                                    type="submit"
                                                    class="ui mini basic <?php
                                                                            echo $contest_is_visible
                                                                                ? 'orange'
                                                                                : 'teal';
                                                                            ?> button">
                                                    <?php if ($contest_is_visible) { ?>
                                                        <i class="eye slash icon"></i>
                                                        대회 숨김
                                                    <?php } else { ?>
                                                        <i class="eye icon"></i>
                                                        대회 공개
                                                    <?php } ?>
                                                </button>
                                            </form>
                                        <?php } ?>

                                        <?php
                                        // Contest Activity의 수업 연결 제거
                                        if (
                                            $view_can_manage_contests &&
                                            intval($view_course['status']) === 1 &&
                                            intval($lesson['status']) === 1 &&
                                            $activity['activity_type'] === 'contest' &&
                                            intval($activity['contest_id']) > 0
                                        ) {
                                        ?>
                                            <form
                                                method="post"
                                                action="course_contest_remove.php"
                                                style="display:inline;"
                                                onsubmit="return confirm(
                                                '이 대회를 수업에서 제거하시겠습니까?\n\n기존 제출 기록은 삭제되지 않습니다.'
                                            );">
                                                <?php echo $view_csrf_input; ?>

                                                <input type="hidden"
                                                    name="course_id"
                                                    value="<?php echo intval($course_id); ?>">

                                                <input type="hidden"
                                                    name="contest_id"
                                                    value="<?php echo intval($activity['contest_id']); ?>">

                                                <button type="submit"
                                                    class="ui mini red basic button">
                                                    <i class="archive icon"></i>
                                                    대회 연결 제거
                                                </button>
                                            </form>
                                        <?php } ?>

                                        <?php
                                        // 교사용 과제 상세 보기 및 제출 현황
                                        if (
                                            $view_can_manage_contests &&
                                            $activity['activity_type'] === 'assignment'
                                        ) {
                                        ?>


                                            <a
                                                class="ui mini blue basic button"
                                                href="course_assignment_submissions.php?activity_id=<?php
                                                                                                    echo intval($activity['activity_id']);
                                                                                                    ?>">
                                                <i class="tasks icon"></i>
                                                제출 현황
                                            </a>
                                        <?php } ?>

                                        <?php
                                        if (
                                            $view_can_manage_contests &&
                                            intval($view_course['status']) === 1 &&
                                            $activity['activity_type'] === 'simulation'
                                        ) {
                                        ?>
                                            <a
                                                class="ui mini teal basic button"
                                                href="course_simulation_edit.php?activity_id=<?php
                                                                                                echo intval($activity['activity_id']);
                                                                                                ?>">
                                                <i class="edit icon"></i>
                                                시뮬레이션 편집
                                            </a>

                                            <a
                                                class="ui mini blue basic button"
                                                href="course_simulation_submissions.php?activity_id=<?php
                                                                                                    echo intval($activity['activity_id']);
                                                                                                    ?>">
                                                <i class="tasks icon"></i>
                                                제출 현황
                                            </a>
                                            <!-- simulation-remove-control:v1 -->
                                            <form
                                                class="course-activity-visibility-form"
                                                method="post"
                                                action="course_activity_remove.php"
                                                style="display:inline-block;"
                                                onsubmit="return confirm('이 활동을 목록에서 제거할까요? 제출 기록은 보존되며 비활성 학습 활동에서 복구할 수 있습니다.');"
                                            >
                                                <?php echo $view_csrf_input; ?>
                                                <input type="hidden" name="course_id" value="<?php echo intval($course_id); ?>">
                                                <input type="hidden" name="activity_id" value="<?php echo intval($activity['activity_id']); ?>">
                                                <button type="submit" class="ui mini red basic button">
                                                    <i class="archive icon"></i>활동 제거
                                                </button>
                                            </form>

                                        <?php } ?>

                                        <?php
                                        if (
                                            $view_can_manage_contests &&
                                            intval($view_course['status']) === 1 &&
                                            in_array(
                                                $activity['activity_type'],
                                                array('material', 'assignment'),
                                                true
                                            )
                                        ) {
                                        ?>
                                            <a
                                                class="ui mini teal basic button"
                                                href="course_activity_edit.php?activity_id=<?php
                                                                                            echo intval($activity['activity_id']);
                                                                                            ?>">
                                                <i class="edit icon"></i>
                                                <?php
                                                echo $activity['activity_type'] === 'material'
                                                    ? '자료 편집'
                                                    : '과제 편집';
                                                ?>
                                            </a>

                                            <form
                                                class="course-activity-visibility-form"
                                                method="post"
                                                action="course_activity_visibility.php">
                                                <?php echo $view_csrf_input; ?>

                                                <input
                                                    type="hidden"
                                                    name="course_id"
                                                    value="<?php echo intval($view_course['course_id']); ?>">

                                                <input
                                                    type="hidden"
                                                    name="activity_id"
                                                    value="<?php echo intval($activity['activity_id']); ?>">

                                                <input
                                                    type="hidden"
                                                    name="visible"
                                                    value="<?php
                                                            echo intval($activity['activity_visible']) === 1
                                                                ? 0
                                                                : 1;
                                                            ?>">

                                                <button
                                                    type="submit"
                                                    class="ui mini basic button">
                                                    <?php
                                                    if (intval($activity['activity_visible']) === 1) {
                                                    ?>
                                                        <i class="eye slash icon"></i>
                                                        활동 숨김
                                                    <?php } else { ?>
                                                        <i class="eye icon"></i>
                                                        활동 공개
                                                    <?php } ?>
                                                </button>
                                            </form>

                                            <form
                                                class="course-activity-visibility-form"
                                                method="post"
                                                action="course_activity_remove.php">
                                                <?php echo $view_csrf_input; ?>

                                                <input
                                                    type="hidden"
                                                    name="course_id"
                                                    value="<?php echo intval($course_id); ?>">

                                                <input
                                                    type="hidden"
                                                    name="activity_id"
                                                    value="<?php echo intval($activity['activity_id']); ?>">

                                                <button
                                                    type="submit"
                                                    class="ui mini red basic button">
                                                    <i class="archive icon"></i>
                                                    활동 비활성화
                                                </button>
                                            </form>
                                        <?php } ?>

                                        <span class="ui mini basic <?php
                                                                    echo intval($activity['activity_visible']) === 1
                                                                        ? 'blue'
                                                                        : 'grey';
                                                                    ?> label">
                                            <?php
                                            echo intval($activity['activity_visible']) === 1
                                                ? '활동 공개'
                                                : '활동 비공개';
                                            ?>
                                        </span>

                                        <?php if ($activity['activity_type'] === 'contest') { ?>
                                            <span class="ui mini basic <?php
                                                                        echo intval($activity['contest_visible']) === 1
                                                                            ? 'teal'
                                                                            : 'grey';
                                                                        ?> label">
                                                <?php
                                                echo intval($activity['contest_visible']) === 1
                                                    ? '대회 공개'
                                                    : '대회 비공개';
                                                ?>
                                            </span>
                                        <?php } ?>

                                        <span class="ui mini basic <?php
                                                                    echo intval($activity['visible']) === 1
                                                                        ? 'green'
                                                                        : 'orange';
                                                                    ?> label">
                                            <?php
                                            echo intval($activity['visible']) === 1
                                                ? '학생 표시 가능'
                                                : '학생 표시 제한';
                                            ?>
                                        </span>



                                    </div><!-- 관리 기능 -->
                                    </div>
                                </div>

                            <?php } ?>

                        </div>

                    <?php } ?>

                </div><!-- /.course-lesson-card -->

            <?php } ?>

            <?php if (!empty($view_unassigned_activities)) { ?>

                <h4 class="ui dividing header">
                    미배정 학습 활동
                    <span class="ui mini basic label">
                        <?php echo count($view_unassigned_activities); ?>개
                    </span>
                </h4>

                <div class="ui relaxed divided list">

                    <?php foreach ($view_unassigned_activities as $activity) { ?>

                        <div class="item">
                            <div class="content">

                                <span class="ui tiny label">
                                    <?php
                                    $activity_type_labels = array(
                                        'material' => '학습 자료',
                                        'assignment' => '학습 과제',
                                        'contest' => '코딩 실습'
                                    );

                                    $activity_type = (string)$activity['activity_type'];

                                    $activity_type_label =
                                        $activity_type_labels[$activity_type]
                                        ?? $activity_type;

                                    echo htmlspecialchars(
                                        $activity_type_label,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>

                                <?php
                                $activity_title =
                                    isset($activity['display_title'])
                                    ? (string)$activity['display_title']
                                    : (string)$activity['activity_title'];

                                if (
                                    $activity['activity_type'] === 'contest' &&
                                    intval($activity['contest_id']) > 0
                                ) {
                                ?>
                                    <a href="contest.php?cid=<?php
                                                                echo intval($activity['contest_id']);
                                                                ?>">
                                        <?php
                                        echo htmlspecialchars(
                                            $activity_title,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </a>
                                <?php } else { ?>
                                    <?php
                                    echo htmlspecialchars(
                                        $activity_title,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                <?php } ?>

                                <span class="ui mini basic label">
                                    <?php
                                    echo intval($activity['activity_visible']) === 1
                                        ? '활동 공개'
                                        : '활동 비공개';
                                    ?>
                                </span>

                                <?php if ($activity['activity_type'] === 'contest') { ?>
                                    <span class="ui mini basic label">
                                        <?php
                                        echo intval($activity['contest_visible']) === 1
                                            ? '대회 공개'
                                            : '대회 비공개';
                                        ?>
                                    </span>
                                <?php } ?>

                                <span class="ui mini basic label">
                                    <?php
                                    echo intval($activity['visible']) === 1
                                        ? '학생 표시 가능'
                                        : '학생 표시 제한';
                                    ?>
                                </span>

                                <?php if (
                                    intval($view_course['status']) === 1 &&
                                    $activity['activity_type'] === 'contest' &&
                                    intval($activity['contest_id']) > 0
                                ): ?>
                                    <form class="ui small form" method="post"
                                          action="course_contest_restore.php"
                                          style="margin-top:1rem;"
                                          onsubmit="return confirm('활동을 비공개로 복구합니다. 선택한 설정에 따라 원래 차시를 복원하거나 다른 차시에 배정하시겠습니까?');">
                                        <?php
                                        $restore_contest_id = intval($activity['contest_id']);
                                        $restore_repair_active = true;
                                        require(__DIR__ . '/course_contest_restore_fields.php');
                                        ?>
                                        <button class="ui tiny blue button" type="submit">차시 연결 복구</button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </div>

                    <?php } ?>

                </div>

            <?php } ?>

        </div>

    <?php
    }
    ?>


    <?php
    if (
        $view_can_manage_contests &&
        !empty($view_removed_contests)
    ) {
    ?>

        <h3
            class="ui dividing header"
            style="margin-top:2rem;">
            제거된 Contest 활동
        </h3>

        <table class="ui celled table">

            <thead>
                <tr>
                    <th>차시</th>
                    <th>대회 번호</th>
                    <th>제목</th>
                    <th>제거 상태</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

                <?php
                foreach ($view_removed_contests as $contest) {

                    $link_type =
                        isset($contest['link_type'])
                        ? $contest['link_type']
                        : 'created';
                ?>

                    <tr>

                        <td>
                            <?php
                            echo intval($contest['lesson_no']);
                            ?>차시
                        </td>

                        <td>
                            <?php
                            echo intval($contest['contest_id']);
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                isset($contest['title'])
                                    ? $contest['title']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            <div style="margin-top:0.4rem;">

                                <?php
                                if ($link_type === 'linked') {
                                ?>

                                    <span class="ui tiny blue basic label">
                                        기존 대회 연결
                                    </span>

                                <?php
                                } else {
                                ?>

                                    <span class="ui tiny teal basic label">
                                        Course 생성
                                    </span>

                                <?php
                                }
                                ?>

                            </div>
                        </td>

                        <td>
                            <span class="ui tiny grey label">
                                제거됨
                            </span>
                        </td>

                        <td class="center aligned">

                            <?php
                            if (
                                intval($view_course['status']) === 1
                            ) {
                            ?>

                                <form class="ui small form" method="post" action="course_contest_restore.php"
                                      onsubmit="return confirm('Contest 활동을 비공개로 복원합니다. 선택한 설정에 따라 원래 차시를 복원하거나 다른 차시에 배정하시겠습니까?');">
                                    <?php
                                    $restore_contest_id = intval($contest['contest_id']);
                                    $restore_repair_active = false;
                                    require(__DIR__ . '/course_contest_restore_fields.php');
                                    ?>
                                    <button class="ui tiny blue basic button" type="submit">비공개로 복원</button>
                                </form>
                            <?php
                            } else {
                                echo '-';
                            }
                            ?>

                        </td>

                    </tr>

                <?php
                }
                ?>

            </tbody>

        </table>

    <?php
    }
    ?>

    <?php
    if (
        $view_can_manage_contests &&
        !empty($view_inactive_general_activities)
    ) {
    ?>

        <h3 class="ui dividing header">
            비활성 학습 활동

            <span class="ui mini basic label">
                <?php
                echo count($view_inactive_general_activities);
                ?>개
            </span>
        </h3>

        <table class="ui celled compact table">

            <thead>
                <tr>
                    <th>차시</th>
                    <th>유형</th>
                    <th>활동 제목</th>
                    <th>상태</th>
                    <th class="center aligned">관리</th>
                </tr>
            </thead>

            <tbody>

                <?php
                foreach ($view_inactive_general_activities as $activity) {

                    $type_labels = array(
                        'material' => '학습 자료',
                        'assignment' => '학습 과제',
                        'simulation' => '시뮬레이션'
                    );

                    $type = (string)$activity['activity_type'];

                    $type_label =
                        $type_labels[$type] ?? $type;

                    $can_restore =
                        intval($view_course['status']) === 1 &&
                        intval($activity['lesson_status']) === 1;
                ?>

                    <tr>

                        <td>
                            <?php
                            if (
                                isset($activity['lesson_no']) &&
                                $activity['lesson_no'] !== null
                            ) {
                                echo intval($activity['lesson_no']) . '차시';

                                $lesson_title =
                                    trim((string)($activity['lesson_title'] ?? ''));

                                if ($lesson_title !== '') {
                                    echo ' — ';
                                    echo htmlspecialchars(
                                        $lesson_title,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                }
                            } else {
                                echo '미배정';
                            }
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                $type_label,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo htmlspecialchars(
                                (string)$activity['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </td>

                        <td>
                            <span class="ui tiny grey label">
                                비활성
                            </span>
                        </td>

                        <td class="center aligned">

                            <?php if ($can_restore) { ?>

                                <form
                                    method="post"
                                    action="course_activity_restore.php">

                                    <?php echo $view_csrf_input; ?>

                                    <input
                                        type="hidden"
                                        name="course_id"
                                        value="<?php echo intval($course_id); ?>">

                                    <input
                                        type="hidden"
                                        name="activity_id"
                                        value="<?php
                                                echo intval($activity['activity_id']);
                                                ?>">

                                    <button
                                        type="submit"
                                        class="ui mini blue basic button">
                                        <i class="undo icon"></i>
                                        활동 복구
                                    </button>

                                </form>

                            <?php } else { ?>

                                <span class="ui mini basic label">
                                    차시 활성화 필요
                                </span>

                            <?php } ?>

                        </td>

                    </tr>

                <?php } ?>

            </tbody>

        </table>

    <?php } ?>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>