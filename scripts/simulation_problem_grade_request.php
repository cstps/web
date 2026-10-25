<?php

require_once(__DIR__ . '/simulation_problem_image_view.php');

class SimulationProblemGradeNotReadyException extends RuntimeException
{
}

// 호출자는 먼저 문제 접근 권한과 제출 허용 여부를 검사해야 한다.
// $reference에는 서버에서 조회한 문제 버전·정답·이미지 행을 전달한다.
// 학생 코드나 정답 코드를 실행하지 않으며 DB를 변경하지 않는다.
function simulation_problem_grade_request_metadata(
    array $reference,
    $student_source
) {
    if (
        !is_string($student_source) ||
        strlen($student_source) > 200000 ||
        strpos($student_source, "\0") !== false ||
        preg_match('//u', $student_source) !== 1 ||
        preg_match('/\S/u', $student_source) !== 1
    ) {
        throw new InvalidArgumentException('채점할 제출 코드가 올바르지 않습니다.');
    }

    if (($reference['simulation_mode'] ?? null) !== 'drawing') {
        throw new InvalidArgumentException('그림 문제만 자동 채점을 요청할 수 있습니다.');
    }

    // 실제 PNG 데이터까지 검사한다.
    // 조회 권한은 이 함수 호출 전에 별도로 확인해야 한다.
    $view = simulation_problem_image_validated_view(
        $reference,
        true
    );

    if ($view['state'] !== 'ready') {
        throw new SimulationProblemGradeNotReadyException(
            '정답 목표 그림의 생성·검사가 완료되지 않았습니다.'
        );
    }

    $request = simulation_problem_image_request_metadata($reference);
    $grading = $request['settings']->grading_config;
    $mode = $grading->scoring_mode ?? null;

    // 지원하지 않는 설정을 채점 대기열에 넣지 않는다.
    $methods = array(
        'metrics_only' => null,
        'whole_image' => 'coverage_linear_v1',
        'regions' => 'region_coverage_linear_v1',
    );

    if (
        !is_string($mode) ||
        !array_key_exists($mode, $methods) ||
        ($grading->scoring_method ?? null) !== $methods[$mode]
    ) {
        throw new SimulationProblemGradeNotReadyException(
            '정답의 점수 산정 설정을 확인해 주세요.'
        );
    }

    if (
        $mode === 'regions' &&
        $view['region_label'] !== '영역 검사 통과'
    ) {
        throw new SimulationProblemGradeNotReadyException(
            '정답의 채점 영역 검사가 완료되지 않았습니다.'
        );
    }

    // 실행 언어·엔진·버전, 정답 코드 해시, 설정 및 실행 파일 해시는
    // 검증된 목표 이미지 요청 정보에 포함되어 있다.
    // 학생·정답 코드와 PNG 원본은 요청 메타데이터에 넣지 않는다.
    return array(
        'schema_version' => 1,
        'reference_request' => (object)$request,
        'target_png_sha256' => $view['png_sha256'],
        'student_source_sha256' => hash('sha256', $student_source),
    );
}
