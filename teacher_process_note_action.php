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

$current_user =
    $_SESSION[$OJ_NAME . '_user_id'];


// ============================================================
// POST 보안키 확인
// ============================================================

require_once("./include/check_post_key.php");


// ============================================================
// 전달값
// ============================================================

$action =
    isset($_POST['action'])
    ? trim($_POST['action'])
    : "";

$note_id =
    isset($_POST['note_id'])
    ? intval($_POST['note_id'])
    : 0;

$sid =
    isset($_POST['sid'])
    ? intval($_POST['sid'])
    : 0;

$course_id =
    isset($_POST['course_id'])
    ? intval($_POST['course_id'])
    : 0;


if (
    $note_id <= 0 ||
    $sid <= 0 ||
    (
        $action !== "update" &&
        $action !== "delete"
    )
) {
    exit("잘못된 요청입니다.");
}


// ============================================================
// 관찰 메모 조회
// ============================================================

$note_result =
    pdo_query(
        "SELECT
            note_id,
            contest_id,
            user_id,
            problem_id,
            teacher_id,
            note_text
         FROM teacher_process_note
         WHERE note_id=?
         LIMIT 1",
        $note_id
    );


if (
    !$note_result ||
    count($note_result) == 0
) {
    exit("존재하지 않는 관찰 메모입니다.");
}


$note =
    $note_result[0];


$contest_id =
    intval(
        $note['contest_id']
    );

$teacher_id =
    trim(
        $note['teacher_id']
    );


// ============================================================
// 대회 접근 권한 확인
//
// administrator
// 또는 해당 대회의 m{cid}
// ============================================================



// ============================================================
// 수정/삭제 권한
//
// 일반 교사:
// 본인이 작성한 메모만 가능
//
// administrator:
// 모든 메모 가능
// ============================================================



// ============================================================
// SID가 같은 학생/문제/대회인지 검증
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



$solution =
    $solution_result[0];


if (
    intval($solution['contest_id']) !==
    intval($note['contest_id']) ||

    intval($solution['problem_id']) !==
    intval($note['problem_id']) ||

    strcasecmp(
        trim($solution['user_id']),
        trim($note['user_id'])
    ) !== 0
) {
    exit("관찰 메모와 제출 정보가 일치하지 않습니다.");
}

// ============================================================
// 관찰 메모 관리 권한 확인
//
// 허용:
// - administrator
// - 해당 Contest 관리자 m{cid}
// - 검증된 Course의 owner / teacher
//
// assistant는 수정·삭제 불가능
// ============================================================

$is_admin =
    oj_is_admin();

$can_manage_teacher_note =
    (
        $is_admin ||
        oj_can_manage_contest($contest_id)
    );


// ------------------------------------------------------------
// Contest 권한이 없으면 Course 권한 확인
// ------------------------------------------------------------

if (
    !$can_manage_teacher_note &&
    $course_id > 0
) {

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
            trim($note['user_id']),
            $course_id,
            $contest_id
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
    exit("이 관찰 메모를 수정하거나 삭제할 권한이 없습니다.");
}




$is_note_owner =
    (
        strcasecmp(
            $current_user,
            $teacher_id
        ) === 0
    );


if (
    !$is_admin &&
    !$is_note_owner
) {
    exit("본인이 작성한 관찰 메모만 수정하거나 삭제할 수 있습니다.");
}



// ============================================================
// 수정
// ============================================================

if ($action === "update") {

    $note_text =
        isset($_POST['note_text'])
        ? trim($_POST['note_text'])
        : "";


    if ($note_text === "") {
        exit("관찰 메모 내용을 입력하세요.");
    }


    if (
        function_exists("mb_strlen") &&
        mb_strlen(
            $note_text,
            "UTF-8"
        ) > 2000
    ) {
        exit("관찰 메모가 너무 깁니다.");
    }


    $update_result =
        pdo_query(
            "UPDATE teacher_process_note
         SET
            note_text=?,
            updated_at=NOW()
         WHERE note_id=?",
            $note_text,
            $note_id
        );

    if (
        $update_result === false ||
        $update_result === null
    ) {
        exit("관찰 메모 수정 중 오류가 발생했습니다.");
    }
}


// ============================================================
// 삭제
// ============================================================

else if ($action === "delete") {

    $delete_result =
        pdo_query(
            "DELETE FROM teacher_process_note
             WHERE note_id=?",
            $note_id
        );

    if (
        $delete_result === false ||
        $delete_result === null
    ) {
        exit("관찰 메모 삭제 중 오류가 발생했습니다.");
    }
}


// ============================================================
// 원래 문제 해결 과정 화면으로 복귀
// ============================================================

$redirect_url =
    "solution_process_view.php?sid=" .
    intval($sid);

if ($course_id > 0) {
    $redirect_url .=
        "&course_id=" .
        intval($course_id);
}

header("Location: " . $redirect_url);
exit;
