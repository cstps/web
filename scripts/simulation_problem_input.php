<?php

require_once(__DIR__ . '/simulation_execution_config.php');

function simulation_problem_input_string(
    array $input,
    $key,
    $max_bytes,
    $required = true
) {
    $value = $input[$key] ?? null;

    if (
        !is_string($value) ||
        strlen($value) > $max_bytes ||
        strpos($value, "\0") !== false ||
        preg_match('//u', $value) !== 1 ||
        ($required && trim($value) === '')
    ) {
        throw new InvalidArgumentException(
            '입력 내용을 확인해 주세요: ' . $key
        );
    }

    return $value;
}


function simulation_problem_input_choice(
    array $input,
    $key,
    array $allowed
) {
    $value = $input[$key] ?? null;

    if (!is_string($value) || !in_array($value, $allowed, true)) {
        throw new InvalidArgumentException(
            '선택한 설정이 올바르지 않습니다: ' . $key
        );
    }

    return $value;
}


function simulation_problem_input_dimension(array $input, $key)
{
    $value = $input[$key] ?? null;

    if (
        !is_string($value) ||
        preg_match('/^[1-9][0-9]{2,3}$/D', $value) !== 1 ||
        (int)$value < 100 ||
        (int)$value > 2000
    ) {
        throw new InvalidArgumentException(
            '캔버스 크기는 100~2000 사이의 정수여야 합니다.'
        );
    }

    return (int)$value;
}


// 소유자와 수정 권한은 여기서 받지 않는다.
// 저장 처리기가 로그인 사용자와 DB의 문제 소유자를 직접 검사한다.
function simulation_validate_problem_input(array $input)
{
    $title = trim(simulation_problem_input_string(
        $input, 'title', 1020
    ));

    if (
        preg_match('/^.{1,255}$/usD', $title) !== 1 ||
        preg_match('/[\x00-\x1f\x7f]/', $title) === 1
    ) {
        throw new InvalidArgumentException(
            '문제 제목은 줄바꿈 없이 1~255자로 입력해 주세요.'
        );
    }

    $description = simulation_problem_input_string(
        $input, 'description', 65535, false
    );

    $starter = simulation_problem_input_string(
        $input, 'starter_code', 200000
    );

    $mode = simulation_problem_input_choice(
        $input, 'simulation_mode', array('free', 'drawing')
    );

    $sharing = simulation_problem_input_choice(
        $input, 'sharing', array('private', 'shared')
    );

    $published = simulation_problem_input_choice(
        $input, 'published', array('0', '1')
    );

    $allow_edit = simulation_problem_input_choice(
        $input, 'allow_edit', array('0', '1')
    );

    $allow_submit = simulation_problem_input_choice(
        $input, 'allow_submit', array('0', '1')
    );

    $profile = simulation_problem_input_choice(
        $input,
        'execution_profile',
        array('p5js-2.3.4', 'python-draw-poc-v1')
    );

    // 언어·엔진·버전은 개별 요청값이 아닌 허용된 조합으로 결정한다.
    if ($profile === 'p5js-2.3.4') {
        $execution = array(
            'source_language' => 'javascript',
            'execution_engine' => 'p5js',
            'execution_version' => '2.3.4',
        );
    } else {
        $execution = array(
            'source_language' => 'python',
            'execution_engine' => 'python-stdlib-rgba-poc',
            'execution_version' => '1',
        );

        if ($published !== '0') {
            throw new InvalidArgumentException(
                'Python 문제는 현재 비공개로만 저장할 수 있습니다.'
            );
        }
    }

    $execution = simulation_validate_execution_metadata(
        $execution,
        true
    );

    $width = simulation_problem_input_dimension($input, 'canvas_width');
    $height = simulation_problem_input_dimension($input, 'canvas_height');

    if (
        ($mode === 'drawing' || $execution['source_language'] === 'python') &&
        ($width !== 400 || $height !== 400)
    ) {
        throw new InvalidArgumentException(
            '현재 그림 문제와 Python 실험은 400×400 캔버스만 지원합니다.'
        );
    }

    return array_merge($execution, array(
        'title' => $title,
        'description' => $description,
        'starter_code' => $starter,
        'simulation_mode' => $mode,
        'canvas_width' => $width,
        'canvas_height' => $height,
        'allow_edit' => (int)$allow_edit,
        'allow_submit' => (int)$allow_submit,
        'sharing' => $sharing,
        'published' => (int)$published,
    ));
}
