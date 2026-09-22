<?php

function class_share_content_plain_text_to_html($text)
{
    if (
        is_array($text) ||
        is_object($text)
    ) {
        return '';
    }

    $text =
        trim(
            str_replace(
                array(
                    "\r\n",
                    "\r"
                ),
                "\n",
                (string)$text
            )
        );

    if ($text === '') {
        return '';
    }

    $paragraphs =
        preg_split(
            '/\n{2,}/',
            $text
        );

    $html =
        array();

    foreach ($paragraphs as $paragraph) {
        $escaped =
            htmlspecialchars(
                trim($paragraph),
                ENT_QUOTES |
                ENT_SUBSTITUTE |
                ENT_HTML5,
                'UTF-8'
            );

        $escaped =
            str_replace(
                "\n",
                '<br>',
                $escaped
            );

        if ($escaped !== '') {
            $html[] =
                '<p>' .
                $escaped .
                '</p>';
        }
    }

    return implode(
        "\n",
        $html
    );
}

function class_share_content_visible_text_length($html)
{
    $text =
        html_entity_decode(
            strip_tags(
                (string)$html
            ),
            ENT_QUOTES |
            ENT_HTML5,
            'UTF-8'
        );

    $text =
        trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            )
        );

    if (function_exists('mb_strlen')) {
        return mb_strlen(
            $text,
            'UTF-8'
        );
    }

    if (function_exists('iconv_strlen')) {
        $length =
            iconv_strlen(
                $text,
                'UTF-8'
            );

        if ($length !== false) {
            return $length;
        }
    }

    $matched =
        preg_match_all(
            '/./us',
            $text,
            $matches
        );

    return $matched !== false
        ? $matched
        : strlen($text);
}

function class_share_content_sanitize_href($value)
{
    $value =
        trim(
            html_entity_decode(
                (string)$value,
                ENT_QUOTES |
                ENT_HTML5,
                'UTF-8'
            )
        );

    /*
     * 제어문자와 공백을 이용한
     * javascript: 우회를 차단합니다.
     */
    $compact =
        preg_replace(
            '/[\x00-\x20\x7F]+/',
            '',
            $value
        );

    if (
        $compact === '' ||
        strpos($compact, '//') === 0
    ) {
        return '';
    }

    if (
        strpos($compact, '#') === 0 ||
        strpos($compact, '/') === 0
    ) {
        return $compact;
    }

    $scheme =
        parse_url(
            $compact,
            PHP_URL_SCHEME
        );

    if ($scheme === null) {
        return $compact;
    }

    if ($scheme === false) {
        return '';
    }

    $scheme =
        strtolower(
            (string)$scheme
        );

    if (
        !in_array(
            $scheme,
            array(
                'http',
                'https',
                'mailto'
            ),
            true
        )
    ) {
        return '';
    }

    return $compact;
}

