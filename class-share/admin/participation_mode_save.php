<?php

require_once(__DIR__ . '/include/admin_init.php');
require_once(dirname(__DIR__) . '/include/participation_functions.php');

$admin = class_share_admin_require_login();
class_share_admin_require_post_csrf();
$event_raw = isset($_POST['event_id']) ? $_POST['event_id'] : '';
$mode_raw = isset($_POST['participation_enabled']) ? $_POST['participation_enabled'] : '';
if (
    !is_string($event_raw) ||
    !preg_match('/^[1-9][0-9]{0,18}$/D', $event_raw) ||
    (string)(int)$event_raw !== $event_raw ||
    !is_string($mode_raw) ||
    !in_array($mode_raw, array('0', '1'), true)
) {
    http_response_code(400);
    exit('행사 또는 참여 구분 사용 설정이 올바르지 않습니다.');
}
$event_id = (int)$event_raw;
$enabled = (int)$mode_raw;
$url = '/class-share/admin/participation_options.php?event_id=' . $event_id;

try {
    $connection = pdo_query('SELECT 1 AS ready');
    if (
        $connection === false || !isset($dbh) || !($dbh instanceof PDO) ||
        !$dbh->beginTransaction()
    ) {
        throw new RuntimeException('데이터베이스 연결을 확인할 수 없습니다.');
    }
    $events = pdo_query(
        'SELECT id, school_id, application_mode, participation_enabled
         FROM class_share_event WHERE id = ? LIMIT 1 FOR UPDATE',
        $event_id
    );
    if ($events === false) {
        throw new RuntimeException('행사 조회 실패');
    }
    if (!isset($events[0])) {
        throw new DomainException('행사를 찾을 수 없습니다.');
    }
    $event = $events[0];
    $school_id = (int)$event['school_id'];
    if (!class_share_admin_can_edit_school($school_id, $admin)) {
        $dbh->rollBack();
        http_response_code(403);
        exit('참여 구분 사용을 설정할 권한이 없습니다.');
    }
    if ($enabled === 1) {
        if ((string)$event['application_mode'] !== 'event') {
            throw new DomainException('행사에 직접 신청하는 행사에서만 참여 구분을 사용할 수 있습니다.');
        }
        // 행사 잠금 동안 구분 설정과 신청 건수가 변경되지 않습니다.
        class_share_participation_validate_activation(
            class_share_participation_list_options($event_id, false)
        );
    }
    $previous = (int)$event['participation_enabled'];
    if ($previous !== $enabled) {
        $result = pdo_query(
            'UPDATE class_share_event SET participation_enabled = ?, updated_by = ? WHERE id = ?',
            $enabled,
            (int)$admin['id'],
            $event_id
        );
        if ($result === false || (int)$result !== 1) {
            throw new RuntimeException('참여 구분 사용 설정 저장 실패');
        }
        $before_json = json_encode(
            array('participation_enabled' => $previous),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $after_json = json_encode(
            array('participation_enabled' => $enabled, 'changed_fields' => array('participation_enabled')),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($before_json === false || $after_json === false) {
            throw new RuntimeException('변경 기록 생성 실패');
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $ip = filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
        $audit = pdo_query(
            "INSERT INTO class_share_audit_log
             (school_id, admin_id, actor_type, action, target_type, target_id,
              before_data, after_data, ip_address)
             VALUES (?, ?, 'admin', 'event.update', 'event', ?, ?, ?, ?)",
            $school_id, (int)$admin['id'], $event_id, $before_json, $after_json, $ip
        );
        if ($audit === false) {
            throw new RuntimeException('변경 기록 저장 실패');
        }
    }
    if (!$dbh->commit()) {
        throw new RuntimeException('참여 구분 사용 설정 확정 실패');
    }
    $_SESSION['class_share_participation_flash'] = array(
        'event_id' => $event_id,
        'option_id' => 0,
        'message' => $enabled === 1
            ? '참여 구분 사용을 설정했습니다. 새 신청자는 참여 구분을 선택해야 합니다.'
            : '참여 구분 사용을 해제했습니다. 기존 신청의 구분 정보는 보존됩니다.'
    );
    header('Location: ' . $url, true, 303);
    exit;
} catch (Throwable $exception) {
    if (isset($dbh) && $dbh instanceof PDO && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
    if (!($exception instanceof DomainException)) {
        error_log('[class-share] 참여 구분 사용 설정 실패: ' . $exception->getMessage());
    }
    $_SESSION['class_share_participation_flash'] = array(
        'event_id' => $event_id, 'option_id' => 0,
        'errors' => array($exception instanceof DomainException
            ? $exception->getMessage() : '참여 구분 사용 설정을 저장하지 못했습니다.')
    );
    header('Location: ' . $url, true, 303);
    exit;
}
