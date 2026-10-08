<?php

require_once(
    __DIR__ .
    '/privacy_crypto.php'
);


require_once(__DIR__ . '/participation_functions.php');
require_once(__DIR__ . '/application_form_functions.php');

function class_share_application_clean($value)
{
    return trim(
        (string)$value
    );
}


function class_share_application_text_length(
    $value
) {
    if (function_exists('mb_strlen')) {
        return mb_strlen(
            (string)$value,
            'UTF-8'
        );
    }

    return strlen(
        (string)$value
    );
}


function class_share_application_csrf_token()
{
    if (
        session_status() !==
        PHP_SESSION_ACTIVE
    ) {
        throw new RuntimeException(
            '신청 세션이 시작되지 않았습니다.'
        );
    }

    $session_key =
        'class_share_public_csrf_token';

    if (
        !isset($_SESSION[$session_key]) ||
        !is_string($_SESSION[$session_key]) ||
        strlen($_SESSION[$session_key]) < 64
    ) {
        $_SESSION[$session_key] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION[$session_key];
}


function class_share_application_verify_csrf(
    $token
) {
    if (
        session_status() !==
        PHP_SESSION_ACTIVE
    ) {
        return false;
    }

    $session_key =
        'class_share_public_csrf_token';

    if (
        !isset($_SESSION[$session_key]) ||
        !is_string($_SESSION[$session_key])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION[$session_key],
        (string)$token
    );
}


function class_share_application_rotate_csrf()
{
    if (
        session_status() !==
        PHP_SESSION_ACTIVE
    ) {
        return;
    }

    $_SESSION[
        'class_share_public_csrf_token'
    ] =
        bin2hex(
            random_bytes(32)
        );
}


function class_share_application_active_statuses()
{
    return array(
        'applied',
        'approved',
        'waiting'
    );
}


function class_share_application_event_is_open(
    $event,
    $now_timestamp = null
) {
    if (!is_array($event)) {
        return false;
    }

    if (
        !isset(
            $event['status'],
            $event['application_mode'],
            $event['application_start_at'],
            $event['application_end_at']
        )
    ) {
        return false;
    }

    if (
        (string)$event['status'] !==
            'published' ||
        (string)$event['application_mode'] !==
            'event'
    ) {
        return false;
    }

    $start_timestamp =
        strtotime(
            (string)$event[
                'application_start_at'
            ]
        );

    $end_timestamp =
        strtotime(
            (string)$event[
                'application_end_at'
            ]
        );

    if (
        $start_timestamp === false ||
        $end_timestamp === false
    ) {
        return false;
    }

    if ($now_timestamp === null) {
        $now_timestamp =
            time();
    }

    $now_timestamp =
        (int)$now_timestamp;

    return
        $now_timestamp >= $start_timestamp &&
        $now_timestamp <= $end_timestamp;
}


function class_share_application_program_is_open(
    $event,
    $class_item,
    $now_timestamp = null
) {
    if (
        !is_array($event) ||
        !is_array($class_item)
    ) {
        return false;
    }

    if (
        !isset(
            $event['status'],
            $event['application_mode'],
            $event['application_start_at'],
            $event['application_end_at'],
            $class_item['status'],
            $class_item[
                'application_deadline'
            ]
        )
    ) {
        return false;
    }

    if (
        (string)$event['status'] !==
            'published' ||
        (string)$event['application_mode'] !==
            'program' ||
        (string)$class_item['status'] !==
            'published'
    ) {
        return false;
    }

    $start_timestamp =
        strtotime(
            (string)$event[
                'application_start_at'
            ]
        );

    $end_timestamp =
        strtotime(
            (string)$event[
                'application_end_at'
            ]
        );

    $deadline_timestamp =
        strtotime(
            (string)$class_item[
                'application_deadline'
            ]
        );

    if (
        $start_timestamp === false ||
        $end_timestamp === false ||
        $deadline_timestamp === false
    ) {
        return false;
    }

    if ($now_timestamp === null) {
        $now_timestamp =
            time();
    }

    $now_timestamp =
        (int)$now_timestamp;

    return
        $now_timestamp >= $start_timestamp &&
        $now_timestamp <= $end_timestamp &&
        $now_timestamp <= $deadline_timestamp;
}


function class_share_application_validate(
    $source,
    $form = null
) {
    if (!is_array($source)) {
        $source =
            array();
    }

    if ($form === null) {
        $form = array('schema_version' => 0, 'fields' => class_share_form_defaults());
    }
    $form_validation = class_share_form_validate_answers($source, $form);
    $name = $form_validation['basic']['name'];
    $school = $form_validation['basic']['school'];

    $phone_input =
        isset($source['phone']) && is_string($source['phone'])
        ? $source['phone']
        : '';

    $password =
        isset($source['password']) && is_string($source['password'])
        ? (string)$source['password']
        : '';

    $password_confirm =
        isset($source['password_confirm']) && is_string($source['password_confirm'])
        ? (string)$source[
            'password_confirm'
        ]
        : '';

    $privacy_agreed =
        isset($source['privacy_agreed']) && is_string($source['privacy_agreed']) &&
        $source['privacy_agreed'] ===
            '1';

    $honeypot =
        isset($source['website']) && !is_string($source['website'])
        ? 'invalid'
        : (isset($source['website'])
        ? class_share_application_clean(
            $source['website']
        )
        : '');

    $errors = $form_validation['errors'];
    $form_version = isset($source['form_schema_version']) ? $source['form_schema_version'] : '0';
    try {
        $form_version = (string)class_share_form_check_version($form_version, $form);
    } catch (DomainException $exception) {
        $errors[] = $exception->getMessage();
        $form_version = (string)$form['schema_version'];
    }

    $normalized_phone =
        '';

    if (!preg_match('/^010-[0-9]{4}-[0-9]{4}$/D', $phone_input)) {
        $errors[] =
            '연락처는 하이픈을 포함하여 010-0000-0000 형식으로 입력해 주세요.';
    } else {
        try {
            $normalized_phone =
                class_share_normalize_phone($phone_input);
        } catch (InvalidArgumentException $e) {
            $errors[] =
                '연락처는 010-0000-0000 형식으로 입력해 주세요.';
        }
    }

    $password_length =
        strlen($password);

    if (
        $password_length < 6 ||
        $password_length > 72
    ) {
        $errors[] =
            '신청 비밀번호는 6~72자로 입력해 주세요.';
    }

    if (
        !hash_equals(
            $password,
            $password_confirm
        )
    ) {
        $errors[] =
            '신청 비밀번호 확인이 일치하지 않습니다.';
    }

    if (!$privacy_agreed) {
        $errors[] =
            '개인정보 수집·이용에 동의해 주세요.';
    }

    if ($honeypot !== '') {
        $errors[] =
            '신청 요청을 처리할 수 없습니다.';
    }

    $participation_raw = isset($source['participation_option_id'])
        ? $source['participation_option_id'] : '';
    $participation_id = null;
    try {
        $participation_id = class_share_participation_parse_id($participation_raw, true);
    } catch (DomainException $exception) {
        $errors[] = $exception->getMessage();
        $participation_raw = '';
    }

    return array(
        'errors' =>
            array_values(
                array_unique($errors)
            ),

        'form_values' =>
            array(
                'name' => $name,
                'school' => $school,
                'phone' => $phone_input,
                'answers' => $form_validation['values'],
                'participation_option_id' => (string)$participation_raw,
                'privacy_agreed' =>
                    $privacy_agreed
                    ? '1'
                    : ''
            ),

        'data' =>
            array(
                'name' => $name,
                'school' => $school,
                'phone' => $normalized_phone,
                'password' => $password,
                'form_schema_version' => $form_version,
                'answers' => $form_validation['values'],
                'participation_option_id' => $participation_id
            )
    );
}


function class_share_application_code()
{
    return bin2hex(
        random_bytes(16)
    );
}


function class_share_application_ip_address()
{
    $ip_address =
        isset($_SERVER['REMOTE_ADDR'])
        ? trim(
            (string)$_SERVER[
                'REMOTE_ADDR'
            ]
        )
        : '';

    if (
        $ip_address === '' ||
        filter_var(
            $ip_address,
            FILTER_VALIDATE_IP
        ) === false
    ) {
        return null;
    }

    return $ip_address;
}
