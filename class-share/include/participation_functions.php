<?php

// 빈 값은 미구분, 번호는 양의 정수로 처리합니다.
function class_share_participation_parse_id($value, $allow_empty = false)
{
    if ($allow_empty && ($value === null || $value === '' || $value === '0' || $value === 0)) {
        return null;
    }
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (
        !is_string($value) ||
        !preg_match('/^[1-9][0-9]{0,18}$/D', $value) ||
        (string)(int)$value !== $value ||
        (int)$value <= 0
    ) {
        throw new DomainException('참여 구분을 올바르게 선택해 주세요.');
    }
    return (int)$value;
}

function class_share_participation_list_options($event_id, $active_only = true)
{
    $condition = $active_only ? ' AND option_item.is_active = 1' : '';
    $rows = pdo_query(
        "SELECT option_item.id, option_item.event_id, option_item.name,
                option_item.capacity, option_item.sort_order, option_item.is_active,
                COALESCE(SUM(CASE
                    WHEN application.application_scope = 'event'
                     AND application.status IN ('applied', 'approved', 'waiting')
                    THEN 1 ELSE 0 END), 0) AS active_count
         FROM class_share_participation_option AS option_item
         LEFT JOIN class_share_application AS application
           ON application.participation_option_id = option_item.id
          AND application.event_id = option_item.event_id
         WHERE option_item.event_id = ?" . $condition . "
         GROUP BY option_item.id, option_item.event_id, option_item.name,
                  option_item.capacity, option_item.sort_order, option_item.is_active
         ORDER BY option_item.sort_order, option_item.id",
        (int)$event_id
    );
    if ($rows === false) {
        throw new RuntimeException('참여 구분 현황을 불러올 수 없습니다.');
    }
    return $rows;
}

// 호출자는 행사 행을 FOR UPDATE로 잠근 트랜잭션 안에서 실행해야 합니다.
function class_share_participation_check_option(
    $event_id,
    $option_id,
    $exclude_application_id = 0,
    $check_capacity = true,
    $require_active = true
) {
    global $dbh;
    if (!($dbh instanceof PDO) || !$dbh->inTransaction()) {
        throw new RuntimeException('참여 구분 검사는 저장 트랜잭션 안에서만 가능합니다.');
    }
    $option_id = class_share_participation_parse_id($option_id);
    $rows = pdo_query(
        'SELECT id, event_id, name, capacity, is_active
         FROM class_share_participation_option
         WHERE id = ? AND event_id = ? LIMIT 1 FOR UPDATE',
        $option_id,
        (int)$event_id
    );
    if ($rows === false) {
        throw new RuntimeException('참여 구분을 다시 확인할 수 없습니다.');
    }
    if (!isset($rows[0])) {
        throw new DomainException('해당 행사의 참여 구분을 찾을 수 없습니다.');
    }
    $option = $rows[0];
    if ($require_active && (int)$option['is_active'] !== 1) {
        throw new DomainException('선택한 참여 구분은 모집이 중지되었습니다.');
    }
    if ($check_capacity && $option['capacity'] !== null) {
        $counts = pdo_query(
            "SELECT COUNT(*) AS active_count FROM class_share_application
             WHERE event_id = ? AND participation_option_id = ?
               AND application_scope = 'event'
               AND status IN ('applied', 'approved', 'waiting')
               AND id <> ?",
            (int)$event_id,
            $option_id,
            (int)$exclude_application_id
        );
        if ($counts === false || !isset($counts[0])) {
            throw new RuntimeException('참여 구분별 정원을 확인할 수 없습니다.');
        }
        if ((int)$counts[0]['active_count'] >= (int)$option['capacity']) {
            throw new DomainException('선택한 참여 구분의 신청 정원이 마감되었습니다.');
        }
    }
    return $option;
}

function class_share_participation_new_choice($event, $value)
{
    if ((int)$event['participation_enabled'] !== 1) {
        return null;
    }
    return class_share_participation_check_option(
        (int)$event['id'],
        class_share_participation_parse_id($value)
    );
}

// 사용 시작 시 모집 대상과 현재 배정 인원을 확인합니다.
function class_share_participation_validate_activation($options)
{
    $has_active_option = false;
    foreach ($options as $option) {
        if ((int)$option['is_active'] !== 1) {
            continue;
        }
        $has_active_option = true;
        if (
            $option['capacity'] !== null &&
            (int)$option['active_count'] > (int)$option['capacity']
        ) {
            throw new DomainException('참여 구분 정원을 현재 활성 신청 인원 이상으로 설정해 주세요.');
        }
    }
    if (!$has_active_option) {
        throw new DomainException('모집 대상으로 설정한 참여 구분을 최소 1개 등록해 주세요.');
    }
}

// 목록과 내보내기에서 동일한 구분 조건을 사용합니다.
function class_share_participation_make_filter($value, $options)
{
    if (!is_string($value)) {
        throw new DomainException('참여 구분 필터가 올바르지 않습니다.');
    }
    if ($value === '') {
        return array('value' => '', 'label' => '전체 구분', 'sql' => '', 'parameters' => array());
    }
    if ($value === 'unassigned') {
        return array(
            'value' => 'unassigned', 'label' => '미구분 (행사 직접 신청)',
            'sql' => " AND application.application_scope = 'event' AND application.participation_option_id IS NULL",
            'parameters' => array()
        );
    }
    $id = class_share_participation_parse_id($value);
    foreach ($options as $option) {
        if ((int)$option['id'] === $id) {
            return array(
                'value' => (string)$id,
                'label' => (string)$option['name'],
                'sql' => " AND application.application_scope = 'event' AND application.participation_option_id = ?",
                'parameters' => array($id)
            );
        }
    }
    throw new DomainException('해당 행사의 참여 구분을 선택해 주세요.');
}
