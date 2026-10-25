<?php

require_once(__DIR__ . "/../include/db_info.inc.php");
require_once(__DIR__ . "/../include/my_func.inc.php");
require_once(__DIR__ . "/../include/permission_functions.inc.php");

require_once(__DIR__ . '/../scripts/simulation_problem_permissions.php');

// 현재 로그인 사용자와 수업 교사 등록에서 권한을 결정한다.
$admin_can_manage_simulation_problems = false;

try {
    if (isset($_SESSION[$OJ_NAME.'_user_id'])) {
        if (
            pdo_query('SELECT 1') === false ||
            !isset($dbh) ||
            !($dbh instanceof PDO)
        ) {
            throw new RuntimeException('문제 관리 권한 조회 연결 실패');
        }

        $admin_simulation_actor = simulation_problem_current_actor($dbh);
        $admin_can_manage_simulation_problems =
            simulation_problem_can_create($admin_simulation_actor);
    }
} catch (Throwable $exception) {
    error_log('[simulation_admin_access] ' . $exception->getMessage());
}

// 교사의 추가 진입은 관리자 첫 화면과 홈으로 제한한다.
$admin_simulation_entry_allowed = (
    $admin_can_manage_simulation_problems === true &&
    in_array(
        realpath($_SERVER['SCRIPT_FILENAME'] ?? ''),
        array(
            __DIR__ . '/index.php',
            __DIR__ . '/help.php',
        ),
        true
    )
);



// ============================================================
// 관리자 페이지 접근 권한
// 기존 admin-header.php의 권한 정책을 그대로 유지한다.
// ============================================================

$admin_page_access_allowed =
    isset($admin_page_access_allowed) &&
    $admin_page_access_allowed === true;

if (
    !oj_can_access_admin() &&
    !$admin_page_access_allowed &&
    !$admin_simulation_entry_allowed
) {

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
