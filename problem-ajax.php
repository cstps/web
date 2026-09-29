<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

////////////////////////////Common head
        $cache_time=2;
        $OJ_CACHE_SHARE=false;
    require_once('./include/db_info.inc.php');
        require_once('./include/setlang.php');
        $view_title= "$MSG_STATUS";


require_once("./include/const.inc.php");



$pid = isset($_GET['pid'])
    ? (int)$_GET['pid']
    : 0;

if ($pid <= 0) {
    http_response_code(400);
    exit('문제 번호가 올바르지 않습니다.');
}

$problem_rows = pdo_query(
    'SELECT creator FROM problem WHERE problem_id = ? LIMIT 1',
    $pid
);

if ($problem_rows === false) {
    http_response_code(500);
    exit('문제 정보를 조회하지 못했습니다.');
}

if (!isset($problem_rows[0])) {
    http_response_code(404);
    exit('문제를 찾을 수 없습니다.');
}

$creator = trim((string)$problem_rows[0]['creator']);

// 표시 문구가 없는 기존 문제만 이전 권한 보유자 값을 사용한다.
if ($creator === '') {
    $grant_rows = pdo_query(
        "SELECT user_id
         FROM privilege
         WHERE rightstr = ?
           AND defunct = 'N'
         LIMIT 1",
        'p' . $pid
    );

    if ($grant_rows === false) {
        http_response_code(500);
        exit('출제자 정보를 조회하지 못했습니다.');
    }

    $creator = isset($grant_rows[0])
        ? (string)$grant_rows[0]['user_id']
        : (string)$MSG_IMPORTED;
}

echo htmlspecialchars(
    $creator,
    ENT_QUOTES,
    'UTF-8'
);
?>
