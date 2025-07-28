<?php
$OJ_CACHE_SHARE = true;
$cache_time = 10;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once("./include/const.inc.php");
require_once("./include/my_func.inc.php");

$view_title = $MSG_CONTEST.$MSG_RANKLIST;

$title = "";

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    if (isset($OJ_GUEST) && $OJ_GUEST) {
        $_SESSION[$OJ_NAME.'_user_id'] = "Guest";
    } else {
        $view_errors = "<button><a href=loginpage.php>$MSG_Login</a></button>";
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
}

class TM {
    var $solved = 0;
    var $time = 0;
    var $p_wa_num;
    var $p_ac_sec;
    var $user_id;
    var $nick;

    function TM() {
        $this->solved = 0;
        $this->time = 0;
        $this->p_wa_num = array(0);
        $this->p_ac_sec = array(0);
    }

    function Add($pid, $sec, $res) {
        global $OJ_CE_PENALTY;
        if ($sec < 0) return;
        if (isset($this->p_ac_sec[$pid]) && $this->p_ac_sec[$pid] > 0)
            return;

        if ($res != 4) {
            if (isset($OJ_CE_PENALTY) && !$OJ_CE_PENALTY && $res == 11)
                return;
            if (isset($this->p_wa_num[$pid]))
                $this->p_wa_num[$pid]++;
            else
                $this->p_wa_num[$pid] = 1;
        } else {
            $this->p_ac_sec[$pid] = $sec;
            $this->solved++;
            if (!isset($this->p_wa_num[$pid]))
                $this->p_wa_num[$pid] = 0;
            $this->time += $sec + $this->p_wa_num[$pid] * 60;
        }
    }
}

function s_cmp($A, $B) {
    if ($A->solved != $B->solved)
        return $A->solved < $B->solved;
    else
        return $A->time > $B->time;
}

// contest start
if (!isset($_GET['cid'])) die("No Such Contest!");
$cid = intval($_GET['cid']);

function getRankingUpdateCount($cid) {
    echo "<!-- INIT COUNT: $cid -->"; // 디버깅용

    // 수동으로 PDO 객체 만들기
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT update_count FROM ranking_cache WHERE contest_id=?");
        $stmt->execute([$cid]);
        $row = $stmt->fetch();

        return $row ? intval($row['update_count']) : 0;
    } catch (PDOException $e) {
        echo "<!-- DB ERROR: " . $e->getMessage() . " -->";
        return 0;
    }
}



// 1. contest 정보 가져오기
if ($OJ_MEMCACHE) {
    $sql = "SELECT `start_time`,`title`,`end_time`,`exam_mode` FROM `contest` WHERE `contest_id`=$cid";
    require("./include/memcache.php");
    $contest_result = mysql_query_cache($sql);
} else {
    $sql = "SELECT `start_time`,`title`,`end_time`,`exam_mode` FROM `contest` WHERE `contest_id`=?";
    $contest_result = pdo_query($sql, $cid);
}

if (!$contest_result || count($contest_result) == 0) {
    $view_errors = "No Such Contest";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$contest_row = $contest_result[0];
$start_time = strtotime($contest_row['start_time']);
$end_time = strtotime($contest_row['end_time']);
$title = $contest_row['title'];
$exam_mode = intval($contest_row['exam_mode']);

if ($start_time == 0 || $start_time > time()) {
    $view_errors = "Contest Not Started!";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// NOIP 금지 조건
$noip = (time() < $end_time) && (stripos($title, $OJ_NOIP_KEYWORD) !== false);
if (
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME."_m$cid"]) ||
    isset($_SESSION[$OJ_NAME.'_source_browser']) ||
    isset($_SESSION[$OJ_NAME.'_contest_creator'])
) {
    $noip = false;
}
if ($noip) {
    $view_errors = "<h2>$MSG_NOIP_WARNING</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// rank lock
if (!isset($OJ_RANK_LOCK_PERCENT)) $OJ_RANK_LOCK_PERCENT = 0;
$lock = $end_time - ($end_time - $start_time) * $OJ_RANK_LOCK_PERCENT;
$view_lock_time = $start_time + ($end_time - $start_time) * (1 - $OJ_RANK_LOCK_PERCENT);
$locked_msg = "";
if (time() > $view_lock_time && time() < $end_time + $OJ_RANK_LOCK_DELAY) {
    $locked_msg = "The board has been locked.";
}

// 2. 문제 개수
if ($OJ_MEMCACHE) {
    $sql = "SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`='$cid'";
    $problem_result = mysql_query_cache($sql);
} else {
    $sql = "SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`=?";
    $problem_result = pdo_query($sql, $cid);
}
$pid_cnt = intval($problem_result[0]['pbc']);

// 3. 제출 결과 가져오기
require("./include/contest_solutions.php");

// 4. exam_mode 분기
$user_cnt = 0;
$user_name = '';
$U = array();
$only_me = false;

if ($exam_mode == 1) {
    $uid = $_SESSION[$OJ_NAME.'_user_id'];
    $is_admin = isset($_SESSION[$OJ_NAME.'_administrator']);
    $is_creator = isset($_SESSION[$OJ_NAME.'_contest_creator']);
    $is_maker = isset($_SESSION[$OJ_NAME."_m$cid"]);
    $is_browser = isset($_SESSION[$OJ_NAME.'_source_browser']);
    if (!$is_admin && !$is_creator && !$is_maker && !$is_browser) {
        $only_me = true;
        $current_user_id = $uid;
    }
}

// 5. 랭킹 계산
$solution_cnt = count($result);
for ($i = 0; $i < $solution_cnt; $i++) {
    $row = $result[$i];
    $n_user = $row['user_id'];

    if ($only_me && $n_user !== $current_user_id) {
        continue;
    }

    if (strcmp($user_name, $n_user)) {
        $user_cnt++;
        $U[$user_cnt] = new TM();
        $U[$user_cnt]->user_id = $n_user;
        $U[$user_cnt]->nick = $row['nick'];
        $user_name = $n_user;
    }

    $time_offset = strtotime($row['in_date']) - $start_time;
    if (time() < $end_time + $OJ_RANK_LOCK_DELAY && $lock < strtotime($row['in_date'])) {
        $U[$user_cnt]->Add($row['num'], $time_offset, 0);
    } else {
        $U[$user_cnt]->Add($row['num'], $time_offset, intval($row['result']));
    }
}
usort($U, "s_cmp");

// 6. first blood
$first_blood = array_fill(0, $pid_cnt, "");

if ($OJ_MEMCACHE) {
    $sql = "SELECT s.num, s.user_id FROM solution s,
           (SELECT num, MIN(solution_id) minId FROM solution WHERE contest_id=$cid AND result=4 GROUP BY num) c
           WHERE s.solution_id = c.minId";
    $fb_result = mysql_query_cache($sql);
} else {
    $sql = "SELECT s.num, s.user_id FROM solution s,
           (SELECT num, MIN(solution_id) minId FROM solution WHERE contest_id=? AND result=4 GROUP BY num) c
           WHERE s.solution_id = c.minId";
    $fb_result = pdo_query($sql, $cid);
}
$fb_cnt = count($fb_result);
for ($i = 0; $i < $fb_cnt; $i++) {
    $row = $fb_result[$i];
    $first_blood[$row['num']] = $row['user_id'];
}

// 7. 출력
$initial_update_count = getRankingUpdateCount($cid);
error_log("Initial Ranking Count: $initial_update_count"); // 또는 로그로 출력

require("template/".$OJ_TEMPLATE."/contestrank.php");

if (file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>
