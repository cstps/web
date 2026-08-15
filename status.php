<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past

////////////////////////////Common head
$cache_time = 2;
$OJ_CACHE_SHARE = false;

require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = "$MSG_STATUS";


// ============================================================
// 기본값 초기화
// ============================================================

$cid = null;
$start_time = 0;
$end_time = 0;

$exam_mode = 0;
$codevisible = 0;

$is_contest_manager = false;


// ============================================================
// 로그인 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {

    if (isset($OJ_GUEST) && $OJ_GUEST) {

        $_SESSION[$OJ_NAME.'_'.'user_id'] = "Guest";

    }
    else {

        $view_errors =
            "<button><a href=loginpage.php>$MSG_Login</a></button>";

        require("template/".$OJ_TEMPLATE."/error.php");

        exit(0);
    }
}


// ============================================================
// 공통 사용자 / 권한 정보
//
// 이 파일에서는 가능하면 아래 변수로 권한을 판단한다.
// ============================================================

$current_user =
    isset($_SESSION[$OJ_NAME.'_'.'user_id'])
        ? $_SESSION[$OJ_NAME.'_'.'user_id']
        : "";

$is_admin =
    isset($_SESSION[$OJ_NAME.'_'.'administrator']);

$is_source_browser =
    isset($_SESSION[$OJ_NAME.'_'.'source_browser']);

$is_contest_creator =
    isset($_SESSION[$OJ_NAME.'_'.'contest_creator']);


// ============================================================
// 유틸
// ============================================================

function formatTimeLength($length) {

    $hour = 0;
    $minute = 0;
    $second = 0;
    $result = '';

    global $MSG_SECONDS, $MSG_MINUTES, $MSG_HOURS, $MSG_DAYS;

    if ($length >= 60) {

        $second = $length % 60;

        if ($second > 0 && $second < 10)
            $result = '0'.$second.' '.$MSG_SECONDS;

        else if ($second > 0)
            $result = $second.' '.$MSG_SECONDS;


        $length = floor($length / 60);


        if ($length >= 60) {

            $minute = $length % 60;

            if ($minute == 0)
                $result =
                    ($result != '' ? '00 '.$MSG_MINUTES.' ' : '').
                    $result;

            else if ($minute > 0 && $minute < 10)
                $result =
                    ($result != '' ? '0'.$minute.' '.$MSG_MINUTES.' ' : '').
                    $result;

            else
                $result =
                    $minute.' '.$MSG_MINUTES.' '.$result;


            $length = floor($length / 60);


            if ($length >= 24) {

                $hour = $length % 24;

                if ($hour == 0)
                    $result =
                        ($result != '' ? '00 '.$MSG_HOURS.' ' : '').
                        $result;

                else if ($hour > 0 && $hour < 10)
                    $result =
                        ($result != '' ? '0'.$hour.' '.$MSG_HOURS.' ' : '').
                        $result;

                else
                    $result =
                        $hour.' '.$MSG_HOURS.' '.$result;


                $length = floor($length / 24);

                $result =
                    $length.$MSG_DAYS.' '.$result;

            }
            else {

                $result =
                    $length.' '.$MSG_HOURS.' '.$result;
            }

        }
        else {

            $result =
                $length.' '.$MSG_MINUTES.' '.$result;
        }

    }
    else {

        $result =
            $length.' '.$MSG_SECONDS;
    }

    return $result;
}


require_once("./include/my_func.inc.php");

if (isset($OJ_LANG))
    require_once("./lang/$OJ_LANG.php");

require_once("./include/const.inc.php");


// ============================================================
// 헤더 디버그
// ============================================================

if (!function_exists('__dbg_headers_log')) {

    function __dbg_headers_log($tag) {

        if (!function_exists('headers_list'))
            return;

        $headers = headers_list();

        $len = 0;
        $setcookie_cnt = 0;
        $lines = array();

        $lines[] = "[HDR:$tag] =====";

        foreach ($headers as $h) {

            $lines[] = "[HDR] $h";

            $len += strlen($h) + 2;

            if (stripos($h, 'Set-Cookie:') === 0)
                $setcookie_cnt++;
        }

        $lines[] =
            "[HDR:$tag] count=".count($headers).
            " set-cookie=".$setcookie_cnt.
            " approx_len=".$len;

        $lines[] = "[HDR:$tag] =====";


        foreach ($lines as $L)
            error_log($L);


        @file_put_contents(
            '/tmp/status_headers.log',
            implode(PHP_EOL, $lines).PHP_EOL,
            FILE_APPEND
        );
    }
}


