<?php

// 기존 유틸리티 주소 호환용 redirect
$target = '/tools/shorturl.php';

if (isset($_GET['code']) && is_string($_GET['code'])) {
    $target .= '?code=' . rawurlencode($_GET['code']);
}

header('Location: ' . $target, true, 302);
exit;
