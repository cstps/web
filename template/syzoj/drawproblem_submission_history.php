<?php

if (
    !isset($draw_history) ||
    !is_array($draw_history) ||
    !isset($draw_history['submissions']) ||
    !is_array($draw_history['submissions']) ||
    !isset($simulation_escape) ||
    !is_callable($simulation_escape)
) {
    http_response_code(404);
    exit;
}
?>

<?php if (!$draw_history['submissions']): ?>
    <p>현재 표시할 제출 기록이 없습니다.</p>
<?php else: ?>
    <div style="overflow-x:auto;">
        <table class="ui compact unstackable table">
            <thead>
                <tr>
                    <th>제출 번호</th>
                    <th>문제 버전</th>
                    <th>제출 일시</th>
                    <th>채점 상태</th>
                    <th>점수</th>
                    <th>상세 결과</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($draw_history['submissions'] as $submission): ?>
                    <?php
                    $draw_history_state = $submission['grading_status'];
                    $draw_history_score = $submission['grade_score'];
                    $draw_history_maximum = $submission['grade_max_score'];

                    $draw_history_labels = array(
                        '' => '채점 기록 없음',
                        'pending' => '채점 대기',
                        'running' => '채점 중',
                        'graded' => $draw_history_score === null
                            ? '비교 완료'
                            : '채점 완료',
                        'error' => '처리 오류',
                    );

                    $draw_history_score_text = '-';

                    if (
                        $draw_history_state === 'graded' &&
                        $draw_history_score !== null
                    ) {
                        $draw_history_score_text = $draw_history_score .
                            ' / ' . $draw_history_maximum . '점';
                    } elseif ($draw_history_state === 'graded') {
                        $draw_history_score_text = '미산정';
                    }
                    ?>
                    <tr
                        data-submission-id="<?= $simulation_escape(
                            $submission['submission_id']
                        ) ?>"
                        data-grade-state="<?= $simulation_escape(
                            $draw_history_state
                        ) ?>"
                    >
                        <td><?= $simulation_escape(
                            $submission['submission_no']
                        ) ?></td>
                        <td><?= $simulation_escape(
                            $submission['version_no']
                        ) ?></td>
                        <td><?= $simulation_escape(
                            $submission['submitted_at']
                        ) ?></td>
                        <td><?= $simulation_escape(
                            $draw_history_labels[$draw_history_state]
                        ) ?></td>
                        <td><?= $simulation_escape(
                            $draw_history_score_text
                        ) ?></td>
                        <td>
                            <?php require(
                                __DIR__ .
                                '/course_simulation_student_grade_details.php'
                            ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
