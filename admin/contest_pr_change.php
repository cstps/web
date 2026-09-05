<?php

require_once("admin-header.php");
require_once("../include/check_get_key.php");
require_once("../include/permission_functions.inc.php");


// ============================================================
// 1. Contest 번호 확인
// ============================================================

$cid =
    isset($_GET['cid'])
    ? intval($_GET['cid'])
    : 0;


if ($cid <= 0) {

    http_response_code(400);
    echo "잘못된 대회 번호입니다.";
    exit;
}


// ============================================================
// 2. 권한 확인
//
// 허용:
// - administrator
// - 해당 Contest의 m{cid}
// ============================================================

if (
    !oj_can_manage_contest(
        $cid
    )
) {

    http_response_code(403);
    echo "이 대회의 공개 상태를 변경할 권한이 없습니다.";
    exit;
}


// ============================================================
// 3. 현재 공개 상태 확인
// ============================================================

$result =
    pdo_query(
        "SELECT private
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );


if (
    !$result ||
    !isset($result[0])
) {

    http_response_code(404);
    echo "대회를 찾을 수 없습니다.";
    exit;
}


$current_private =
    isset($result[0]['private'])
    ? intval($result[0]['private'])
    : intval($result[0][0]);


// ============================================================
// 4. 공개 상태 전환
//
// private=0 : 공개
// private=1 : 비공개
// ============================================================

$new_private =
    $current_private === 0
    ? 1
    : 0;


pdo_query(
    "UPDATE contest
     SET private=?
     WHERE contest_id=?",
    $new_private,
    $cid
);

?>


<script>
    history.go(-1);
</script>