<?php

$grade_status_buffer_level = ob_get_level();
ob_start();

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

function simulation_teacher_grade_json($data, $status = 200)
{
    global $grade_status_buffer_level;
    while (ob_get_level() > $grade_status_buffer_level) {
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

function simulation_teacher_grade_fail($message, $status)
{
    simulation_teacher_grade_json(
        array('ok' => false, 'message' => $message), $status
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    simulation_teacher_grade_fail('조회 요청만 허용합니다.', 405);
}
if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    simulation_teacher_grade_fail('로그인이 필요합니다.', 403);
}

$activity = $_GET['activity_id'] ?? null;
$raw_ids = $_GET['submission_ids'] ?? null;

if (
    !is_string($activity) ||
    !preg_match('/^[1-9][0-9]{0,9}$/D', $activity) ||
    (float)$activity > 2147483647 ||
    !is_string($raw_ids) ||
    strlen($raw_ids) > 1000
) {
    simulation_teacher_grade_fail('조회 번호가 올바르지 않습니다.', 400);
}

$ids = explode(',', $raw_ids);
if (count($ids) > 50) {
    simulation_teacher_grade_fail('한 번에 최대 50건만 조회할 수 있습니다.', 400);
}
foreach ($ids as $id) {
    if (
        !preg_match('/^[1-9][0-9]{0,18}$/D', $id) ||
        (strlen($id) === 19 && strcmp($id, '9223372036854775807') > 0)
    ) {
        simulation_teacher_grade_fail('제출 번호가 올바르지 않습니다.', 400);
    }
}
$ids = array_values(array_unique($ids));

try {
    if (pdo_query('SELECT 1') === false ||
        !isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('DB 연결 실패');
    }

    $stmt = $dbh->prepare(
        "SELECT a.course_id
         FROM course_activity a
         INNER JOIN course_lesson l
           ON l.lesson_id = a.lesson_id
          AND l.course_id = a.course_id
         INNER JOIN course c ON c.course_id = a.course_id
         INNER JOIN course_simulation s ON s.activity_id = a.activity_id
         WHERE a.activity_id = ?
           AND a.activity_type = 'simulation'
           AND a.status = 1 AND l.status = 1 AND c.status = 1
           AND s.simulation_mode = 'drawing'"
    );
    $stmt->execute(array($activity));
    $context = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$context) {
        simulation_teacher_grade_fail('조회할 수 없는 활동입니다.', 404);
    }

    $course_id = intval($context['course_id']);
    if (
        !course_can_access($course_id) ||
        !course_can_manage_contests($course_id)
    ) {
        simulation_teacher_grade_fail('결과 조회 권한이 없습니다.', 403);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $sql = <<<'SQL'
SELECT
        s.submission_id,
        g.grade_id,
        g.grading_no,
        g.grading_status,
        g.score AS grade_score,
        g.max_score AS grade_max_score,
        JSON_UNQUOTE(JSON_EXTRACT(
            g.feedback_json, '$.request.reference_no'
        )) AS grade_reference_no,
        JSON_UNQUOTE(JSON_EXTRACT(
            g.feedback_json, '$.result.metrics.foreground_match'
        )) AS grade_foreground_match,
        JSON_UNQUOTE(JSON_EXTRACT(
            g.feedback_json, '$.result.metrics.foreground_overlap'
        )) AS grade_foreground_overlap,
        JSON_UNQUOTE(JSON_EXTRACT(
            g.feedback_json, '$.result.metrics.tolerance.symmetric_coverage'
        )) AS grade_tolerance_coverage,
        JSON_UNQUOTE(JSON_EXTRACT(
            g.feedback_json, '$.result.metrics.tolerance.position_tolerance_px'
        )) AS grade_tolerance_radius,
        JSON_EXTRACT(
            g.feedback_json, '$.result.metrics.region_comparison'
        ) AS grade_region_comparison_json
     FROM course_simulation_submission s
     INNER JOIN course_student cs
       ON cs.course_id = ?
      AND cs.user_id = s.user_id
      AND cs.status = 1
     LEFT JOIN course_simulation_grade g
       ON g.grade_id = (
           SELECT g2.grade_id
           FROM course_simulation_grade g2
           WHERE g2.submission_id = s.submission_id
           ORDER BY g2.grading_no DESC
           LIMIT 1
       )
     WHERE s.activity_id = ?
       AND s.submission_id IN (__IDS__)
     ORDER BY
         s.user_id,
         s.submission_no DESC
SQL;
    $sql = str_replace(
        '__IDS__',
        implode(',', array_fill(0, count($ids), '?')),
        $sql
    );
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array_merge(array($course_id, $activity), $ids));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $simulation_escape = function ($value) {
        return htmlspecialchars(
            (string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
        );
    };

    $results = array();
    foreach ($rows as $submission) {
        ob_start();
        require(
            __DIR__ . '/template/syzoj/course_simulation_grade_result.php'
        );
        $html = ob_get_clean();

        $results[] = array(
            'submission_id' => (string)$submission['submission_id'],
            'grading_status' => (string)($submission['grading_status'] ?? ''),
            'html' => $html,
            'score' => $submission['grade_score'] === null
                ? null : (string)$submission['grade_score'],
            'max_score' => $submission['grade_max_score'] === null
                ? null : (string)$submission['grade_max_score'],
        );
    }

    simulation_teacher_grade_json(
        array('ok' => true, 'submissions' => $results)
    );
} catch (Throwable $e) {
    error_log('[simulation_teacher_grade_status] ' . $e->getMessage());
    simulation_teacher_grade_fail('채점 결과를 조회하지 못했습니다.', 500);
}
