<?php

require_once(__DIR__ . '/include/admin_init.php');

header('Content-Type: application/json; charset=UTF-8');

function class_share_notice_image_response($status, $data)
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function class_share_notice_image_error($status, $message)
{
    class_share_notice_image_response(
        $status,
        array('error' => $message)
    );
}

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    class_share_notice_image_error(405, 'POST 요청만 허용됩니다.');
}

$admin = class_share_admin_require_login();
class_share_admin_require_post_csrf();

// 행사 번호는 양의 정수만 허용합니다.
$raw_event_id = isset($_POST['event_id'])
    ? $_POST['event_id']
    : '';

if (
    !is_string($raw_event_id) ||
    !preg_match('/^[1-9][0-9]{0,18}$/D', $raw_event_id)
) {
    class_share_notice_image_error(400, '행사 번호가 올바르지 않습니다.');
}

$event_id = (int)$raw_event_id;

if ($event_id <= 0 || (string)$event_id !== $raw_event_id) {
    class_share_notice_image_error(400, '행사 번호가 올바르지 않습니다.');
}

$events = pdo_query(
    'SELECT id, school_id FROM class_share_event WHERE id = ? LIMIT 1',
    $event_id
);

if ($events === false) {
    class_share_notice_image_error(500, '행사 정보를 불러올 수 없습니다.');
}

if (!isset($events[0])) {
    class_share_notice_image_error(404, '행사를 찾을 수 없습니다.');
}

if (!class_share_admin_can_edit_school(
    (int)$events[0]['school_id'],
    $admin
)) {
    class_share_notice_image_error(403, '공지 이미지를 올릴 권한이 없습니다.');
}

// 업로드 파일 구조와 전송 결과를 확인합니다.
$file = isset($_FILES['file']) ? $_FILES['file'] : null;

if (
    !is_array($file) ||
    !isset($file['error'], $file['tmp_name']) ||
    !is_int($file['error']) ||
    !is_string($file['tmp_name'])
) {
    class_share_notice_image_error(400, '업로드할 이미지가 없습니다.');
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = in_array(
        $file['error'],
        array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE),
        true
    )
        ? '서버의 업로드 용량 제한을 초과했습니다.'
        : '이미지를 전송하지 못했습니다. 다시 선택해 주세요.';

    class_share_notice_image_error(400, $message);
}

$temporary_path = $file['tmp_name'];

if (!is_uploaded_file($temporary_path)) {
    class_share_notice_image_error(400, '정상적인 업로드 파일이 아닙니다.');
}

$file_size = filesize($temporary_path);

if (
    $file_size === false ||
    $file_size <= 0 ||
    $file_size > 5 * 1024 * 1024
) {
    class_share_notice_image_error(400, '이미지는 5MB 이하로 올려 주세요.');
}

if (!class_exists('finfo')) {
    class_share_notice_image_error(500, '서버의 이미지 검사 기능이 없습니다.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($temporary_path);

$allowed_types = array(
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
);

if (!isset($allowed_types[$mime_type])) {
    class_share_notice_image_error(
        400,
        'JPG, PNG, GIF, WebP 이미지만 올릴 수 있습니다.'
    );
}

// 파일 이름이나 확장자가 아닌 실제 이미지 정보를 검사합니다.
$image_info = @getimagesize($temporary_path);

if (
    $image_info === false ||
    !isset($image_info['mime']) ||
    $image_info['mime'] !== $mime_type ||
    $image_info[0] < 1 ||
    $image_info[1] < 1 ||
    $image_info[0] > 10000 ||
    $image_info[1] > 10000 ||
    $image_info[0] * $image_info[1] > 40000000
) {
    class_share_notice_image_error(
        400,
        '올바른 이미지가 아니거나 이미지 해상도가 너무 큽니다.'
    );
}

$upload_root = dirname(__DIR__) . '/uploads/notices';

if (!is_dir($upload_root) || !is_writable($upload_root)) {
    error_log('[class-share] 공지 이미지 저장 폴더 접근 실패');
    class_share_notice_image_error(500, '이미지 저장 폴더를 확인해 주세요.');
}

$event_directory = $upload_root . '/' . $event_id;

if (
    !is_dir($event_directory) &&
    !@mkdir($event_directory, 0755) &&
    !is_dir($event_directory)
) {
    error_log('[class-share] 공지 이미지 행사 폴더 생성 실패');
    class_share_notice_image_error(500, '이미지 저장 폴더를 만들 수 없습니다.');
}

try {
    $filename = bin2hex(random_bytes(16))
        . '.' . $allowed_types[$mime_type];
} catch (Throwable $exception) {
    error_log('[class-share] 공지 이미지 파일명 생성 실패');
    class_share_notice_image_error(500, '이미지 파일명을 생성할 수 없습니다.');
}

$destination = $event_directory . '/' . $filename;

if (!move_uploaded_file($temporary_path, $destination)) {
    error_log('[class-share] 공지 이미지 저장 실패');
    class_share_notice_image_error(500, '이미지를 저장하지 못했습니다.');
}

// 공개 행사 페이지에서 이미지를 읽을 수 있도록 설정합니다.
if (!chmod($destination, 0644)) {
    @unlink($destination);
    class_share_notice_image_error(500, '이미지 읽기 권한을 설정하지 못했습니다.');
}

class_share_notice_image_response(
    200,
    array(
        'location' => '/class-share/uploads/notices/'
            . $event_id . '/' . $filename
    )
);
