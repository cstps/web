<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./scripts/course_simulation_grade_queue.php');

function simulation_regrade_error($message, $status)
{
    global $OJ_TEMPLATE, $view_errors, $view_title;
    http_response_code($status);
    $view_title = '시뮬레이션 재채점';
    $view_errors = '<h2>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h2>';
    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

function simulation_regrade_positive_id($value, $maximum)
{
    if (
        !is_string($value) ||
        !preg_match('/^[1-9][0-9]*$/D', $value) ||
        strlen($value) > strlen($maximum) ||
        (
            strlen($value) === strlen($maximum) &&
            strcmp($value, $maximum) > 0
        )
    ) {
        simulation_regrade_error('요청 번호가 올바르지 않습니다.', 400);
    }
    return $value;
}

function simulation_regrade_context(PDO $db, $activity_id, $locked)
{
    $sql =
        "SELECT a.course_id,
                a.status AS activity_status,
                l.status AS lesson_status,
                c.status AS course_status,
                s.simulation_mode
         FROM course_activity a
         INNER JOIN course_lesson l
           ON l.lesson_id = a.lesson_id
          AND l.course_id = a.course_id
         INNER JOIN course c ON c.course_id = a.course_id
         INNER JOIN course_simulation s ON s.activity_id = a.activity_id
         WHERE a.activity_id = ?
           AND a.activity_type = 'simulation'";

    if ($locked) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute(array($activity_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $row;
}

function simulation_regrade_context_is_active($row)
{
    return $row &&
        intval($row['course_status']) === 1 &&
        intval($row['lesson_status']) === 1 &&
        intval($row['activity_status']) === 1 &&
        $row['simulation_mode'] === 'drawing';
}

header('Cache-Control: private, no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    simulation_regrade_error('재채점 요청은 제출 버튼으로 실행해 주세요.', 405);
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    simulation_regrade_error('로그인이 필요합니다.', 403);
}

$csrf = $_POST['csrf'] ?? null;
$keys = $_SESSION[$OJ_NAME . '_csrf_keys'] ?? null;

if (
    !is_string($csrf) ||
    !is_array($keys) ||
    !in_array($csrf, $keys, true)
) {
    simulation_regrade_error('요청 확인에 실패했습니다. 화면을 새로고침해 주세요.', 403);
}

require_once('./include/csrf_check.php');

$activity_id = simulation_regrade_positive_id(
    $_POST['activity_id'] ?? null, '2147483647'
);
$submission_id = simulation_regrade_positive_id(
    $_POST['submission_id'] ?? null, '9223372036854775807'
);
$reference_no = simulation_regrade_positive_id(
    $_POST['reference_no'] ?? null, '2147483647'
);

try {
    if (!isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('DB 연결을 확인하지 못했습니다.');
    }

    $context = simulation_regrade_context($dbh, $activity_id, false);
    if (!simulation_regrade_context_is_active($context)) {
        simulation_regrade_error('재채점을 요청할 수 없는 활동입니다.', 409);
    }

    $course_id = intval($context['course_id']);
    if (
        !course_can_access($course_id) ||
        !course_can_manage_contests($course_id)
    ) {
        simulation_regrade_error('이 수업의 재채점 요청 권한이 없습니다.', 403);
    }

    $dbh->beginTransaction();

    // 현재 활성 수강생의 해당 활동 제출만 허용한다.
    $stmt = $dbh->prepare(
        "SELECT s.submission_id
         FROM course_simulation_submission s
         INNER JOIN course_student cs
           ON cs.course_id = ?
          AND cs.user_id = s.user_id
          AND cs.status = 1
         WHERE s.submission_id = ?
           AND s.activity_id = ?
         FOR UPDATE"
    );
    $stmt->execute(array($course_id, $submission_id, $activity_id));
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$submission) {
        throw new RuntimeException('해당 활동의 활성 수강생 제출을 찾을 수 없습니다.');
    }

    $current = simulation_regrade_context($dbh, $activity_id, true);
    if (
        !simulation_regrade_context_is_active($current) ||
        intval($current['course_id']) !== $course_id
    ) {
        throw new RuntimeException('활동 상태가 변경됐습니다.');
    }

    simulation_enqueue_teacher_grade(
        $dbh, $submission_id, $activity_id, $reference_no
    );

    $dbh->commit();
} catch (Throwable $e) {
    if (isset($dbh) && $dbh instanceof PDO && $dbh->inTransaction()) {
        $dbh->rollBack();
    }

    error_log('[simulation_regrade] ' . $e->getMessage());

    $message = $e instanceof PDOException
        ? '재채점 등록에 실패했습니다. 잠시 후 다시 시도해 주세요.'
        : $e->getMessage();

    simulation_regrade_error($message, 409);
}

// 등록 완료 후 알림 실패는 이미 저장된 요청을 취소하지 않는다.
$socket = @stream_socket_client(
    'unix:///run/1024kr-simulation-grade/notify.sock',
    $notify_errno,
    $notify_error,
    0.05
);
if (is_resource($socket)) {
    stream_set_blocking($socket, false);
    @fwrite($socket, "grade\n");
    fclose($socket);
} else {
    error_log('[simulation_regrade] 처리기 알림 연결 실패');
}

header(
    'Location: course_simulation_submissions.php?activity_id=' .
    rawurlencode($activity_id) . '&regrade=queued',
    true,
    303
);
exit;
