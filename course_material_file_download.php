<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = '학습 자료 파일 다운로드';

function course_file_download_error($message, $status = 400)
{
    global $view_errors, $OJ_TEMPLATE;

    http_response_code($status);

    $view_errors = '<h2>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
        '</h2>';

    require("template/" . $OJ_TEMPLATE . "/error.php");
    exit;
}

// ============================================================
// 1. 요청 및 로그인 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    course_file_download_error('잘못된 요청입니다.', 405);
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_file_download_error('로그인이 필요합니다.', 403);
}

// ============================================================
// 2. 파일 번호 검증
// ============================================================

$file_raw = (
    isset($_GET['file_id']) &&
    is_string($_GET['file_id'])
) ? $_GET['file_id'] : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $file_raw) ||
    strlen($file_raw) > 10 ||
    (float)$file_raw > 2147483647
) {
    course_file_download_error('잘못된 첨부파일 번호입니다.');
}

$file_id = intval($file_raw);

// ============================================================
// 3. 파일 및 소속 Course 조회
// ============================================================

$rows = pdo_query(
    "SELECT
        f.file_id,
        f.original_name,
        f.stored_name,
        f.file_size,
        a.course_id,
        a.visible AS activity_visible,
        a.status AS activity_status,
        l.visible AS lesson_visible,
        l.status AS lesson_status
     FROM course_material_file f
     INNER JOIN course_material m
       ON m.activity_id = f.activity_id
     INNER JOIN course_activity a
       ON a.activity_id = f.activity_id
      AND a.activity_type = 'material'
     INNER JOIN course_lesson l
       ON l.lesson_id = a.lesson_id
      AND l.course_id = a.course_id
     INNER JOIN course c
       ON c.course_id = a.course_id
     WHERE f.file_id = ?
     LIMIT 1",
    $file_id
);

if ($rows === false || !isset($rows[0])) {
    course_file_download_error(
        '첨부파일을 찾을 수 없습니다.',
        404
    );
}

$file = $rows[0];
$course_id = intval($file['course_id']);

// ============================================================
// 4. 활성 상태 및 접근 권한 검사
// ============================================================

if (
    intval($file['activity_status']) !== 1 ||
    intval($file['lesson_status']) !== 1
) {
    course_file_download_error(
        '다운로드할 수 없는 학습 자료입니다.',
        403
    );
}

$is_manager = (
    course_can_access($course_id) &&
    course_can_manage_contests($course_id)
);

$is_student = course_is_active_student($course_id);

if (!$is_manager && !$is_student) {
    course_file_download_error(
        '첨부파일 다운로드 권한이 없습니다.',
        403
    );
}

if (
    !$is_manager &&
    (
        intval($file['lesson_visible']) !== 1 ||
        intval($file['activity_visible']) !== 1
    )
) {
    course_file_download_error(
        '공개되지 않은 학습 자료입니다.',
        403
    );
}

// ============================================================
// 5. 비공개 파일 저장소 확인
// ============================================================

$stored_name = (string)$file['stored_name'];

if (!preg_match('/^[a-f0-9]{64}$/D', $stored_name)) {
    course_file_download_error(
        '첨부파일 저장 정보가 올바르지 않습니다.',
        404
    );
}

$storage_dir = '/home/judge/course_files';
$storage_real = realpath($storage_dir);

if (
    $storage_real === false ||
    is_link($storage_dir) ||
    !is_dir($storage_real)
) {
    course_file_download_error(
        '파일 저장소를 사용할 수 없습니다.',
        500
    );
}

$path = $storage_real . '/' . $stored_name;

if (
    is_link($path) ||
    !is_file($path) ||
    !is_readable($path)
) {
    course_file_download_error(
        '저장된 첨부파일을 찾을 수 없습니다.',
        404
    );
}

$handle = @fopen($path, 'rb');

if ($handle === false) {
    course_file_download_error(
        '첨부파일을 열 수 없습니다.',
        500
    );
}

$stat = fstat($handle);

if (
    $stat === false ||
    !isset($stat['size']) ||
    intval($stat['size']) !== intval($file['file_size']) ||
    intval($stat['size']) <= 0 ||
    intval($stat['size']) > 20 * 1024 * 1024
) {
    fclose($handle);

    course_file_download_error(
        '첨부파일 크기가 저장 정보와 일치하지 않습니다.',
        500
    );
}

// ============================================================
// 6. 다운로드 응답
// ============================================================

// 저장 시 검증된 원본 이름을 사용하되,
// HTTP 헤더에 제어문자가 들어가지 않도록 재검사한다.
$download_name = (string)$file['original_name'];

if (
    $download_name === '' ||
    strlen($download_name) > 255 ||
    !preg_match('//u', $download_name) ||
    preg_match('/[\x00-\x1F\x7F]/', $download_name) ||
    strpos($download_name, '/') !== false ||
    strpos($download_name, '\\') !== false
) {
    $download_name = 'attachment';
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// 앞서 생성된 출력 버퍼는 다운로드 데이터와 섞이지 않게 제거한다.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="attachment"; filename*=UTF-8' .
    "''" . rawurlencode($download_name));
header('Content-Length: ' . intval($stat['size']));
header('Cache-Control: private, no-store');
header('Pragma: no-cache');

while (!feof($handle)) {
    $chunk = fread($handle, 65536);

    if ($chunk === false || $chunk === '') {
        break;
    }

    echo $chunk;

    if (connection_aborted()) {
        break;
    }

    flush();
}

fclose($handle);
exit;