function class_share_content_sanitize_html($html)
{
    if (
        is_array($html) ||
        is_object($html)
    ) {
        return '';
    }

    $html =
        trim(
            (string)$html
        );

    if ($html === '') {
        return '';
    }

    $allowed_elements =
        array(
            'p' => true,
            'br' => true,
            'strong' => true,
            'b' => true,
            'em' => true,
            'i' => true,
            'u' => true,
            's' => true,
            'sub' => true,
            'sup' => true,
            'h2' => true,
            'h3' => true,
            'h4' => true,
            'ul' => true,
            'ol' => true,
            'li' => true,
            'blockquote' => true,
            'a' => true,
            'table' => true,
            'thead' => true,
            'tbody' => true,
            'tfoot' => true,
            'tr' => true,
            'th' => true,
            'td' => true,
            'caption' => true,
            'pre' => true,
            'code' => true,
            'hr' => true
        );

    $remove_entirely =
        array(
            'script' => true,
            'style' => true,
            'iframe' => true,
            'frame' => true,
            'frameset' => true,
            'object' => true,
            'embed' => true,
            'applet' => true,
            'svg' => true,
            'math' => true,
            'form' => true,
            'input' => true,
            'button' => true,
            'textarea' => true,
            'select' => true,
            'option' => true,
            'img' => true,
            'video' => true,
            'audio' => true,
            'source' => true,
            'link' => true,
            'meta' => true,
            'base' => true
        );

    $document =
        new DOMDocument(
            '1.0',
            'UTF-8'
        );

    $previous_errors =
        libxml_use_internal_errors(
            true
        );

    $wrapper_id =
        'class-share-content-root';

    $loaded =
        $document->loadHTML(
            '<?xml encoding="UTF-8">' .
            '<div id="' .
            $wrapper_id .
            '">' .
            $html .
            '</div>',
            LIBXML_HTML_NOIMPLIED |
            LIBXML_HTML_NODEFDTD |
            LIBXML_NONET
        );

    libxml_clear_errors();

    libxml_use_internal_errors(
        $previous_errors
    );

    if ($loaded === false) {
        return '';
    }

    $root =
        $document->getElementById(
            $wrapper_id
        );

    if (!$root instanceof DOMElement) {
        return '';
    }

    $sanitize_node = null;

    $sanitize_node =
        function ($node) use (
            &$sanitize_node,
            $allowed_elements,
            $remove_entirely
        ) {
            $children =
                array();

            foreach ($node->childNodes as $child) {
                $children[] =
                    $child;
            }

            foreach ($children as $child) {
                if (
                    $child->nodeType ===
                    XML_COMMENT_NODE ||
                    $child->nodeType ===
                    XML_PI_NODE
                ) {
                    $node->removeChild(
                        $child
                    );

                    continue;
                }

                if (
                    $child->nodeType !==
                    XML_ELEMENT_NODE
                ) {
                    continue;
                }

                $tag_name =
                    strtolower(
                        $child->nodeName
                    );

                if (
                    isset(
                        $remove_entirely[
                            $tag_name
                        ]
                    )
                ) {
                    $node->removeChild(
                        $child
                    );

                    continue;
                }

                $sanitize_node(
                    $child
                );

                if (
                    !isset(
                        $allowed_elements[
                            $tag_name
                        ]
                    )
                ) {
                    while (
                        $child->firstChild !==
                        null
                    ) {
                        $node->insertBefore(
                            $child->firstChild,
                            $child
                        );
                    }

                    $node->removeChild(
                        $child
                    );

                    continue;
                }

                $attribute_names =
                    array();

                if ($child->hasAttributes()) {
                    foreach (
                        $child->attributes as
                        $attribute
                    ) {
                        $attribute_names[] =
                            $attribute->nodeName;
                    }
                }

                foreach (
                    $attribute_names as
                    $attribute_name
                ) {
                    $attribute_value =
                        $child->getAttribute(
                            $attribute_name
                        );

                    $lower_name =
                        strtolower(
                            $attribute_name
                        );

                    $keep_attribute =
                        false;

                    if (
                        $tag_name === 'a' &&
                        $lower_name === 'href'
                    ) {
                        $safe_href =
                            class_share_content_sanitize_href(
                                $attribute_value
                            );

                        if ($safe_href !== '') {
                            $child->setAttribute(
                                'href',
                                $safe_href
                            );

                            $keep_attribute =
                                true;
                        }
                    } elseif (
                        $tag_name === 'a' &&
                        $lower_name === 'title'
                    ) {
                        $child->setAttribute(
                            'title',
                            mb_substr(
                                trim(
                                    $attribute_value
                                ),
                                0,
                                300,
                                'UTF-8'
                            )
                        );

                        $keep_attribute =
                            true;
                    } elseif (
                        $tag_name === 'a' &&
                        $lower_name === 'target' &&
                        $attribute_value ===
                            '_blank'
                    ) {
                        $child->setAttribute(
                            'target',
                            '_blank'
                        );

                        $keep_attribute =
                            true;
                    } elseif (
                        in_array(
                            $tag_name,
                            array(
                                'th',
                                'td'
                            ),
                            true
                        ) &&
                        in_array(
                            $lower_name,
                            array(
                                'colspan',
                                'rowspan'
                            ),
                            true
                        ) &&
                        preg_match(
                            '/^[1-9][0-9]{0,2}$/D',
                            $attribute_value
                        )
                    ) {
                        $keep_attribute =
                            true;
                    } elseif (
                        $tag_name === 'th' &&
                        $lower_name === 'scope' &&
                        in_array(
                            strtolower(
                                $attribute_value
                            ),
                            array(
                                'row',
                                'col'
                            ),
                            true
                        )
                    ) {
                        $child->setAttribute(
                            'scope',
                            strtolower(
                                $attribute_value
                            )
                        );

                        $keep_attribute =
                            true;
                    }

                    if (!$keep_attribute) {
                        $child->removeAttribute(
                            $attribute_name
                        );
                    }
                }

                if ($tag_name === 'a') {
                    if (
                        $child->getAttribute(
                            'target'
                        ) === '_blank'
                    ) {
                        $child->setAttribute(
                            'rel',
                            'noopener noreferrer'
                        );
                    } else {
                        $child->removeAttribute(
                            'target'
                        );

                        $child->removeAttribute(
                            'rel'
                        );
                    }
                }
            }
        };

    $sanitize_node(
        $root
    );

    $safe_html =
        '';

    foreach ($root->childNodes as $child) {
        $safe_html .=
            $document->saveHTML(
                $child
            );
    }

    return trim(
        $safe_html
    );
}
