<?php

/**
 * 수업 등록·수정·일괄 등록에서 공통으로 사용하는 함수입니다.
 */

function class_share_class_clean_text($value)
{
    if (
        is_array($value) ||
        is_object($value)
    ) {
        return '';
    }

    return trim(
        (string)$value
    );
}

function class_share_class_text_length($value)
{
    $value =
        (string)$value;

    if (function_exists('mb_strlen')) {
        return mb_strlen(
            $value,
            'UTF-8'
        );
    }

    if (function_exists('iconv_strlen')) {
        $length =
            iconv_strlen(
                $value,
                'UTF-8'
            );

        if ($length !== false) {
            return $length;
        }
    }

    $matched =
        preg_match_all(
            '/./us',
            $value,
            $matches
        );

    if ($matched !== false) {
        return $matched;
    }

    return strlen($value);
}

function class_share_class_normalize_datetime($value)
{
    $value =
        class_share_class_clean_text(
            $value
        );

    if ($value === '') {
        return null;
    }

    $timezone =
        new DateTimeZone(
            'Asia/Seoul'
        );

    $formats =
        array(
            'Y-m-d\TH:i',
            'Y-m-d H:i',
            'Y-m-d H:i:s'
        );

    foreach ($formats as $format) {
        $date =
            DateTimeImmutable::createFromFormat(
                '!' . $format,
                $value,
                $timezone
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        $has_error =
            is_array($errors) &&
            (
                (int)$errors['warning_count'] > 0 ||
                (int)$errors['error_count'] > 0
            );

        if (
            $date !== false &&
            !$has_error &&
            $date->format($format) === $value
        ) {
            return $date->format(
                'Y-m-d H:i:s'
            );
        }
    }

    return false;
}

function class_share_class_database_timestamp($value)
{
    if (
        $value === null ||
        $value === ''
    ) {
        return null;
    }

    $timestamp =
        strtotime(
            (string)$value
        );

    return $timestamp === false
        ? null
        : $timestamp;
}

/**
 * 반환값:
 *
 * array(
 *     'errors' => array(),
 *     'form_values' => array(),
 *     'data' => array()
 * )
 */
function class_share_class_validate_input(
    $source,
    $event
) {
    if (!is_array($source)) {
        $source =
            array();
    }

    if (!is_array($event)) {
        $event =
            array();
    }

    $field_names =
        array(
            'subject',
            'title',
            'teacher_name',
            'target',
            'class_start_at',
            'class_end_at',
            'place',
            'application_deadline',
            'capacity',
            'description',
            'sort_order'
        );

    $form_values =
        array();

    foreach ($field_names as $field_name) {
        $form_values[$field_name] =
            array_key_exists(
                $field_name,
                $source
            )
            ? class_share_class_clean_text(
                $source[$field_name]
            )
            : '';
    }

    $form_values['description'] =
        str_replace(
            array(
                "\r\n",
                "\r"
            ),
            "\n",
            $form_values['description']
        );

    $errors =
        array();

    $length_rules =
        array(
            'subject' => array(
                '교과',
                50
            ),
            'title' => array(
                '수업명',
                200
            ),
            'teacher_name' => array(
                '교사명',
                100
            ),
            'target' => array(
                '수업 대상',
                100
            ),
            'place' => array(
                '장소',
                150
            ),
            'description' => array(
                '수업 소개',
                5000
            )
        );

    foreach (
        $length_rules as
        $field_name => $rule
    ) {
        if (
            class_share_class_text_length(
                $form_values[$field_name]
            ) > $rule[1]
        ) {
            $errors[] =
                $rule[0] .
                '은(는) ' .
                $rule[1] .
                '자 이하로 입력해 주세요.';
        }
    }

    if ($form_values['title'] === '') {
        $errors[] =
            '수업명을 입력해 주세요.';
    }

    if ($form_values['teacher_name'] === '') {
        $errors[] =
            '교사명을 입력해 주세요.';
    }

    $class_start_at =
        class_share_class_normalize_datetime(
            $form_values['class_start_at']
        );

    if ($form_values['class_start_at'] === '') {
        $errors[] =
            '수업 시작일시를 입력해 주세요.';
    } elseif ($class_start_at === false) {
        $errors[] =
            '수업 시작일시의 형식이 올바르지 않습니다.';
    }

    $class_end_at =
        class_share_class_normalize_datetime(
            $form_values['class_end_at']
        );

    if (
        $form_values['class_end_at'] !== '' &&
        $class_end_at === false
    ) {
        $errors[] =
            '수업 종료일시의 형식이 올바르지 않습니다.';
    }

    $application_deadline =
        class_share_class_normalize_datetime(
            $form_values[
                'application_deadline'
            ]
        );

    if (
        $form_values[
            'application_deadline'
        ] === ''
    ) {
        $errors[] =
            '신청 마감일시를 입력해 주세요.';
    } elseif ($application_deadline === false) {
        $errors[] =
            '신청 마감일시의 형식이 올바르지 않습니다.';
    }

    $class_start_timestamp =
        class_share_class_database_timestamp(
            $class_start_at
        );

    $class_end_timestamp =
        class_share_class_database_timestamp(
            $class_end_at
        );

    $deadline_timestamp =
        class_share_class_database_timestamp(
            $application_deadline
        );

    if (
        $class_start_timestamp !== null &&
        $class_end_timestamp !== null &&
        $class_end_timestamp <=
            $class_start_timestamp
    ) {
        $errors[] =
            '수업 종료일시는 시작일시보다 늦어야 합니다.';
    }

    if (
        $class_start_timestamp !== null &&
        $deadline_timestamp !== null &&
        $deadline_timestamp >
            $class_start_timestamp
    ) {
        $errors[] =
            '신청 마감일시는 수업 시작일시보다 늦을 수 없습니다.';
    }

    $event_start_timestamp =
        class_share_class_database_timestamp(
            isset($event['event_start_at'])
            ? $event['event_start_at']
            : null
        );

    $event_end_timestamp =
        class_share_class_database_timestamp(
            isset($event['event_end_at'])
            ? $event['event_end_at']
            : null
        );

    $application_start_timestamp =
        class_share_class_database_timestamp(
            isset(
                $event[
                    'application_start_at'
                ]
            )
            ? $event[
                'application_start_at'
            ]
            : null
        );

    $application_end_timestamp =
        class_share_class_database_timestamp(
            isset(
                $event[
                    'application_end_at'
                ]
            )
            ? $event[
                'application_end_at'
            ]
            : null
        );

    if (
        $class_start_timestamp !== null &&
        $event_start_timestamp !== null &&
        $class_start_timestamp <
            $event_start_timestamp
    ) {
        $errors[] =
            '수업 시작일시는 행사 시작일시보다 빠를 수 없습니다.';
    }

    if (
        $class_start_timestamp !== null &&
        $event_end_timestamp !== null &&
        $class_start_timestamp >
            $event_end_timestamp
    ) {
        $errors[] =
            '수업 시작일시는 행사 종료일시보다 늦을 수 없습니다.';
    }

    if (
        $class_end_timestamp !== null &&
        $event_end_timestamp !== null &&
        $class_end_timestamp >
            $event_end_timestamp
    ) {
        $errors[] =
            '수업 종료일시는 행사 종료일시보다 늦을 수 없습니다.';
    }

    if (
        $deadline_timestamp !== null &&
        $application_start_timestamp !== null &&
        $deadline_timestamp <
            $application_start_timestamp
    ) {
        $errors[] =
            '신청 마감일시는 행사 신청 시작일시보다 빠를 수 없습니다.';
    }

    if (
        $deadline_timestamp !== null &&
        $application_end_timestamp !== null &&
        $deadline_timestamp >
            $application_end_timestamp
    ) {
        $errors[] =
            '신청 마감일시는 행사 신청 종료일시보다 늦을 수 없습니다.';
    }

    if (
        !preg_match(
            '/^[0-9]+$/',
            $form_values['capacity']
        )
    ) {
        $errors[] =
            '정원은 1 이상의 정수로 입력해 주세요.';
    } else {
        $capacity =
            (int)$form_values['capacity'];

        if (
            $capacity < 1 ||
            $capacity > 1000
        ) {
            $errors[] =
                '정원은 1명 이상 1,000명 이하로 입력해 주세요.';
        }
    }

    if (
        !preg_match(
            '/^[0-9]+$/',
            $form_values['sort_order']
        )
    ) {
        $errors[] =
            '정렬 순서는 0 이상의 정수로 입력해 주세요.';
    } else {
        $sort_order =
            (int)$form_values['sort_order'];

        if (
            $sort_order < 0 ||
            $sort_order > 999999
        ) {
            $errors[] =
                '정렬 순서는 0 이상 999,999 이하로 입력해 주세요.';
        }
    }

    $data =
        array(
            'subject' =>
                $form_values['subject'],

            'title' =>
                $form_values['title'],

            'teacher_name' =>
                $form_values['teacher_name'],

            'target' =>
                $form_values['target'],

            'class_start_at' =>
                $class_start_at === false
                ? null
                : $class_start_at,

            'class_end_at' =>
                $class_end_at === false
                ? null
                : $class_end_at,

            'place' =>
                $form_values['place'],

            'application_deadline' =>
                $application_deadline === false
                ? null
                : $application_deadline,

            'capacity' =>
                isset($capacity)
                ? $capacity
                : 0,

            'description' =>
                $form_values['description'],

            'sort_order' =>
                isset($sort_order)
                ? $sort_order
                : 0
        );

    return array(
        'errors' => $errors,
        'form_values' => $form_values,
        'data' => $data
    );
}
