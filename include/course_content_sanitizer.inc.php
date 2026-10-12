<?php

// Course 학습 자료 전용 HTML 정화
// 외부 임베드, 이미지, 임의 스타일 및 이벤트 속성 차단

function course_content_sanitize_html($html)
{
    if (!is_string($html)) {
        return '';
    }

    $html = trim($html);

    if ($html === '') {
        return '';
    }

    $allowed = array_fill_keys(
        array(
            'p', 'br', 'strong', 'b', 'em', 'i',
            'u', 's', 'sub', 'sup',
            'h2', 'h3', 'h4',
            'ul', 'ol', 'li', 'blockquote',
            'a', 'table', 'thead', 'tbody',
            'tfoot', 'tr', 'th', 'td',
            'caption', 'pre', 'code', 'hr'
        ),
        true
    );

    $blocked = array_fill_keys(
        array(
            'script', 'style', 'iframe', 'frame',
            'frameset', 'object', 'embed',
            'applet', 'svg', 'math',
            'form', 'input', 'button',
            'textarea', 'select', 'option',
            'img', 'video', 'audio', 'source',
            'link', 'meta', 'base'
        ),
        true
    );

    $document = new DOMDocument('1.0', 'UTF-8');

    $previous = libxml_use_internal_errors(true);

    $loaded = $document->loadHTML(
        '<?xml encoding="UTF-8">' .
        '<div id="course-content-root">' .
        $html .
        '</div>',
        LIBXML_HTML_NOIMPLIED |
        LIBXML_HTML_NODEFDTD |
        LIBXML_NONET
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return '';
    }

    $root = $document->getElementById(
        'course-content-root'
    );

    if (!$root instanceof DOMElement) {
        return '';
    }

    $clean = null;

    $clean = function ($node) use (
        &$clean,
        $allowed,
        $blocked
    ) {
        $children = array();

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (
                $child->nodeType === XML_COMMENT_NODE ||
                $child->nodeType === XML_PI_NODE
            ) {
                $node->removeChild($child);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (isset($blocked[$tag])) {
                $node->removeChild($child);
                continue;
            }

            $clean($child);

            if (!isset($allowed[$tag])) {
                while ($child->firstChild !== null) {
                    $node->insertBefore(
                        $child->firstChild,
                        $child
                    );
                }

                $node->removeChild($child);
                continue;
            }

            $href = '';

            if ($tag === 'a') {
                $href = trim(
                    html_entity_decode(
                        $child->getAttribute('href'),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    )
                );
            }

            while ($child->attributes->length > 0) {
                $child->removeAttributeNode(
                    $child->attributes->item(0)
                );
            }

            if ($tag === 'a' && $href !== '') {
                $scheme = parse_url(
                    $href,
                    PHP_URL_SCHEME
                );

                if (
                    !preg_match('/[\x00-\x20\x7F]/', $href) &&
                    filter_var($href, FILTER_VALIDATE_URL) !== false &&
                    is_string($scheme) &&
                    in_array(
                        strtolower($scheme),
                        array('http', 'https'),
                        true
                    )
                ) {
                    $child->setAttribute('href', $href);
                    $child->setAttribute(
                        'rel',
                        'noopener noreferrer'
                    );
                }
            }
        }
    };

    $clean($root);

    $result = '';

    foreach ($root->childNodes as $child) {
        $result .= $document->saveHTML($child);
    }

    return trim($result);
}
