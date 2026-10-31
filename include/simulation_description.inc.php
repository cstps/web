<?php
// 활동 설명 전용 HTML 정제 및 기존 일반 텍스트 호환.
function simulation_description_html($value, $from_editor = false)
{
    $mark = '<!--simulation-description:v1-->';
    $text = (string)$value;
    $escape = static function ($s) {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    if (!$from_editor && strpos($text, $mark) !== 0) {
        return nl2br($escape($text));
    }
    if (strpos($text, $mark) === 0) {
        $text = substr($text, strlen($mark));
    }
    if (strlen($text) > 65535 || preg_match('//u', $text) !== 1) {
        throw new InvalidArgumentException('활동 설명은 UTF-8 기준 65,535바이트 이내로 입력해 주세요.');
    }
    $old = libxml_use_internal_errors(true);
    $input = new DOMDocument('1.0', 'UTF-8');
    try {
        $loaded = $input->loadHTML('<?xml encoding="UTF-8"><html><body>' . $text . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($old);
    }
    if (!$loaded) {
        throw new InvalidArgumentException('활동 설명의 HTML을 확인해 주세요.');
    }
    $output = new DOMDocument('1.0', 'UTF-8');
    $root = $output->createElement('div');
    $output->appendChild($root);
    $allowed = explode(' ', 'p br div span strong b em i u s strike sub sup h1 h2 h3 h4 h5 h6 ul ol li blockquote pre code hr table caption thead tbody tfoot tr th td a img');
    $blocked = explode(' ', 'script style iframe frame frameset object embed form input button textarea select option svg math template head title meta link base');
    $copy = null;
    $copy = static function ($node, $parent, $depth) use (&$copy, $output, $allowed, $blocked) {
        if ($node instanceof DOMText) {
            $parent->appendChild($output->createTextNode($node->nodeValue));
            return;
        }
        if (!($node instanceof DOMElement)) return;
        $tag = strtolower($node->tagName);
        if (in_array($tag, $blocked, true)) return;
        if ($depth > 40) {
            $parent->appendChild($output->createTextNode($node->textContent));
            return;
        }
        $target = $parent;
        if (in_array($tag, $allowed, true)) {
            $target = $output->createElement($tag);
            foreach (array('title', 'alt') as $attr) {
                if ($node->hasAttribute($attr)) $target->setAttribute($attr, $node->getAttribute($attr));
            }
            foreach (array('colspan', 'rowspan', 'width', 'height', 'border', 'cellpadding', 'cellspacing') as $attr) {
                $v = $node->getAttribute($attr);
                if (preg_match('/^[1-9][0-9]{0,3}$/D', $v)) $target->setAttribute($attr, $v);
            }
            $attr = $tag === 'a' ? 'href' : ($tag === 'img' ? 'src' : '');
            if ($attr !== '') {
                $url = trim($node->getAttribute($attr));
                $safe = $url !== '' && !preg_match('/[\x00-\x20\x7f\\\\]/', $url) &&
                    (preg_match('~^https?://~i', $url) || ($tag === 'a' && stripos($url, 'mailto:') === 0) ||
                     preg_match('~^(?:/(?!/)|\#|[A-Za-z0-9_.-]+(?:[/?\#]|$))~', $url));
                if ($safe) $target->setAttribute($attr, $url);
                if ($tag === 'a') $target->setAttribute('rel', 'noopener noreferrer');
            }
            $styles = array();
            foreach (explode(';', $node->getAttribute('style')) as $rule) {
                $parts = explode(':', $rule, 2);
                if (count($parts) !== 2) continue;
                $key = strtolower(trim($parts[0])); $v = strtolower(trim($parts[1]));
                if (($key === 'text-align' && in_array($v, array('left','center','right','justify'), true)) ||
                    (in_array($key, array('color','background-color'), true) && preg_match('/^(?:#[0-9a-f]{3,8}|[a-z]{1,20}|rgba?\([0-9.,% ]+\))$/D', $v)) ||
                    ($key === 'font-weight' && preg_match('/^(?:normal|bold|[1-9]00)$/D', $v)) ||
                    ($key === 'font-style' && in_array($v, array('normal','italic'), true)) ||
                    ($key === 'text-decoration' && in_array($v, array('underline','line-through','none'), true))) {
                    $styles[] = $key . ':' . $v;
                }
            }
            if ($tag === 'img') $styles[] = 'max-width:100%;height:auto';
            if ($styles) $target->setAttribute('style', implode(';', $styles));
            $parent->appendChild($target);
        }
        foreach ($node->childNodes as $child) $copy($child, $target, $depth + 1);
    };
    $body = $input->getElementsByTagName('body')->item(0);
    if ($body) foreach ($body->childNodes as $child) $copy($child, $root, 0);
    $html = '';
    foreach ($root->childNodes as $child) $html .= $output->saveHTML($child);
    return $html;
}

function simulation_description_store($html)
{
    $clean = simulation_description_html($html, true);
    if ($clean === '') return '';
    $value = '<!--simulation-description:v1-->' . $clean;
    if (strlen($value) > 65535) {
        throw new InvalidArgumentException('서식을 포함한 활동 설명이 65,535바이트를 초과합니다.');
    }
    return $value;
}
