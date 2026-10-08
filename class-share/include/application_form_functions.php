<?php

// 이름과 소속은 기존 신청 열에 저장합니다. 전화번호와 비밀번호 인증은 유지합니다.
function class_share_form_defaults()
{
    $fields = array();
    foreach (array('name' => '성명', 'school' => '소속 학교 또는 기관', 'phone' => '연락처') as $key => $label) {
        $fields[] = array(
            'id' => 0, 'field_key' => $key, 'label' => $label, 'help_text' => '',
            'field_type' => $key === 'phone' ? 'phone' : 'short_text',
            'options' => array(), 'is_required' => 1, 'is_active' => 1,
            'sort_order' => count($fields) * 10
        );
    }
    return $fields;
}

function class_share_form_id($value, $allow_zero = false)
{
    if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,18})$/D', $value)
        || (string)(int)$value !== $value || (!$allow_zero && $value === '0')) {
        throw new DomainException('행사 또는 항목 번호가 올바르지 않습니다.');
    }
    return (int)$value;
}

function class_share_form_length($value)
{
    if (preg_match('//u', $value) !== 1) {
        throw new DomainException('문자 인코딩이 올바르지 않습니다.');
    }
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
}

function class_share_form_validate_field($source, $key)
{
    if (!is_array($source) || !is_string($key)) {
        throw new DomainException('질문 입력 형식이 올바르지 않습니다.');
    }
    $data = array();
    foreach (array('label', 'help_text', 'field_type', 'options_text', 'is_required', 'is_active', 'sort_order') as $name) {
        if (!isset($source[$name]) || !is_string($source[$name])) {
            throw new DomainException('질문 입력 형식이 올바르지 않습니다.');
        }
        $data[$name] = trim($source[$name]);
    }
    if (class_share_form_length($data['label']) < 1 || class_share_form_length($data['label']) > 100
        || preg_match('/[\x00-\x1F\x7F]/u', $data['label'])) {
        throw new DomainException('항목 이름은 제어문자 없이 1~100자로 입력해 주세요.');
    }
    if (class_share_form_length($data['help_text']) > 500
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $data['help_text'])) {
        throw new DomainException('입력 설명은 500자 이하로 입력해 주세요.');
    }
    foreach (array('is_required', 'is_active') as $flag) {
        if (!in_array($data[$flag], array('0', '1'), true)) {
            throw new DomainException('필수 여부 또는 사용 여부가 올바르지 않습니다.');
        }
    }
    if (!preg_match('/^(0|[1-9][0-9]{0,6})$/D', $data['sort_order']) || (int)$data['sort_order'] > 1000000) {
        throw new DomainException('표시 순서는 0~1,000,000으로 입력해 주세요.');
    }
    if ($key === 'phone') {
        if ($data['field_type'] !== 'phone' || $data['is_required'] !== '1' || $data['is_active'] !== '1'
            || $data['label'] !== '연락처' || $data['sort_order'] !== '20') {
            throw new DomainException('연락처는 기본 이름과 순서를 유지하며 항상 필수로 사용합니다. 입력 설명만 수정할 수 있습니다.');
        }
    } elseif (in_array($key, array('name', 'school'), true)) {
        if ($data['field_type'] !== 'short_text') {
            throw new DomainException('기본 이름과 소속 항목의 입력 방식은 짧은 글입니다.');
        }
    } elseif (!in_array($data['field_type'], array('short_text', 'long_text', 'single_choice', 'multiple_choice'), true)) {
        throw new DomainException('추가 질문의 입력 방식이 올바르지 않습니다.');
    }
    $options = array();
    if (in_array($data['field_type'], array('single_choice', 'multiple_choice'), true)) {
        class_share_form_length($data['options_text']);
        foreach (preg_split('/\r\n|\r|\n/', $data['options_text']) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (class_share_form_length($line) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $line)) {
                throw new DomainException('각 선택지는 제어문자 없이 100자 이하로 입력해 주세요.');
            }
            if (in_array($line, $options, true)) {
                throw new DomainException('같은 선택지를 중복해서 입력할 수 없습니다.');
            }
            $options[] = $line;
        }
        if (count($options) < 1 || count($options) > 30) {
            throw new DomainException('선택지는 한 줄에 하나씩 1~30개 입력해 주세요.');
        }
    } elseif ($data['options_text'] !== '') {
        throw new DomainException('글 입력 항목에는 선택지를 입력하지 않습니다.');
    }
    return array(
        'label' => $data['label'], 'help_text' => $data['help_text'],
        'field_type' => $data['field_type'], 'options' => $options,
        'is_required' => (int)$data['is_required'], 'is_active' => (int)$data['is_active'],
        'sort_order' => (int)$data['sort_order']
    );
}

