<?php
$dashboard_sim_escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<section class="ui segment" aria-labelledby="dashboard-simulation-title">
    <h3 class="ui header" id="dashboard-simulation-title">시뮬레이션 제출·채점 현황</h3>
    <p>현재 활성 수강생의 전체 기간 기록입니다.
       학생별 최신 제출과 그 제출의 최신 채점 회차를 표시합니다. 초안은 포함하지 않습니다.</p>
    <?php if ($dashboard_simulation['error'] !== ''): ?>
        <div class="ui negative message" role="alert"><?= $dashboard_sim_escape($dashboard_simulation['error']) ?></div>
    <?php elseif (!$dashboard_simulation['activities']): ?>
        <p>이 수업에 등록된 활성 시뮬레이션 활동이 없습니다.</p>
    <?php else: ?>
        <p><strong><?= (int)$dashboard_simulation['selected']['lesson_no'] ?>차시 · <?= $dashboard_sim_escape($dashboard_simulation['selected']['title']) ?></strong></p>
        <form class="ui form" method="get" action="course_performance_dashboard.php">
            <input type="hidden" name="course_id" value="<?= (int)$course_id ?>">
            <input type="hidden" name="activity_type" value="simulation">
            <input type="hidden" name="simulation_activity_id" value="<?= (int)$dashboard_simulation['selected']['activity_id'] ?>">
            <button class="ui small primary button" type="submit">조회·새로고침</button>
            <a class="ui small basic button" href="course_simulation_submissions.php?activity_id=<?= (int)$dashboard_simulation['selected']['activity_id'] ?>">활동별 제출 상세</a>
        </form>
        <div class="ui divider"></div>
        <p>
            <?= $dashboard_sim_escape(array('python' => 'Python', 'javascript' => 'JavaScript · p5.js')[$dashboard_simulation['selected']['source_language']] ?? '언어 확인 필요') ?>
            · <?= $dashboard_simulation['selected']['simulation_mode'] === 'drawing' ? '그림 문제' : '자유 실습 · 자동채점 없음' ?>
        </p>
        <p>
            전체 <strong><?= (int)$dashboard_simulation['counts']['total'] ?></strong>명 ·
            제출 <strong><?= (int)$dashboard_simulation['counts']['submitted'] ?></strong>명 ·
            미제출 <strong><?= (int)($dashboard_simulation['counts']['total'] - $dashboard_simulation['counts']['submitted']) ?></strong>명
            <?php if ($dashboard_simulation['selected']['simulation_mode'] === 'drawing'): ?>
                · 채점 대기·진행 <strong><?= (int)$dashboard_simulation['counts']['waiting'] ?></strong>명
                · 채점 완료 <strong><?= (int)$dashboard_simulation['counts']['graded'] ?></strong>명
                · 실행 오류 <strong><?= (int)$dashboard_simulation['counts']['error'] ?></strong>명
            <?php endif; ?>
        </p>
        <div class="course-performance-table-wrap">
            <table class="ui celled compact table">
                <thead><tr><th>학생</th><th>최신 제출</th><th>제출 시각</th><th>채점 회차</th><th>상태</th><th>점수</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard_simulation['students'] as $sim_student): ?>
                    <tr>
                        <td><?= $dashboard_sim_escape($sim_student['user_id']) ?><?= ($sim_student['nick'] ?? '') !== '' ? ' (' . $dashboard_sim_escape($sim_student['nick']) . ')' : '' ?></td>
                        <td><?= $sim_student['submission_id'] !== null ? (int)$sim_student['submission_no'] . '회차' : '—' ?></td>
                        <td><?= $dashboard_sim_escape($sim_student['submitted_at'] ?? '—') ?></td>
                        <td><?= $dashboard_simulation['selected']['simulation_mode'] === 'drawing' && $sim_student['grade_id'] !== null ? (int)$sim_student['grading_no'] . '회차' : '—' ?></td>
                        <td><span class="ui tiny <?= $sim_student['state'] === 'error' ? 'red' : ($sim_student['state'] === 'graded' || $sim_student['state'] === 'submitted' ? 'green' : 'basic') ?> label"><?= $dashboard_sim_escape($sim_student['state_label']) ?></span></td>
                        <td><?= $dashboard_sim_escape($sim_student['score_label']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$dashboard_simulation['students']): ?>
                    <tr><td colspan="6">활성 수강생이 없습니다.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
