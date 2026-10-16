<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = '시뮬레이션 코드 임시 저장';

// ============================================================
// 1. 로그인 및 요청 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = '<h2>로그인이 필요합니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $view_errors = '<h2>잘못된 요청입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id = (string)$_SESSION[$OJ_NAME.'_user_id'];

// ============================================================
// 2. 입력값 확인
// ============================================================

$activity_id_raw = $_POST['activity_id'] ?? '';
$source_code = $_POST['source_code'] ?? null;

if (
    !is_string($activity_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($activity_id_raw) > 10 ||
    (float)$activity_id_raw > 2147483647 ||
    !is_string($source_code) ||
    trim($source_code) === '' ||
    strlen($source_code) > 200000
) {
    $view_errors = '<h2>저장할 코드 또는 활동 번호가 올바르지 않습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id = intval($activity_id_raw);

// ============================================================
// 3. Activity 및 Course 정보 확인
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        ca.activity_id,
        ca.course_id
     FROM course_activity ca
     WHERE ca.activity_id = ?
       AND ca.activity_type = 'simulation'
     LIMIT 1",
    $activity_id
);

if ($activity_rows === false || !isset($activity_rows[0])) {
    $view_errors = '<h2>시뮬레이션 활동을 찾을 수 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($activity_rows[0]['course_id']);

// 학생 본인만 임시 저장할 수 있다.
if (!course_is_active_student($course_id)) {
    $view_errors = '<h2>코드를 저장할 수 있는 수강생이 아닙니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 4. PDO 연결
// ============================================================

$connection_check = pdo_query('SELECT 1 AS connection_ok');

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    $view_errors = '<h2>데이터베이스 연결에 실패했습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 5. 트랜잭션 및 최신 상태 재검증
// ============================================================

$transaction_started = false;

try {
    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();
    $transaction_started = true;

    // 수강 상태를 잠그고 재확인한다.
    $student_check = $dbh->prepare(
        "SELECT status
         FROM course_student
         WHERE course_id = ?
           AND user_id = ?
         FOR UPDATE"
    );

    $student_check->execute(array(
        $course_id,
        $user_id
    ));

    $student = $student_check->fetch(PDO::FETCH_ASSOC);
    $student_check->closeCursor();

    if (!$student || intval($student['status']) !== 1) {
        throw new RuntimeException(
            '수강 상태가 변경되었습니다.'
        );
    }

    // 수업·차시·활동 공개 여부 및 편집 허용 상태를 재검사한다.
    $activity_check = $dbh->prepare(
        "SELECT
            c.status AS course_status,
            cl.status AS lesson_status,
            cl.visible AS lesson_visible,
            ca.status AS activity_status,
            ca.visible AS activity_visible,
            s.allow_edit
         FROM course_activity ca
         INNER JOIN course c
           ON c.course_id = ca.course_id
         INNER JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         INNER JOIN course_simulation s
           ON s.activity_id = ca.activity_id
         WHERE ca.activity_id = ?
           AND ca.course_id = ?
           AND ca.activity_type = 'simulation'
         FOR UPDATE"
    );

    $activity_check->execute(array(
        $activity_id,
        $course_id
    ));

    $settings = $activity_check->fetch(PDO::FETCH_ASSOC);
    $activity_check->closeCursor();

    if (
        !$settings ||
        intval($settings['course_status']) !== 1 ||
        intval($settings['lesson_status']) !== 1 ||
        intval($settings['lesson_visible']) !== 1 ||
        intval($settings['activity_status']) !== 1 ||
        intval($settings['activity_visible']) !== 1 ||
        intval($settings['allow_edit']) !== 1
    ) {
        throw new RuntimeException(
            '현재 코드를 저장할 수 없는 활동입니다.'
        );
    }

    // 학생·활동별 최신 작업 코드 하나만 저장한다.
    $save = $dbh->prepare(
        "INSERT INTO course_simulation_draft
            (activity_id, user_id, source_code)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            source_code = VALUES(source_code),
            updated_at = CURRENT_TIMESTAMP"
    );

    $save->execute(array(
        $activity_id,
        $user_id,
        $source_code
    ));

    $save->closeCursor();

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
        '[course_simulation_draft_save] ' .
        $e->getMessage()
    );

    $view_errors =
        '<h2>코드를 임시 저장하지 못했습니다.</h2>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. 시뮬레이션 화면으로 이동
// ============================================================

header(
    "Location: course_simulation_view.php?activity_id=" .
    $activity_id,
    true,
    303
);

exit(0);
