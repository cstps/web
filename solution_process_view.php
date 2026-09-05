<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
// Course 권한 확인용
require_once('./include/course_functions.inc.php');
require_once('./include/permission_functions.inc.php');

$view_title = "문제 해결 과정";

// ============================================================
// 학생 코드 변경 비교 함수
//
// 이전 제출 코드와 현재 제출 코드를 줄 단위로 비교한다.
// LCS(Longest Common Subsequence)를 이용한다.
//
// 학생 코드가 지나치게 큰 경우 서버 부하를 막기 위해
// 최대 500줄까지 상세 비교한다.
// ============================================================

function build_source_diff($old_source, $new_source) {

    // 줄바꿈 통일
    $old_source = str_replace(
        array("\r\n", "\r"),
        "\n",
        (string)$old_source
    );

    $new_source = str_replace(
        array("\r\n", "\r"),
        "\n",
        (string)$new_source
    );


    $old_lines = explode("\n", $old_source);
    $new_lines = explode("\n", $new_source);


    $old_count = count($old_lines);
    $new_count = count($new_lines);


    // --------------------------------------------------------
    // 너무 큰 코드는 상세 LCS 비교 생략
    // --------------------------------------------------------

    $max_diff_lines = 500;

    if (
        $old_count > $max_diff_lines ||
        $new_count > $max_diff_lines
    ) {

        return array(
            'added'       => 0,
            'deleted'     => 0,
            'changed'     => ($old_source !== $new_source),
            'too_large'   => true,
            'diff_lines'  => array()
        );
    }


    // --------------------------------------------------------
    // LCS 테이블
    // --------------------------------------------------------

    $dp = array();

    for ($i = 0; $i <= $old_count; $i++) {

        $dp[$i] =
            array_fill(
                0,
                $new_count + 1,
                0
            );
    }


    for ($i = $old_count - 1; $i >= 0; $i--) {

        for ($j = $new_count - 1; $j >= 0; $j--) {

            if ($old_lines[$i] === $new_lines[$j]) {

                $dp[$i][$j] =
                    $dp[$i + 1][$j + 1] + 1;

            }
            else {

                $dp[$i][$j] =
                    max(
                        $dp[$i + 1][$j],
                        $dp[$i][$j + 1]
                    );
            }
        }
    }


    // --------------------------------------------------------
    // 실제 Diff 생성
    //
    // equal  : 동일한 줄
    // delete : 이전 코드에서 삭제
    // add    : 새 코드에서 추가
    // --------------------------------------------------------

    $diff_lines = array();

    $added = 0;
    $deleted = 0;

    $i = 0;
    $j = 0;


    while (
        $i < $old_count &&
        $j < $new_count
    ) {

        if ($old_lines[$i] === $new_lines[$j]) {

            $diff_lines[] = array(
                'type' => 'equal',
                'text' => $old_lines[$i]
            );

            $i++;
            $j++;

        }
        else if (
            $dp[$i + 1][$j] >=
            $dp[$i][$j + 1]
        ) {

            $diff_lines[] = array(
                'type' => 'delete',
                'text' => $old_lines[$i]
            );

            $deleted++;

            $i++;

        }
        else {

            $diff_lines[] = array(
                'type' => 'add',
                'text' => $new_lines[$j]
            );

            $added++;

            $j++;
        }
    }


    while ($i < $old_count) {

        $diff_lines[] = array(
            'type' => 'delete',
            'text' => $old_lines[$i]
        );

        $deleted++;

        $i++;
    }


    while ($j < $new_count) {

        $diff_lines[] = array(
            'type' => 'add',
            'text' => $new_lines[$j]
        );

        $added++;

        $j++;
    }


    return array(
        'added'      => $added,
        'deleted'    => $deleted,
        'changed'    => ($added > 0 || $deleted > 0),
        'too_large'  => false,
        'diff_lines' => $diff_lines
    );
}

// ============================================================
// 1. 로그인 확인
// ============================================================

