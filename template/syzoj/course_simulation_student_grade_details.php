<?php

// 본인 제출 권한 검사를 마친 학생용 조회 경로에서 사용한다.
if (
    !isset($submission) ||
    !is_array($submission) ||
    !isset($simulation_escape) ||
    !is_callable($simulation_escape)
) {
    http_response_code(404);
    exit;
}

if (
    ($submission['grading_status'] ?? '') !== 'graded' ||
    ($submission['grade_region_comparison_json'] ?? null) === null
) {
    echo '-';
    return;
}
?>
<details>
    <summary>영역별 결과 보기</summary>
    <?php require(__DIR__ . '/course_simulation_region_grade_result.php'); ?>
</details>
