<?php

$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = '그림 문제 목록';
$show_title = $view_title;

$view_problem_rows = array();
$view_problem_total = 0;
$view_problem_pages = 1;
$view_problem_page = 1;
$view_problem_scope = 'public';
$view_problem_search = '';

function drawproblemset_error($message, $status = 400)
{
    global $view_errors, $OJ_TEMPLATE;

    http_response_code($status);

    $view_errors =
        '<h2>' .
        htmlspecialchars(
            $message,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) .
        '</h2>';

    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}



try {
    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    $user_id = (string)($_SESSION[$OJ_NAME . '_user_id'] ?? '');

    $scope = $_GET['scope'] ?? 'public';
    $search = $_GET['q'] ?? '';
    $page = $_GET['page'] ?? '1';

    if (
        !is_string($scope) ||
        !in_array($scope, array('public', 'mine'), true) ||
        !is_string($search) ||
        strlen($search) > 400 ||
        !is_string($page) ||
        !preg_match('/^[1-9][0-9]{0,5}$/D', $page)
    ) {
        drawproblemset_error('검색 조건이 올바르지 않습니다.');
    }

    // 비로그인 사용자는 공개 문제 목록만 조회한다.
    if ($user_id === '') {
        $scope = 'public';
    }

    $view_problem_scope = $scope;
    $view_problem_search = trim($search);
    $view_problem_page = (int)$page;

    $conditions = array('p.status = 1');
    $arguments = array();

    if ($scope === 'mine') {
        $conditions[] = 'p.owner_user_id = ?';
        $arguments[] = $user_id;
    } else {
        // 학생 공개 여부와 교사 공유 여부는 별도로 관리한다.
        $conditions[] = 'p.published = 1';
    }

    if ($view_problem_search !== '') {
        $conditions[] = 'v.title LIKE ?';
        $arguments[] = '%' . $view_problem_search . '%';
    }

    // 목록 화면에서는 정답 코드와 채점 설정을 조회하지 않는다.
    $from_sql = "
        FROM simulation_problem p
        INNER JOIN simulation_problem_version v
          ON v.version_id = (
              SELECT v2.version_id
              FROM simulation_problem_version v2
              WHERE v2.problem_id = p.problem_id
              ORDER BY v2.version_no DESC
              LIMIT 1
          )
        WHERE " . implode(' AND ', $conditions);

    $stmt = $dbh->prepare('SELECT COUNT(*) ' . $from_sql);

    if (!$stmt || !$stmt->execute($arguments)) {
        throw new RuntimeException('문제 수 조회 실패');
    }

    $view_problem_total = (int)$stmt->fetchColumn();
    $stmt->closeCursor();

    $view_problem_pages = max(
        1,
        (int)ceil($view_problem_total / 20)
    );

    $view_problem_page = min(
        $view_problem_page,
        $view_problem_pages
    );

    $offset = ($view_problem_page - 1) * 20;

    $stmt = $dbh->prepare(
        "SELECT
            p.problem_id,
            p.owner_user_id,
            p.sharing,
            v.version_id,
            v.version_no,
            v.title,
            v.simulation_mode,
            v.created_at
         " . $from_sql . "
         ORDER BY p.problem_id DESC
         LIMIT 20 OFFSET " . $offset
    );

    if (!$stmt || !$stmt->execute($arguments)) {
        throw new RuntimeException('문제 목록 조회 실패');
    }

    $view_problem_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

} catch (Throwable $e) {
    error_log('[drawproblemset] ' . $e->getMessage());

    drawproblemset_error(
        '그림 문제 목록을 불러오지 못했습니다.',
        500
    );
}

require('template/' . $OJ_TEMPLATE . '/drawproblemset.php');

if (file_exists('./include/cache_end.php')) {
    require_once('./include/cache_end.php');
}