if (!oj_is_logged_in()) {

    $view_errors = "<h2>로그인이 필요합니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 2. sid 확인
// ============================================================

if (!isset($_GET['sid']) || intval($_GET['sid']) <= 0) {

    $view_errors = "<h2>잘못된 제출 번호입니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$sid = intval($_GET['sid']);


// ============================================================
// 3. 현재 사용자 / 공통 권한
// ============================================================

$current_user = isset($_SESSION[$OJ_NAME.'_'.'user_id'])
    ? trim((string)$_SESSION[$OJ_NAME.'_'.'user_id'])
    : "";

$is_admin =
    oj_is_admin();

$is_source_browser =
    oj_has_global_privilege('source_browser');


// ============================================================
// 4. 기준 제출 조회
//
// URL로 전달받은 sid가 실제 제출인지 먼저 확인
// ============================================================

$sql = "SELECT
        s.solution_id,
        s.user_id,
        s.problem_id,
        s.contest_id,
        s.result,
        s.language,
        s.in_date,
        u.nick AS user_nick
    FROM solution s
    LEFT JOIN users u
      ON u.user_id = s.user_id
    WHERE s.solution_id=?
    LIMIT 1";

$result = pdo_query($sql, $sid);



if (!$result || count($result) == 0) {

    $view_errors = "<h2>해당 제출을 찾을 수 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


$solution = $result[0];

$solution_user_id = isset($solution['user_id'])
    ? trim((string)$solution['user_id'])
    : "";

$solution_nick = isset($solution['user_nick'])
    ? trim((string)$solution['user_nick'])
    : "";

$problem_id = intval($solution['problem_id']);
$contest_id = intval($solution['contest_id']);


// ============================================================
// 5. 본인 여부
// ============================================================

$is_owner = (
    $current_user !== "" &&
    $solution_user_id !== "" &&
    strcasecmp($current_user, $solution_user_id) === 0
);


// ============================================================
// 6. 해당 대회 관리자 여부
//
// m2976 같은 해당 대회 관리자만 인정
// contest_creator 자체는 과정 열람 권한이 아님
// ============================================================

$is_contest_manager =
    (
        $contest_id > 0 &&
        oj_can_manage_contest($contest_id)
    );

// ============================================================
// Course를 통한 학생 해결과정 조회 권한
//
// URL 예:
// solution_process_view.php?sid=151043&course_id=1
//
// 다음 조건을 모두 만족해야 한다.
//
// 1. 실제 존재하는 course_id
// 2. 현재 사용자가 해당 Course에 접근 가능
// 3. 이 제출 학생이 해당 Course 학생
// 4. 이 제출의 contest가 해당 Course에 연결됨
//
// 단순히 course_id만 URL에 추가해서 다른 학생의 제출을
// 열람하는 것을 방지한다.
// ============================================================

$is_course_process_viewer = false;

$process_course_id =
    isset($_GET['course_id'])
        ? intval($_GET['course_id'])
        : 0;


if (
    $process_course_id > 0 &&
    $contest_id > 0 &&
    $solution_user_id !== ''
) {

    // --------------------------------------------------------
    // 먼저 현재 사용자가 해당 Course에 접근 가능한지 확인
    // administrator / owner / teacher / assistant
    // --------------------------------------------------------

    if (course_can_view_student_records($process_course_id)) {

        // ----------------------------------------------------
        // 제출 학생 + Course + Contest 관계를 동시에 검증
        // ----------------------------------------------------

        $course_process_rows = pdo_query(
            "SELECT 1

               FROM course_student cs

               INNER JOIN course_contest cc
                 ON cc.course_id = cs.course_id

              WHERE cs.course_id = ?
                AND cs.user_id = ?
                AND cc.contest_id = ?

              LIMIT 1",
            $process_course_id,
            $solution_user_id,
            $contest_id
        );


        if (
            $course_process_rows &&
            isset($course_process_rows[0])
        ) {

            $is_course_process_viewer = true;
        }
    }
}

// ============================================================
// Course 교사 관찰 메모 관리 권한
//
// Course를 통한 정상적인 해결과정 접근인 경우에만 확인한다.
//
// 허용:
// - administrator
// - owner
// - teacher
//
// 조회만 가능:
// - assistant
// ============================================================

$can_manage_course_teacher_note = false;


if (
    $is_course_process_viewer &&
    $process_course_id > 0
) {

    if (
    course_can_manage_student_records(
        $process_course_id
        )
    ) {
        $can_manage_course_teacher_note = true;
    }
}

// ============================================================
// 7. 사고과정 열람 권한
//
// 허용
// - 학생 본인
// - administrator
// - source_browser
// - 해당 대회 관리자 m{contest_id}
//
// 허용하지 않음
// - 일반 사용자
// - contest_creator 권한만 가진 사용자
// - 다른 대회의 관리자
// ============================================================

$can_view_process = (
    $is_owner ||
    $is_admin ||
    $is_source_browser ||
    $is_contest_manager ||

    // Course owner / teacher / assistant
    $is_course_process_viewer
);

if (!$can_view_process) {

    $view_errors =
        "<h2>이 제출의 문제 해결 과정을 볼 권한이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}


// ============================================================
// 8. 선택한 sid에 과정 기록이 실제 존재하는지 확인
// ============================================================

$check_sql = "SELECT id
    FROM solution_process
    WHERE solution_id=?
    LIMIT 1";

$check_result =
    pdo_query(
        $check_sql,
        $sid
    );


if (!$check_result || count($check_result) == 0) {

    $view_errors =
        "<h2>이 제출에는 기록된 문제 해결 과정이 없습니다.</h2>";

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 교사 관찰 메모 권한
//
// 허용:
// - administrator
// - 해당 대회의 m{cid}
//
// 학생 / 다른 대회 교사 / source_browser / contest_creator는 불가
// ============================================================
// 관찰 메모 조회 권한
// 학생 본인과 source_browser는 제외
$can_view_teacher_note =
    (
        $is_admin ||
        $is_contest_manager ||
        $is_course_process_viewer
    );


// 관찰 메모 작성·수정·삭제 권한
// Course assistant는 제외
$can_manage_teacher_note =
    (
        $is_admin ||
        $is_contest_manager ||
        $can_manage_course_teacher_note
    );
// ============================================================
// 교사 관찰 메모 조회
// ============================================================

$teacher_notes = array();

if ($can_view_teacher_note) {

    $note_sql = "SELECT
                    note_id,
                    teacher_id,
                    note_text,
                    created_at,
                    updated_at
                 FROM teacher_process_note
                 WHERE contest_id=?
                   AND user_id=?
                   AND problem_id=?
                 ORDER BY created_at DESC,
                          note_id DESC";

    $note_result =
        pdo_query(
            $note_sql,
            $contest_id,
            $solution_user_id,
            $problem_id
        );

    if ($note_result) {
        $teacher_notes = $note_result;
    }
}    

// ============================================================
// 9. 같은 학생 + 같은 문제의 전체 과정 조회
//
// 매우 중요:
//
// 일반 문제와 대회 문제를 섞지 않는다.
//
// 일반:
// contest_id = 0 또는 NULL
//
// 대회:
// 동일 contest_id
// ============================================================

if ($contest_id > 0) {

    $process_sql = "SELECT
            sp.id,
            sp.solution_id,
            sp.user_id,
            sp.problem_id,
            sp.contest_id,

            sp.plan_text,
            sp.ai_used,
            sp.ai_usage_type,
            sp.ai_prompt,
            sp.reflection,

            sp.created_at,
            sp.updated_at,

            s.result,
            s.language,
            s.in_date,
            s.code_length,
            s.memory,
            s.time

        FROM solution_process sp

        INNER JOIN solution s
            ON sp.solution_id = s.solution_id

        WHERE sp.user_id=?
          AND sp.problem_id=?
          AND sp.contest_id=?

        ORDER BY s.solution_id ASC";

    $process_result =
        pdo_query(
            $process_sql,
            $solution_user_id,
            $problem_id,
            $contest_id
        );

}
else {

    $process_sql = "SELECT
            sp.id,
            sp.solution_id,
            sp.user_id,
            sp.problem_id,
            sp.contest_id,

            sp.plan_text,
            sp.ai_used,
            sp.ai_usage_type,
            sp.ai_prompt,
            sp.reflection,

            sp.created_at,
            sp.updated_at,

            s.result,
            s.language,
            s.in_date,
            s.code_length,
            s.memory,
            s.time

        FROM solution_process sp

        INNER JOIN solution s
            ON sp.solution_id = s.solution_id

        WHERE sp.user_id=?
          AND sp.problem_id=?
          AND (
                sp.contest_id=0
                OR sp.contest_id IS NULL
              )

        ORDER BY s.solution_id ASC";

    $process_result =
        pdo_query(
            $process_sql,
            $solution_user_id,
            $problem_id
        );
}


if (!$process_result) {
    $process_result = array();
}
// ============================================================
// 제출별 학생 원본 코드 일괄 조회
//
// 제출마다 SQL을 실행하지 않고
// 현재 과정의 solution_id를 한 번에 조회한다.
// ============================================================

$source_map = array();

$source_version_map = array();

$process_solution_ids = array();


foreach ($process_result as $process) {

    $process_sid =
        isset($process['solution_id'])
            ? intval($process['solution_id'])
            : 0;

    if ($process_sid > 0) {

        $process_solution_ids[] =
            $process_sid;
    }
}


$process_solution_ids =
    array_values(
        array_unique(
            $process_solution_ids
        )
    );


if (count($process_solution_ids) > 0) {

    $solution_id_list =
        implode(
            ",",
            $process_solution_ids
        );


    $source_sql = "SELECT
        solution_id,
        source,
        source_version
   FROM source_code_user
   WHERE solution_id IN ($solution_id_list)";


    $source_result =
        pdo_query(
            $source_sql
        );


    if ($source_result) {

        foreach ($source_result as $source_row) {

            $source_sid =
                isset($source_row['solution_id'])
                    ? intval($source_row['solution_id'])
                    : intval($source_row[0]);


            $source_text =
                isset($source_row['source'])
                    ? $source_row['source']
                    : $source_row[1];


            $source_version =
                isset($source_row['source_version'])
                ? intval($source_row['source_version'])
                : (
                    isset($source_row[2])
                    ? intval($source_row[2])
                    : 0
                );


            $source_map[$source_sid] = $source_text;


            $source_version_map[$source_sid] = $source_version;
        }
    }
}
// ============================================================
// 이전 제출 ↔ 현재 제출 코드 변화 계산
// ============================================================

$process_diff_map = array();

$previous_source = null;
$previous_sid = 0;


foreach ($process_result as $process) {

    $current_sid =
        isset($process['solution_id'])
            ? intval($process['solution_id'])
            : 0;


    $current_source =
        isset($source_map[$current_sid])
            ? $source_map[$current_sid]
            : null;
            
    $current_source_version =
        isset($source_version_map[$current_sid])
        ? intval($source_version_map[$current_sid])
        : 0;


    // 원본 코드가 아니거나 코드가 없으면
    // 이전 제출과의 코드 비교를 중단한다.
    if (
        $current_source === null ||
        $current_source_version !== 1
    ) {

        $previous_source = null;
        $previous_sid = 0;

        continue;
    }

    // 첫 제출은 비교 대상 없음
    if (
        $previous_source !== null &&
        $current_source !== null
    ) {

        $diff =
            build_source_diff(
                $previous_source,
                $current_source
            );


        $diff['previous_solution_id'] =
            $previous_sid;


        $process_diff_map[
            $current_sid
        ] = $diff;
    }


    // 현재 코드를 다음 제출 비교 기준으로 사용
    if ($current_source !== null) {

        $previous_source =
            $current_source;

        $previous_sid =
            $current_sid;
    }
}

// ============================================================
// 10. 화면용 기본 정보
// ============================================================

$process_count =
    count($process_result);


// ============================================================
// 11. AI 사용 유형 표시 이름
//
// 실제 DB에는 영문 코드 저장
// 화면에서는 학생/교사가 이해하기 쉽게 표시
// ============================================================

$ai_usage_names = array(

    'none' =>
        '사용하지 않음',

    'understand' =>
        '문제 이해',

    'idea' =>
        '풀이 아이디어',

    'hint' =>
        '힌트',

    'syntax' =>
        '문법 도움',

    'debug' =>
        '오류 수정',

    'generate' =>
        '코드 생성',

    'explain' =>
        '코드 설명',

    'other' =>
        '기타'
);


// ============================================================
// 12. Template
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/solution_process_view.php"
);

?>