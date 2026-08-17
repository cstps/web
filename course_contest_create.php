<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "차시 생성";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id = $_SESSION[$OJ_NAME.'_user_id'];


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. 기본 입력값
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$lesson_no =
    isset($_POST['lesson_no'])
        ? intval($_POST['lesson_no'])
        : 0;

$source_contest_id =
    isset($_POST['source_contest_id'])
        ? intval($_POST['source_contest_id'])
        : 0;

$contest_title =
    isset($_POST['contest_title'])
        ? trim($_POST['contest_title'])
        : '';

$start_time_raw =
    isset($_POST['start_time'])
        ? trim($_POST['start_time'])
        : '';

$end_time_raw =
    isset($_POST['end_time'])
        ? trim($_POST['end_time'])
        : '';

$selected_problem_ids =
    isset($_POST['problem_ids']) &&
    is_array($_POST['problem_ids'])
        ? $_POST['problem_ids']
        : array();


// ============================================================
// 4. 기본값 검증
// ============================================================

if ($course_id <= 0) {

    $view_errors = "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($lesson_no <= 0) {

    $view_errors = "<h2>잘못된 차시 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($source_contest_id <= 0) {

    $view_errors = "<h2>잘못된 원본 대회 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    $contest_title === '' ||
    mb_strlen($contest_title, 'UTF-8') > 100
) {

    $view_errors =
        "<h2>차시 제목은 1자 이상 100자 이내로 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (empty($selected_problem_ids)) {

    $view_errors =
        "<h2>최소 한 개 이상의 문제를 선택해야 합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 5. 시작 / 종료 시간 검증
//
// datetime-local:
// 2026-08-17T10:30
// ============================================================

$start_timestamp =
    strtotime($start_time_raw);

$end_timestamp =
    strtotime($end_time_raw);


if (
    $start_timestamp === false ||
    $end_timestamp === false
) {

    $view_errors =
        "<h2>시작 시간 또는 종료 시간이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($end_timestamp <= $start_timestamp) {

    $view_errors =
        "<h2>종료 시간은 시작 시간보다 늦어야 합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$start_time =
    date('Y-m-d H:i:s', $start_timestamp);

$end_time =
    date('Y-m-d H:i:s', $end_timestamp);


// ============================================================
// 6. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        status
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);


if (
    !$course_rows ||
    !isset($course_rows[0]['course_id'])
) {

    $view_errors =
        "<h2>존재하지 않는 수업입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$view_course = $course_rows[0];


// ============================================================
// 7. Course 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 종료된 Course 확인
// ============================================================

if (intval($view_course['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업에는 새로운 차시를 추가할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 원본 Contest 확인
//
// Course Contest에서 가져올 설정:
// - codevisible
// - langmask
//
// 가져오지 않는 설정:
// - 시간
// - password
// - 참가자
// - description
// - exam_mode
// ============================================================

$source_rows = pdo_query(
    "SELECT
        contest_id,
        title,
        codevisible,
        langmask
     FROM contest
     WHERE contest_id = ?
     LIMIT 1",
    $source_contest_id
);


if (
    !$source_rows ||
    !isset($source_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>원본 대회가 존재하지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$source_contest = $source_rows[0];


// ============================================================
// 10. 원본 Contest 사용 권한 재확인
//
// 화면 단계에서 검사했더라도
// 실제 생성 처리에서도 반드시 다시 검사한다.
// ============================================================

$can_use_source_contest =
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator']) ||
    isset($_SESSION[$OJ_NAME.'_m'.$source_contest_id]);


if (!$can_use_source_contest) {

    $view_errors =
        "<h2>이 대회의 문제 구성을 가져올 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 11. 선택 문제 ID 정리
// ============================================================

$selected_problem_map = array();

foreach ($selected_problem_ids as $problem_id) {

    $problem_id = intval($problem_id);

    if ($problem_id > 0) {
        $selected_problem_map[$problem_id] = true;
    }
}


if (empty($selected_problem_map)) {

    $view_errors =
        "<h2>유효한 문제가 선택되지 않았습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 12. 원본 contest_problem 조회
//
// 클라이언트가 보낸 문제 번호/점수는 사용하지 않는다.
// 원본 Contest의 실제 데이터를 다시 조회한다.
// ============================================================

$source_problem_rows = pdo_query(
    "SELECT
        problem_id,
        num,
        score
     FROM contest_problem
     WHERE contest_id = ?
     ORDER BY
        num,
        problem_id",
    $source_contest_id
);


if (!is_array($source_problem_rows)) {
    $source_problem_rows = array();
}


$problems_to_copy = array();


foreach ($source_problem_rows as $problem) {

    $problem_id =
        intval($problem['problem_id']);


    if (
        isset(
            $selected_problem_map[$problem_id]
        )
    ) {

        $problems_to_copy[] = array(
            'problem_id' => $problem_id,

            'score' =>
                isset($problem['score']) &&
                $problem['score'] !== null
                    ? intval($problem['score'])
                    : 100
        );
    }
}


// ============================================================
// 13. 선택 문제 검증
//
// 전송된 문제 중 원본 Contest에 없는 문제가 하나라도 있으면
// 생성하지 않는다.
// ============================================================

if (
    count($problems_to_copy) !==
    count($selected_problem_map)
) {

    $view_errors =
        "<h2>선택한 문제 중 원본 대회에 포함되지 않은 문제가 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (empty($problems_to_copy)) {

    $view_errors =
        "<h2>가져올 문제가 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 14. 표시 순서 계산
//
// 기존 차시:
// 10, 20, 30 ...
//
// 다음:
// MAX + 10
// ============================================================

$sort_rows = pdo_query(
    "SELECT
        COALESCE(MAX(sort_order), 0) + 10 AS next_sort_order
     FROM course_contest
     WHERE course_id = ?",
    $course_id
);


$sort_order =
    isset($sort_rows[0]['next_sort_order'])
        ? intval($sort_rows[0]['next_sort_order'])
        : 10;


if ($sort_order <= 0) {
    $sort_order = 10;
}


// ============================================================
// 15. 새 Contest 기본 설정
// ============================================================

$codevisible =
    isset($source_contest['codevisible'])
        ? intval($source_contest['codevisible'])
        : 0;

$langmask =
    isset($source_contest['langmask'])
        ? intval($source_contest['langmask'])
        : 0;


// Course 전용 Contest는 기본 비공개
$private = 1;


// 첫 버전에서는 시험모드 사용하지 않음
$exam_mode = 0;


// Course 전용 설명은 나중에 별도 기능으로 확장
$description = '';


// Course 접근권한으로 관리할 예정이므로 password 사용하지 않음
$password = '';


// ============================================================
// 16. Contest 생성
// ============================================================

$new_contest_id = 0;


try {

    $sql =
        "INSERT INTO contest
        (
            title,
            start_time,
            end_time,
            codevisible,
            private,
            langmask,
            description,
            password,
            user_id,
            exam_mode
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";


    $new_contest_id = pdo_query(
        $sql,
        $contest_title,
        $start_time,
        $end_time,
        $codevisible,
        $private,
        $langmask,
        $description,
        $password,
        $user_id,
        $exam_mode
    );


    $new_contest_id =
        intval($new_contest_id);


    if ($new_contest_id <= 0) {

        throw new Exception(
            '새 Contest 생성에 실패했습니다.'
        );
    }


    // ========================================================
    // 17. 선택 문제 복사
    //
    // 새 Contest에서는 선택된 문제 순서대로
    // num = 0, 1, 2 ... 재부여
    // ========================================================

    $problem_num = 0;


    foreach ($problems_to_copy as $problem) {

        pdo_query(
            "INSERT INTO contest_problem
            (
                contest_id,
                problem_id,
                num,
                score
            )
            VALUES (?, ?, ?, ?)",
            $new_contest_id,
            intval($problem['problem_id']),
            $problem_num,
            intval($problem['score'])
        );


        $problem_num++;
    }


    // ========================================================
    // 18. 생성 교사에게 Contest 관리 권한 부여
    // ========================================================

    pdo_query(
        "INSERT INTO privilege
        (
            user_id,
            rightstr
        )
        VALUES (?, ?)",
        $user_id,
        "m".$new_contest_id
    );


    $_SESSION[
        $OJ_NAME.'_m'.$new_contest_id
    ] = true;

    // ========================================================
    // 19. Course 학생에게 Contest 참가 권한 부여
    // ========================================================

    $course_student_rows = pdo_query(
        "SELECT
            user_id
         FROM course_student
         WHERE course_id = ?
           AND status = 1
         ORDER BY user_id",
        $course_id
    );


    if (!is_array($course_student_rows)) {
        $course_student_rows = array();
    }


    foreach ($course_student_rows as $student) {

        if (
            !isset($student['user_id']) ||
            trim($student['user_id']) === ''
        ) {
            continue;
        }


        pdo_query(
            "INSERT INTO privilege
            (
                user_id,
                rightstr
            )
            VALUES (?, ?)",
            $student['user_id'],
            "c".$new_contest_id
        );
    }

    // ========================================================
    // 20. Course ↔ Contest 연결
    // ========================================================

    pdo_query(
        "INSERT INTO course_contest
        (
            course_id,
            contest_id,
            source_contest_id,
            lesson_no,
            sort_order,
            visible,
            created_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?, 1, ?
        )",
        $course_id,
        $new_contest_id,
        $source_contest_id,
        $lesson_no,
        $sort_order,
        $user_id
    );


}
catch (Exception $e) {

    // --------------------------------------------------------
    // 생성 도중 오류가 발생했다면
    // 가능한 범위에서 생성 데이터를 정리한다.
    // --------------------------------------------------------

    if ($new_contest_id > 0) {

        try {

            pdo_query(
                "DELETE FROM course_contest
                 WHERE contest_id = ?",
                $new_contest_id
            );

            pdo_query(
                "DELETE FROM privilege
                WHERE rightstr = ?
                    OR rightstr = ?",
                "m".$new_contest_id,
                "c".$new_contest_id
            );

            pdo_query(
                "DELETE FROM contest_problem
                 WHERE contest_id = ?",
                $new_contest_id
            );

            pdo_query(
                "DELETE FROM contest
                 WHERE contest_id = ?",
                $new_contest_id
            );

        }
        catch (Exception $cleanup_error) {

            // 정리 실패는 여기서 별도 출력하지 않음
        }
    }


    $view_errors =
        "<h2>차시 생성 중 오류가 발생했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 21. 생성 완료
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);