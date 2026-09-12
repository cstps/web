<?php
require_once("../include/db_info.inc.php");

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

function upload_response($status, $data)
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function upload_error($status, $message)
{
    upload_response($status, array("error" => $message));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    upload_error(405, "POST 요청만 허용됩니다.");
}

$is_editor =
    isset($_SESSION[$OJ_NAME . "_administrator"]) ||
    isset($_SESSION[$OJ_NAME . "_problem_editor"]) ||
    isset($_SESSION[$OJ_NAME . "_contest_creator"]);

if (!$is_editor) {
    upload_error(403, "이미지 업로드 권한이 없습니다.");
}

$session_key_name = $OJ_NAME . "_postkey";
$session_key = isset($_SESSION[$session_key_name])
    ? (string) $_SESSION[$session_key_name]
    : "";
$post_key = isset($_POST["postkey"])
    ? (string) $_POST["postkey"]
    : "";

if (
    $session_key === "" ||
    $post_key === "" ||
    !hash_equals($session_key, $post_key)
) {
    upload_error(403, "보안 키가 올바르지 않습니다. 페이지를 새로 고친 뒤 다시 시도하세요.");
}

if (!isset($_FILES["file"]) || !is_array($_FILES["file"])) {
    upload_error(400, "업로드할 이미지가 없습니다.");
}

$file = $_FILES["file"];

if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
    $upload_errors = array(
        UPLOAD_ERR_INI_SIZE => "서버의 업로드 용량 제한을 초과했습니다.",
        UPLOAD_ERR_FORM_SIZE => "폼의 업로드 용량 제한을 초과했습니다.",
        UPLOAD_ERR_PARTIAL => "이미지가 일부만 업로드되었습니다.",
        UPLOAD_ERR_NO_FILE => "업로드할 이미지가 없습니다.",
        UPLOAD_ERR_NO_TMP_DIR => "서버의 임시 디렉터리를 찾을 수 없습니다.",
        UPLOAD_ERR_CANT_WRITE => "서버에 이미지를 기록하지 못했습니다.",
        UPLOAD_ERR_EXTENSION => "서버 확장 기능이 업로드를 중지했습니다."
    );

    $error_code = isset($file["error"])
        ? (int) $file["error"]
        : -1;
    $message = isset($upload_errors[$error_code])
        ? $upload_errors[$error_code]
        : "이미지를 업로드하지 못했습니다.";

    upload_error(400, $message);
}

$max_file_size = 10 * 1024 * 1024;
$file_size = isset($file["size"])
    ? (int) $file["size"]
    : 0;

if ($file_size <= 0 || $file_size > $max_file_size) {
    upload_error(400, "이미지는 10MB 이하만 업로드할 수 있습니다.");
}

$temporary_path = isset($file["tmp_name"])
    ? $file["tmp_name"]
    : "";

if ($temporary_path === "" || !is_uploaded_file($temporary_path)) {
    upload_error(400, "정상적인 업로드 파일이 아닙니다.");
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($temporary_path);
$allowed_types = array(
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/gif" => "gif",
    "image/webp" => "webp"
);

if (!isset($allowed_types[$mime_type])) {
    upload_error(400, "JPG, PNG, GIF, WebP 이미지만 업로드할 수 있습니다.");
}

$image_info = @getimagesize($temporary_path);

if ($image_info === false) {
    upload_error(400, "올바른 이미지 파일이 아닙니다.");
}

$image_width = (int) $image_info[0];
$image_height = (int) $image_info[1];
$max_dimension = 12000;
$max_pixels = 40000000;

if (
    $image_width <= 0 ||
    $image_height <= 0 ||
    $image_width > $max_dimension ||
    $image_height > $max_dimension ||
    ($image_width * $image_height) > $max_pixels
) {
    upload_error(400, "이미지 해상도가 허용 범위를 초과했습니다.");
}

$relative_directory = "upload/image/" . date("Ym");
$web_root = dirname(__DIR__);
$target_directory = $web_root . "/" . $relative_directory;

if (
    !is_dir($target_directory) &&
    !mkdir($target_directory, 0755, true)
) {
    upload_error(500, "이미지 저장 디렉터리를 만들지 못했습니다.");
}

if (!is_writable($target_directory)) {
    upload_error(500, "이미지 저장 디렉터리에 쓰기 권한이 없습니다.");
}

try {
    $random_name = bin2hex(random_bytes(16));
} catch (Exception $exception) {
    upload_error(500, "안전한 파일 이름을 만들지 못했습니다.");
}

$file_name = $random_name . "." . $allowed_types[$mime_type];
$target_path = $target_directory . "/" . $file_name;

if (!move_uploaded_file($temporary_path, $target_path)) {
    upload_error(500, "업로드한 이미지를 저장하지 못했습니다.");
}

@chmod($target_path, 0644);

$script_name = isset($_SERVER["SCRIPT_NAME"])
    ? str_replace("\\", "/", $_SERVER["SCRIPT_NAME"])
    : "/admin/tinymce_upload_image.php";
$application_root = rtrim(dirname(dirname($script_name)), "/");

if ($application_root === ".") {
    $application_root = "";
}

$location =
    $application_root .
    "/" .
    $relative_directory .
    "/" .
    $file_name;

upload_response(200, array("location" => $location));
