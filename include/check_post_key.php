<?php

$postkey_session_name = $OJ_NAME . '_postkey';

if (
    !isset($_SESSION[$postkey_session_name]) ||
    !is_string($_SESSION[$postkey_session_name]) ||
    !isset($_POST['postkey']) ||
    !is_string($_POST['postkey']) ||
    !hash_equals(
        $_SESSION[$postkey_session_name],
        $_POST['postkey']
    )
) {
    http_response_code(403);
    exit('유효하지 않은 요청입니다.');
}
