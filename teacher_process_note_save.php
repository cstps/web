<?php

require_once("./include/db_info.inc.php");
require_once("./include/my_func.inc.php");


// ============================================================
// 로그인 확인
// ============================================================

if (
    !isset(
        $_SESSION[
            $OJ_NAME.'_user_id'
        ]
    )
) {

    exit("로그인이 필요합니다.");
}


// ============================================================
// POST 값
// ============================================================

$cid =
    isset($_POST['cid'])
        ? intval($_POST['cid'])
        : 0;

$user_id =
    isset($_POST['user_id'])
        ? trim($_POST['user_id'])
        : "";

$problem_id =
    isset($_POST['problem_id'])
        ? intval($_POST['problem_id'])
        : 0;

$sid =
    isset($_POST['sid'])
        ? intval($_POST['sid'])
        : 0;

$note_text =
    isset($_POST['note_text'])
        ? trim($_POST['note_text'])
        : "";


if (
    $cid <= 0 ||
    $user_id === "" ||
    $problem_id <= 0 ||
    $sid <= 0 ||
    $note_text === ""
) {

    exit("잘못된 요청입니다.");
}


// ============================================================
// HUSTOJ 기존 POST 보안키 검사
// ============================================================

require_once("./include/check_post_key.php");


// ============================================================
// 권한 확인
//
// administrator 또는 해당 대회의 m{cid}
// ============================================================

$is_admin =
    isset(
        $_SESSION[
            $OJ_NAME.'_administrator'
        ]
    );

$is_contest_manager =
    isset(
        $_SESSION[
            $OJ_NAME.'_m'.$cid
        ]
    );


if (
    !$is_admin &&
    !$is_contest_manager
) {

    exit("이 메모를 작성할 권한이 없습니다.");
}


// ============================================================
// SID와 전달 정보가 실제로 일치하는지 검증
//
// URL/POST 조작 방지
// ============================================================

$solution_result =
    pdo_query(
        "SELECT
            user_id,
            problem_id,
            contest_id
         FROM solution
         WHERE solution_id=?
         LIMIT 1",
        $sid
    );


if (
    !$solution_result ||
    count($solution_result) == 0
) {

    exit("존재하지 않는 제출입니다.");
}


$solution_user =
    isset($solution_result[0]['user_id'])
        ? trim($solution_result[0]['user_id'])
        : trim($solution_result[0][0]);

$solution_problem =
    isset($solution_result[0]['problem_id'])
        ? intval($solution_result[0]['problem_id'])
        : intval($solution_result[0][1]);

$solution_contest =
    isset($solution_result[0]['contest_id'])
        ? intval($solution_result[0]['contest_id'])
        : intval($solution_result[0][2]);


if (
    strcasecmp(
        $solution_user,
        $user_id
    ) !== 0 ||
    $solution_problem !== $problem_id ||
    $solution_contest !== $cid
) {

    exit("제출 정보가 일치하지 않습니다.");
}


// ============================================================
// 메모 길이 제한
// ============================================================

if (
    function_exists('mb_strlen') &&
    mb_strlen(
        $note_text,
        'UTF-8'
    ) > 2000
) {

    exit("관찰 메모가 너무 깁니다.");
}


// ============================================================
// 저장
// ============================================================

$teacher_id =
    $_SESSION[
        $OJ_NAME.'_user_id'
    ];


pdo_query(
    "INSERT INTO teacher_process_note
        (
            contest_id,
            user_id,
            problem_id,
            teacher_id,
            note_text,
            created_at
        )
     VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW()
        )",
    $cid,
    $user_id,
    $problem_id,
    $teacher_id,
    $note_text
);


// ============================================================
// 원래 과정 화면으로 복귀
// ============================================================

header(
    "Location: solution_process_view.php?sid=".
    intval($sid)
);

exit;
?>