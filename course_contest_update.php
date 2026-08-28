<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');


// ============================================================
// 1. 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. POST 요청 확인
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $view_errors = "<h2>잘못된 요청입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 3. 입력값
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;

$contest_id =
    isset($_POST['contest_id'])
        ? intval($_POST['contest_id'])
        : 0;

$lesson_no =
    isset($_POST['lesson_no'])
        ? intval($_POST['lesson_no'])
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


$selected_languages =
    isset($_POST['lang']) &&
    is_array($_POST['lang'])
        ? $_POST['lang']
        : array();


// ============================================================
// 4. 기본값 검증
// ============================================================

if (
    $course_id <= 0 ||
    $contest_id <= 0
) {

    $view_errors =
        "<h2>잘못된 차시 정보입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($lesson_no <= 0) {

    $view_errors =
        "<h2>차시 번호는 1 이상이어야 합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}




// ============================================================
// 5. Course 존재 및 상태 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
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


if (intval($course_rows[0]['status']) !== 1) {

    $view_errors =
        "<h2>종료된 수업의 차시는 수정할 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 7. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {

    $view_errors =
        "<h2>이 수업의 차시를 수정할 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. Course ↔ Contest 연결 확인
// ============================================================

$link_rows = pdo_query(
    "SELECT
        id,
        contest_id,
        link_type
     FROM course_contest
     WHERE course_id = ?
       AND contest_id = ?
       AND status = 1
     LIMIT 1",
    $course_id,
    $contest_id
);


if (
    !$link_rows ||
    !isset($link_rows[0]['id'])
) {

    $view_errors =
        "<h2>이 수업에 등록되지 않은 차시입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$link_type =
    isset($link_rows[0]['link_type'])
        ? trim($link_rows[0]['link_type'])
        : 'created';


if (
    !in_array(
        $link_type,
        array('created', 'linked'),
        true
    )
) {

    $view_errors =
        "<h2>차시 연결 유형이 올바르지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 9. 실제 Contest 존재 확인
// ============================================================

$contest_rows = pdo_query(
    "SELECT contest_id
     FROM contest
     WHERE contest_id = ?
     LIMIT 1",
    $contest_id
);


if (
    !$contest_rows ||
    !isset($contest_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>연결된 대회가 존재하지 않습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// created Contest 입력값 검증
//
// linked는 기존 Contest의 제목과 시간을 변경하지 않는다.
// ============================================================

if ($link_type === 'created') {

    if (
        $contest_title === '' ||
        mb_strlen($contest_title, 'UTF-8') > 100
    ) {

        $view_errors =
            "<h2>차시 제목은 1자 이상 100자 이내로 입력하세요.</h2>";

        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }


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


    // ========================================================
    // Course 제출 가능 언어
    // ========================================================

    $course_language_specs = array(

        array(
            'aliases' => array('C++')
        ),

        array(
            'aliases' => array('Python3', 'Python')
        ),

        array(
            'aliases' => array('JavaScript')
        ),

        array(
            'aliases' => array('Java')
        )

    );


    $course_allowed_language_ids =
        array();


    foreach (
        $course_language_specs
        as $language_spec
    ) {

        foreach (
            $language_spec['aliases']
            as $language_alias
        ) {

            $language_id =
                array_search(
                    $language_alias,
                    $language_name,
                    true
                );


            if ($language_id !== false) {

                $course_allowed_language_ids[] =
                    intval($language_id);

                break;
            }
        }
    }


    // ========================================================
    // POST 언어 검증
    // ========================================================

    $selected_language_ids =
        array();


    foreach (
        $selected_languages
        as $language_id_raw
    ) {

        if (
            !is_scalar($language_id_raw) ||
            filter_var(
                $language_id_raw,
                FILTER_VALIDATE_INT
            ) === false
        ) {

            $view_errors =
                "<h2>제출 언어 정보가 올바르지 않습니다.</h2>";

            require(
                "template/".$OJ_TEMPLATE."/error.php"
            );
            exit(0);
        }


        $language_id =
            intval($language_id_raw);


        if (
            !in_array(
                $language_id,
                $course_allowed_language_ids,
                true
            )
        ) {

            $view_errors =
                "<h2>Course에서 사용할 수 없는 제출 언어입니다.</h2>";

            require(
                "template/".$OJ_TEMPLATE."/error.php"
            );
            exit(0);
        }


        $selected_language_ids[
            $language_id
        ] = true;
    }


    if (empty($selected_language_ids)) {

        $view_errors =
            "<h2>제출 가능 언어를 하나 이상 선택해야 합니다.</h2>";

        require(
            "template/".$OJ_TEMPLATE."/error.php"
        );
        exit(0);
    }


    // ========================================================
    // HUSTOJ langmask 계산
    // ========================================================

    $allowed_language_mask = 0;


    foreach (
        array_keys($selected_language_ids)
        as $language_id
    ) {

        $allowed_language_mask |=
            (1 << $language_id);
    }


    $lang_count =
        count($language_ext);


    $langmask =
        (
            (1 << $lang_count) - 1
        )
        &
        (~$allowed_language_mask);
}


// ============================================================
// 10. lesson_no 중복 확인
//
// 같은 Course 안에서는 동일한 차시 번호를 사용할 수 없다.
// 현재 수정 중인 Contest 자신은 중복 검사에서 제외한다.
// ============================================================

$duplicate_lesson_rows = pdo_query(
    "SELECT
        contest_id
     FROM course_contest
     WHERE course_id = ?
       AND lesson_no = ?
       AND contest_id <> ?
     LIMIT 1",
    $course_id,
    $lesson_no,
    $contest_id
);


if (
    $duplicate_lesson_rows &&
    isset($duplicate_lesson_rows[0]['contest_id'])
) {

    $view_errors =
        "<h2>".$lesson_no."차시는 이미 등록되어 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// Contest 기본 정보 수정
//
// Course에서 생성한 created Contest만 수정한다.
// linked Contest의 원본 정보는 변경하지 않는다.
// ============================================================

if ($link_type === 'created') {

    pdo_query(
        "UPDATE contest
         SET
            title = ?,
            start_time = ?,
            end_time = ?,
            langmask = ?
         WHERE contest_id = ?",
        $contest_title,
        $start_time,
        $end_time,
        $langmask,
        $contest_id
    );
}

// ============================================================
// 12. Course 차시 정보 수정
// ============================================================

pdo_query(
    "UPDATE course_contest
     SET
        lesson_no = ?
     WHERE course_id = ?
       AND contest_id = ?
       AND status = 1",
    $lesson_no,
    $course_id,
    $contest_id
);


// ============================================================
// 13. Course 화면으로 복귀
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);