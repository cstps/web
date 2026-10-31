<?php
$ca_type = (string)$activity['activity_type'];
$ca_title = (string)($activity['display_title'] ?? $activity['activity_title'] ?? $activity['title'] ?? '');
$ca_id = (int)($activity['activity_id'] ?? 0);
$ca_url = '';
$ca_label = '';
$ca_color = 'blue';
$ca_disabled = false;
$ca_routes = array(
    'material' => array('course_material_view.php', '학습 자료 보기', 'blue'),
    'assignment' => array('course_assignment_view.php', '과제 보기', 'orange'),
    'simulation' => array('course_simulation_view.php', '시뮬레이션 실행', 'teal'),
);
if (isset($ca_routes[$ca_type]) && $ca_id > 0) {
    $ca_route = $ca_routes[$ca_type];
    $ca_url = $ca_route[0] . '?activity_id=' . $ca_id;
    $ca_label = $ca_route[1];
    if (!$course_activity_teacher) {
        $ca_student_labels = array(
            'material' => '자료 열기',
            'assignment' => '과제 확인·제출',
            'simulation' => '시뮬레이션 실행',
        );
        $ca_label = $ca_student_labels[$ca_type];
    }
    $ca_color = $ca_route[2];
} elseif ($ca_type === 'contest') {
    if ($course_activity_teacher) {
        $ca_contest_id = (int)($activity['contest_id'] ?? 0);
        if ($ca_contest_id > 0) {
            $ca_url = 'contest.php?cid=' . $ca_contest_id;
            $ca_label = '코딩 실습 보기';
            $ca_color = 'green';
        }
    } elseif (isset($activity['contest_learning'])) {
        $ca_learning = $activity['contest_learning'];
        $ca_contest_id = (int)$ca_learning['contest_id'];
        $ca_state = (string)($ca_learning['lesson_status'] ?? 'unscheduled');
        $ca_active = (int)$view_course['status'] === 1;
        if ($ca_active && $ca_contest_id > 0 && in_array($ca_state, array('ongoing', 'ended'), true)) {
            $ca_url = 'contest.php?cid=' . $ca_contest_id;
            $ca_label = $ca_state === 'ongoing' ? '코딩 문제 풀기' : '코딩 실습 보기';
            $ca_color = 'green';
        } else {
            $ca_label = $ca_active ? '시작 전' : '수업 종료';
            $ca_disabled = true;
        }
    }
}
?>
<div class="course-activity-heading<?= $course_activity_teacher ? '' : ' course-student-activity-heading' ?><?= (!$course_activity_teacher && $ca_label === '') ? ' course-student-activity-no-action' : '' ?>">
    <div class="course-activity-name"><?= htmlspecialchars($ca_title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php if ($ca_label !== ''): ?>
        <div class="course-activity-primary">
            <?php if ($ca_disabled): ?>
                <button type="button" class="ui small disabled button" disabled><?= htmlspecialchars($ca_label, ENT_QUOTES, 'UTF-8') ?></button>
            <?php else: ?>
                <a class="ui small <?= $ca_color ?> button" href="<?= htmlspecialchars($ca_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ca_label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
