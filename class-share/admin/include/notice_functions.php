<?php

require_once(
    __DIR__ .
    '/content_sanitizer.php'
);

function class_share_notice_clean_text($value)
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

function class_share_notice_text_length($value)
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

function class_share_notice_validate_input($source)
{
    if (!is_array($source)) {
        $source =
            array();
    }

    $form_values =
        array(
            'title' =>
                isset($source['title'])
                ? class_share_notice_clean_text(
                    $source['title']
                )
                : '',

            'content' =>
                isset($source['content'])
                ? class_share_notice_clean_text(
                    $source['content']
                )
                : '',

            'important' =>
                isset($source['important']) &&
                (string)$source['important'] === '1'
                ? '1'
                : '0',

            'sort_order' =>
                isset($source['sort_order'])
                ? class_share_notice_clean_text(
                    $source['sort_order']
                )
                : ''
        );

    $errors =
        array();

    if ($form_values['title'] === '') {
        $errors[] =
            '공지 제목을 입력해 주세요.';
    } elseif (
        class_share_notice_text_length(
            $form_values['title']
        ) > 200
    ) {
        $errors[] =
            '공지 제목은 200자 이하로 입력해 주세요.';
    }

    $safe_content =
        class_share_content_sanitize_html(
            $form_values['content'],
            true
        );

    $content_text_length =
        class_share_content_visible_text_length(
            $safe_content
        );

    // 정제 후 유효한 이미지가 남아 있으면 이미지 공지도 허용합니다.
    $has_image = strpos($safe_content, '<img ') !== false;

    if ($content_text_length < 1 && !$has_image) {
        $errors[] =
            '공지 내용을 입력해 주세요.';
    } elseif ($content_text_length > 10000) {
        $errors[] =
            '공지 내용의 실제 글은 10,000자 이하로 입력해 주세요.';
    }

    if (
        strlen(
            $safe_content
        ) > 60000
    ) {
        $errors[] =
            '공지 내용의 서식 포함 데이터가 너무 큽니다. 표나 서식을 줄여 주세요.';
    }

    if (
        !preg_match(
            '/^[0-9]+$/D',
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

    return array(
        'errors' =>
            $errors,

        'form_values' =>
            $form_values,

        'data' =>
            array(
                'title' =>
                    $form_values['title'],

                'content' =>
                    $safe_content,

                'important' =>
                    $form_values['important'] === '1'
                    ? 1
                    : 0,

                'sort_order' =>
                    isset($sort_order)
                    ? $sort_order
                    : 0
            )
    );
}
