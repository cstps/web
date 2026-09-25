<?php

// 이 파일은 모든 학교 행사 관리자 페이지에서
// 가장 먼저 불러와야 합니다.

if (session_status() === PHP_SESSION_ACTIVE) {
    error_log(
        '[class-share] admin_init.php보다 먼저 ' .
        '다른 세션이 시작되었습니다.'
    );

    http_response_code(500);
    exit('관리자 세션을 초기화할 수 없습니다.');
}


// ------------------------------------------------------------
// 관리자 전용 세션 설정
// ------------------------------------------------------------

$is_https =
    (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== '' &&
        strtolower((string)$_SERVER['HTTPS']) !== 'off'
    ) ||
    (
        isset($_SERVER['SERVER_PORT']) &&
        (int)$_SERVER['SERVER_PORT'] === 443
    );

ini_set(
    'session.use_strict_mode',
    '1'
);

ini_set(
    'session.use_only_cookies',
    '1'
);

session_name(
    'CLASS_SHARE_ADMIN_SESSION'
);

session_set_cookie_params(
    array(
        'lifetime' => 0,
        'path' => '/class-share/admin/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Strict'
    )
);


// ------------------------------------------------------------
// 기존 OJ DB 설정 및 PDO 함수
// ------------------------------------------------------------

$web_root =
    dirname(
        __DIR__,
        3
    );

require_once(
    $web_root .
    '/include/db_info.inc.php'
);

if (!function_exists('pdo_query')) {
    require_once(
        $web_root .
        '/include/pdo.php'
    );
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    error_log(
        '[class-share] 관리자 세션 시작 실패'
    );

    http_response_code(500);
    exit('관리자 세션을 시작할 수 없습니다.');
}


// ------------------------------------------------------------
// DB 연결 문자셋
// ------------------------------------------------------------

$charset_result =
    pdo_query(
        "
        SET NAMES utf8mb4
        COLLATE utf8mb4_0900_ai_ci
        "
    );

if ($charset_result === false) {
    error_log(
        '[class-share] utf8mb4 설정 실패'
    );

    http_response_code(500);
    exit('데이터베이스 연결을 초기화할 수 없습니다.');
}


// ------------------------------------------------------------
// 관리자 페이지 공통 보안 헤더
// ------------------------------------------------------------

header(
    'X-Frame-Options: DENY'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Referrer-Policy: same-origin'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);


// ------------------------------------------------------------
// 관리자 세션 상수
// ------------------------------------------------------------

define(
    'CLASS_SHARE_ADMIN_SESSION_KEY',
    'class_share_admin'
);

define(
    'CLASS_SHARE_ADMIN_IDLE_TIMEOUT',
    3600
);


require_once(
    __DIR__ .
    '/admin_functions.php'
);


require_once(
    __DIR__ .
    '/admin_auth.php'
);
