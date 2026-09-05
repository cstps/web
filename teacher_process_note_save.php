<?php

require_once("./include/db_info.inc.php");
require_once("./include/my_func.inc.php");
require_once("./include/course_functions.inc.php");
require_once("./include/permission_functions.inc.php");

// ============================================================
// 로그인 확인
// ============================================================
if (!oj_is_logged_in()) {
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

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
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
// 관찰 메모 작성 권한 확인
//
// 허용:
// - administrator
// - 해당 Contest 관리자 m{cid}
// - 검증된 Course의 owner / teacher
//
// assistant는 조회만 가능
// ============================================================

$can_manage_teacher_note =
    oj_can_manage_contest($cid);


// ------------------------------------------------------------
// Contest 권한이 없으면 Course 권한 확인
// ------------------------------------------------------------

if (
    !$can_manage_teacher_note &&
    $course_id > 0
) {

    // 전달받은 Course에 해당 Contest와 학생이
    // 실제로 소속되어 있는지 동시에 확인한다.
    $course_relation_rows =
        pdo_query(
            "SELECT 1
               FROM course_contest cc

               INNER JOIN course_student cs
                  ON cs.course_id = cc.course_id
                 AND cs.user_id = ?

              WHERE cc.course_id = ?
                AND cc.contest_id = ?
                AND cc.status = 1

              LIMIT 1",
            $solution_user,
            $course_id,
            $cid
        );


    $has_valid_course_relation =
        (
            $course_relation_rows &&
            isset($course_relation_rows[0])
        );


    if (
        $has_valid_course_relation &&
        course_can_manage_student_records($course_id)
    ) {
        $can_manage_teacher_note = true;
    }
}


if (!$can_manage_teacher_note) {
    exit("이 관찰 메모를 작성할 권한이 없습니다.");
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
$redirect_url =
    "solution_process_view.php?sid=" .
    intval($sid);

if ($course_id > 0) {
    $redirect_url .=
        "&course_id=" .
        intval($course_id);
}

header("Location: ".$redirect_url);
exit;


?>