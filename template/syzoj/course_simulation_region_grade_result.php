<?php

// 권한 검사를 마친 교사용 결과 템플릿에서만 사용한다.
if (
    !isset($submission) ||
    !is_array($submission) ||
    !isset($simulation_escape) ||
    !is_callable($simulation_escape)
) {
    http_response_code(404);
    exit;
}

$region_display_raw = $submission['grade_region_comparison_json'] ?? null;

if (
    ($submission['grading_status'] ?? '') !== 'graded' ||
    $region_display_raw === null
) {
    return;
}

$region_display = is_string($region_display_raw) &&
    strlen($region_display_raw) <= 131072
    ? json_decode($region_display_raw, true)
    : null;

$region_display_number = static function ($value, $maximum) {
    return (is_int($value) || is_float($value)) &&
        is_finite((float)$value) &&
        $value >= 0 &&
        $value <= $maximum;
};

$region_display_valid = (
    is_array($region_display) &&
    ($region_display['metric'] ?? '') ===
        'weighted_region_color_coverage_v1' &&
    ($region_display['aggregation'] ?? '') ===
        'weighted_mean_with_outside_penalty' &&
    isset($region_display['regions']) &&
    is_array($region_display['regions']) &&
    count($region_display['regions']) >= 1 &&
    count($region_display['regions']) <= 20 &&
    $region_display_number(
        $region_display['weighted_coverage'] ?? null, 100
    ) &&
    $region_display_number(
        $region_display['outside_penalty_factor'] ?? null, 1
    ) &&
    $region_display_number(
        $region_display['final_coverage'] ?? null, 100
    ) &&
    is_int($region_display['outside_student_foreground'] ?? null) &&
    $region_display['outside_student_foreground'] >= 0 &&
    $region_display['outside_student_foreground'] <= 160000
);

if ($region_display_valid) {
    foreach ($region_display['regions'] as $region_display_row) {
        if (
            !is_array($region_display_row) ||
            !is_string($region_display_row['label'] ?? null) ||
            !$region_display_number(
                $region_display_row['coverage'] ?? null, 100
            ) ||
            !is_string($region_display_row['max_score'] ?? null) ||
            !preg_match(
                '/^[0-9]{1,5}\.[0-9]{2}$/D',
                $region_display_row['max_score']
            ) ||
            (float)$region_display_row['max_score'] <= 0
        ) {
            $region_display_valid = false;
            break;
        }
    }
}
?>

<?php if ($region_display_valid): ?>
    <div class="ui divider"></div>
    <div class="ui small header">영역별 비교 결과</div>

    <table class="ui compact unstackable table">
        <thead>
            <tr>
                <th>영역</th>
                <th>충족률</th>
                <th>배점</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($region_display['regions'] as $region_display_row): ?>
                <tr>
                    <td><?= $simulation_escape(
                        $region_display_row['label']
                    ) ?></td>
                    <td><?= $simulation_escape(number_format(
                        (float)$region_display_row['coverage'],
                        3, '.', ''
                    )) ?>%</td>
                    <td><?= $simulation_escape(
                        $region_display_row['max_score']
                    ) ?>점</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        영역별 배점 반영 충족률:
        <?= $simulation_escape(number_format(
            (float)$region_display['weighted_coverage'],
            3, '.', ''
        )) ?>%
        <br>
        영역 밖 추가 전경:
        <?= intval($region_display['outside_student_foreground']) ?>픽셀
        · 추가 도형 감점 반영 비율:
        <?= $simulation_escape(number_format(
            100 * (float)$region_display['outside_penalty_factor'],
            3, '.', ''
        )) ?>%
    </p>

    <p>
        <span class="ui teal basic label">
            영역별 최종 충족률:
            <?= $simulation_escape(number_format(
                (float)$region_display['final_coverage'],
                3, '.', ''
            )) ?>%
        </span>
    </p>

    <p>
        영역별 충족률에 배점을 반영한 뒤,
        영역 밖 추가 도형 감점을 적용하여 최종 점수를 산정합니다.
    </p>
<?php else: ?>
    <p>영역별 결과 정보 확인이 필요합니다.</p>
<?php endif; ?>