if (!function_exists('__dbg_headers_shutdown')) {

    function __dbg_headers_shutdown() {

        __dbg_headers_log('on-shutdown');
    }

    register_shutdown_function('__dbg_headers_shutdown');
}


__dbg_headers_log('after-includes');


// ============================================================
// 기본 Status 조건
// ============================================================

$str2 = "";

$lock = false;

$lock_time =
    date("Y-m-d H:i:s", time());

$sql =
    "WHERE problem_id>0 ";


// ============================================================
// 대회 Status
// ============================================================

if (isset($_GET['cid'])) {

    $cid =
        intval($_GET['cid']);

    // 현재 대회의 실제 관리자(mXXXX)
    $is_contest_manager =
        isset($_SESSION[$OJ_NAME."_m$cid"]);


    $sql .=
        " AND `contest_id`='$cid' and num>=0 ";

    $str2 .=
        "&cid=$cid";


    $sql_lock =
        "SELECT
            `start_time`,
            `title`,
            `end_time`,
            `codevisible`,
            `exam_mode`
         FROM `contest`
         WHERE `contest_id`=?";


    $result =
        pdo_query($sql_lock, $cid);

    $rows_cnt =
        count($result);


    $start_time = 0;
    $end_time = 0;


    if ($rows_cnt > 0) {

        $row =
            $result[0];

        $start_time =
            strtotime($row[0]);

        $title =
            $row[1];

        $end_time =
            strtotime($row[2]);

        $codevisible =
            isset($row['codevisible'])
                ? intval($row['codevisible'])
                : 0;

        $exam_mode =
            isset($row['exam_mode'])
                ? intval($row['exam_mode'])
                : 0;


        // ----------------------------------------------------
        // NOIP 처리
        // ----------------------------------------------------

        $noip =
            (time() < $end_time) &&
            (stripos($title, $OJ_NOIP_KEYWORD) !== false);


        if (
            $is_admin ||
            $is_contest_manager ||
            $is_source_browser ||
            $is_contest_creator
        ) {

            $noip = false;
        }


        if ($noip) {

            $view_errors =
                "<h2> $MSG_NOIP_WARNING ".
                "<a href=\"contest.php?cid=$cid\">대회로 돌아가기</a></h2>";


            $refererUrl =
                @parse_url($_SERVER['HTTP_REFERER'] ?? '');


            if (
                isset($refererUrl['path']) &&
                $refererUrl['path'] == "/submitpage.php"
            ) {

                $view_errors =
                    "<h2>성공적으로 제출됨!</h2>".
                    "<a href=\"contest.php?cid=$cid\">대회로 돌아가기</a></h2>";
            }


            require("template/".$OJ_TEMPLATE."/error.php");

            exit(0);
        }
    }


    $lock_time =
        $end_time -
        ($end_time - $start_time) *
        $OJ_RANK_LOCK_PERCENT;


    if (
        time() > $lock_time &&
        time() < $end_time
    ) {

        $lock = true;

    }
    else {

        $lock = false;
    }

}


// ============================================================
// 일반 Status
// ============================================================

else {

    if (
        $is_admin ||
        $is_source_browser ||
        (
            $current_user != "" &&
            isset($_GET['user_id']) &&
            $_GET['user_id'] == $current_user
        )
    ) {

        if ($is_source_browser) {

            $sql =
                "WHERE problem_id>0  ";

        }
        else if (strtolower($current_user) != "guest") {

            $sql =
                "WHERE (contest_id=0 or contest_id is null)  ";
        }

    }
    else {

        $sql =
            "WHERE problem_id>0 ".
            "and (contest_id=0 or contest_id is null) ";
    }
}


