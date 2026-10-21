<?php

$status_buffer_level = ob_get_level();
ob_start();

function simulation_grade_status_response(array $data, $status = 200)
{
    global $status_buffer_level;

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        $status = 500;
        $json = '{"ok":false,"message":"결과를 제공하지 못했습니다."}';
    }

    while (ob_get_level() > $status_buffer_level) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

try {
    require_once(__DIR__ . '/include/db_info.inc.php');
    require_once(__DIR__ . '/include/const.inc.php');
    require_once(__DIR__ . '/include/memcache.php');
    require_once(__DIR__ . '/include/setlang.php');
    require_once(__DIR__ . '/include/course_functions.inc.php');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        simulation_grade_status_response(
            array('ok' => false, 'message' => '지원하지 않는 요청 방식입니다.'),
            405
        );
    }

    if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
        simulation_grade_status_response(
            array('ok' => false, 'message' => '로그인이 필요합니다.'),
            401
        );
    }

    $raw_id = (
        isset($_GET['activity_id']) &&
        is_string($_GET['activity_id'])
    ) ? $_GET['activity_id'] : '';

    if (
        !preg_match('/^[1-9][0-9]*$/D', $raw_id) ||
        strlen($raw_id) > 10 ||
        (float)$raw_id > 2147483647
    ) {
        simulation_grade_status_response(
            array('ok' => false, 'message' => '잘못된 활동 번호입니다.'),
            400
        );
    }

    $activity_id = (int)$raw_id;
    $user_id = (string)$_SESSION[$OJ_NAME . '_user_id'];

    $rows = pdo_query(
        "SELECT
            ca.course_id,
            ca.status AS activity_status,
            ca.visible AS activity_visible,
            cl.status AS lesson_status,
            cl.visible AS lesson_visible,
            c.status AS course_status
         FROM course_activity ca
         INNER JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         INNER JOIN course c
           ON c.course_id = ca.course_id
         INNER JOIN course_simulation s
           ON s.activity_id = ca.activity_id
         WHERE ca.activity_id = ?
           AND ca.activity_type = 'simulation'
         LIMIT 1",
        $activity_id
    );

    if ($rows === false) {
        throw new RuntimeException('활동 조회 실패');
    }

    if (!isset($rows[0])) {
        simulation_grade_status_response(
            array('ok' => false, 'message' => '활동을 찾을 수 없습니다.'),
            404
        );
    }

    $activity = $rows[0];
    $course_id = (int)$activity['course_id'];

    $is_manager = (
        course_can_access($course_id) &&
        course_can_manage_contests($course_id)
    );

    if (
        !course_is_active_student($course_id) ||
        (int)$activity['activity_status'] !== 1 ||
        (int)$activity['lesson_status'] !== 1 ||
        (
            !$is_manager &&
            (
                (int)$activity['course_status'] !== 1 ||
                (int)$activity['lesson_visible'] !== 1 ||
                (int)$activity['activity_visible'] !== 1
            )
        )
    ) {
        simulation_grade_status_response(
            array('ok' => false, 'message' => '제출 결과 열람 권한이 없습니다.'),
            403
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $submissions = pdo_query(
        "SELECT
            s.submission_id,
            g.grading_status,
            g.score,
            g.max_score,
            JSON_EXTRACT(
                g.feedback_json, '$.result.metrics.region_comparison'
            ) AS grade_region_comparison_json
         FROM course_simulation_submission s
         LEFT JOIN course_simulation_grade g
           ON g.grade_id = (
               SELECT g2.grade_id
               FROM course_simulation_grade g2
               WHERE g2.submission_id = s.submission_id
               ORDER BY g2.grading_no DESC
               LIMIT 1
           )
         WHERE s.activity_id = ?
           AND s.user_id = ?
         ORDER BY s.submission_no DESC
         LIMIT 10",
        $activity_id,
        $user_id
    );

    if ($submissions === false) {
        throw new RuntimeException('제출 결과 조회 실패');
    }

    $simulation_escape = static function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    };

    $results = array();

    foreach ($submissions as $submission) {
        ob_start();
        require(
            __DIR__ .
            '/template/syzoj/course_simulation_student_grade_details.php'
        );
        $region_html = ob_get_clean();

        $results[] = array(
            // 큰 정수 식별자는 문자열로 전달한다.
            'submission_id' => (string)$submission['submission_id'],
            'grading_status' => (string)($submission['grading_status'] ?? ''),
            'score' => $submission['score'] === null
                ? null : (string)$submission['score'],
            'max_score' => $submission['max_score'] === null
                ? null : (string)$submission['max_score'],
            'region_html' => $region_html,
        );
    }

    simulation_grade_status_response(array(
        'ok' => true,
        'submissions' => $results,
    ));

} catch (Throwable $e) {
    error_log('[course_simulation_grade_status] ' . $e->getMessage());

    simulation_grade_status_response(
        array('ok' => false, 'message' => '제출 결과를 불러오지 못했습니다.'),
        500
    );
}
