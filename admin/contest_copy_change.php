<?php

require_once("../include/db_info.inc.php");
require_once("../include/permission_functions.inc.php");


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!oj_is_logged_in()) {

    http_response_code(403);
    echo "로그인이 필요합니다.";
    exit;
}


// ============================================================
// 2. CSRF 검증
// ============================================================

require_once("../include/check_post_key.php");


// ============================================================
// 3. 요청값 확인
// ============================================================

$cid =
    isset($_POST['cid'])
    ? intval($_POST['cid'])
    : 0;


if ($cid <= 0) {

    http_response_code(400);
    echo "잘못된 대회 번호입니다.";
    exit;
}


// ============================================================
// 4. Contest 존재 확인
// ============================================================

$contest_rows =
    pdo_query(
        "SELECT
            contest_id,
            allow_copy
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );


if (
    !$contest_rows ||
    !isset($contest_rows[0]['contest_id'])
) {

    http_response_code(404);
    echo "대회를 찾을 수 없습니다.";
    exit;
}


// ============================================================
// 5. 변경 권한 확인
//
// contest_edit.php와 동일하게 허용:
// - administrator
// - 해당 Contest의 활성 m{cid}
// ============================================================

$can_change =
    oj_is_admin();


if (!$can_change) {

    $current_user_id =
        trim(
            (string)$_SESSION[$OJ_NAME . '_user_id']
        );


    // 세션 갱신 전 새로 부여된 m{cid}도 반영하기 위해
    // privilege를 서버에서 다시 확인한다.
    $manager_rows =
        pdo_query(
            "SELECT 1
             FROM privilege
             WHERE user_id=?
               AND rightstr=?
               AND valuestr='true'
               AND defunct='N'
             LIMIT 1",
            $current_user_id,
            'm' . $cid
        );


    $can_change = (
        $manager_rows &&
        isset($manager_rows[0])
    );
}


if (!$can_change) {

    http_response_code(403);
    echo "이 대회의 복사 정책을 변경할 권한이 없습니다.";
    exit;
}


// ============================================================
// 6. allow_copy 토글
//
// 1: 다른 사용자의 복사 허용
// 0: 다른 사용자의 복사 금지
// ============================================================

$current_allow_copy =
    intval(
        $contest_rows[0]['allow_copy']
    );

$new_allow_copy =
    $current_allow_copy === 1
    ? 0
    : 1;


pdo_query(
    "UPDATE contest
     SET allow_copy=?
     WHERE contest_id=?",
    $new_allow_copy,
    $cid
);


// ============================================================
// 7. 목록으로 이동
// ============================================================



// header(
//     "Location: contest_list.php"
// );

// exit;

?>

<script>
    history.go(-1);
</script>