// ============================================================
// 정렬
// ============================================================

$start_first = true;

$order_str =
    " ORDER BY `solution_id` DESC ";


// ============================================================
// top
// ============================================================

if (isset($_GET['top'])) {

    $top =
        strval(intval($_GET['top']));

    if ($top != -1) {

        $sql .=
            "AND `solution_id`<='".$top."' ";
    }
}


// ============================================================
// problem_id
// ============================================================

$problem_id = "";


if (
    isset($_GET['problem_id']) &&
    $_GET['problem_id'] != ""
) {

    if (isset($_GET['cid'])) {

        $problem_id =
            htmlentities(
                $_GET['problem_id'],
                ENT_QUOTES,
                'UTF-8'
            );


        $num =
            array_search(
                $problem_id,
                $PID
            );


        $problem_id =
            $PID[$num];


        $sql .=
            "AND `num`='".$num."' ";


        $str2 .=
            "&problem_id=".trim($problem_id);

    }
    else {

        $problem_id =
            strval(
                intval($_GET['problem_id'])
            );


        if ($problem_id != '0') {

            $sql .=
                "AND `problem_id`='".$problem_id."' ";

            $str2 .=
                "&problem_id=".trim($problem_id);

        }
        else {

            $problem_id = "";
        }
    }
}


// ============================================================
// user_id
// ============================================================

$user_id = "";


if (
    isset($OJ_ON_SITE_CONTEST_ID) &&
    $OJ_ON_SITE_CONTEST_ID > 0 &&
    !$is_admin &&
    !$is_source_browser
) {

    $_GET['user_id'] =
        $current_user;
}


if (isset($_GET['user_id'])) {

    $user_id =
        trim($_GET['user_id']);


    if (
        $user_id != "" &&
        is_valid_user_name($user_id)
    ) {

        $sql .=
            "AND `user_id`=? ";


        if ($str2 != "")
            $str2 .= "&";


        $str2 .=
            "user_id=".urlencode($user_id);

    }
    else {

        $user_id = "";
    }
}


// ============================================================
// language
// ============================================================

if (isset($_GET['language']))
    $language = intval($_GET['language']);

else
    $language = -1;


if (
    $language > count($language_ext) ||
    $language < 0
) {

    $language = -1;
}


if ($language != -1) {

    $sql .=
        "AND `language`='".($language)."' ";

    $str2 .=
        "&language=".$language;
}


// ============================================================
// result
// ============================================================

if (isset($_GET['jresult']))
    $result = intval($_GET['jresult']);

else
    $result = -1;


if (
    $result > 12 ||
    $result < 0
) {

    $result = -1;
}


if (
    $result != -1 &&
    !$lock
) {

    $sql .=
        "AND `result`='".($result)."' ";

    $str2 .=
        "&jresult=".$result;
}


// ============================================================
// 메인 solution SQL
//
// 기존 Status SQL은 그대로 유지한다.
// ============================================================

if ($OJ_SIM) {

    $sql =
        "select * from solution solution ".
        "left join `sim` sim ".
        "on solution.solution_id=sim.s_id ".
        $sql;


    if (
        isset($_GET['showsim']) &&
        intval($_GET['showsim']) > 0
    ) {

        $showsim =
            intval($_GET['showsim']);

        $sql .=
            " and sim.sim>=$showsim";

        $str2 .=
            "&showsim=$showsim";
    }

}
else {

    $sql =
        "select * from `solution` ".$sql;
}


$sql =
    $sql.$order_str." LIMIT 50";


// ============================================================
// solution 조회
// ============================================================

if (isset($_GET['user_id'])) {

    $result =
        pdo_query(
            $sql,
            $user_id
        );

}
else {

    $result =
        pdo_query($sql);
}


__dbg_headers_log('after-query');


if ($result)
    $rows_cnt = count($result);

else
    $rows_cnt = 0;


// ============================================================
// 수업용 OJ
// 현재 Status 목록의 사고과정 존재 여부 일괄 조회
//
// 기존 Status SQL을 변경하지 않고
// 최대 50개의 solution_id만 한 번에 조회한다.
// ============================================================

