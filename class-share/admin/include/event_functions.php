<?php

function class_share_event_clean_text($value)
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

function class_share_event_text_length($value)
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

    return $matched !== false
        ? $matched
        : strlen($value);
}

function class_share_event_normalize_datetime($value)
{
    $value =
        class_share_event_clean_text(
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

function class_share_event_normalize_date($value)
{
    $value =
        class_share_event_clean_text(
            $value
        );

    if ($value === '') {
        return null;
    }

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            new DateTimeZone(
                'Asia/Seoul'
            )
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
        $date === false ||
        $has_error ||
        $date->format('Y-m-d') !== $value
    ) {
        return false;
    }

    return $date->format(
        'Y-m-d'
    );
}

function class_share_event_timestamp($value)
{
    if (
        $value === null ||
        $value === false ||
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

function class_share_event_validate_input($source)
{
    if (!is_array($source)) {
        $source =
            array();
    }

    $field_names =
        array(
            'academic_year',
            'event_type',
            'application_mode',
            'application_capacity',
            'slug',
            'title',
            'subtitle',
            'event_start_at',
            'event_end_at',
            'application_start_at',
            'application_end_at',
            'privacy_policy_version',
            'privacy_notice',
            'retention_until',
            'status'
        );

    $form_values =
        array();

    foreach ($field_names as $field_name) {
        $form_values[$field_name] =
            array_key_exists(
                $field_name,
                $source
            )
            ? class_share_event_clean_text(
                $source[$field_name]
            )
            : '';
    }

    $form_values['privacy_notice'] =
        str_replace(
            array(
                "\r\n",
                "\r"
            ),
            "\n",
            $form_values['privacy_notice']
        );

    $errors =
        array();

    if (
        !preg_match(
            '/^[0-9]{4}$/D',
            $form_values['academic_year']
        )
    ) {
        $errors[] =
            '학년도를 정확히 입력해 주세요.';
    } else {
        $academic_year =
            (int)$form_values[
                'academic_year'
            ];

        if (
            $academic_year < 2020 ||
            $academic_year > 2100
        ) {
            $errors[] =
                '학년도는 2020년 이상 2100년 이하로 입력해 주세요.';
        }
    }

    $allowed_event_types =
        array(
            'class_share',
            'school_event',
            'briefing',
            'experience',
            'training',
            'other'
        );

    if (
        !in_array(
            $form_values['event_type'],
            $allowed_event_types,
            true
        )
    ) {
        $errors[] =
            '행사 유형을 정확히 선택해 주세요.';
    }

    $allowed_application_modes =
        array(
            'none',
            'event',
            'program'
        );

    if (
        !in_array(
            $form_values[
                'application_mode'
            ],
            $allowed_application_modes,
            true
        )
    ) {
        $errors[] =
            '신청 방식을 정확히 선택해 주세요.';
    }

    $application_capacity =
        null;

    if (
        $form_values['application_mode'] !==
        'event'
    ) {
        $form_values[
            'application_capacity'
        ] =
            '';
    } elseif (
        $form_values[
            'application_capacity'
        ] !== ''
    ) {
        if (
            !preg_match(
                '/^[1-9][0-9]*$/D',
                $form_values[
                    'application_capacity'
                ]
            ) ||
            (int)$form_values[
                'application_capacity'
            ] > 1000000
        ) {
            $errors[] =
                '행사 직접 신청 정원은 1~1,000,000명 사이로 입력해 주세요.';
        } else {
            $application_capacity =
                (int)$form_values[
                    'application_capacity'
                ];
        }
    }

    if (
        !preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
            $form_values['slug']
        ) ||
        class_share_event_text_length(
            $form_values['slug']
        ) < 3 ||
        class_share_event_text_length(
            $form_values['slug']
        ) > 80
    ) {
        $errors[] =
            '행사 주소 식별자는 3~80자의 영문 소문자, 숫자와 가운데 하이픈만 사용할 수 있습니다.';
    }

    if ($form_values['title'] === '') {
        $errors[] =
            '행사명을 입력해 주세요.';
    } elseif (
        class_share_event_text_length(
            $form_values['title']
        ) > 200
    ) {
        $errors[] =
            '행사명은 200자 이하로 입력해 주세요.';
    }

    if (
        class_share_event_text_length(
            $form_values['subtitle']
        ) > 300
    ) {
        $errors[] =
            '행사 부제목은 300자 이하로 입력해 주세요.';
    }

    if (
        $form_values[
            'privacy_policy_version'
        ] === ''
    ) {
        $errors[] =
            '개인정보 처리 안내 버전을 입력해 주세요.';
    } elseif (
        class_share_event_text_length(
            $form_values[
                'privacy_policy_version'
            ]
        ) > 50
    ) {
        $errors[] =
            '개인정보 처리 안내 버전은 50자 이하로 입력해 주세요.';
    }

    if (
        $form_values[
            'privacy_notice'
        ] === ''
    ) {
        $errors[] =
            '개인정보 수집·이용 안내를 입력해 주세요.';
    } elseif (
        class_share_event_text_length(
            $form_values[
                'privacy_notice'
            ]
        ) > 10000
    ) {
        $errors[] =
            '개인정보 수집·이용 안내는 10,000자 이하로 입력해 주세요.';
    }

    $event_start_at =
        class_share_event_normalize_datetime(
            $form_values['event_start_at']
        );

    $event_end_at =
        class_share_event_normalize_datetime(
            $form_values['event_end_at']
        );

    $application_start_at =
        class_share_event_normalize_datetime(
            $form_values[
                'application_start_at'
            ]
        );

    $application_end_at =
        class_share_event_normalize_datetime(
            $form_values[
                'application_end_at'
            ]
        );

    $datetime_fields =
        array(
            '행사 시작일시' =>
                array(
                    $form_values[
                        'event_start_at'
                    ],
                    $event_start_at
                ),

            '행사 종료일시' =>
                array(
                    $form_values[
                        'event_end_at'
                    ],
                    $event_end_at
                ),

            '전체 신청 시작일시' =>
                array(
                    $form_values[
                        'application_start_at'
                    ],
                    $application_start_at
                ),

            '전체 신청 종료일시' =>
                array(
                    $form_values[
                        'application_end_at'
                    ],
                    $application_end_at
                )
        );

    foreach (
        $datetime_fields as
        $label => $datetime_values
    ) {
        if ($datetime_values[0] === '') {
            $is_event_datetime =
                $label === '행사 시작일시' ||
                $label === '행사 종료일시';

            $is_application_datetime =
                $label === '전체 신청 시작일시' ||
                $label === '전체 신청 종료일시';

            $is_required =
                $form_values['status'] ===
                    'published' &&
                (
                    $is_event_datetime ||
                    (
                        $is_application_datetime &&
                        $form_values[
                            'application_mode'
                        ] !== 'none'
                    )
                );

            if ($is_required) {
                $errors[] =
                    $label .
                    '를 입력해 주세요.';
            }
        } elseif ($datetime_values[1] === false) {
            $errors[] =
                $label .
                '의 형식이 올바르지 않습니다.';
        }
    }

    $event_start_timestamp =
        class_share_event_timestamp(
            $event_start_at
        );

    $event_end_timestamp =
        class_share_event_timestamp(
            $event_end_at
        );

    $application_start_timestamp =
        class_share_event_timestamp(
            $application_start_at
        );

    $application_end_timestamp =
        class_share_event_timestamp(
            $application_end_at
        );

    if (
        $event_start_timestamp !== null &&
        $event_end_timestamp !== null &&
        $event_end_timestamp <=
            $event_start_timestamp
    ) {
        $errors[] =
            '행사 종료일시는 시작일시보다 늦어야 합니다.';
    }

    if (
        $application_start_timestamp !== null &&
        $application_end_timestamp !== null &&
        $application_end_timestamp <=
            $application_start_timestamp
    ) {
        $errors[] =
            '전체 신청 종료일시는 시작일시보다 늦어야 합니다.';
    }

    if (
        $application_end_timestamp !== null &&
        $event_end_timestamp !== null &&
        $application_end_timestamp >
            $event_end_timestamp
    ) {
        $errors[] =
            '전체 신청 종료일시는 행사 종료일시보다 늦을 수 없습니다.';
    }

    $retention_until =
        class_share_event_normalize_date(
            $form_values['retention_until']
        );

    if (
        $form_values['retention_until'] ===
        ''
    ) {
        if (
            $form_values['status'] ===
                'published' &&
            $form_values[
                'application_mode'
            ] !== 'none'
        ) {
            $errors[] =
                '개인정보 보관 기한을 입력해 주세요.';
        }
    } elseif ($retention_until === false) {
        $errors[] =
            '개인정보 보관 기한의 형식이 올바르지 않습니다.';
    }

    if (
        $retention_until !== null &&
        $retention_until !== false &&
        $event_end_at !== null &&
        $event_end_at !== false &&
        $retention_until <
            substr(
                $event_end_at,
                0,
                10
            )
    ) {
        $errors[] =
            '개인정보 보관 기한은 행사 종료일보다 빠를 수 없습니다.';
    }

    $allowed_statuses =
        array(
            'draft',
            'published',
            'closed',
            'archived'
        );

    if (
        !in_array(
            $form_values['status'],
            $allowed_statuses,
            true
        )
    ) {
        $errors[] =
            '행사 상태가 올바르지 않습니다.';
    }

    return array(
        'errors' =>
            $errors,

        'form_values' =>
            $form_values,

        'data' =>
            array(
                'academic_year' =>
                    isset($academic_year)
                    ? $academic_year
                    : 0,

                'event_type' =>
                    $form_values[
                        'event_type'
                    ],

                'application_mode' =>
                    $form_values[
                        'application_mode'
                    ],

                'application_capacity' =>
                    $application_capacity,

                'slug' =>
                    $form_values['slug'],

                'title' =>
                    $form_values['title'],

                'subtitle' =>
                    $form_values['subtitle'],

                'event_start_at' =>
                    $event_start_at === false
                    ? null
                    : $event_start_at,

                'event_end_at' =>
                    $event_end_at === false
                    ? null
                    : $event_end_at,

                'application_start_at' =>
                    $application_start_at === false
                    ? null
                    : $application_start_at,

                'application_end_at' =>
                    $application_end_at === false
                    ? null
                    : $application_end_at,

                'privacy_policy_version' =>
                    $form_values[
                        'privacy_policy_version'
                    ],

                'privacy_notice' =>
                    $form_values[
                        'privacy_notice'
                    ],

                'retention_until' =>
                    $retention_until === false
                    ? null
                    : $retention_until,

                'status' =>
                    $form_values['status']
            )
    );
}
