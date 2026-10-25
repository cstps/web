<?php
require_once(__DIR__ . '/simulation-problem-init.php');
require_once(__DIR__ . '/../scripts/simulation_problem_image_view.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

try {
    $view = simulation_problem_image_view(
        $dbh,
        $simulation_admin_actor,
        $_GET['version_id'] ?? null
    );

    echo json_encode(
        array('ok' => true, 'image' => $view),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'message' => '버전 번호를 확인해 주세요.'));
} catch (UnexpectedValueException $exception) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'message' => '조회 권한이 없습니다.'));
} catch (Throwable $exception) {
    error_log('[simulation_problem_image_status] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(array('ok' => false, 'message' => '생성 상태 조회에 실패했습니다.'));
}
