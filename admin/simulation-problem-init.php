<?php

require_once(__DIR__ . '/../include/db_info.inc.php');
require_once(__DIR__ . '/../scripts/simulation_problem_permissions.php');

header('Cache-Control: private, no-store');

try {
    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('데이터베이스 연결 실패');
    }

    $simulation_admin_actor = simulation_problem_current_actor($dbh);
} catch (Throwable $e) {
    error_log('[simulation_problem_admin] ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ko"><meta charset="utf-8">';
    echo '<title>조회 오류</title><body>';
    echo '<p>그림 문제 관리 권한을 확인하지 못했습니다.</p>';
    echo '<p><a href="/">홈으로 이동</a></p></body></html>';
    exit;
}

if (!simulation_problem_can_create($simulation_admin_actor)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ko"><meta charset="utf-8">';
    echo '<title>접근 권한 없음</title><body>';
    echo '<p>그림 문제는 관리자 또는 수업 소유자·담당교사만 관리할 수 있습니다.</p>';
    echo '<p><a href="/">홈으로 이동</a></p></body></html>';
    exit;
}

// 이 파일을 사용하는 그림 문제 관리 페이지에만 진입 예외를 적용한다.
$admin_page_access_allowed = true;
require_once(__DIR__ . '/admin-init.php');
