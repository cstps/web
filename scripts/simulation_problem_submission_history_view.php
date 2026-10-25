<?php

require_once(__DIR__ . '/simulation_problem_submission_history.php');

// 본인 조회 권한과 수행 범위 검사를 마친 결과만 전달한다.
function simulation_problem_render_submission_history(array $draw_history)
{
    if (
        !isset($draw_history['submissions']) ||
        !is_array($draw_history['submissions']) ||
        count($draw_history['submissions']) > 10
    ) {
        throw new RuntimeException('제출 이력 표시 정보가 올바르지 않습니다.');
    }

    $simulation_escape = static function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    };

    $buffer_level = ob_get_level();
    ob_start();

    try {
        require(
            dirname(__DIR__) .
            '/template/syzoj/drawproblem_submission_history.php'
        );

        return ob_get_clean();

    } catch (Throwable $exception) {
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }
        throw $exception;
    }
}