function class_share_form_check_history($before, $after, $has_answers)
{
    if ($has_answers && ($before['field_type'] !== $after['field_type'] || $before['options'] !== $after['options'])) {
        throw new DomainException('답변이 있는 질문은 입력 방식과 선택지를 변경할 수 없습니다. 기존 질문을 사용 중지하고 새 질문을 추가해 주세요.');
    }
}

function class_share_form_load($event_id)
{
    $settings = pdo_query('SELECT schema_version FROM class_share_application_form_setting WHERE event_id = ? LIMIT 1', $event_id);
    if ($settings === false) {
        throw new RuntimeException('신청서 설정 조회 실패');
    }
    if (!isset($settings[0])) {
        return array('schema_version' => 0, 'fields' => class_share_form_defaults());
    }
    $fields = pdo_query(
        'SELECT id, field_key, label, help_text, field_type, options_json, is_required, is_active, sort_order
         FROM class_share_application_form_field WHERE event_id = ? ORDER BY sort_order, id', $event_id
    );
    if ($fields === false) {
        throw new RuntimeException('신청서 항목 조회 실패');
    }
    $basic_keys = array();
    foreach ($fields as &$field) {
        $field['options'] = $field['options_json'] === null ? array() : json_decode($field['options_json'], true);
        if (!is_array($field['options'])) {
            throw new RuntimeException('신청서 선택지 정보가 올바르지 않습니다.');
        }
        unset($field['options_json']);
        if (in_array($field['field_key'], array('name', 'school', 'phone'), true)) {
            $basic_keys[] = $field['field_key'];
        }
    }
    unset($field);
    if (count($basic_keys) !== 3) {
        throw new RuntimeException('신청서 기본 항목이 누락되었습니다.');
    }
    return array('schema_version' => (int)$settings[0]['schema_version'], 'fields' => $fields);
}

// 공개 신청서는 저장된 항목으로 구성하며 전화번호와 비밀번호 인증은 유지합니다.
function class_share_form_check_version($value, $form)
{
    $version = class_share_form_id($value, true);
    if ($version !== (int)$form['schema_version']) {
        throw new DomainException('신청서 항목이 변경되었습니다. 새로고침한 뒤 입력 내용을 확인해 주세요.');
    }
    return $version;
}

function class_share_form_validate_answers($source, $form)
{
    if (!is_array($source) || !isset($form['fields']) || !is_array($form['fields'])) {
        throw new DomainException('신청서 입력 형식이 올바르지 않습니다.');
    }
    $errors = array();
    $basic = array('name' => '', 'school' => '');
    $values = array();
    $answers = array();
    $submitted = isset($source['answers']) ? $source['answers'] : array();
    if (!is_array($submitted) || count($submitted) > 100) {
        $errors[] = '추가 질문의 입력 형식이 올바르지 않습니다.';
        $submitted = array();
    }
    $known_keys = array();
    foreach ($form['fields'] as $field) {
        $key = $field['field_key'];
        if ($key === 'phone' || (int)$field['is_active'] !== 1) {
            continue;
        }
        $is_basic = in_array($key, array('name', 'school'), true);
        if (!$is_basic) {
            $known_keys[] = $key;
        }
        $value = $is_basic ? (isset($source[$key]) ? $source[$key] : '')
            : (isset($submitted[$key]) ? $submitted[$key] : ($field['field_type'] === 'multiple_choice' ? array() : ''));
        $label = (string)$field['label'];
        $multiple = $field['field_type'] === 'multiple_choice';
        if ($multiple) {
            if (!is_array($value) || count($value) > 30) {
                $errors[] = $label . ': 선택값이 올바르지 않습니다.';
                $value = array();
            }
            $canonical = array();
            foreach ($value as $item) {
                if (!is_string($item) || !in_array($item, $field['options'], true) || in_array($item, $canonical, true)) {
                    $errors[] = $label . ': 등록된 선택지를 중복 없이 선택해 주세요.';
                    continue;
                }
                $canonical[] = $item;
            }
            // 체크한 순서가 아니라 관리자 선택지 순서로 저장합니다.
            $value = array_values(array_intersect($field['options'], $canonical));
            if ((int)$field['is_required'] === 1 && count($value) === 0) {
                $errors[] = $label . ': 한 개 이상 선택해 주세요.';
            }
        } else {
            if (!is_string($value)) {
                $errors[] = $label . ': 입력 형식이 올바르지 않습니다.';
                $value = '';
            }
            $value = trim(str_replace(array("\r\n", "\r"), "\n", $value));
            try {
                $length = class_share_form_length($value);
            } catch (DomainException $exception) {
                $errors[] = $label . ': 문자 인코딩이 올바르지 않습니다.';
                $value = '';
                $length = 0;
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)
                || ($field['field_type'] !== 'long_text' && preg_match('/[\r\n\t]/u', $value))) {
                $errors[] = $label . ': 사용할 수 없는 문자가 있습니다.';
            }
            if ($field['field_type'] === 'single_choice') {
                if ($value !== '' && !in_array($value, $field['options'], true)) {
                    $errors[] = $label . ': 등록된 선택지를 선택해 주세요.';
                }
            } else {
                $maximum = $is_basic ? ($key === 'name' ? 60 : 100) : ($field['field_type'] === 'long_text' ? 2000 : 500);
                if ($length > $maximum || ($is_basic && $value !== '' && $length < 2)) {
                    $errors[] = $label . ': ' . ($is_basic ? '2~' : '1~') . $maximum . '자로 입력해 주세요.';
                }
            }
            if ((int)$field['is_required'] === 1 && $value === '') {
                $errors[] = $label . ': 입력해 주세요.';
            }
        }
        if ($is_basic) {
            $basic[$key] = $value;
        } else {
            $values[$key] = $value;
            // 당시 질문 이름/방식/선택지를 보존하고 기본 개인정보를 중복 저장하지 않습니다.
            $answers[] = array(
                'field_key' => $key, 'label' => $label, 'field_type' => $field['field_type'],
                'options' => $field['options'], 'value' => $value
            );
        }
    }
    foreach ($submitted as $key => $value) {
        if (!is_string($key) || !in_array($key, $known_keys, true)) {
            $errors[] = '현재 신청서에 없는 추가 질문이 포함되어 있습니다. 새로고침해 주세요.';
        }
    }
    return array('errors' => array_values(array_unique($errors)), 'basic' => $basic, 'values' => $values, 'answers' => $answers);
}

