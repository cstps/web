<?php

// 배점을 정수 단위로 변환한다.
function simulation_region_score_units($value)
{
    if (
        !is_string($value) ||
        !preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/D', $value)
    ) {
        throw new InvalidArgumentException(
            '배점은 소수 둘째 자리까지의 올바른 문자열이어야 합니다.'
        );
    }

    $parts = explode('.', $value, 2);
    $units = intval($parts[0]) * 100
        + intval(str_pad($parts[1] ?? '', 2, '0'));

    if ($units <= 0 || $units > 9999999) {
        throw new InvalidArgumentException(
            '배점은 0 초과 99999.99 이하여야 합니다.'
        );
    }

    return $units;
}

function simulation_region_coordinate($value, $minimum, $maximum)
{
    if (is_string($value)) {
        if (!preg_match('/^(0|[1-9][0-9]{0,4})$/D', $value)) {
            throw new InvalidArgumentException('영역 좌표와 크기는 정수여야 합니다.');
        }
        $value = intval($value);
    }

    if (
        !is_int($value) ||
        $value < $minimum ||
        $value > $maximum
    ) {
        throw new InvalidArgumentException(
            '영역 좌표 또는 크기가 허용 범위를 벗어났습니다.'
        );
    }

    return $value;
}

// 기하 정보와 배점을 검사한다.
// 정답 도형 포함 여부·경계 여백은 렌더링 결과로 별도 검사해야 한다.
function simulation_validate_region_config(
    $regions,
    $max_score,
    $canvas_width = 400,
    $canvas_height = 400
) {
    if (
        !is_int($canvas_width) ||
        !is_int($canvas_height) ||
        $canvas_width < 1 || $canvas_width > 65535 ||
        $canvas_height < 1 || $canvas_height > 65535
    ) {
        throw new InvalidArgumentException('캔버스 크기가 올바르지 않습니다.');
    }

    if (!is_array($regions) || count($regions) < 1 || count($regions) > 20) {
        throw new InvalidArgumentException('채점 영역은 1~20개여야 합니다.');
    }

    $total_units = simulation_region_score_units($max_score);
    $sum_units = 0;
    $normalized = array();
    $identifiers = array();

    $required = array('label', 'x', 'y', 'width', 'height', 'max_score');
    $allowed = array_merge($required, array('id'));

    foreach (array_values($regions) as $index => $region) {
        if (!is_array($region)) {
            throw new InvalidArgumentException('영역 설정 형식이 올바르지 않습니다.');
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $region)) {
                throw new InvalidArgumentException('영역 설정 필드가 누락됐습니다.');
            }
        }
        foreach (array_keys($region) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('지원하지 않는 영역 설정 필드입니다.');
            }
        }

        $id = array_key_exists('id', $region)
            ? $region['id']
            : 'region_' . ($index + 1);

        if (
            !is_string($id) ||
            !preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $id) ||
            isset($identifiers[$id])
        ) {
            throw new InvalidArgumentException(
                '영역 식별자는 중복 없는 영문·숫자·밑줄·하이픈 조합이어야 합니다.'
            );
        }
        $identifiers[$id] = true;

        if (!is_string($region['label'])) {
            throw new InvalidArgumentException('영역 이름은 문자열이어야 합니다.');
        }

        $label = trim($region['label']);
        if (
            preg_match('/\A[^\x00-\x1F\x7F]{1,80}\z/u', $label) !== 1
        ) {
            throw new InvalidArgumentException(
                '영역 이름은 제어문자 없는 1~80자여야 합니다.'
            );
        }

        $x = simulation_region_coordinate(
            $region['x'], 0, $canvas_width - 1
        );
        $y = simulation_region_coordinate(
            $region['y'], 0, $canvas_height - 1
        );
        $width = simulation_region_coordinate(
            $region['width'], 1, $canvas_width
        );
        $height = simulation_region_coordinate(
            $region['height'], 1, $canvas_height
        );

        if (
            $x + $width > $canvas_width ||
            $y + $height > $canvas_height
        ) {
            throw new InvalidArgumentException(
                $label . ': 영역이 캔버스 범위를 벗어났습니다.'
            );
        }

        foreach ($normalized as $previous) {
            if (
                $x < $previous['x'] + $previous['width'] &&
                $x + $width > $previous['x'] &&
                $y < $previous['y'] + $previous['height'] &&
                $y + $height > $previous['y']
            ) {
                throw new InvalidArgumentException(
                    $label . ': 다른 채점 영역과 겹칩니다.'
                );
            }
        }

        $units = simulation_region_score_units($region['max_score']);
        $sum_units += $units;

        $normalized[] = array(
            'id' => $id,
            'label' => $label,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'max_score' => sprintf(
                '%d.%02d',
                intdiv($units, 100),
                $units % 100
            ),
        );
    }

    if ($sum_units !== $total_units) {
        throw new InvalidArgumentException(
            '영역별 배점 합계가 전체 배점과 다릅니다.'
        );
    }

    return $normalized;
}
