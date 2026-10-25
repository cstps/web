<?php

// 공통 초기화 과정의 공백·줄바꿈이 PNG 응답에 섞이지 않도록 한다.
$simulation_image_buffer_level = ob_get_level();
ob_start();

require_once(__DIR__ . '/simulation-problem-init.php');
require_once(__DIR__ . '/../scripts/simulation_problem_image_view.php');

$simulation_image_status = 200;
$simulation_image_type = 'image/png';
$simulation_image_body = '';

try {
    $simulation_image_view = simulation_problem_image_view(
        $dbh,
        $simulation_admin_actor,
        $_GET['version_id'] ?? null,
        true
    );

    if (
        $simulation_image_view['state'] !== 'ready' ||
        !isset($simulation_image_view['png_data'])
    ) {
        $simulation_image_status = 409;
        $simulation_image_type = 'text/plain; charset=utf-8';
        $simulation_image_body = '완료된 목표 이미지가 없습니다.';
    } else {
        $simulation_image_body = $simulation_image_view['png_data'];
    }
} catch (InvalidArgumentException $exception) {
    $simulation_image_status = 400;
    $simulation_image_type = 'text/plain; charset=utf-8';
    $simulation_image_body = '문제 버전 번호가 올바르지 않습니다.';
} catch (UnexpectedValueException $exception) {
    $simulation_image_status = 403;
    $simulation_image_type = 'text/plain; charset=utf-8';
    $simulation_image_body = '목표 이미지 조회 권한이 없습니다.';
} catch (Throwable $exception) {
    error_log('[simulation_problem_image] ' . $exception->getMessage());
    $simulation_image_status = 500;
    $simulation_image_type = 'text/plain; charset=utf-8';
    $simulation_image_body = '목표 이미지를 불러오지 못했습니다.';
}

// 이 요청에서 시작한 출력 버퍼를 비우고 검증된 응답만 전송한다.
while (ob_get_level() > $simulation_image_buffer_level) {
    if (!ob_end_clean()) {
        throw new RuntimeException('이미지 응답 출력 버퍼 정리에 실패했습니다.');
    }
}

http_response_code($simulation_image_status);
header('Content-Type: ' . $simulation_image_type);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($simulation_image_body));

echo $simulation_image_body;
exit;
