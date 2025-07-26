<?php
$OJ_CACHE_SHARE = false;
$cache_time = 10;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once("./include/const.inc.php");
require_once("./include/my_func.inc.php");

$view_title = $MSG_CONTEST . $MSG_RANKLIST;
$title = "";

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    if (isset($OJ_GUEST) && $OJ_GUEST) {
        $_SESSION[$OJ_NAME . '_user_id'] = "Guest";
    } else {
        $view_errors = "<button><a href=loginpage.php>$MSG_Login</a></button>";
        require("template/" . $OJ_TEMPLATE . "/error.php");
        exit(0);
    }
}

// class 정의
class TM {
    var $solved = 0;
    var $time = 0;
    var $p_wa_num;
    var $p_ac_sec;
    var $p_pass_rate;
    var $user_id;
    var $nick;
    var $total;

    function TM() {
        $this->solved = 0;
        $this->time = 0;
        $this->p_wa_num = array(0);
        $this->p_ac_sec = array(0);
        $this->p_pass_rate = array(0);
        $this->total = 0;
    }

    function Add($pid, $sec, $res) {
        global $score_map;

        $score = isset($score_map[$pid]) ? $score_map[$pid] : 100;

        if (isset($this->p_ac_sec[$pid]) && $this->p_ac_sec[$pid] > 0) return;

        if ($res * 100 < 99) {
            $old_rate = isset($this->p_pass_rate[$pid]) ? $this->p_pass_rate[$pid] : 0.0;
            if ($res > $old_rate) {
                $this->total += ($res - $old_rate) * $score;
                $this->p_pass_rate[$pid] = $res;
            }
            if (isset($this->p_wa_num[$pid]))
                $this->p_wa_num[$pid]++;
            else
                $this->p_wa_num[$pid] = 1;
        } else {
            $this->p_ac_sec[$pid] = $sec;
            $this->solved++;

            if (!isset($this->p_wa_num[$pid])) $this->p_wa_num[$pid] = 0;

            if (isset($this->p_pass_rate[$pid]))
                $this->total -= $this->p_pass_rate[$pid] * $score;

            $this->p_pass_rate[$pid] = 1.0;
            $this->total += $score;
            $this->time += $sec + $this->p_wa_num[$pid] * 60;
        }
    }
}

function s_cmp($A, $B) {
    if ($A->total != $B->total)
        return $A->total < $B->total;
    else if ($A->solved != $B->solved)
        return $A->solved < $B->solved;
    else
        return $A->time > $B->time;
}

// contest id 체크
if (!isset($_GET['cid'])) die("No Such Contest!");
$cid = intval($_GET['cid']);

// 문제별 배점
$score_map = array();
$sql = "SELECT num, score FROM contest_problem WHERE contest_id = ?";
$score_result = pdo_query($sql, $cid);
foreach ($score_result as $row) {
    $score_map[intval($row['num'])] = intval($row['score'] ?? 100);
}

// contest 정보 가져오기
if ($OJ_MEMCACHE) {
    $sql = "SELECT `start_time`,`title`,`end_time`, `exam_mode` FROM `contest` WHERE `contest_id`='$cid'";
    require("./include/memcache.php");
    $result = mysql_query_cache($sql);
} else {
    $sql = "SELECT `start_time`,`title`,`end_time`, `exam_mode` FROM `contest` WHERE `contest_id`=?";
    $result = pdo_query($sql, $cid);
}

if (!$result || count($result) == 0) {
    $view_errors = "No Such Contest";
    require("template/" . $OJ_TEMPLATE . "/error.php");
    exit(0);
}

$row = $result[0];
$start_time = strtotime($row['start_time']);
$end_time = strtotime($row['end_time']);
$title = $row['title'];
$exam_mode = intval($row['exam_mode']);

if ($start_time == 0 || $start_time > time()) {
    $view_errors = "Contest Not Started!";
    require("template/" . $OJ_TEMPLATE . "/error.php");
    exit(0);
}

