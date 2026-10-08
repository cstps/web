<?php

require_once(__DIR__ . '/include/admin_init.php');
require_once(dirname(__DIR__) . '/include/application_form_functions.php');
$admin = class_share_admin_require_login();
class_share_admin_require_post_csrf();
try {
    $event_id = class_share_form_id(isset($_POST['event_id']) ? $_POST['event_id'] : null);
    $field_id = class_share_form_id(isset($_POST['field_id']) ? $_POST['field_id'] : null, true);
    $version = class_share_form_id(isset($_POST['schema_version']) ? $_POST['schema_version'] : null, true);
    $key = isset($_POST['field_key']) && is_string($_POST['field_key']) ? $_POST['field_key'] : null;
    if ($key === null || ($field_id === 0 && !in_array($key, array('', 'name', 'school', 'phone'), true))) {
        throw new DomainException('항목 정보가 올바르지 않습니다.');
    }
} catch (DomainException $exception) {
    http_response_code(400);
    exit($exception->getMessage());
}
$request_key = $key;
$url = '/class-share/admin/application_form_fields.php?event_id=' . $event_id;
$values = array();
foreach (array('label', 'help_text', 'field_type', 'options_text', 'is_required', 'is_active', 'sort_order') as $name) {
    $values[$name] = isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
}
try {
    $ready = pdo_query('SELECT 1 AS ready');
    if ($ready === false || !isset($dbh) || !($dbh instanceof PDO) || !$dbh->beginTransaction()) {
        throw new RuntimeException('DB 연결 실패');
    }
    // 신청 및 설정 변경이 행사를 먼저 잠그는 순서를 공유합니다.
    $events = pdo_query('SELECT id, school_id FROM class_share_event WHERE id = ? LIMIT 1 FOR UPDATE', $event_id);
    if ($events === false) {
        throw new RuntimeException('행사 조회 실패');
    }
    if (!isset($events[0])) {
        $dbh->rollBack();
        http_response_code(404);
        exit('행사를 찾을 수 없습니다.');
    }
    $school_id = (int)$events[0]['school_id'];
    if (!class_share_admin_can_edit_school($school_id, $admin)) {
        $dbh->rollBack();
        http_response_code(403);
        exit('신청서를 설정할 권한이 없습니다.');
    }
    $form = class_share_form_load($event_id);
    if ($form['schema_version'] !== $version) {
        throw new DomainException('다른 화면에서 신청서가 변경되었습니다. 새로고침한 뒤 다시 수정해 주세요.');
    }
    $before = null;
    if ($field_id > 0 || $key !== '') {
        foreach ($form['fields'] as $field) {
            if (($field_id > 0 && (int)$field['id'] === $field_id) || ($field_id === 0 && $field['field_key'] === $key)) {
                $before = $field;
                break;
            }
        }
        if ($before === null || $before['field_key'] !== $key) {
            throw new DomainException('해당 행사의 항목을 찾을 수 없습니다.');
        }
    }
    $data = class_share_form_validate_field($_POST, $before === null ? '' : $before['field_key']);
    if ($before === null && count($form['fields']) >= 103) {
        throw new DomainException('행사별 추가 질문은 사용 중지 항목을 포함하여 최대 100개까지 등록할 수 있습니다.');
    }
    if ($before !== null && !in_array($key, array('name', 'school', 'phone'), true)) {
        $candidate = json_encode(array('field_key' => $key), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($candidate === false) {
            throw new RuntimeException('질문 이력 조건 생성 실패');
        }
        $counts = pdo_query(
            'SELECT COUNT(*) AS answer_count FROM class_share_application_form_response AS response
             INNER JOIN class_share_application AS application ON application.id = response.application_id
             WHERE application.event_id = ? AND JSON_CONTAINS(response.answers_json, ?)',
            $event_id, $candidate
        );
        if ($counts === false || !isset($counts[0])) {
            throw new RuntimeException('질문 답변 이력 조회 실패');
        }
        class_share_form_check_history($before, $data, (int)$counts[0]['answer_count'] > 0);
    }
    if ($version === 0) {
        if (pdo_query('INSERT INTO class_share_application_form_setting (event_id, schema_version) VALUES (?, 1)', $event_id) === false) {
            throw new RuntimeException('신청서 초기 설정 실패');
        }
        foreach (class_share_form_defaults() as $default) {
            $result = pdo_query(
                'INSERT INTO class_share_application_form_field
                 (event_id, field_key, label, help_text, field_type, is_required, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                $event_id, $default['field_key'], $default['label'], $default['help_text'],
                $default['field_type'], $default['is_required'], $default['is_active'], $default['sort_order']
            );
            if ($result === false || (int)$result <= 0) {
                throw new RuntimeException('기본 항목 초기 저장 실패');
            }
        }
    }
    $options_json = in_array($data['field_type'], array('single_choice', 'multiple_choice'), true)
        ? json_encode($data['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    if ($options_json === false) {
        throw new RuntimeException('선택지 저장 정보 생성 실패');
    }
    if ($before === null) {
        $key = 'q_' . bin2hex(random_bytes(16));
        $result = pdo_query(
            'INSERT INTO class_share_application_form_field
             (event_id, field_key, label, help_text, field_type, options_json, is_required, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $event_id, $key, $data['label'], $data['help_text'], $data['field_type'], $options_json,
            $data['is_required'], $data['is_active'], $data['sort_order']
        );
        if ($result === false || (int)$result <= 0) {
            throw new RuntimeException('질문 추가 실패');
        }
    } else {
        $result = pdo_query(
            'UPDATE class_share_application_form_field
             SET label = ?, help_text = ?, field_type = ?, options_json = ?, is_required = ?, is_active = ?, sort_order = ?
             WHERE event_id = ? AND field_key = ?',
            $data['label'], $data['help_text'], $data['field_type'], $options_json,
            $data['is_required'], $data['is_active'], $data['sort_order'], $event_id, $key
        );
        if ($result === false) {
            throw new RuntimeException('질문 수정 실패');
        }
    }
    if (pdo_query('UPDATE class_share_application_form_setting SET schema_version = schema_version + 1 WHERE event_id = ?', $event_id) === false) {
        throw new RuntimeException('신청서 버전 저장 실패');
    }
    $before_json = json_encode(array('form_field' => $before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $after_json = json_encode(array('form_field' => array_merge(array('field_key' => $key), $data), 'changed_fields' => array('application_form')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($before_json === false || $after_json === false) {
        throw new RuntimeException('변경 기록 생성 실패');
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    $audit = pdo_query(
        "INSERT INTO class_share_audit_log
         (school_id, admin_id, actor_type, action, target_type, target_id, before_data, after_data, ip_address)
         VALUES (?, ?, 'admin', 'event.update', 'event', ?, ?, ?, ?)",
        $school_id, (int)$admin['id'], $event_id, $before_json, $after_json, $ip
    );
    if ($audit === false || !$dbh->commit()) {
        throw new RuntimeException('신청서 저장 확정 실패');
    }
    $_SESSION['class_share_form_flash'] = array('event_id' => $event_id, 'message' => '신청서 항목을 저장했습니다.');
    header('Location: ' . $url, true, 303);
    exit;
} catch (Throwable $exception) {
    if (isset($dbh) && $dbh instanceof PDO && $dbh->inTransaction()) {
        $dbh->rollBack();
    }
    if (!($exception instanceof DomainException)) {
        error_log('[class-share] 신청서 항목 저장 실패: ' . $exception->getMessage());
    }
    $_SESSION['class_share_form_flash'] = array(
        'event_id' => $event_id, 'field_id' => $field_id, 'field_key' => $request_key,
        'errors' => array($exception instanceof DomainException ? $exception->getMessage() : '항목을 저장하지 못했습니다. 다시 시도해 주세요.'),
        'values' => $values
    );
    header('Location: ' . $url . '&field_id=' . $field_id . '&field_key=' . rawurlencode($request_key), true, 303);
    exit;
}
