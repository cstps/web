<?php

require_once(__DIR__ . '/include/admin_init.php');
$admin = class_share_admin_require_login();
class_share_admin_require_post_csrf();

$event_raw = isset($_POST['event_id']) ? $_POST['event_id'] : '';
$option_raw = isset($_POST['option_id']) ? $_POST['option_id'] : '';
if (
    !is_string($event_raw) ||
    !preg_match('/^[1-9][0-9]{0,18}$/D', $event_raw) ||
    (string)(int)$event_raw !== $event_raw ||
    !is_string($option_raw) ||
    !preg_match('/^(0|[1-9][0-9]{0,18})$/D', $option_raw) ||
    (string)(int)$option_raw !== $option_raw
) {
    http_response_code(400);
    exit('행사 또는 참여 구분 번호가 올바르지 않습니다.');
}
$event_id = (int)$event_raw;
$option_id = (int)$option_raw;
$url = '/class-share/admin/participation_options.php?event_id=' . $event_id;
$values = array();
foreach (array('name', 'capacity', 'sort_order', 'is_active') as $key) {
    $values[$key] = isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key]) : '';
}

try {
    // 배열 입력과 누락된 값도 서버에서 검증합니다.
    foreach (array('name', 'capacity', 'sort_order', 'is_active') as $key) {
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
            throw new DomainException('입력 형식이 올바르지 않습니다.');
        }
    }
    if ($values['name'] === '' || mb_strlen($values['name'], 'UTF-8') > 60) {
        throw new DomainException('구분 이름은 1~60자로 입력해 주세요.');
    }
    if (preg_match('/[\x00-\x1F\x7F]/u', $values['name'])) {
        throw new DomainException('구분 이름에 제어문자를 사용할 수 없습니다.');
    }
    if (
        $values['capacity'] !== '' &&
        (!preg_match('/^[1-9][0-9]{0,6}$/D', $values['capacity']) ||
         (int)$values['capacity'] > 1000000)
    ) {
        throw new DomainException('정원은 1~1,000,000명으로 입력하거나 비워 두세요.');
    }
    if (
        !preg_match('/^(0|[1-9][0-9]{0,6})$/D', $values['sort_order']) ||
        (int)$values['sort_order'] > 1000000
    ) {
        throw new DomainException('표시 순서는 0~1,000,000으로 입력해 주세요.');
    }
    if (!in_array($values['is_active'], array('0', '1'), true)) {
        throw new DomainException('모집 여부가 올바르지 않습니다.');
    }
    $capacity = $values['capacity'] === '' ? null : (int)$values['capacity'];
    $sort_order = (int)$values['sort_order'];
    $is_active = (int)$values['is_active'];

    $connection = pdo_query('SELECT 1 AS ready');
    if (
        $connection === false ||
        !isset($dbh) || !($dbh instanceof PDO) || !$dbh->beginTransaction()
    ) {
        throw new RuntimeException('데이터베이스 연결을 확인할 수 없습니다.');
    }

    // 신규 신청 및 관리자 상태 변경과 같은 순서로 행사를 잠급니다.
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
        exit('참여 구분을 설정할 권한이 없습니다.');
    }
    if ((string)$event['application_mode'] !== 'event') {
        throw new DomainException('행사에 직접 신청하는 행사에서만 설정할 수 있습니다.');
    }

    $before = null;
    if ($option_id > 0) {
        $rows = pdo_query(
            'SELECT id, event_id, name, capacity, sort_order, is_active
             FROM class_share_participation_option
             WHERE id = ? AND event_id = ? LIMIT 1 FOR UPDATE',
            $option_id,
            $event_id
        );
        if ($rows === false) {
            throw new RuntimeException('참여 구분 조회 실패');
        }
        if (!isset($rows[0])) {
            throw new DomainException('해당 행사의 참여 구분을 찾을 수 없습니다.');
        }
        $before = $rows[0];
    }

    $duplicates = pdo_query(
        'SELECT id FROM class_share_participation_option
         WHERE event_id = ? AND name = ? AND id <> ? LIMIT 1',
        $event_id,
        $values['name'],
        $option_id
    );
    if ($duplicates === false) {
        throw new RuntimeException('구분 이름 중복 조회 실패');
    }
    if (isset($duplicates[0])) {
        throw new DomainException('같은 이름의 참여 구분이 이미 있습니다.');
    }

    if ($option_id > 0) {
        $counts = pdo_query(
            "SELECT COUNT(*) AS active_count FROM class_share_application
             WHERE event_id = ? AND participation_option_id = ?
               AND application_scope = 'event'
               AND status IN ('applied', 'approved', 'waiting')",
            $event_id,
            $option_id
        );
        if ($counts === false || !isset($counts[0])) {
            throw new RuntimeException('참여 구분별 신청 인원 조회 실패');
        }
        if ($capacity !== null && $capacity < (int)$counts[0]['active_count']) {
            throw new DomainException('정원을 현재 활성 신청 인원보다 작게 설정할 수 없습니다.');
        }
        if ((int)$event['participation_enabled'] === 1 && $is_active === 0) {
            $remaining = pdo_query(
                'SELECT COUNT(*) AS active_count FROM class_share_participation_option
                 WHERE event_id = ? AND id <> ? AND is_active = 1',
                $event_id,
                $option_id
            );
            if ($remaining === false || !isset($remaining[0])) {
                throw new RuntimeException('모집 대상 구분 조회 실패');
            }
            if ((int)$remaining[0]['active_count'] === 0) {
                throw new DomainException('구분 사용 중에는 모집 대상 구분이 최소 1개 필요합니다.');
            }
        }
        $result = pdo_query(
            'UPDATE class_share_participation_option
             SET name = ?, capacity = ?, sort_order = ?, is_active = ?
             WHERE id = ? AND event_id = ?',
            $values['name'], $capacity, $sort_order, $is_active, $option_id, $event_id
        );
        if ($result === false) {
            throw new RuntimeException('참여 구분 수정 실패');
        }
    } else {
        $result = pdo_query(
            'INSERT INTO class_share_participation_option
             (event_id, name, capacity, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?)',
            $event_id, $values['name'], $capacity, $sort_order, $is_active
        );
        if ($result === false || (int)$result <= 0) {
            throw new RuntimeException('참여 구분 추가 실패');
        }
        $option_id = (int)$result;
    }

    $after = array(
        'id' => $option_id, 'event_id' => $event_id, 'name' => $values['name'],
        'capacity' => $capacity, 'sort_order' => $sort_order, 'is_active' => $is_active
    );
    $before_json = json_encode(array('participation_option' => $before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $after_json = json_encode(array('participation_option' => $after, 'changed_fields' => array('participation_option')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    if (!$dbh->commit()) {
        throw new RuntimeException('참여 구분 저장 확정 실패');
    }
    $_SESSION['class_share_participation_flash'] = array(
        'event_id' => $event_id, 'option_id' => 0,
        'message' => '참여 구분을 저장했습니다.'
    );
    header('Location: ' . $url, true, 303);
    exit;
} catch (Throwable $exception) {
    if (isset($dbh) && $dbh instanceof PDO && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
    if (!($exception instanceof DomainException)) {
        error_log('[class-share] 참여 구분 저장 실패: ' . $exception->getMessage());
    }
    $_SESSION['class_share_participation_flash'] = array(
        'event_id' => $event_id, 'option_id' => (int)$option_raw,
        'errors' => array($exception instanceof DomainException
            ? $exception->getMessage() : '참여 구분을 저장하지 못했습니다. 다시 시도해 주세요.'),
        'values' => $values
    );
    header('Location: ' . $url . '&option_id=' . (int)$option_raw, true, 303);
    exit;
}