function class_share_form_lock_validate($event_id, $data)
{
    global $dbh;
    if (!isset($dbh) || !($dbh instanceof PDO) || !$dbh->inTransaction()) {
        throw new RuntimeException('신청서 저장은 신청 트랜잭션 안에서 수행해야 합니다.');
    }
    $form = class_share_form_load($event_id);
    class_share_form_check_version(isset($data['form_schema_version']) ? $data['form_schema_version'] : '0', $form);
    $validation = class_share_form_validate_answers(array(
        'name' => isset($data['name']) ? $data['name'] : '',
        'school' => isset($data['school']) ? $data['school'] : '',
        'answers' => isset($data['answers']) ? $data['answers'] : array()
    ), $form);
    if (count($validation['errors']) > 0) {
        throw new DomainException(implode(' ', $validation['errors']));
    }
    return array('schema_version' => $form['schema_version'], 'validation' => $validation);
}

function class_share_form_store_response($application_id, $locked)
{
    global $dbh;
    if (!isset($dbh) || !($dbh instanceof PDO) || !$dbh->inTransaction() || (int)$application_id < 1) {
        throw new RuntimeException('추가 답변을 신청과 함께 저장할 수 없습니다.');
    }
    if (count($locked['validation']['answers']) === 0) {
        return;
    }
    $json = json_encode($locked['validation']['answers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('추가 답변 저장 정보 생성 실패');
    }
    $result = pdo_query(
        'INSERT INTO class_share_application_form_response (application_id, schema_version, answers_json) VALUES (?, ?, ?)',
        (int)$application_id, (int)$locked['schema_version'], $json
    );
    if ($result === false) {
        throw new RuntimeException('추가 답변을 저장할 수 없습니다.');
    }
}

function class_share_form_decode_response($json)
{
    if ($json === null) {
        return array();
    }
    if (!is_string($json) || !preg_match('/^\s*\[/', $json)) {
        throw new RuntimeException('추가 답변의 저장 형식이 올바르지 않습니다.');
    }
    $answers = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($answers) || count($answers) > 100
        || (count($answers) > 0 && array_keys($answers) !== range(0, count($answers) - 1))) {
        throw new RuntimeException('추가 답변을 해석할 수 없습니다.');
    }
    $fields = array();
    $values = array();
    foreach ($answers as $answer) {
        if (!is_array($answer) || !isset($answer['field_key'], $answer['label'], $answer['field_type'], $answer['options'])
            || !array_key_exists('value', $answer) || !is_string($answer['field_key'])
            || !preg_match('/^q_[A-Za-z0-9_]{1,62}$/D', $answer['field_key'])
            || strlen($answer['field_key']) > 64 || array_key_exists($answer['field_key'], $values)
            || !is_string($answer['label']) || !is_string($answer['field_type']) || !is_array($answer['options'])
            || (count($answer['options']) > 0 && array_keys($answer['options']) !== range(0, count($answer['options']) - 1))) {
            throw new RuntimeException('추가 답변의 질문 정보가 올바르지 않습니다.');
        }
        foreach ($answer['options'] as $option) {
            if (!is_string($option) || strpos($option, "\n") !== false || strpos($option, "\r") !== false) {
                throw new RuntimeException('추가 답변의 선택지 정보가 올바르지 않습니다.');
            }
        }
        try {
            $definition = class_share_form_validate_field(array(
                'label' => $answer['label'], 'help_text' => '', 'field_type' => $answer['field_type'],
                'options_text' => implode("\n", $answer['options']),
                'is_required' => '0', 'is_active' => '1', 'sort_order' => '0'
            ), $answer['field_key']);
        } catch (DomainException $exception) {
            throw new RuntimeException('추가 답변의 질문 정의가 올바르지 않습니다.');
        }
        if ($definition['options'] !== $answer['options']) {
            throw new RuntimeException('추가 답변의 선택지 정보가 일치하지 않습니다.');
        }
        $fields[] = array_merge($definition, array('field_key' => $answer['field_key']));
        $values[$answer['field_key']] = $answer['value'];
    }
    $validated = class_share_form_validate_answers(array('answers' => $values), array('fields' => $fields));
    if (count($validated['errors']) > 0) {
        throw new RuntimeException('추가 답변의 값이 올바르지 않습니다.');
    }
    return $validated['answers'];
}