$process_solution_map =
    array();


if ($rows_cnt > 0) {

    $solution_ids =
        array();


    foreach ($result as $status_row) {

        if (
            isset($status_row['solution_id']) &&
            intval($status_row['solution_id']) > 0
        ) {

            $solution_ids[] =
                intval(
                    $status_row['solution_id']
                );
        }
    }


    // 중복 제거
    $solution_ids =
        array_values(
            array_unique($solution_ids)
        );


    if (count($solution_ids) > 0) {

        $solution_id_list =
            implode(
                ",",
                $solution_ids
            );


        $process_result =
            pdo_query(
                "SELECT DISTINCT solution_id
                 FROM solution_process
                 WHERE solution_id IN ($solution_id_list)"
            );


        if ($process_result) {

            foreach (
                $process_result
                as
                $process_row
            ) {

                $process_sid =
                    intval(
                        $process_row['solution_id']
                    );

                $process_solution_map[
                    $process_sid
                ] = true;
            }
        }
    }
}


// ============================================================
// 목록 준비
// ============================================================

$top =
    $bottom =
    -1;

$cnt = 0;


if ($start_first) {

    $row_start = 0;
    $row_add = 1;

}
else {

    $row_start =
        $rows_cnt - 1;

    $row_add = -1;
}


$view_status =
    array();

$last = 0;


__dbg_headers_log('before-list-loop');


// ============================================================
// Status 목록
// ============================================================

