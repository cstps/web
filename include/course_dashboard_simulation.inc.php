<?php
// 수업 현황의 기존 교사 권한 검사 이후에만 호출한다.
$dashboard_simulation = (static function ($course_id) use ($dashboard_activity_type) {
    $data = array('activities' => array(), 'selected' => null,
        'students' => array(), 'error' => '',
        'counts' => array('total' => 0, 'submitted' => 0, 'waiting' => 0,
            'graded' => 0, 'error' => 0));
    try {
        $rows = pdo_query(
            "SELECT a.activity_id, a.title, a.visible, l.lesson_no,
                    l.visible AS lesson_visible, s.simulation_mode,
                    s.source_language
             FROM course_activity a
             INNER JOIN course_lesson l
               ON l.lesson_id = a.lesson_id AND l.course_id = a.course_id
              AND l.status = 1
             INNER JOIN course_simulation s ON s.activity_id = a.activity_id
             WHERE a.course_id = ? AND a.activity_type = 'simulation'
               AND a.status = 1
             ORDER BY l.lesson_no DESC, a.sort_order, a.activity_id DESC",
            $course_id
        );
        if (!is_array($rows)) throw new RuntimeException('activity query failed');
        $data['activities'] = $rows;
        $raw = $_GET['simulation_activity_id'] ?? '';
        $selected_id = is_string($raw) && preg_match('/^[1-9][0-9]{0,9}$/D', $raw)
            && (float)$raw <= 2147483647 ? (int)$raw : 0;
        foreach ($rows as $row) {
            if ((int)$row['activity_id'] === $selected_id) {
                $data['selected'] = $row;
                break;
            }
        }
        if ($data['selected'] === null && $rows) $data['selected'] = $rows[0];
        if ($data['selected'] === null || $dashboard_activity_type !== 'simulation') return $data;

        $rows = pdo_query(
            "SELECT cs.user_id, u.nick, sub.submission_id, sub.submission_no,
                    sub.submitted_at, g.grade_id, g.grading_no,
                    g.grading_status, g.score, g.max_score
             FROM course_student cs
             LEFT JOIN users u ON u.user_id = cs.user_id
             LEFT JOIN course_simulation_submission sub
               ON sub.submission_id = (
                   SELECT s2.submission_id FROM course_simulation_submission s2
                   WHERE s2.activity_id = ? AND s2.user_id = cs.user_id
                   ORDER BY s2.submission_no DESC, s2.submission_id DESC LIMIT 1
               )
             LEFT JOIN course_simulation_grade g
               ON g.grade_id = (
                   SELECT g2.grade_id FROM course_simulation_grade g2
                   WHERE g2.submission_id = sub.submission_id
                   ORDER BY g2.grading_no DESC, g2.grade_id DESC LIMIT 1
               )
             WHERE cs.course_id = ? AND cs.status = 1
             ORDER BY cs.user_id",
            (int)$data['selected']['activity_id'], $course_id
        );
        if (!is_array($rows)) throw new RuntimeException('submission query failed');
        $labels = array('pending' => '채점 대기', 'running' => '채점 중',
            'graded' => '채점 완료', 'error' => '실행 오류');
        foreach ($rows as $row) {
            $data['counts']['total']++;
            $row['state'] = 'unsubmitted';
            $row['state_label'] = '미제출';
            $row['score_label'] = '—';
            if ($row['submission_id'] !== null) {
                $data['counts']['submitted']++;
                if ($data['selected']['simulation_mode'] !== 'drawing') {
                    $row['state'] = 'submitted';
                    $row['state_label'] = '제출 완료';
                } else {
                    $row['state'] = $row['grade_id'] === null
                        ? 'ungraded' : (string)$row['grading_status'];
                    $row['state_label'] = $row['state'] === 'ungraded'
                        ? '미채점' : ($labels[$row['state']] ?? '상태 확인 필요');
                    if (in_array($row['state'], array('pending', 'running'), true)) {
                        $data['counts']['waiting']++;
                    }
                    if ($row['state'] === 'error') $data['counts']['error']++;
                    if ($row['state'] === 'graded') {
                        $data['counts']['graded']++;
                        if ($row['score'] !== null && $row['max_score'] !== null) {
                            $row['score_label'] = (string)$row['score'] . ' / ' . (string)$row['max_score'];
                        }
                    }
                }
            }
            $data['students'][] = $row;
        }
    } catch (Throwable $e) {
        error_log('[course_dashboard_simulation] ' . $e->getMessage());
        $data['students'] = array();
        $data['error'] = '시뮬레이션 현황을 불러오지 못했습니다. 잠시 후 다시 조회해 주세요.';
    }
    return $data;
})((int)$course_id);
