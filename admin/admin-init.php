<?php

require_once(__DIR__ . "/../include/db_info.inc.php");
require_once(__DIR__ . "/../include/my_func.inc.php");
require_once(__DIR__ . "/../include/permission_functions.inc.php");


// ============================================================
// 관리자 페이지 접근 권한
// 기존 admin-header.php의 권한 정책을 그대로 유지한다.
// ============================================================

$admin_page_access_allowed =
    isset($admin_page_access_allowed) &&
    $admin_page_access_allowed === true;

if (!oj_can_access_admin() && !$admin_page_access_allowed) {

    http_response_code(403);

    echo "<!DOCTYPE html>";
    echo "<html lang=\"ko\">";
    echo "<head>";
    echo "<meta charset=\"utf-8\">";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
    echo "<title>접근 권한 없음</title>";
    echo "</head>";
    echo "<body>";
    echo "<p>관리자 페이지에 접근하려면 로그인이 필요합니다.</p>";
    echo "<p><a href=\"../loginpage.php\" target=\"_top\">로그인</a></p>";
    echo "</body>";
    echo "</html>";

    exit(1);
}


// ============================================================
// 언어 파일
// ============================================================

if (
    isset($OJ_LANG) &&
    file_exists(__DIR__ . "/../lang/" . $OJ_LANG . ".php")
) {
    require_once(
        __DIR__ . "/../lang/" . $OJ_LANG . ".php"
    );
}