for (
    $i = 0;
    $i < $rows_cnt;
    $i++
) {

    $row =
        $result[$i];


    // ========================================================
    // 현재 제출 기본정보
    // ========================================================

    $current_solution_id =
        intval(
            $row['solution_id']
        );


    $row_contest_id =
        intval(
            $row['contest_id']
        );


    // ========================================================
    // 제출자 본인 여부
    // ========================================================

    $is_owner = (
        $current_user != "" &&
        strtolower($row['user_id']) ===
        strtolower($current_user)
    );


    // ========================================================
    // 현재 행의 대회 관리자 여부
    //
    // m2976 형태의 해당 대회 관리자
    // ========================================================

    $row_is_contest_manager =
        false;


    if ($row_contest_id > 0) {

        $row_is_contest_manager =
            isset(
                $_SESSION[
                    $OJ_NAME.'_m'.$row_contest_id
                ]
            );
    }


    // ========================================================
    // 사고과정 기록 존재 여부
    // ========================================================

    $has_process =
        isset(
            $process_solution_map[
                $current_solution_id
            ]
        );


    // ========================================================
    // 기능별 권한
    // ========================================================


    // --------------------------------------------------------
    // Status 결과 표시 권한
    //
    // 수행모드에서는 contest_creator 권한만으로
    // 다른 학생의 제출 결과를 볼 수 없다.
    // --------------------------------------------------------

    $can_view_result = (
        $is_admin ||
        $is_source_browser ||
        $row_is_contest_manager ||
        $exam_mode == 0 ||
        $is_owner
    );


    // --------------------------------------------------------
    // 소스코드 기본 열람
    //
    // 기존 로직 그대로:
    // 본인 또는 source_browser
    // --------------------------------------------------------

    $can_view_source = (
        $is_owner ||
        $is_source_browser
    );


    // --------------------------------------------------------
    // 관리자 수준 소스 관리
    //
    // 기존 코드 내부의 $is_admin 의미
    // --------------------------------------------------------

    $can_manage_source = (
        $is_admin ||
        $is_source_browser ||
        $row_is_contest_manager
    );


    // --------------------------------------------------------
    // 학생 사고과정 열람
    //
    // contest_creator는 단순 권한만으로
    // 다른 학생의 과정 열람 불가
    // --------------------------------------------------------

    $can_view_process = (
        $is_owner ||
        $is_admin ||
        $is_source_browser ||
        $row_is_contest_manager
    );


    // ========================================================
    // 수행모드
    // 타인의 제출 정보 숨김
    //
    // 전체 확인 가능:
    // - administrator
    // - source_browser
    // - 해당 대회의 실제 관리자(m{cid})
    //
    // contest_creator 권한만으로는 예외 처리하지 않는다.
    // ========================================================

    if (
        $exam_mode == 1 &&
        !$is_owner &&
        !$is_admin &&
        !$is_source_browser &&
        !$row_is_contest_manager
    ) {

        $view_status[$i][0] =
            "수행모드";

        $view_status[$i][1] =
            "----";

        $view_status[$i]['nick'] =
            "----";

        $view_status[$i][2] =
            "----";

        $view_status[$i][3] =
            "----";

        $view_status[$i][4] =
            "----";

        $view_status[$i][5] =
            "----";

        $view_status[$i][6] =
            "----";

        $view_status[$i][7] =
            "----";

        $view_status[$i][8] =
            "수행모드";

        $view_status[$i][9] =
            "----";

        continue;
    }


    // ========================================================
    // 기본 solution 정보
    // ========================================================

    if (
        $i == 0 &&
        $row['result'] < 4
    ) {

        $last =
            $row['solution_id'];
    }


    if ($top == -1) {

        $top =
            $row['solution_id'];
    }


    $bottom =
        $row['solution_id'];


    $cnt =
        1 - $cnt;


    // ========================================================
    // Run ID
    // ========================================================

    $view_status[$i][0] =
        $row['solution_id'];


    // ========================================================
    // 사용자
    // ========================================================

    if ($row_contest_id > 0) {

        if ($is_admin) {

            $view_status[$i][1] =
                "<a href='contestrank.php?cid=".
                $row['contest_id'].
                "&user_id=".
                $row['user_id'].
                "#".
                $row['user_id'].
                "' title='".
                $row['ip'].
                "'>".
                $row['user_id'].
                "</a>";

        }
        else if (
            $exam_mode == 0 ||
            $is_source_browser ||
            $row_is_contest_manager
        ) {

            $view_status[$i][1] =
                "<a href='contestrank.php?cid=".
                $row['contest_id'].
                "&user_id=".
                $row['user_id'].
                "#".
                $row['user_id'].
                "'>".
                $row['user_id'].
                "</a>";

        }
        else {

            $view_status[$i][1] =
                "수행모드";
        }

    }
    else {

        if ($is_admin) {

            $view_status[$i][1] =
                "<a href='userinfo.php?user=".
                $row['user_id'].
                "' title='".
                $row['nick'].
                "[".
                $row['ip'].
                "]'>".
                $row['user_id'].
                "</a>";

        }
        else {

            $view_status[$i][1] =
                "<a href='userinfo.php?user=".
                $row['user_id'].
                "'>".
                $row['user_id'].
                "</a>";
        }
    }


    // ========================================================
    // 닉네임
    // ========================================================

    if ($is_admin) {

        $view_status[$i]['nick'] =
            $row['nick'];

    }
    else {

        $view_status[$i]['nick'] =
            "비공개";
    }


    // ========================================================
    // 문제
    // ========================================================

    if ($row_contest_id > 0) {

        if (time() < $end_time) {

            $view_status[$i][2] =
                "<div><a href='problem.php?cid=".
                $row['contest_id'].
                "&pid=".
                $row['num'].
                "'>";


            if (isset($cid)) {

                $view_status[$i][2] .=
                    $PID[$row['num']];

            }
            else {

                $view_status[$i][2] .=
                    $row['problem_id'];
            }


            $view_status[$i][2] .=
                "</div></a>";

        }
        else {

            $view_status[$i][2] =
                "<div class=center>";


            if (isset($cid)) {

                $tpid =
                    intval(
                        $row['problem_id']
                    );


                $problem_check_sql =
                    "SELECT `problem_id`
                     FROM `problem`
                     WHERE `problem_id`=?
                     AND `problem_id` IN (
                        SELECT `problem_id`
                        FROM `contest_problem`
                        WHERE `contest_id` IN (
                            SELECT `contest_id`
                            FROM `contest`
                            WHERE (
                                `defunct`='N'
                                AND now()<`end_time`
                            )
                        )
                     )";


                $tresult =
                    pdo_query(
                        $problem_check_sql,
                        $tpid
                    );


                if (
                    $tresult &&
                    count($tresult) > 0
                ) {

                    $view_status[$i][2] .=
                        $PID[$row['num']];

                }
                else {

                    $view_status[$i][2] .=
                        "<a href='problem.php?id=".
                        $row['problem_id'].
                        "'>".
                        $PID[$row['num']].
                        "</a>";
                }

            }
            else {

                $view_status[$i][2] .=
                    "<a href='problem.php?id=".
                    $row['problem_id'].
                    "'>".
                    $row['problem_id'].
                    "</a>";
            }


            $view_status[$i][2] .=
                "</div>";
        }

    }
    else {

        $view_status[$i][2] =
            "<div class=center>".
            "<a href='problem.php?id=".
            $row['problem_id'].
            "'>".
            $row['problem_id'].
            "</a>".
            "</div>";
    }


    // ========================================================
    // 결과 도움말
    // ========================================================

    switch ($row['result']) {

        case 4:
            $MSG_Tips = $MSG_HELP_AC;
            break;

        case 5:
            $MSG_Tips = $MSG_HELP_PE;
            break;

        case 6:
            $MSG_Tips = $MSG_HELP_WA;
            break;

        case 7:
            $MSG_Tips = $MSG_HELP_TLE;
            break;

        case 8:
            $MSG_Tips = $MSG_HELP_MLE;
            break;

        case 9:
            $MSG_Tips = $MSG_HELP_OLE;
            break;

        case 10:
            $MSG_Tips = $MSG_HELP_RE;
            break;

        case 11:
            $MSG_Tips = $MSG_HELP_CE;
            break;

        default:
            $MSG_Tips = "";
    }


    // ========================================================
    // 정답 비율
    // ========================================================

    $AC_RATE =
        intval(
            $row['pass_rate'] * 100
        );


    if (
        isset($OJ_MARK) &&
        $OJ_MARK != "mark"
    ) {

        $mark = "";

    }
    else {

        $mark =
            ($AC_RATE > 99)
                ? ""
                : " "."(정답비율:".$AC_RATE."%)";
    }


    if (
        !$is_owner &&
        !$is_source_browser
    ) {

        $mark = "";
    }


    // ========================================================
    // 결과 표시
    // ========================================================

    $view_status[$i][3] =
        "<span class='hidden' ".
        "style='display:none' ".
        "result=".$row['result'].">".
        "</span>";


    // Compile Error
    if (
        intval($row['result']) == 11 &&
        (
            $is_owner ||
            $is_source_browser
        )
    ) {

        $view_status[$i][3] .=
            "<a href=ceinfo.php?sid=".
            $row['solution_id'].
            " class='".
            $judge_color[$row['result']].
            "' title='$MSG_Tips'>".
            $MSG_Compile_Error.
            "</a>";

    }

    // RE / WA / TLE 등
    else if (
        (
            (
                (
                    intval($row['result']) == 8 ||
                    intval($row['result']) == 7 ||
                    intval($row['result']) == 5 ||
                    intval($row['result']) == 6
                )
                &&
                (
                    $OJ_SHOW_DIFF ||
                    $is_source_browser
                )
            )
            ||
            $row['result'] == 10
            ||
            $row['result'] == 13
        )
        &&
        (
            $is_owner ||
            $is_source_browser
        )
    ) {

        $view_status[$i][3] .=
            "<a href=reinfo.php?sid=".
            $row['solution_id'].
            " class='".
            $judge_color[$row['result']].
            "' title='$MSG_Tips'>".
            $judge_result[$row['result']].
            $mark.
            "</a>";

    }

    else {

        if (
            !$lock ||
            $lock_time > $row['in_date'] ||
            $is_owner
        ) {

            if (
                $OJ_SIM &&
                $row['sim'] > 80 &&
                $row['sim_s_id'] != $row['s_id']
            ) {

                $view_status[$i][3] .=
                    "<a href=reinfo.php?sid=".
                    $row['solution_id'].
                    " class='".
                    $judge_color[$row['result']].
                    "' title='$MSG_Tips'>*".
                    $judge_result[$row['result']];


                if (
                    $row['result'] != 4 &&
                    isset($row['pass_rate']) &&
                    $row['pass_rate'] != 1
                ) {

                    $view_status[$i][3] .=
                        $mark."</a>";

                }
                else {

                    $view_status[$i][3] .=
                        "</a>";
                }


                if ($is_source_browser) {

                    $view_status[$i][3] .=
                        "<a href=comparesource.php?left=".
                        $row['sim_s_id'].
                        "&right=".
                        $row['solution_id'].
                        " class='label label-info' ".
                        "target=original>".
                        $row['sim_s_id'].
                        "(".
                        $row['sim'].
                        "%)</a>";

                }
                else {

                    $view_status[$i][3] .=
                        "<span class='label label-info'>".
                        $row['sim_s_id'].
                        "</span>";
                }


                if (
                    isset($_GET['showsim']) &&
                    isset($row['sim_s_id'])
                ) {

                    $view_status[$i][3] .=
                        "<span sid='".
                        $row['sim_s_id'].
                        "' class='original'></span>";
                }

            }
            else {

                if ($row['result'] == 4) {

                    $view_status[$i][3] .=
                        "<span class='".
                        $judge_color[$row['result']].
                        "' title='$MSG_Tips'>".
                        $judge_result[$row['result']].
                        $mark.
                        "</span>";

                }
                else {

                    $view_status[$i][3] .=
                        "<a href=reinfo.php?sid=".
                        $row['solution_id'].
                        " class='".
                        $judge_color[$row['result']].
                        "' title='$MSG_Tips'>".
                        $judge_result[$row['result']].
                        $mark.
                        "</a>";
                }
            }

        }
        else {

            $view_status[$i][3] =
                "----";
        }
    }


    // ========================================================
    // HTTP Judge
    // ========================================================

    if (
        isset(
            $_SESSION[
                $OJ_NAME.'_'.'http_judge'
            ]
        )
    ) {

        $view_status[$i][3] .=
            "<form class='http_judge_form form-inline'>".
            "<input type=hidden ".
            "name=sid ".
            "value='".
            $row['solution_id'].
            "'>".
            "</form>";
    }


    // ========================================================
    // 메모리 / 시간 / 코드
    // ========================================================

    if ($can_view_result) {

        if ($row['result'] >= 4) {

            $view_status[$i][4] =
                "<div id=center>".
                $row['memory'].
                "KB</div>";

            $view_status[$i][5] =
                "<div id=center>".
                $row['time'].
                "ms</div>";

        }
        else {

            $view_status[$i][4] =
                "---";

            $view_status[$i][5] =
                "---";
        }


        // ----------------------------------------------------
        // 소스 열람 불가
        // ----------------------------------------------------

        if (!$can_view_source) {

            $view_status[$i][6] =
                $language_name[
                    $row['language']
                ];

        }

        // ----------------------------------------------------
// 본인 또는 source_browser
// ----------------------------------------------------

else {

    // =================================================
    // 소스코드 표시 가능 여부
    //
    // 일반 문제:
    //   본인 / source_browser는 기존처럼 가능
    //
    // 대회 문제:
    //   codevisible=1이면 일반 학생은 소스 열람 제한
    //   관리자급 권한은 열람 가능
    // =================================================

    $can_show_source_link = true;

    if ($row_contest_id > 0) {

        if (
            $codevisible == 1 &&
            !$can_manage_source
        ) {

            $can_show_source_link = false;
        }
    }


    // -------------------------------------------------
    // 언어 / 소스 링크
    // -------------------------------------------------

    if ($can_show_source_link) {

        $view_status[$i][6] =
            "<a target=_self ".
            "href=showsource.php?id=".
            $row['solution_id'].
            ">".
            $language_name[$row['language']].
            "</a>";

    }
    else {

        // codevisible로 소스 숨김
        $view_status[$i][6] =
            "제한";
    }


    // =================================================
    // Edit
    // =================================================

    if ($row["problem_id"] > 0) {

        // ---------------------------------------------
        // 대회 문제
        // ---------------------------------------------

        if ($row_contest_id > 0) {

            if (
                (time() < intval($end_time)) ||
                $can_manage_source
            ) {

                if (
                    $exam_mode == 0 ||
                    $can_manage_source
                ) {

                    if (
                        $codevisible == 0 ||
                        $can_manage_source
                    ) {

                        $view_status[$i][6] .=
                            "/<a target=_self ".
                            "href=\"submitpage.php?cid=".
                            $row['contest_id'].
                            "&pid=".
                            $row['num'].
                            "&sid=".
                            $row['solution_id'].
                            "\">Edit</a>";

                    }
                    else {

                        // 이미 앞에서 "제한"으로 표시했으므로
                        // /제한을 다시 붙이지 않음
                    }

                }
                else if (
                    $exam_mode == 1 &&
                    $is_owner
                ) {

                    // 수행모드에서도 codevisible=1이면
                    // 학생에게 Edit 허용하지 않음
                    if (
                        $codevisible == 0 ||
                        $can_manage_source
                    ) {

                        $view_status[$i][6] .=
                            "/<a target=_self ".
                            "href=\"submitpage.php?cid=".
                            $row['contest_id'].
                            "&pid=".
                            $row['num'].
                            "&sid=".
                            $row['solution_id'].
                            "\">Edit</a>";
                    }

                }
                else {

                    $view_status[$i][6] .=
                        "/수행모드";
                }
            }

        }

        // ---------------------------------------------
        // 일반 문제
        // ---------------------------------------------

        else {

            if (
                $is_owner ||
                $can_manage_source
            ) {

                $view_status[$i][6] .=
                    "/<a target=_self ".
                    "href=\"submitpage.php?id=".
                    $row['problem_id'].
                    "&sid=".
                    $row['solution_id'].
                    "\">Edit</a>";
            }
        }
    }
}


        $view_status[$i][7] =
            $row['code_length'].
            " bytes";

    }

    // ========================================================
    // 결과 접근 제한
    // ========================================================

    else {

        if (
            $exam_mode == 1 &&
            !$is_owner
        ) {

            $view_status[$i][4] =
                "----";

            $view_status[$i][5] =
                "----";

            $view_status[$i][6] =
                "----";

            $view_status[$i][7] =
                "----";

        }
        else {

            $view_status[$i][4] =
                $row['memory']."KB";

            $view_status[$i][5] =
                $row['time']."ms";

            $view_status[$i][6] =
                $language_name[
                    $row['language']
                ];

            $view_status[$i][7] =
                $row['code_length'].
                " bytes";
        }
    }


    // ========================================================
    // 제출 시간
    // ========================================================

    if ($is_admin) {

        $view_status[$i][8] =
            $row['in_date'].
            "[".
            (
                strtotime($row['judgetime']) -
                strtotime($row['in_date'])
            ).
            "]";

    }
    else {

        $view_status[$i][8] =
            $row['in_date'];
    }


    // ========================================================
    // 수업용 OJ - 사고과정 버튼
    //
    // 1. 해당 제출에 사고과정이 존재
    // 2. 현재 사용자가 열람 권한 보유
    // ========================================================

    if (
        $has_process &&
        $can_view_process
    ) {

        $view_status[$i][9] =
            "<a href='solution_process_view.php?sid=".
            $current_solution_id.
            "' class='ui mini basic button'>".
            "과정".
            "</a>";

    }
    else {

        $view_status[$i][9] =
            "-";
    }
}


// ============================================================
// 목록 종료
// ============================================================

__dbg_headers_log('after-list-loop');




// ============================================================
// Template
// ============================================================

__dbg_headers_log('before-template');
?>


<?php

if (isset($_GET['cid'])) {

    require(
        "template/".
        $OJ_TEMPLATE.
        "/conteststatus.php"
    );

}
else {

    require(
        "template/".
        $OJ_TEMPLATE.
        "/status.php"
    );
}


__dbg_headers_log('after-template');


// ============================================================
// Common foot
// ============================================================

if (
    file_exists(
        './include/cache_end.php'
    )
) {

    require_once(
        './include/cache_end.php'
    );
}


__dbg_headers_log('after-cache-end');

?>