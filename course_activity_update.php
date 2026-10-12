<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/course_content_sanitizer.inc.php');
require_once('./include/csrf_check.php');

$view_title = "학습 활동 수정";


// ============================================================
// 1. 로그인 및 POST 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. 입력값 확인
// ============================================================

$course_id = isset($_POST['course_id']) &&
             is_string($_POST['course_id'])
    ? trim($_POST['course_id'])
    : '';

$activity_id = isset($_POST['activity_id']) &&
               is_string($_POST['activity_id'])
    ? trim($_POST['activity_id'])
    : '';

$activity_type = isset($_POST['activity_type']) &&
                 is_string($_POST['activity_type'])
    ? trim($_POST['activity_type'])
    : '';

$title = isset($_POST['title']) &&
         is_string($_POST['title'])
    ? trim($_POST['title'])
    : '';

$description = isset($_POST['description']) &&
               is_string($_POST['description'])
    ? trim($_POST['description'])
    : '';

$visible_raw = isset($_POST['visible']) &&
               is_string($_POST['visible'])
    ? trim($_POST['visible'])
    : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $course_id) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id) ||
    strlen($course_id) > 10 ||
    strlen($activity_id) > 10 ||
    !in_array(
        $activity_type,
        array('material', 'assignment'),
        true
    ) ||
    $title === '' ||
    strlen($title) > 255 ||
    strlen($description) > 65535 ||
    !in_array($visible_raw, array('0', '1'), true)
) {

    $view_errors =
        "<h2>학습 활동 수정 입력값이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($course_id);
$activity_id = intval($activity_id);
$visible = intval($visible_raw);

if (
    $course_id > 2147483647 ||
    $activity_id > 2147483647
) {

    $view_errors =
        "<h2>학습 활동 번호가 허용 범위를 초과했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Activity 조회 및 소속 확인
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        activity_id,
        course_id,
        lesson_id,
        activity_type,
        status
     FROM course_activity
     WHERE activity_id = ?
       AND course_id = ?
     LIMIT 1",
    $activity_id,
    $course_id
);

if (
    !$activity_rows ||
    !isset($activity_rows[0]['activity_id'])
) {

    $view_errors =
        "<h2>해당 수업의 학습 활동을 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity = $activity_rows[0];


// ============================================================
// 4. Course 확인 및 관리 권한 검사
// ============================================================

$course_rows = pdo_query(
    "SELECT course_id, status
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);

if (
    !$course_rows ||
    !isset($course_rows[0]['course_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 학습 활동을 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. Course / Activity 상태 및 유형 검사
// ============================================================

if (
    intval($course_rows[0]['status']) !== 1 ||
    intval($activity['status']) !== 1
) {

    $view_errors =
        "<h2>비활성 상태의 수업 또는 활동은 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if (
    !in_array(
        $activity['activity_type'],
        array('material', 'assignment'),
        true
    )
) {

    $view_errors =
        "<h2>Contest 활동은 이 화면에서 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. 소속 Lesson 확인
// ============================================================

$lesson_id = isset($activity['lesson_id'])
    ? intval($activity['lesson_id'])
    : 0;

$lesson = $lesson_id > 0
    ? course_get_lesson($lesson_id)
    : null;

if (
    !$lesson ||
    intval($lesson['course_id']) !== $course_id ||
    intval($lesson['status']) !== 1
) {

    $view_errors =
        "<h2>유효하지 않은 소속 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. 학습 활동 및 상세 본문 트랜잭션 저장
// ============================================================

// 기존 material을 편집할 때만 본문 입력을 받아 저장한다.
// 유형을 변경하는 경우 기존 본문은 삭제하지 않는다.
$save_material_html = (
    $activity['activity_type'] === 'material' &&
    $activity_type === 'material'
);

$material_html = '';

if ($save_material_html) {
    if (
        !isset($_POST['material_content_html']) ||
        !is_string($_POST['material_content_html']) ||
        strlen($_POST['material_content_html']) > 1024 * 1024
    ) {
        $view_errors =
            "<h2>학습 자료 본문 입력값이 올바르지 않습니다.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }

    $material_html = course_content_sanitize_html(
        $_POST['material_content_html']
    );
}

// 트랜잭션 전에 PDO 연결을 준비한다.
$connection_check = pdo_query('SELECT 1 AS connection_ok');

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    $view_errors =
        "<h2>데이터베이스 연결에 실패했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$transaction_started = false;

try {
    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();
    $transaction_started = true;

    // Course 및 Activity 상태를 트랜잭션 안에서 다시 확인한다.
    $check = $dbh->prepare(
        "SELECT activity_type
         FROM course_activity
         WHERE activity_id = ?
           AND course_id = ?
           AND lesson_id = ?
           AND status = 1
           AND activity_type IN ('material', 'assignment')
         FOR UPDATE"
    );

    $check->execute(array(
        $activity_id,
        $course_id,
        $lesson_id
    ));

    $locked_activity = $check->fetch(PDO::FETCH_ASSOC);
    $check->closeCursor();

    if (
        !$locked_activity ||
        $locked_activity['activity_type'] !== $activity['activity_type']
    ) {
        throw new RuntimeException(
            '학습 활동 상태가 변경되었습니다.'
        );
    }

    $update = $dbh->prepare(
        "UPDATE course_activity
         SET activity_type = ?,
             title = ?,
             description = ?,
             visible = ?
         WHERE activity_id = ?
           AND course_id = ?
           AND lesson_id = ?
           AND status = 1"
    );

    $update->execute(array(
        $activity_type,
        $title,
        $description,
        $visible,
        $activity_id,
        $course_id,
        $lesson_id
    ));

    $update->closeCursor();

    if ($save_material_html) {
        $save = $dbh->prepare(
            "INSERT INTO course_material
                (activity_id, content_html)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                content_html = VALUES(content_html)"
        );

        $save->execute(array(
            $activity_id,
            $material_html
        ));

        $save->closeCursor();
    }

    $dbh->commit();
    $transaction_started = false;

} catch (Throwable $e) {
    if (
        $transaction_started &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        'course_activity_update: ' . $e->getMessage()
    );

    $view_errors =
        "<h2>학습 활동 저장에 실패했습니다. 다시 시도해 주세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. Course 관리 화면으로 이동
// ============================================================

header(
    "Location: course_view.php?course_id=" .
    $course_id
);

exit(0);
