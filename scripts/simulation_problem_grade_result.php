<?php

require_once(__DIR__ . '/course_simulation_region_result.php');
require_once(dirname(__DIR__) . '/include/course_simulation_scoring.inc.php');

// 서버에서 확인한 정답 설정을 기준으로 비교 결과를 재검산한다.
// 요청·코드·이미지 해시 확인은 이 함수 호출 전에 처리해야 한다.
// 코드 실행과 DB 저장은 수행하지 않는다.
function simulation_problem_validate_grade_metrics(
    $metrics,
    array $grading_config,
    $max_score
) {
    if (!is_array($metrics)) {
        throw new RuntimeException('그림 비교 결과가 없습니다.');
    }

    $mode = $grading_config['scoring_mode'] ?? null;
    $methods = array(
        'metrics_only' => null,
        'whole_image' => 'coverage_linear_v1',
        'regions' => 'region_coverage_linear_v1',
    );

    if (
        !is_string($mode) ||
        !array_key_exists($mode, $methods) ||
        ($grading_config['scoring_method'] ?? null) !== $methods[$mode]
    ) {
        throw new RuntimeException('점수 산정 설정이 올바르지 않습니다.');
    }

    $radius = $grading_config['position_tolerance_px'] ?? null;

    if (
        ($grading_config['schema_version'] ?? null) !== 1 ||
        ($grading_config['metric'] ?? null) !==
            'symmetric_foreground_color_coverage' ||
        ($grading_config['neighborhood'] ?? null) !== 'euclidean_disk' ||
        ($grading_config['color_tolerance'] ?? null) !== 0 ||
        !is_int($radius) ||
        $radius < 0 ||
        $radius > 10
    ) {
        throw new RuntimeException('지원하지 않는 그림 비교 설정입니다.');
    }

    // 비교 지표 전용 방식도 정답 배점 형식은 확인한다.
    $normalized_max = course_simulation_calculate_score(
        0,
        $max_score
    )['max_score'];

    $whole = $metrics['tolerance'] ?? null;

    if (!is_array($whole)) {
        throw new RuntimeException('전체 그림 충족률 결과가 없습니다.');
    }

    $validated = array(
        'metric' => 'symmetric_foreground_color_coverage',
        'aggregation' => 'harmonic_mean',
        'neighborhood' => 'euclidean_disk',
        'position_tolerance_px' => $radius,
        'color_tolerance' => 0,
    );

    foreach ($validated as $key => $expected) {
        if (($whole[$key] ?? null) !== $expected) {
            throw new RuntimeException(
                '요청과 다른 그림 비교 설정입니다: ' . $key
            );
        }
    }

    foreach (array(
        'reference_foreground',
        'student_foreground',
        'reference_matched',
        'student_matched',
    ) as $key) {
        $value = $whole[$key] ?? null;

        if (!is_int($value) || $value < 0 || $value > 160000) {
            throw new RuntimeException(
                '그림 비교 픽셀 수가 올바르지 않습니다: ' . $key
            );
        }

        $validated[$key] = $value;
    }

    $reference_count = $validated['reference_foreground'];
    $student_count = $validated['student_foreground'];

    if (
        $reference_count === 0 ||
        $validated['reference_matched'] > $reference_count ||
        $validated['student_matched'] > $student_count ||
        (
            $student_count === 0 &&
            $validated['reference_matched'] !== 0
        )
    ) {
        throw new RuntimeException('전경과 일치 픽셀 수가 일치하지 않습니다.');
    }

    $reference_ratio =
        $validated['reference_matched'] / $reference_count;

    $student_ratio = $student_count > 0
        ? $validated['student_matched'] / $student_count
        : 0.0;

    $coverage = $reference_ratio + $student_ratio > 0
        ? 200.0 * $reference_ratio * $student_ratio /
            ($reference_ratio + $student_ratio)
        : 0.0;

    foreach (array(
        'reference_coverage' => 100.0 * $reference_ratio,
        'student_coverage' => 100.0 * $student_ratio,
        'symmetric_coverage' => $coverage,
    ) as $key => $expected) {
        $validated[$key] = simulation_region_verify_number(
            $whole[$key] ?? null,
            $expected
        );
    }

    $validated_metrics = array('tolerance' => $validated);

    if ($mode === 'regions') {
        $validated_metrics['region_comparison'] =
            simulation_validate_region_result(
                $metrics['region_comparison'] ?? null,
                $grading_config['regions'] ?? null,
                $normalized_max,
                $radius,
                $validated
            );

        $coverage =
            $validated_metrics['region_comparison']['final_coverage'];
    }

    $score = null;
    $score_max = null;
    $scoring = array(
        'mode' => $mode,
        'method' => null,
    );

    if ($mode !== 'metrics_only') {
        $calculated = course_simulation_calculate_score(
            $coverage,
            $normalized_max
        );

        $score = $calculated['score'];
        $score_max = $calculated['max_score'];

        $scoring = array(
            'mode' => $mode,
            'method' => $methods[$mode],
            'coverage_used' => $coverage,
            'score' => $score,
            'max_score' => $score_max,
        );
    }

    // 검증한 항목과 재계산한 점수만 반환한다.
    return array(
        'metrics' => $validated_metrics,
        'scoring' => $scoring,
        'score' => $score,
        'max_score' => $score_max,
    );
}
