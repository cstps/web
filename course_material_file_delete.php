<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = '학습 자료 첨부파일 삭제';

function course_file_delete_error($message)
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
// 1. POST / CSRF / 로그인 검사
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    course_file_delete_error('잘못된 요청입니다.');
}

require_once('./include/csrf_check.php');

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    http_response_code(403);
    course_file_delete_error('로그인이 필요합니다.');
}

// ============================================================
// 2. 입력값 검증
// ============================================================

$activity_raw =
    isset($_POST['activity_id']) &&
    is_string($_POST['activity_id'])
        ? trim($_POST['activity_id'])
        : '';

$file_raw =
    isset($_POST['file_id']) &&
    is_string($_POST['file_id'])
        ? trim($_POST['file_id'])
        : '';

foreach (array($activity_raw, $file_raw) as $id) {
    if (
        !preg_match('/^[1-9][0-9]*$/D', $id) ||
        strlen($id) > 10 ||
        (float)$id > 2147483647
    ) {
        course_file_delete_error(
            '첨부파일 번호가 올바르지 않습니다.'
        );
    }
}

$activity_id = intval($activity_raw);
$file_id = intval($file_raw);

// ============================================================
// 3. Course / Lesson / Activity 확인
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
     WHERE a.activity_id = ?
     LIMIT 1",
    $activity_id
);

if (!$rows || !isset($rows[0])) {
    course_file_delete_error('학습 자료를 찾을 수 없습니다.');
}

$activity = $rows[0];
$course_id = intval($activity['course_id']);

if (
    $activity['activity_type'] !== 'material' ||
    intval($activity['activity_status']) !== 1 ||
    intval($activity['course_status']) !== 1 ||
    intval($activity['lesson_status']) !== 1
) {
    course_file_delete_error(
        '첨부파일을 삭제할 수 없는 학습 자료입니다.'
    );
}

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {
    http_response_code(403);
    course_file_delete_error(
        '첨부파일 삭제 권한이 없습니다.'
    );
}

// ============================================================
// 4. DB 및 저장소 확인
// ============================================================

$connection_check = pdo_query(
    'SELECT 1 AS connection_ok'
);

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    course_file_delete_error(
        'DB 연결을 확인할 수 없습니다.'
    );
}

$storage_dir = '/home/judge/course_files';

if (
    !is_dir($storage_dir) ||
    is_link($storage_dir) ||
    !is_writable($storage_dir)
) {
    course_file_delete_error(
        '첨부파일 저장소를 사용할 수 없습니다.'
    );
}

$original_path = null;
$pending_path = null;
$committed = false;

// ============================================================
// 5. 파일 및 DB 삭제
// ============================================================

try {
    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();

    // 동일 학습 자료의 업로드·삭제를 직렬화한다.
    $lock = $dbh->prepare(
        "SELECT activity_id, activity_type, status
         FROM course_activity
         WHERE activity_id = ?
           AND course_id = ?
         FOR UPDATE"
    );

    $lock->execute(array($activity_id, $course_id));

    $locked_activity = $lock->fetch(PDO::FETCH_ASSOC);

    if (
        !$locked_activity ||
        $locked_activity['activity_type'] !== 'material' ||
        intval($locked_activity['status']) !== 1
    ) {
        throw new RuntimeException(
            '학습 자료 상태가 변경되었습니다.'
        );
    }

    $select = $dbh->prepare(
        "SELECT file_id, stored_name
         FROM course_material_file
         WHERE file_id = ?
           AND activity_id = ?
         FOR UPDATE"
    );

    $select->execute(array($file_id, $activity_id));

    $file = $select->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        throw new RuntimeException(
            '삭제할 첨부파일을 찾지 못했습니다.'
        );
    }

    $stored_name = (string)$file['stored_name'];

    if (!preg_match('/^[a-f0-9]{64}$/D', $stored_name)) {
        throw new RuntimeException(
            '첨부파일 저장 식별자가 올바르지 않습니다.'
        );
    }

    $original_path =
        $storage_dir . '/' . $stored_name;

    if (
        is_link($original_path) ||
        !is_file($original_path)
    ) {
        throw new RuntimeException(
            '첨부파일 원본이 존재하지 않습니다.'
        );
    }

    // DB 삭제 전 파일을 임시 이름으로 격리한다.
    $pending_path =
        $storage_dir .
        '/.deleting-' .
        $stored_name .
        '-' .
        bin2hex(random_bytes(8));

    if (
        file_exists($pending_path) ||
        is_link($pending_path) ||
        !rename($original_path, $pending_path)
    ) {
        throw new RuntimeException(
            '첨부파일 임시 이동에 실패했습니다.'
        );
    }

    $delete = $dbh->prepare(
        "DELETE FROM course_material_file
         WHERE file_id = ?
           AND activity_id = ?"
    );

    if (
        !$delete->execute(array($file_id, $activity_id)) ||
        $delete->rowCount() !== 1
    ) {
        throw new RuntimeException(
            '첨부파일 DB 삭제에 실패했습니다.'
        );
    }

    if (!$dbh->commit()) {
        throw new RuntimeException(
            'DB 트랜잭션 완료에 실패했습니다.'
        );
    }

    $committed = true;

} catch (Throwable $e) {

    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }

    // DB 삭제가 확정되지 않았으면 원본 파일을 복원한다.
    if (
        !$committed &&
        $pending_path !== null &&
        is_file($pending_path)
    ) {
        if (
            $original_path === null ||
            file_exists($original_path) ||
            !rename($pending_path, $original_path)
        ) {
            error_log(
                '[course_material_file_delete] ' .
                'CRITICAL: file restoration failed'
            );
        }
    }

    error_log(
        '[course_material_file_delete] ' .
        $e->getMessage()
    );

    course_file_delete_error(
        '첨부파일을 삭제하지 못했습니다.'
    );
}

// ============================================================
// 6. DB 삭제 확정 후 실제 파일 제거
// ============================================================

if (
    $pending_path !== null &&
    is_file($pending_path) &&
    !@unlink($pending_path)
) {
    error_log(
        '[course_material_file_delete] ' .
        'Pending file cleanup failed: ' .
        basename($pending_path)
    );
}

// ============================================================
// 7. 편집 화면으로 이동
// ============================================================

header(
    'Location: course_activity_edit.php?activity_id=' .
    $activity_id,
    true,
    303
);

exit;
