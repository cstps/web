<?php

require_once(
    dirname(__DIR__) .
    '/admin/include/content_sanitizer.php'
);

function class_share_public_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES |
        ENT_SUBSTITUTE |
        ENT_HTML5,
        'UTF-8'
    );
}

function class_share_public_format_datetime($value)
{
    if (
        $value === null ||
        $value === ''
    ) {
        return '-';
    }

    $timestamp =
        strtotime(
            (string)$value
        );

    if ($timestamp === false) {
        return (string)$value;
    }

    return date(
        'Y-m-d H:i',
        $timestamp
    );
}

function class_share_public_event_url(
    $school_slug,
    $event_slug
) {
    return
        '/class-share/index.php' .
        '?school=' .
        rawurlencode(
            (string)$school_slug
        ) .
        '&event=' .
        rawurlencode(
            (string)$event_slug
        );
}

function class_share_public_valid_slug($value)
{
    return preg_match(
        '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
        (string)$value
    ) === 1;
}

function class_share_public_is_class_available(
    $class_item,
    $event,
    $now_timestamp
) {
    if (
        (string)$event['status'] !==
            'published' ||
        (string)$class_item['status'] !==
            'published'
    ) {
        return false;
    }

    $application_start =
        strtotime(
            (string)$event[
                'application_start_at'
            ]
        );

    $application_end =
        strtotime(
            (string)$event[
                'application_end_at'
            ]
        );

    $class_deadline =
        strtotime(
            (string)$class_item[
                'application_deadline'
            ]
        );

    if (
        $application_start === false ||
        $application_end === false ||
        $class_deadline === false
    ) {
        return false;
    }

    if (
        $now_timestamp <
            $application_start ||
        $now_timestamp >
            $application_end ||
        $now_timestamp >
            $class_deadline
    ) {
        return false;
    }

    return
        (int)$class_item[
            'active_application_count'
        ] <
        (int)$class_item['capacity'];
}
