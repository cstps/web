<?php
require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');
require_once(__DIR__ . '/include/course_contest_restore.inc.php');

$view_title = 'Contest 활동 복원·차시 배정';
header('Cache-Control: no-store, private');

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_contest_activity_error('로그인이 필요합니다.', 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    course_contest_activity_error('POST 요청으로 복원해 주세요.', 405);
}
try {
    $course_id = course_contest_activity_id($_POST['course_id'] ?? null);
    $contest_id = course_contest_activity_id($_POST['contest_id'] ?? null);
    $target_raw = $_POST['target_lesson_id'] ?? '';
    $target_lesson_id = $target_raw === '' ? 0 : course_contest_activity_id($target_raw);
    $restore_raw = $_POST['restore_lesson'] ?? '0';
    $repair_raw = $_POST['repair_active'] ?? '0';
    if (!in_array($restore_raw, array('0', '1'), true) || !in_array($repair_raw, array('0', '1'), true)) {
        throw new InvalidArgumentException('복원 요청을 확인해 주세요.');
    }
    course_contest_restore_store(course_contest_activity_db(), $course_id, $contest_id,
        $target_lesson_id, $restore_raw === '1', $repair_raw === '1');
} catch (InvalidArgumentException $error) {
    course_contest_activity_error($error->getMessage(), 400);
} catch (UnexpectedValueException $error) {
    course_contest_activity_error($error->getMessage(), 409);
} catch (Throwable $error) {
    error_log('[course_contest_restore] ' . $error->getMessage());
    course_contest_activity_error('활동을 복원하지 못했습니다. 서버 로그를 확인해 주세요.', 500);
}
header('Location: course_view.php?course_id=' . $course_id, true, 303);
exit;