// NOIP 체크
$noip = (time() < $end_time) && (stripos($title, $OJ_NOIP_KEYWORD) !== false);
if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . "_m$cid"]) ||
    isset($_SESSION[$OJ_NAME . '_source_browser']) ||
    isset($_SESSION[$OJ_NAME . '_contest_creator'])
) {
    $noip = false;
}
if ($noip) {
    $view_errors = "<h2>$MSG_NOIP_WARNING</h2>";
    require("template/" . $OJ_TEMPLATE . "/error.php");
    exit(0);
}

// rank lock 계산
if (!isset($OJ_RANK_LOCK_PERCENT)) $OJ_RANK_LOCK_PERCENT = 1;
$lock = $end_time - ($end_time - $start_time) * $OJ_RANK_LOCK_PERCENT;
$view_lock_time = $start_time + ($end_time - $start_time) * (1 - $OJ_RANK_LOCK_PERCENT);
$locked_msg = "";
if (time() > $view_lock_time && time() < $end_time + $OJ_RANK_LOCK_DELAY) {
    $locked_msg = "The board has been locked.";
}

// 문제 수
if ($OJ_MEMCACHE) {
    $sql = "SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`='$cid'";
    $result = mysql_query_cache($sql);
} else {
    $sql = "SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`=?";
    $result = pdo_query($sql, $cid);
}
$pid_cnt = intval($result[0]['pbc']);

// 제출 불러오기
require("./include/contest_solutions.php");

// 수행평가 모드 권한 분기
$only_me = false;
if ($exam_mode == 1) {
    $uid = $_SESSION[$OJ_NAME . '_user_id'];
    $is_admin = isset($_SESSION[$OJ_NAME . '_administrator']);
    $is_creator = isset($_SESSION[$OJ_NAME . '_contest_creator']);
    $is_maker = isset($_SESSION[$OJ_NAME . "_m$cid"]);
    $is_browser = isset($_SESSION[$OJ_NAME . '_source_browser']);
    if (!$is_admin && !$is_creator && !$is_maker && !$is_browser) {
        $only_me = true;
        $current_user_id = $uid;
    }
}

// 랭킹 데이터 구성
$user_cnt = 0;
$user_name = '';
$U = array();
$solution_cnt = count($result);
for ($i = 0; $i < $solution_cnt; $i++) {
    $row = $result[$i];
    $n_user = $row['user_id'];

    if ($only_me && $n_user !== $current_user_id) continue;

    if (strcmp($user_name, $n_user)) {
        $user_cnt++;
        $U[$user_cnt] = new TM();
        $U[$user_cnt]->user_id = $n_user;
        $U[$user_cnt]->nick = $row['nick'];
        $user_name = $n_user;
    }

    if ($row['result'] != 4 && $row['pass_rate'] >= 0.99) $row['pass_rate'] = 0;

    $time_offset = strtotime($row['in_date']) - $start_time;
    if (time() < $end_time + $OJ_RANK_LOCK_DELAY && $lock < strtotime($row['in_date']))
        $U[$user_cnt]->Add($row['num'], $time_offset, 0);
    else
        $U[$user_cnt]->Add($row['num'], $time_offset, $row['pass_rate']);
}
usort($U, "s_cmp");

// first blood
$first_blood = array_fill(0, $pid_cnt, "");

if ($OJ_MEMCACHE) {
    $sql = "SELECT s.num, s.user_id FROM solution s,
           (SELECT num, MIN(solution_id) minId FROM solution WHERE contest_id=$cid AND result=4 GROUP BY num) c
           WHERE s.solution_id = c.minId";
    $fb = mysql_query_cache($sql);
} else {
    $sql = "SELECT s.num, s.user_id FROM solution s,
           (SELECT num, MIN(solution_id) minId FROM solution WHERE contest_id=? AND result=4 GROUP BY num) c
           WHERE s.solution_id = c.minId";
    $fb = pdo_query($sql, $cid);
}
foreach ($fb as $row) {
    $first_blood[$row['num']] = $row['user_id'];
}

// 템플릿 출력
require("template/" . $OJ_TEMPLATE . "/contestrank-oi.php");

if (file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>
