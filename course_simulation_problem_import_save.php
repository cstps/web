<?php

require_once(__DIR__ . '/scripts/simulation_execution_config.php');
require_once(__DIR__ . '/include/db_info.inc.php');
require_once(__DIR__ . '/include/const.inc.php');
require_once(__DIR__ . '/include/memcache.php');
require_once(__DIR__ . '/include/setlang.php');
require_once(__DIR__ . '/scripts/course_simulation_problem_import_store.php');

$view_title = '문제 가져오기';
header('Cache-Control: no-store, private');

function course_simulation_problem_import_save_error($message, $status)
{
    global $OJ_TEMPLATE, $view_errors, $view_title;

    http_response_code($status);
    $view_errors = '<h2>' . htmlspecialchars(
        $message,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</h2>';

    require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_simulation_problem_import_save_error(
        '로그인이 필요합니다.',
        403
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    course_simulation_problem_import_save_error(
        'POST 요청으로만 문제를 가져올 수 있습니다.',
        405
    );
}

// 업로드와 추가 필드를 허용하지 않는다.
// 번호·토큰의 자료형과 값은 저장 함수에서 다시 검사한다.
$required = array('course_id', 'lesson_id', 'version_id', 'import_token');

if (
    !empty($_FILES) ||
    count($_POST) !== count($required) ||
    array_diff(array_keys($_POST), $required)
) {
    course_simulation_problem_import_save_error(
        '가져오기 요청 항목이 올바르지 않습니다.',
        400
    );
}

foreach ($required as $key) {
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        course_simulation_problem_import_save_error(
            '가져오기 요청값이 올바르지 않습니다.',
            400
        );
    }
}

try {
    if (!function_exists('pdo_query')) {
        require_once(__DIR__ . '/include/pdo.php');
    }

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('가져오기 DB 연결에 실패했습니다.');
    }

    $result = course_simulation_store_problem_import($dbh, $_POST);
} catch (InvalidArgumentException $error) {
    course_simulation_problem_import_save_error(
        '가져오기 요청값이 올바르지 않습니다. 확인 화면을 다시 열어 주세요.',
        400
    );
} catch (UnexpectedValueException $error) {
    course_simulation_problem_import_save_error(
        '가져오기 권한 또는 보안 토큰을 확인해 주세요. 확인 화면을 다시 열어 주세요.',
        403
    );
} catch (Throwable $error) {
    error_log('[course_simulation_problem_import_save] ' . $error->getMessage());
    course_simulation_problem_import_save_error(
        '문제 가져오기를 완료하지 못했습니다. 문제와 목표 이미지 상태를 확인한 뒤 확인 화면을 다시 열어 주세요.',
        500
    );
}

// 저장 성공 뒤 GET 화면으로 이동하여 새로고침에 의한 POST 재전송을 피한다.
header(
    'Location: course_simulation_edit.php?activity_id=' .
    rawurlencode((string)$result['activity_id']),
    true,
    303
);
exit;
