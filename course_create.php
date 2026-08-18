<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = "수업 생성";


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$user_id =
    $_SESSION[$OJ_NAME.'_user_id'];


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors =
        "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. Course 생성 권한 확인
// ============================================================

$can_create_course =
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator']);


if (!$can_create_course) {

    $view_errors =
        "<h2>수업을 생성할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 4. 입력값
// ============================================================

$course_name =
    isset($_POST['course_name'])
        ? trim($_POST['course_name'])
        : '';

$school =
    isset($_POST['school'])
        ? trim($_POST['school'])
        : '';

$school_year =
    isset($_POST['school_year'])
        ? intval($_POST['school_year'])
        : 0;

$semester =
    isset($_POST['semester'])
        ? intval($_POST['semester'])
        : 0;

$description =
    isset($_POST['description'])
        ? trim($_POST['description'])
        : '';


// ============================================================
// 5. 기본값 검증
// ============================================================

if (
    $course_name === '' ||
    mb_strlen($course_name, 'UTF-8') > 100
) {

    $view_errors =
        "<h2>수업명은 1자 이상 100자 이내로 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (mb_strlen($school, 'UTF-8') > 100) {

    $view_errors =
        "<h2>학교명은 100자 이내로 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    $school_year < 2000 ||
    $school_year > 2100
) {

    $view_errors =
        "<h2>학년도가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (
    !in_array(
        $semester,
        array(1, 2),
        true
    )
) {

    $view_errors =
        "<h2>학기 정보가 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if (mb_strlen($description, 'UTF-8') > 1000) {

    $view_errors =
        "<h2>수업 설명은 1000자 이내로 입력하세요.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 6. Course 생성
// ============================================================

$new_course_id = 0;


try {

    $new_course_id = pdo_query(
        "INSERT INTO course
        (
            course_name,
            school,
            school_year,
            semester,
            description,
            status,
            created_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?, 1, ?
        )",
        $course_name,
        $school,
        $school_year,
        $semester,
        $description,
        $user_id
    );


    $new_course_id =
        intval($new_course_id);


    if ($new_course_id <= 0) {

        throw new Exception(
            'Course 생성에 실패했습니다.'
        );
    }


    // ========================================================
    // 7. 생성자를 책임교사(owner)로 등록
    // ========================================================

    pdo_query(
        "INSERT INTO course_teacher
        (
            course_id,
            user_id,
            role,
            status
        )
        VALUES
        (
            ?, ?, 'owner', 1
        )",
        $new_course_id,
        $user_id
    );

}
catch (Exception $e) {

    // --------------------------------------------------------
    // owner 등록 중 실패한 경우
    // 생성된 Course도 가능한 범위에서 정리한다.
    // --------------------------------------------------------

    if ($new_course_id > 0) {

        try {

            pdo_query(
                "DELETE FROM course_teacher
                 WHERE course_id = ?",
                $new_course_id
            );


            pdo_query(
                "DELETE FROM course
                 WHERE course_id = ?",
                $new_course_id
            );

        }
        catch (Exception $cleanup_error) {

            // 정리 실패는 별도로 출력하지 않음
        }
    }


    $view_errors =
        "<h2>수업 생성 중 오류가 발생했습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 생성 완료
// ============================================================

header(
    "Location: course_view.php?course_id=".$new_course_id
);

exit(0);