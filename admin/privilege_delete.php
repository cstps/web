<?php

require_once(__DIR__ . "/admin-init.php");

if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("권한을 관리할 권한이 없습니다.");
}

header("Content-Type: text/plain; charset=UTF-8");
http_response_code(410);

exit(
    "기존 권한 삭제 기능은 종료되었습니다. " .
    "사용자 목록에서 해당 사용자의 권한 관리 화면을 이용해 주세요."
);
