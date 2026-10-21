<?php

require_once(__DIR__ . '/course_simulation_region_config.php');

function simulation_region_verify_number($value, $expected, $limit = 100.0)
{
    if (
        (!is_int($value) && !is_float($value)) ||
        !is_finite((float)$value) ||
        $value < 0 ||
        $value > $limit ||
        abs((float)$value - (float)$expected) > 0.0000001
    ) {
        throw new RuntimeException('영역별 비교 결과의 계산값이 올바르지 않습니다.');
    }
    return (float)$expected;
}

function simulation_validate_region_result(
    $result,
    $regions,
    $max_score,
    $radius,
    $whole
) {
    $configured = simulation_validate_region_config($regions, $max_score);
    $total_units = simulation_region_score_units($max_score);
    $normalized_max = sprintf(
        '%d.%02d', intdiv($total_units, 100), $total_units % 100
    );

    if (!is_int($radius) || $radius < 0 || $radius > 10) {
        throw new RuntimeException('영역별 비교 허용 반경이 올바르지 않습니다.');
    }

    if (
        !is_array($result) ||
        !isset($result['regions']) ||
        !is_array($result['regions']) ||
        count($result['regions']) !== count($configured) ||
        array_keys($result['regions']) !== range(0, count($configured) - 1)
    ) {
        throw new RuntimeException('영역별 비교 결과 목록이 올바르지 않습니다.');
    }

    $metadata = array(
        'metric' => 'weighted_region_color_coverage_v1',
        'aggregation' => 'weighted_mean_with_outside_penalty',
        'max_score' => $normalized_max,
        'position_tolerance_px' => $radius,
        'color_tolerance' => 0,
    );

    foreach ($metadata as $key => $expected) {
        if (($result[$key] ?? null) !== $expected) {
            throw new RuntimeException('영역별 비교 결과의 설정 정보가 다릅니다.');
        }
    }

    if (!is_array($whole)) {
        throw new RuntimeException('전체 그림 비교 결과가 없습니다.');
    }
    foreach (array('reference_foreground', 'student_foreground') as $key) {
        if (
            !isset($whole[$key]) ||
            !is_int($whole[$key]) ||
            $whole[$key] < 0 ||
            $whole[$key] > 160000
        ) {
            throw new RuntimeException('전체 그림의 전경 픽셀 수가 올바르지 않습니다.');
        }
    }
    if ($whole['reference_foreground'] === 0) {
        throw new RuntimeException('정답 전경이 없는 결과입니다.');
    }

    $validated_regions = array();
    $reference_sum = 0;
    $student_sum = 0;
    $covered_area = 0;
    $weighted = 0.0;

    foreach ($configured as $index => $region) {
        $item = $result['regions'][$index];

        if (!is_array($item)) {
            throw new RuntimeException('개별 영역 결과가 올바르지 않습니다.');
        }

        foreach (array('id', 'x', 'y', 'width', 'height', 'max_score') as $key) {
            if (($item[$key] ?? null) !== $region[$key]) {
                throw new RuntimeException('요청한 영역과 비교 결과의 영역이 다릅니다.');
            }
        }

        $metrics = $item['metrics'] ?? null;
        if (!is_array($metrics)) {
            throw new RuntimeException('영역별 픽셀 비교 결과가 없습니다.');
        }

        $metric_metadata = array(
            'metric' => 'symmetric_foreground_color_coverage',
            'aggregation' => 'harmonic_mean',
            'position_tolerance_px' => $radius,
            'color_tolerance' => 0,
            'neighborhood' => 'euclidean_disk',
        );

        foreach ($metric_metadata as $key => $expected) {
            if (($metrics[$key] ?? null) !== $expected) {
                throw new RuntimeException('영역별 픽셀 비교 방식이 다릅니다.');
            }
        }

        $area = $region['width'] * $region['height'];
        $counts = array();

        foreach (array(
            'reference_foreground', 'student_foreground',
            'reference_matched', 'student_matched'
        ) as $key) {
            $value = $metrics[$key] ?? null;
            if (!is_int($value) || $value < 0 || $value > $area) {
                throw new RuntimeException('영역별 픽셀 수가 올바르지 않습니다.');
            }
            $counts[$key] = $value;
        }

        $reference_count = $counts['reference_foreground'];
        $student_count = $counts['student_foreground'];

        if (
            $reference_count === 0 ||
            $counts['reference_matched'] > $reference_count ||
            $counts['student_matched'] > $student_count ||
            (
                $student_count === 0 &&
                $counts['reference_matched'] !== 0
            )
        ) {
            throw new RuntimeException('영역별 일치 픽셀 수가 올바르지 않습니다.');
        }

        $reference_ratio = $counts['reference_matched'] / $reference_count;
        $student_ratio = $student_count > 0
            ? $counts['student_matched'] / $student_count
            : 0.0;

        $coverage = $reference_ratio + $student_ratio > 0
            ? 200.0 * $reference_ratio * $student_ratio
                / ($reference_ratio + $student_ratio)
            : 0.0;

        $validated_metrics = array_merge($metric_metadata, $counts);

        foreach (array(
            'reference_coverage' => 100.0 * $reference_ratio,
            'student_coverage' => 100.0 * $student_ratio,
            'symmetric_coverage' => $coverage,
        ) as $key => $expected) {
            $validated_metrics[$key] = simulation_region_verify_number(
                $metrics[$key] ?? null, $expected
            );
        }

        $units = simulation_region_score_units($region['max_score']);
        $contribution = $coverage * $units / $total_units;

        simulation_region_verify_number($item['coverage'] ?? null, $coverage);
        simulation_region_verify_number(
            $item['weighted_contribution'] ?? null, $contribution
        );

        $validated_regions[] = array_merge($region, array(
            'coverage' => $coverage,
            'weighted_contribution' => $contribution,
            'metrics' => $validated_metrics,
        ));

        $reference_sum += $reference_count;
        $student_sum += $student_count;
        $covered_area += $area;
        $weighted += $contribution;
    }

    $outside = $result['outside_student_foreground'] ?? null;

    if (
        !is_int($outside) ||
        $outside < 0 ||
        $outside > 160000 - $covered_area ||
        $reference_sum !== $whole['reference_foreground'] ||
        $student_sum + $outside !== $whole['student_foreground'] ||
        ($result['student_foreground'] ?? null) !== $whole['student_foreground']
    ) {
        throw new RuntimeException('전체·영역별·영역 밖 픽셀 수가 일치하지 않습니다.');
    }

    $factor = $whole['student_foreground'] > 0
        ? $student_sum / $whole['student_foreground']
        : 1.0;
    $final = min(100.0, max(0.0, $weighted * $factor));

    $validated = $metadata;
    $validated['regions'] = $validated_regions;
    $validated['student_foreground'] = $whole['student_foreground'];
    $validated['outside_student_foreground'] = $outside;
    $validated['weighted_coverage'] = simulation_region_verify_number(
        $result['weighted_coverage'] ?? null, $weighted
    );
    $validated['outside_penalty_factor'] = simulation_region_verify_number(
        $result['outside_penalty_factor'] ?? null, $factor, 1.0
    );
    $validated['final_coverage'] = simulation_region_verify_number(
        $result['final_coverage'] ?? null, $final
    );

    $whole_coverage = $whole['symmetric_coverage'] ?? null;
    if (
        (!is_int($whole_coverage) && !is_float($whole_coverage)) ||
        !is_finite((float)$whole_coverage) ||
        $whole_coverage < 0 || $whole_coverage > 100
    ) {
        throw new RuntimeException('전체 그림 충족률이 올바르지 않습니다.');
    }

    $validated['whole_image_coverage'] = simulation_region_verify_number(
        $result['whole_image_coverage'] ?? null, $whole_coverage
    );

    return $validated;
}
