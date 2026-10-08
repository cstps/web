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

function class_share_content_sanitize_html(
    $html,
    $allow_images = false
) {
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

    // 공지에서 명시적으로 요청한 경우에만 이미지를 허용합니다.
    if ($allow_images === true) {
        $allowed_elements['img'] = true;
        unset($remove_entirely['img']);
    }

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
            $remove_entirely,
            $allow_images
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

                // 업로드 전용 경로의 이미지와 필요한 속성만 유지합니다.
                if ($tag_name === 'img') {
                    $src = trim($child->getAttribute('src'));

                    if (!preg_match(
                        '~^/class-share/uploads/notices/'
                        . '[1-9][0-9]*/[a-f0-9]{32}'
                        . '\\.(?:jpg|png|gif|webp)$~D',
                        $src
                    )) {
                        $node->removeChild($child);
                        continue;
                    }

                    $alt = mb_substr(
                        $child->getAttribute('alt'),
                        0,
                        300,
                        'UTF-8'
                    );
                    $title = mb_substr(
                        $child->getAttribute('title'),
                        0,
                        300,
                        'UTF-8'
                    );
                    $width = $child->getAttribute('width');
                    $height = $child->getAttribute('height');

                    // 이벤트, 임의 스타일 등 기존 속성은 모두 제거합니다.
                    while ($child->attributes->length > 0) {
                        $child->removeAttributeNode(
                            $child->attributes->item(0)
                        );
                    }

                    $child->setAttribute('src', $src);
                    $child->setAttribute('alt', $alt);

                    if ($title !== '') {
                        $child->setAttribute('title', $title);
                    }

                    foreach (
                        array('width' => $width, 'height' => $height)
                        as $name => $value
                    ) {
                        if (
                            preg_match('/^[1-9][0-9]{0,3}$/D', $value)
                        ) {
                            $child->setAttribute($name, $value);
                        }
                    }

                    // 작은 화면에서도 이미지가 본문 너비를 넘지 않습니다.
                    $child->setAttribute(
                        'class',
                        'cs-notice-image'
                    );
                    continue;
                }

                if (
                    $allow_images === true &&
                    in_array(
                        $tag_name,
                        array('p', 'h2', 'h3', 'h4', 'li', 'blockquote'),
                        true
                    )
                ) {
                    $alignment_value = '';
                    $alignment_classes = array(
                        'cs-notice-align-left' => 'left',
                        'cs-notice-align-center' => 'center',
                        'cs-notice-align-right' => 'right'
                    );

                    $existing_class = trim($child->getAttribute('class'));

                    if (isset($alignment_classes[$existing_class])) {
                        $alignment_value = $alignment_classes[$existing_class];
                    }

                    // 편집기에서 새로 지정한 정렬을 우선합니다.
                    if (preg_match(
                        '/^\s*text-align\s*:\s*(left|center|right)\s*;?\s*$/iD',
                        $child->getAttribute('style'),
                        $alignment_match
                    )) {
                        $alignment_value = strtolower($alignment_match[1]);
                    }

                    $child->removeAttribute('style');
                    $child->removeAttribute('class');

                    if ($alignment_value !== '') {
                        $child->setAttribute(
                            'class',
                            'cs-notice-align-' . $alignment_value
                        );
                    }
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
                        $allow_images === true &&
                        $lower_name === 'class' &&
                        in_array(
                            $tag_name,
                            array('p', 'h2', 'h3', 'h4', 'li', 'blockquote'),
                            true
                        ) &&
                        in_array(
                            $attribute_value,
                            array(
                                'cs-notice-align-left',
                                'cs-notice-align-center',
                                'cs-notice-align-right'
                            ),
                            true
                        )
                    ) {
                        $keep_attribute = true;
                    } elseif (
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
