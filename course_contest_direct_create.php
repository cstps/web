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
// 3. 입력값
// ============================================================

$course_id =
    isset($_POST['course_id'])
        ? intval($_POST['course_id'])
        : 0;


$lesson_no =
    isset($_POST['lesson_no'])
        ? intval($_POST['lesson_no'])
        : 0;


$contest_title =
    isset($_POST['contest_title'])
        ? trim($_POST['contest_title'])
        : '';


// 혹시 화면에서 lesson_title을 사용하고 있다면 호환
if (
    $contest_title === '' &&
    isset($_POST['lesson_title'])
) {

    $contest_title =
        trim($_POST['lesson_title']);
}


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


$score_input =
    isset($_POST['score']) &&
    is_array($_POST['score'])
        ? $_POST['score']
        : array();


$selected_languages =
    isset($_POST['lang']) &&
    is_array($_POST['lang'])
        ? $_POST['lang']
        : array();


// ============================================================
// 4. 기본값 검증
// ============================================================

if ($course_id <= 0) {

    $view_errors =
        "<h2>잘못된 수업 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


if ($lesson_no <= 0) {

    $view_errors =
        "<h2>차시 번호는 1 이상이어야 합니다.</h2>";

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
// 제출 가능 언어 검증
//
// Course 직접 생성 차시에서는:
// C++ / Python / JavaScript / Java만 허용
// ============================================================

$lang_count =
    count($language_ext);


// ------------------------------------------------------------
// Course에서 허용할 실제 언어 ID 찾기
// ------------------------------------------------------------

$course_language_specs = array(

    array(
        'label' => 'C++',
        'aliases' => array(
            'C++'
        )
    ),

    array(
        'label' => 'Python',
        'aliases' => array(
            'Python3',
            'Python 3',
            'Python'
        )
    ),

    array(
        'label' => 'JavaScript',
        'aliases' => array(
            'JavaScript',
            'Java Script',
            'Node.js'
        )
    ),

    array(
        'label' => 'Java',
        'aliases' => array(
            'Java'
        )
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


// ------------------------------------------------------------
// POST로 전달된 언어 검증
// ------------------------------------------------------------

$allowed_language_ids =
    array();

$allowed_language_map =
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


    // Course에서 허용한 4개 언어인지 확인
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


    // 중복값 방지
    if (
        isset(
            $allowed_language_map[
                $language_id
            ]
        )
    ) {
        continue;
    }


    $allowed_language_map[
        $language_id
    ] = true;

    $allowed_language_ids[] =
        $language_id;
}


if (empty($allowed_language_ids)) {

    $view_errors =
        "<h2>제출 가능 언어를 하나 이상 선택해야 합니다.</h2>";

    require(
        "template/".$OJ_TEMPLATE."/error.php"
    );

    exit(0);
}


// ------------------------------------------------------------
// HUSTOJ langmask 생성
//
// bit = 0 : 허용
// bit = 1 : 금지
// ------------------------------------------------------------

$allowed_language_mask = 0;


foreach (
    $allowed_language_ids
    as $language_id
) {

    $allowed_language_mask |=
        (1 << $language_id);
}


$all_language_mask =
    (1 << $lang_count) - 1;


$langmask =
    $all_language_mask &
    (~$allowed_language_mask);


// ============================================================
// 5. 시작 / 종료 시간 검증
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
    date(
        'Y-m-d H:i:s',
        $start_timestamp
    );


$end_time =
    date(
        'Y-m-d H:i:s',
        $end_timestamp
    );


// ============================================================
// 6. Course 존재 확인
// ============================================================

$course_rows = pdo_query(
    "SELECT
        course_id,
        course_name,
        status,
        created_by
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


$view_course =
    $course_rows[0];


// ============================================================
// 7. Course 접근 / 차시 관리 권한 확인
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
// 9. lesson_no 중복 확인
//
// 제거된 차시(status=0)도 복원 대상이므로
// 동일한 차시 번호로 새로 생성하지 않는다.
// ============================================================

$duplicate_lesson_rows = pdo_query(
    "SELECT
        contest_id
     FROM course_contest
     WHERE course_id = ?
       AND lesson_no = ?
     LIMIT 1",
    $course_id,
    $lesson_no
);


if (
    $duplicate_lesson_rows &&
    isset(
        $duplicate_lesson_rows[0]['contest_id']
    )
) {

    $view_errors =
        "<h2>".$lesson_no.
        "차시는 이미 등록되어 있습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 10. 선택 문제 ID 정리
//
// POST 배열 순서를 그대로 Contest 문제 순서로 사용한다.
//
// 예:
//
// problem_ids[]
// 1000
// 1005
// 1010
//
// → A = 1000
// → B = 1005
// → C = 1010
// ============================================================

$problem_ids = array();

$problem_id_map = array();


foreach ($selected_problem_ids as $problem_id) {

    $problem_id =
        intval($problem_id);


    if ($problem_id <= 0) {
        continue;
    }


    // 중복 문제 방지
    if (
        isset(
            $problem_id_map[$problem_id]
        )
    ) {
        continue;
    }


    $problem_id_map[$problem_id] =
        true;


    $problem_ids[] =
        $problem_id;
}


if (empty($problem_ids)) {

    $view_errors =
        "<h2>유효한 문제가 선택되지 않았습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 11. 선택한 문제 서버 재검증
//
// 정책:
//
// administrator
// → 모든 문제 사용 가능
//
// 문제 생성자(p{problem_id})
// → 공개/비공개 및 allow_reuse와 관계없이 사용 가능
//
// 다른 사용자
// → 공개 문제(defunct=N)이면서
//   allow_reuse=1인 문제만 사용 가능
//
// 화면에서 문제를 숨기는 것만으로 끝내지 않고
// 직접 POST 조작도 이 단계에서 차단한다.
// ============================================================

$problems_to_add =
    array();


$is_admin =
    isset(
        $_SESSION[$OJ_NAME . '_administrator']
    );


foreach ($problem_ids as $problem_id) {

    $problem_rows =
        pdo_query(
            "SELECT
                p.problem_id,
                p.title,
                p.defunct,
                p.allow_reuse,

                EXISTS
                (
                    SELECT 1
                    FROM privilege pr
                    WHERE pr.user_id = ?
                      AND pr.rightstr =
                          CONCAT(
                              'p',
                              p.problem_id
                          )
                      AND pr.defunct = 'N'
                ) AS is_owner

             FROM problem p

             WHERE p.problem_id = ?

             LIMIT 1",
            $user_id,
            $problem_id
        );


    // --------------------------------------------------------
    // 존재하지 않는 문제
    // --------------------------------------------------------

    if (
        !$problem_rows ||
        !isset(
            $problem_rows[0]['problem_id']
        )
    ) {

        $view_errors =
            "<h2>존재하지 않는 문제(" .
            intval($problem_id) .
            ")가 포함되어 있습니다.</h2>";

        require(
            "template/" .
            $OJ_TEMPLATE .
            "/error.php"
        );

        exit(0);
    }


    $problem =
        $problem_rows[0];


    $is_owner =
        intval(
            $problem['is_owner']
        ) === 1;


    $is_public =
        strtoupper(
            trim(
                $problem['defunct']
            )
        ) === 'N';


    $allow_reuse =
        intval(
            $problem['allow_reuse']
        ) === 1;


    // --------------------------------------------------------
    // 문제 사용 권한
    // --------------------------------------------------------

    $can_use_problem =
        $is_admin ||
        $is_owner ||
        (
            $is_public &&
            $allow_reuse
        );


    if (!$can_use_problem) {

        if (
            !$is_owner &&
            !$allow_reuse
        ) {

            $view_errors =
                "<h2>문제 " .
                intval($problem_id) .
                "번은 문제 생성자가 다른 대회에서의 " .
                "사용을 허용하지 않았습니다.</h2>";
        } else {

            $view_errors =
                "<h2>문제 " .
                intval($problem_id) .
                "번을 이 차시에 사용할 권한이 없습니다.</h2>";
        }


        require(
            "template/" .
            $OJ_TEMPLATE .
            "/error.php"
        );

        exit(0);
    }


    // --------------------------------------------------------
    // 점수
    // --------------------------------------------------------

    $score = 100;


    if (
        isset(
            $score_input[$problem_id]
        ) &&
        $score_input[$problem_id] !== '' &&
        is_numeric(
            $score_input[$problem_id]
        )
    ) {

        $score =
            intval(
                $score_input[$problem_id]
            );
    }


    if ($score < 0) {
        $score = 0;
    }


    $problems_to_add[] =
        array(
            'problem_id' =>
            $problem_id,

            'score' =>
            $score
        );
}


// ============================================================
// 12. 표시 순서 계산
// ============================================================

$sort_rows = pdo_query(
    "SELECT
        COALESCE(MAX(sort_order), 0) + 10
            AS next_sort_order
     FROM course_contest
     WHERE course_id = ?",
    $course_id
);


$sort_order =
    isset(
        $sort_rows[0]['next_sort_order']
    )
        ? intval(
            $sort_rows[0]['next_sort_order']
        )
        : 10;


if ($sort_order <= 0) {
    $sort_order = 10;
}


// ============================================================
// 13. 직접 생성 Contest 기본 설정
//
// source Contest가 없으므로:
//
// codevisible = 0
// langmask    = 위에서 선택 언어 기준으로 계산
// private     = 1
// exam_mode   = 0
// ============================================================

$codevisible = 0;

// $langmask는 제출 가능 언어 검증 단계에서 이미 계산됨

$private = 1;

$exam_mode = 0;

$description = '';

$password = '';


// ============================================================
// 14. Contest 생성 시작
// ============================================================

$new_contest_id = 0;


try {

    // ========================================================
    // 14-1. Contest 생성
    // ========================================================

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
        $exam_mode,
    );


    $new_contest_id =
        intval($new_contest_id);


    if ($new_contest_id <= 0) {

        throw new Exception(
            '새 Contest 생성에 실패했습니다.'
        );
    }


    // ========================================================
    // 14-2. contest_problem 생성
    //
    // 문제 원본 자체는 복사하지 않는다.
    // problem_id만 새 Contest에 연결한다.
    // ========================================================

    $problem_num = 0;


    foreach ($problems_to_add as $problem) {

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
            intval(
                $problem['problem_id']
            ),
            $problem_num,
            intval(
                $problem['score']
            )
        );


        $problem_num++;
    }


    // ========================================================
    // 14-3. 생성자에게 Contest 관리 권한
    // ========================================================

    pdo_query(
        "INSERT INTO privilege
        (
            user_id,
            rightstr
        )
        SELECT ?, ?
        WHERE NOT EXISTS
        (
            SELECT 1
            FROM privilege
            WHERE user_id = ?
              AND rightstr = ?
        )",
        $user_id,
        "m".$new_contest_id,
        $user_id,
        "m".$new_contest_id
    );


    $_SESSION[
        $OJ_NAME.'_m'.$new_contest_id
    ] = true;


    // ========================================================
    // 14-4. Course 책임교사 / 담당교사에게
    //       Contest 관리 권한
    //
    // assistant는 현재 m{cid} 권한에서 제외
    // ========================================================

    $course_teacher_rows = pdo_query(
        "SELECT
            user_id,
            role
         FROM course_teacher
         WHERE course_id = ?
           AND status = 1
           AND role IN (
               'owner',
               'teacher'
           )
         ORDER BY user_id",
        $course_id
    );


    if (!is_array($course_teacher_rows)) {

        $course_teacher_rows =
            array();
    }


    foreach (
        $course_teacher_rows
        as $teacher
    ) {

        if (
            !isset(
                $teacher['user_id']
            ) ||
            trim(
                $teacher['user_id']
            ) === ''
        ) {

            continue;
        }


        $teacher_user_id =
            trim(
                $teacher['user_id']
            );


        // 생성자는 이미 처리
        if (
            $teacher_user_id ===
            $user_id
        ) {

            continue;
        }


        pdo_query(
            "INSERT INTO privilege
            (
                user_id,
                rightstr
            )
            SELECT ?, ?
            WHERE NOT EXISTS
            (
                SELECT 1
                FROM privilege
                WHERE user_id = ?
                  AND rightstr = ?
            )",
            $teacher_user_id,
            "m".$new_contest_id,
            $teacher_user_id,
            "m".$new_contest_id
        );
    }


    // ========================================================
    // 14-5. 활성 Course 학생에게 Contest 참가 권한
    //
    // 차시는 visible=0으로 생성되므로
    // 권한이 있어도 현재 contest_can_access() 정책에서
    // 학생 직접 접근은 차단된다.
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

        $course_student_rows =
            array();
    }


    foreach (
        $course_student_rows
        as $student
    ) {

        if (
            !isset(
                $student['user_id']
            ) ||
            trim(
                $student['user_id']
            ) === ''
        ) {

            continue;
        }


        $student_user_id =
            trim(
                $student['user_id']
            );


        pdo_query(
            "INSERT INTO privilege
            (
                user_id,
                rightstr
            )
            SELECT ?, ?
            WHERE NOT EXISTS
            (
                SELECT 1
                FROM privilege
                WHERE user_id = ?
                  AND rightstr = ?
            )",
            $student_user_id,
            "c".$new_contest_id,
            $student_user_id,
            "c".$new_contest_id
        );
    }


    // ========================================================
    // 14-6. Course ↔ Contest 연결
    //
    // 직접 문제를 선택해서 만든 차시이므로:
    //
    // source_contest_id = NULL
    // link_type         = created
    // visible           = 0
    // status            = 기본값 사용
    // ========================================================

    pdo_query(
        "INSERT INTO course_contest
        (
            course_id,
            contest_id,
            source_contest_id,
            link_type,
            lesson_no,
            sort_order,
            visible,
            created_by
        )
        VALUES
        (
            ?,
            ?,
            NULL,
            'created',
            ?,
            ?,
            0,
            ?
        )",
        $course_id,
        $new_contest_id,
        $lesson_no,
        $sort_order,
        $user_id
    );

}
catch (Exception $e) {

    // ========================================================
    // 15. 생성 실패 시 정리
    //
    // 중간 생성물 때문에
    // 불완전한 Contest가 남지 않도록 한다.
    // ========================================================

    if ($new_contest_id > 0) {

        try {

            // Course 관계
            pdo_query(
                "DELETE FROM course_contest
                 WHERE contest_id = ?",
                $new_contest_id
            );


            // 관리자 / 참가 권한
            pdo_query(
                "DELETE FROM privilege
                 WHERE rightstr = ?
                    OR rightstr = ?",
                "m".$new_contest_id,
                "c".$new_contest_id
            );


            // 문제 연결
            pdo_query(
                "DELETE FROM contest_problem
                 WHERE contest_id = ?",
                $new_contest_id
            );


            // Contest
            pdo_query(
                "DELETE FROM contest
                 WHERE contest_id = ?",
                $new_contest_id
            );

        }
        catch (Exception $cleanup_error) {

            // cleanup 실패는
            // 사용자 화면에 별도 노출하지 않는다.
        }
    }


    $view_errors =
        "<h2>차시 생성 중 오류가 발생했습니다.</h2>";

    require(
        "template/".$OJ_TEMPLATE."/error.php"
    );

    exit(0);
}


// ============================================================
// 16. 생성 완료
// ============================================================

header(
    "Location: course_view.php?course_id=".$course_id
);

exit(0);