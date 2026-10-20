<?php

$simulation_source_buffer_level = ob_get_level();
ob_start();

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

function simulation_monitor_source_json($data, $status = 200)
{
    global $simulation_source_buffer_level;

    while (ob_get_level() > $simulation_source_buffer_level) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function simulation_monitor_source_fail($message, $status)
{
    simulation_monitor_source_json(
        array('ok' => false, 'message' => $message),
        $status
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    simulation_monitor_source_fail('조회 요청만 허용합니다.', 405);
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    simulation_monitor_source_fail('로그인이 필요합니다.', 403);
}

$course_raw = $_GET['course_id'] ?? null;
$activity_raw = $_GET['activity_id'] ?? null;
$student_id = $_GET['user_id'] ?? null;

foreach (array($course_raw, $activity_raw) as $number) {
    if (
        !is_string($number) ||
        !preg_match('/^[1-9][0-9]{0,9}$/D', $number) ||
        (float)$number > 2147483647
    ) {
        simulation_monitor_source_fail('조회 번호가 올바르지 않습니다.', 400);
    }
}

if (
    !is_string($student_id) ||
    trim($student_id) === '' ||
    strlen($student_id) > 192 ||
    preg_match('/[\x00-\x1F\x7F]/', $student_id)
) {
    simulation_monitor_source_fail('학생 아이디가 올바르지 않습니다.', 400);
}

$course_id = intval($course_raw);
$activity_id = intval($activity_raw);

try {
    if (pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('DB 연결 실패');
    }

    if (
        !course_can_access($course_id) ||
        !course_can_manage_contests($course_id)
    ) {
        simulation_monitor_source_fail('학생 코드 조회 권한이 없습니다.', 403);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $stmt = $dbh->prepare(
        "SELECT
            d.source_code,
            d.updated_at,
            a.title AS activity_title,
            l.lesson_no
         FROM course_simulation_draft d
         INNER JOIN course_activity a
           ON a.activity_id = d.activity_id
          AND a.course_id = ?
          AND a.activity_type = 'simulation'
          AND a.status = 1
          AND a.visible = 1
         INNER JOIN course_lesson l
           ON l.lesson_id = a.lesson_id
          AND l.course_id = a.course_id
          AND l.status = 1
          AND l.visible = 1
         INNER JOIN course c
           ON c.course_id = a.course_id
          AND c.status = 1
         INNER JOIN course_simulation s
           ON s.activity_id = a.activity_id
         INNER JOIN course_student cs
           ON cs.course_id = a.course_id
          AND cs.user_id = d.user_id
          AND cs.status = 1
         WHERE d.activity_id = ?
           AND d.user_id = ?
         LIMIT 1"
    );
    $stmt->execute(array($course_id, $activity_id, $student_id));
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$draft) {
        simulation_monitor_source_fail(
            '조회 가능한 학생 작업 코드를 찾지 못했습니다.',
            404
        );
    }

    simulation_monitor_source_json(array(
        'ok' => true,
        'source_kind' => 'simulation',
        'activity_id' => (string)$activity_id,
        'activity_title' => (string)$draft['activity_title'],
        'lesson_no' => intval($draft['lesson_no']),
        'language_name' => 'JavaScript · p5.js',
        'source' => (string)$draft['source_code'],
        'source_sha256' => hash('sha256', (string)$draft['source_code']),
        'updated_at' => (string)$draft['updated_at'],
    ));
} catch (Throwable $e) {
    error_log('[simulation_monitor_source] ' . $e->getMessage());
    simulation_monitor_source_fail('학생 코드를 조회하지 못했습니다.', 500);
}
