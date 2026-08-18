<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = "수업 관리";


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
// 2. 관리자 여부
// ============================================================

$is_admin =
    isset($_SESSION[$OJ_NAME.'_administrator']);


// ============================================================
// Course 생성 권한
// ============================================================

$view_can_create_course =
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator']);

    
// ============================================================
// 3. 접근 가능한 Course 목록 조회
// ============================================================

if ($is_admin) {

    // --------------------------------------------------------
    // administrator
    // - 모든 Course 조회
    // --------------------------------------------------------

    $sql = "
        SELECT
            c.course_id,
            c.course_name,
            c.school,
            c.school_year,
            c.semester,
            c.description,
            c.status,
            c.created_by,
            c.created_at,
            c.updated_at,

            'administrator' AS course_role,

            (
                SELECT COUNT(*)
                FROM course_student cs
                WHERE cs.course_id = c.course_id
                  AND cs.status = 1
            ) AS student_count,

            (
                SELECT COUNT(*)
                FROM course_contest cc
                WHERE cc.course_id = c.course_id
                  AND cc.status = 1
            ) AS contest_count

        FROM course c

        ORDER BY
            c.status DESC,
            c.school_year DESC,
            c.semester DESC,
            c.course_id DESC
    ";

    $view_courses = pdo_query($sql);

} else {

    // --------------------------------------------------------
    // 일반 교사
    // - course_teacher에 현재 등록된 Course만 조회
    // --------------------------------------------------------

    $sql = "
        SELECT
            c.course_id,
            c.course_name,
            c.school,
            c.school_year,
            c.semester,
            c.description,
            c.status,
            c.created_by,
            c.created_at,
            c.updated_at,

            ct.role AS course_role,

            (
                SELECT COUNT(*)
                FROM course_student cs
                WHERE cs.course_id = c.course_id
                  AND cs.status = 1
            ) AS student_count,

            (
                SELECT COUNT(*)
                FROM course_contest cc
                WHERE cc.course_id = c.course_id
                  AND cc.status = 1
            ) AS contest_count

        FROM course c

        INNER JOIN course_teacher ct
            ON ct.course_id = c.course_id
           AND ct.user_id = ?
           AND ct.status = 1

        ORDER BY
            c.status DESC,
            c.school_year DESC,
            c.semester DESC,
            c.course_id DESC
    ";

    $view_courses = pdo_query(
        $sql,
        $user_id
    );
}

// ============================================================
// 4. 결과 배열 보정
// ============================================================

if (!is_array($view_courses)) {
    $view_courses = array();
}


// ============================================================
// 5. 화면 출력
// ============================================================

require("template/".$OJ_TEMPLATE."/course_list.php");