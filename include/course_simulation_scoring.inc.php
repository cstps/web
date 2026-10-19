<?php

// 전체 그림 충족률을 하나의 배점으로 환산하는 계산 함수.
// 복합 그림의 항목별 배점 계산은 별도로 구성한다.
// DB 조회·저장과 채점 방식 선택은 수행하지 않는다.

function course_simulation_calculate_score($coverage, $max_score)
{
    if (
        (!is_int($coverage) && !is_float($coverage)) ||
        !is_finite((float)$coverage) ||
        $coverage < 0 ||
        $coverage > 100
    ) {
        throw new InvalidArgumentException(
            '충족률은 0~100 사이의 유한한 숫자여야 합니다.'
        );
    }

    // DB의 DECIMAL(7,2) 배점을 문자열로 받아 정수 단위로 변환한다.
    if (
        !is_string($max_score) ||
        !preg_match(
            '/^(0|[1-9][0-9]{0,4})(?:\.([0-9]{1,2}))?$/D',
            $max_score,
            $parts
        )
    ) {
        throw new InvalidArgumentException(
            '배점은 소수 둘째 자리까지의 올바른 문자열이어야 합니다.'
        );
    }

    $fraction = str_pad($parts[2] ?? '', 2, '0');
    $max_units = (int)$parts[1] * 100 + (int)$fraction;

    if ($max_units <= 0) {
        throw new InvalidArgumentException('배점은 0보다 커야 합니다.');
    }

    $score_units = (int)round(
        $max_units * (float)$coverage / 100.0,
        0,
        PHP_ROUND_HALF_UP
    );

    $score_units = max(0, min($max_units, $score_units));

    return array(
        'score' => sprintf(
            '%d.%02d',
            intdiv($score_units, 100),
            $score_units % 100
        ),
        'max_score' => sprintf(
            '%d.%02d',
            intdiv($max_units, 100),
            $max_units % 100
        ),
        'scoring_method' => 'coverage_linear_v1',
    );
}
