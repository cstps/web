<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/course_file_upload.inc.php');

$view_title = '학습 자료 파일 업로드';

function course_file_upload_error($message)
{
    global $view_errors, $OJ_TEMPLATE;

    $view_errors =
        '<h2>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
        '</h2>';

    require(
        'template/' . $OJ_TEMPLATE . '/error.php'
    );

    exit;
}

// ============================================================
// 1. POST 및 CSRF 검사
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    course_file_upload_error('잘못된 요청입니다.');
}

require_once('./include/csrf_check.php');

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    http_response_code(403);
    course_file_upload_error('로그인이 필요합니다.');
}

// ============================================================
// 2. Activity 번호 확인
// ============================================================

$activity_raw =
    isset($_POST['activity_id']) &&
    is_string($_POST['activity_id'])
        ? trim($_POST['activity_id'])
        : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $activity_raw) ||
    strlen($activity_raw) > 10 ||
    (float)$activity_raw > 2147483647
) {
    course_file_upload_error('학습 자료 번호가 올바르지 않습니다.');
}

$activity_id = intval($activity_raw);

// ============================================================
// 3. Course / Lesson / Activity 조회
// ============================================================

$rows = pdo_query(
    "SELECT
        a.activity_id,
        a.course_id,
        a.activity_type,
        a.status AS activity_status,
        c.status AS course_status,
        l.status AS lesson_status
     FROM course_activity a
     INNER JOIN course c
       ON c.course_id = a.course_id
     INNER JOIN course_lesson l
       ON l.lesson_id = a.lesson_id
      AND l.course_id = a.course_id
     INNER JOIN course_material m
       ON m.activity_id = a.activity_id
     WHERE a.activity_id = ?
     LIMIT 1",
    $activity_id
);

if (!$rows || !isset($rows[0])) {
    course_file_upload_error('학습 자료를 찾을 수 없습니다.');
}

$activity = $rows[0];
$course_id = intval($activity['course_id']);

if (
    $activity['activity_type'] !== 'material' ||
    intval($activity['activity_status']) !== 1 ||
    intval($activity['course_status']) !== 1 ||
    intval($activity['lesson_status']) !== 1
) {
    course_file_upload_error(
        '현재 파일을 첨부할 수 없는 학습 자료입니다.'
    );
}

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {
    http_response_code(403);
    course_file_upload_error('파일 업로드 권한이 없습니다.');
}

// ============================================================
// 4. 실제 업로드 파일 검증
// ============================================================

if (
    !isset($_FILES['material_file']) ||
    !is_array($_FILES['material_file'])
) {
    course_file_upload_error('첨부할 파일을 선택해 주세요.');
}

try {
    $validated = course_file_validate_basic(
        $_FILES['material_file']
    );

    $content_info = course_file_validate_content(
        $validated
    );

    if (
        !isset($content_info['mime_type']) ||
        !is_string($content_info['mime_type']) ||
        strlen($content_info['mime_type']) > 127
    ) {
        throw new RuntimeException('파일 MIME 검사 결과가 올바르지 않습니다.');
    }

} catch (Throwable $e) {
    course_file_upload_error(
        '파일 검증 실패: ' . $e->getMessage()
    );
}

// ============================================================
// 5. DB 연결 및 저장소 확인
// ============================================================

$connection_check = pdo_query('SELECT 1 AS connection_ok');

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    course_file_upload_error('DB 연결을 확인할 수 없습니다.');
}

$storage_dir = '/home/judge/course_files';

if (
    !is_dir($storage_dir) ||
    is_link($storage_dir) ||
    !is_writable($storage_dir)
) {
    course_file_upload_error('파일 저장소를 사용할 수 없습니다.');
}

$stored_path = null;

// ============================================================
// 6. 트랜잭션으로 파일 저장 및 DB 등록
// ============================================================

try {
    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 DB 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();

    // 같은 Activity에 대한 동시 업로드를 직렬화한다.
    $lock = $dbh->prepare(
        "SELECT activity_id, activity_type, status
         FROM course_activity
         WHERE activity_id = ?
           AND course_id = ?
         FOR UPDATE"
    );

    if (!$lock->execute(array($activity_id, $course_id))) {
        throw new RuntimeException('학습 자료 잠금에 실패했습니다.');
    }

    $locked_activity = $lock->fetch(PDO::FETCH_ASSOC);

    if (
        !$locked_activity ||
        $locked_activity['activity_type'] !== 'material' ||
        intval($locked_activity['status']) !== 1
    ) {
        throw new RuntimeException(
            '학습 자료의 상태가 변경되었습니다.'
        );
    }

    $check = $dbh->prepare(
        "SELECT
            COUNT(*) AS file_count,
            COALESCE(MAX(sort_order), 0) AS max_order
         FROM course_material_file
         WHERE activity_id = ?"
    );

    if (!$check->execute(array($activity_id))) {
        throw new RuntimeException('첨부파일 수를 확인하지 못했습니다.');
    }

    $stats = $check->fetch(PDO::FETCH_ASSOC);

    if (!$stats) {
        throw new RuntimeException('첨부파일 정보를 확인하지 못했습니다.');
    }

    if (intval($stats['file_count']) >= 10) {
        throw new RuntimeException(
            '학습 자료당 첨부파일은 최대 10개입니다.'
        );
    }

    // 경로를 추측할 수 없도록 32바이트 난수를 사용한다.
    $stored_name = bin2hex(random_bytes(32));
    $stored_path = $storage_dir . '/' . $stored_name;

    if (file_exists($stored_path) || is_link($stored_path)) {
        throw new RuntimeException('저장 파일명 충돌이 발생했습니다.');
    }

    if (!move_uploaded_file(
        $validated['tmp_name'],
        $stored_path
    )) {
        throw new RuntimeException('파일 저장에 실패했습니다.');
    }

    if (!chmod($stored_path, 0600)) {
        throw new RuntimeException('파일 접근 권한 설정에 실패했습니다.');
    }

    $file_hash = hash_file('sha256', $stored_path);

    if ($file_hash === false) {
        throw new RuntimeException('파일 해시 계산에 실패했습니다.');
    }

    $insert = $dbh->prepare(
        "INSERT INTO course_material_file
            (
                activity_id,
                original_name,
                stored_name,
                mime_type,
                file_size,
                file_sha256,
                sort_order
            )
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$insert->execute(array(
        $activity_id,
        $validated['original_name'],
        $stored_name,
        $content_info['mime_type'],
        $validated['file_size'],
        $file_hash,
        intval($stats['max_order']) + 1
    ))) {
        throw new RuntimeException(
            '첨부파일 DB 등록에 실패했습니다.'
        );
    }

    if (!$dbh->commit()) {
        throw new RuntimeException(
            '첨부파일 DB 트랜잭션 완료에 실패했습니다.'
        );
    }

} catch (Throwable $e) {

    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    // DB 저장에 실패한 신규 파일은 남기지 않는다.
    if (
        $stored_path !== null &&
        is_file($stored_path)
    ) {
        if (!@unlink($stored_path)) {
            error_log(
                '[course_material_file_upload] cleanup failed'
            );
        }
    }

    error_log(
        '[course_material_file_upload] ' .
        $e->getMessage()
    );

    course_file_upload_error(
        '첨부파일을 저장하지 못했습니다.'
    );
}

// ============================================================
// 7. 기존 학습 자료 편집 화면으로 이동
// ============================================================

header(
    'Location: course_activity_edit.php?activity_id=' .
    $activity_id,
    true,
    303
);

exit;
