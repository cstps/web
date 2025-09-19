<?php
require_once("./include/db_info.inc.php");
require_once("./include/cookie_helper.php"); // [ADD 안전 쿠키 헬퍼]

session_start();

// 세션에 저장된 로그인 정보 제거
unset($_SESSION[$OJ_NAME.'_'.'user_id']);

// 안전하게 쿠키 만료
safe_expire_cookie($OJ_NAME."_user",  "/");
safe_expire_cookie($OJ_NAME."_check", "/");

// 세션 완전 파괴
session_unset();
session_destroy();

// 메인 페이지로 이동
header("Location: index.php");
exit(0);
?>