// 이 함수는 권한 검사 후에만 호출합니다. 공개 조회에는 인증된 세션의 신청번호도 확인합니다.
// null은 파기되었거나 해당 행사/신청번호에 일치하는 신청이 없음을 뜻합니다.
function class_share_form_load_response($event_id, $application_id, $application_code = null)
{
    if ((int)$event_id < 1 || (int)$application_id < 1
        || ($application_code !== null && (!is_string($application_code) || !preg_match('/^[a-f0-9]{32}$/D', $application_code)))) {
        throw new DomainException('추가 답변 조회 정보가 올바르지 않습니다.');
    }
    $parameters = array((int)$event_id, (int)$application_id);
    $code_condition = '';
    if ($application_code !== null) {
        $code_condition = ' AND application.application_code = ?';
        $parameters[] = $application_code;
    }
    $rows = pdo_query(
        'SELECT response.answers_json
         FROM class_share_application AS application
         LEFT JOIN class_share_application_form_response AS response ON response.application_id = application.id
         WHERE application.event_id = ? AND application.id = ? AND application.privacy_destroyed_at IS NULL'
         . $code_condition . ' LIMIT 1', ...$parameters
    );
    if ($rows === false) {
        throw new RuntimeException('추가 답변 조회 실패');
    }
    return isset($rows[0]) ? class_share_form_decode_response($rows[0]['answers_json']) : null;
}

function class_share_form_answer_text($answer)
{
    return is_array($answer['value']) ? implode(', ', $answer['value']) : (string)$answer['value'];
}

// 질문의 안정된 키와 당시 이름으로 열을 구분하여 이름 변경 전 응답도 유지합니다.
function class_share_form_export_plan($fields, $response_sets)
{
    $columns = array();
    $seen = array();
    foreach ($fields as $field) {
        if (in_array($field['field_key'], array('name', 'school', 'phone'), true)) {
            continue;
        }
        $signature = $field['field_key'] . "\x1F" . $field['label'];
        if (!isset($seen[$signature])) {
            $columns[] = array('field_key' => $field['field_key'], 'label' => $field['label'],
                'signature' => $signature, 'header' => $field['label'] . ((int)$field['is_active'] === 1 ? '' : ' [사용 중지]'));
            $seen[$signature] = true;
        }
    }
    foreach ($response_sets as $answers) {
        foreach ($answers as $answer) {
            $signature = $answer['field_key'] . "\x1F" . $answer['label'];
            if (!isset($seen[$signature])) {
                $columns[] = array('field_key' => $answer['field_key'], 'label' => $answer['label'],
                    'signature' => $signature, 'header' => $answer['label'] . ' [이전 항목명]');
                $seen[$signature] = true;
            }
        }
    }
    $headers = array();
    foreach ($columns as &$column) {
        $base = '추가 질문: ' . $column['header'];
        $header = $base;
        $suffix = 2;
        while (in_array($header, $headers, true)) {
            $header = $base . ' (' . $suffix++ . ')';
        }
        $column['header'] = $header;
        $headers[] = $header;
    }
    unset($column);
    return $columns;
}

function class_share_form_export_cells($columns, $answers)
{
    $values = array();
    foreach ($answers as $answer) {
        $values[$answer['field_key'] . "\x1F" . $answer['label']] = class_share_form_answer_text($answer);
    }
    $cells = array();
    foreach ($columns as $column) {
        $cells[] = isset($values[$column['signature']]) ? $values[$column['signature']] : '';
    }
    return $cells;
}
