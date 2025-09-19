<?php
session_start();
require_once "include/db_info.inc.php";
require_once "include/my_func.inc.php";
require_once "include/memcache.php";
require_once "include/const.inc.php";
require_once "include/cookie_helper.php"; // [ADD] lastlang 안전 쿠키용

if (isset($OJ_CSRF) && $OJ_CSRF && $OJ_TEMPLATE=="bs3" && !isset($_SESSION[$OJ_NAME.'_'.'http_judge']))
  require_once(dirname(__FILE__)."/include/csrf_check.php");

// 로그인 확인
if (!isset($_SESSION[$OJ_NAME . '_' . 'user_id'])) {
  $view_errors = "<a href=loginpage.php>$MSG_Login</a>";
  require("template/".$OJ_TEMPLATE."/error.php");
  exit(0);
}

$now = strftime("%Y-%m-%d %H:%M", time());
$user_id = $_SESSION[$OJ_NAME.'_'.'user_id'];

// 언어 파라미터
$language = isset($_POST['language']) ? intval($_POST['language']) : 0;

// 벤치마크 아닐 때 캡차 체크 사전 준비
if (!$OJ_BENCHMARK_MODE) {
  $sql = "SELECT count(1) FROM `solution` WHERE result<4";
  $result = mysql_query_cache($sql);
  $row = $result[0];
  if ($row[0] > 50) $OJ_VCODE = true;

  if ($OJ_VCODE) $vcode = isset($_POST["vcode"]) ? $_POST["vcode"] : "";

  if ($OJ_VCODE && ($_SESSION[$OJ_NAME.'_'."vcode"]==null || $vcode!=$_SESSION[$OJ_NAME.'_'."vcode"] || $vcode=="" || $vcode==null)) {
    $_SESSION[$OJ_NAME.'_'."vcode"] = null;
    $view_errors = $MSG_VCODE_WRONG."\\n";
    require "template/".$OJ_TEMPLATE."/error.php";
    exit(0);
  }
}

// ===== 제출 대상 판별 (단일문제 or 대회문제) =====
$test_run = false;
$title = "";
$id = 0;         // 문제 ID (확정 후 front/rear 코드 조회에 사용)
$cid = null;     // 대회 ID
$pid = null;     // 대회 내 문제 번호
$langmask = $OJ_LANGMASK;

// (A) 단일 문제 제출
if (isset($_POST['id'])) {
  $id = intval($_POST['id']);
  $test_run = ($id<=0);
  $langmask = $OJ_LANGMASK;

} else if (isset($_POST['pid']) && isset($_POST['cid']) && $_POST['cid']!=0) {
  // (B) 대회 문제 제출
  $pid = intval($_POST['pid']);
  $cid = intval($_POST['cid']);
  $test_run = ($cid<0);
  if ($test_run) $cid = -$cid;

  // 대회 유효성/권한/기간 확인
  $now_time_str = date('Y/m/d H:i:s D', time());
  // 관리자/출제자/문제편집자는 기간 무시 가능
  if (isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])) {
    $sql = "SELECT `private`, langmask, title FROM `contest` WHERE `contest_id`=?";
    $cres = pdo_query($sql, $cid);
  } else {
    $sql = "SELECT `private`, langmask, title FROM `contest`
            WHERE `contest_id`=? AND `start_time`<=? AND ?<`end_time`";
    $cres = pdo_query($sql, $cid, $now_time_str, $now_time_str);
  }
  if (!$cres || count($cres)!=1) {
    $view_errors = $MSG_NOT_IN_CONTEST;
    require "template/".$OJ_TEMPLATE."/error.php";
    exit(0);
  } else {
    $row = $cres[0];
    $isprivate = intval(isset($row['private']) ? $row['private'] : $row[0]);
    $langmask  =        isset($row['langmask']) ? $row['langmask'] : $row[1];
    $title     =        isset($row['title'])    ? $row['title']    : $row[2];

    if ($isprivate==1 && !isset($_SESSION[$OJ_NAME.'_'.'c'.$cid])) {
      $sql = "SELECT count(*) FROM `privilege` WHERE `user_id`=? AND `rightstr`=?";
      $rs = pdo_query($sql, $user_id, "c$cid");
      $ccnt = intval($rs[0][0]);
      if ($ccnt==0 && !isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
        $view_errors = $MSG_NOT_INVITED."\n";
        require "template/".$OJ_TEMPLATE."/error.php";
        exit(0);
      }
    }
  }

  // 대회 내 문제 → 실제 problem_id 얻기
  $sql = "SELECT `problem_id` FROM `contest_problem` WHERE `contest_id`=? AND `num`=?";
  $pres = pdo_query($sql, $cid, $pid);
  if (!$pres || count($pres)!=1) {
    $view_errors = $MSG_NO_PROBLEM."\n";
    require "template/".$OJ_TEMPLATE."/error.php";
    exit(0);
  } else {
    $id = intval($pres[0]['problem_id']);
    if ($test_run) $id = -$id;  // 테스트런일 땐 음수 전환
  }

} else {
  // (C) custom test run (문제 ID 미제공)
  $id = 0;
  $langmask = $OJ_LANGMASK;
  $test_run = true;
}

// 언어 인덱스 보정/검증
if ($language < 0 || $language >= count($language_name)) $language = 0;
// 비트마스크 차단 언어
if ($langmask & (1<<$language)) {
  $view_errors = $MSG_NO_PLS."\n[$language][$langmask][".($langmask&(1<<$language))."]";
  require "template/".$OJ_TEMPLATE."/error.php";
  exit(0);
}

// 제출 소스/입력 받기
$source = isset($_POST['source']) ? $_POST['source'] : "";
$input_text = isset($_POST['input_text']) ? $_POST['input_text'] : "";

// encoded_submit 지원
if (isset($_POST['encoded_submit'])) $source = base64_decode($source);

// 줄바꿈 정규화
$input_text = preg_replace("(\r\n)", "\n", $input_text);

// === 여기까지 오면 $id 확정됨 ===

// (1) problem front/rear/ban 코드 로딩  [MOVE] ← 핵심 수정
$front_code = "";
$rear_code  = "";
$ban_code   = "";
if ($id !== 0) {
  // test_run에서 $id가 음수일 수 있으므로 절댓값으로 원래 문제를 찾음
  $abs_pid = abs($id);
  $rowp = pdo_query("SELECT `front_code`,`rear_code`,`ban_code` FROM `problem` WHERE `problem_id`=?", $abs_pid);
  if ($rowp && count($rowp)>0) {
    $front_code = (string)$rowp[0]['front_code'];
    $rear_code  = (string)$rowp[0]['rear_code'];
    $ban_code   = (string)$rowp[0]['ban_code'];
  }
}

// (2) 금지어 검사
if (!empty($ban_code)) {
  $ban_words = explode('/', $ban_code);
  foreach ($ban_words as $bw) {
    $bw = trim($bw);
    if ($bw!=='' && strpos($source, $bw)!==false) {
      $view_errors = $MSG_CODE_USE_BANCODE;
      require "template/".$OJ_TEMPLATE."/error.php";
      exit(0);
    }
  }
}

// (3) front/rear 코드 삽입 (언어 구분 토큰: //"언어"//)
if (!empty($front_code) || !empty($rear_code)) {
  $find_str = "//".$language_name[$language]."//";
  $front_code_print = "";
  $rear_code_print  = "";

  if (strpos($front_code, $find_str)!==false) {
    $split = explode($find_str, $front_code, 2);
    if (isset($split[1])) $front_code_print = explode("//", $split[1])[0];
  }
  if (strpos($rear_code, $find_str)!==false) {
    $split = explode($find_str, $rear_code, 2);
    if (isset($split[1])) $rear_code_print = explode("//", $split[1])[0];
  }
  $source = trim($front_code_print)."\n".$source."\n".trim($rear_code_print);
}

// (4) 인코딩 주석 (파이썬)
if ($language == 6) { // Python3
  if (strpos($source, "# coding=") !== 0 && strpos($source, "# -*- coding:") !== 0) {
    $source = "# coding=utf-8\n".$source;
  }
}

// (5) custom test run에서만 실제 채점ID 0으로 전환
$source_user = $source;
if ($test_run) $id = 0;

// (6) prepend/append 파일 자동 삽입
$prepend_file = "$OJ_DATA/$id/prepend.".$language_ext[$language];
if (isset($OJ_APPENDCODE) && $OJ_APPENDCODE && file_exists($prepend_file)) {
  $source = file_get_contents($prepend_file)."\n".$source;
}
$append_file = "$OJ_DATA/$id/append.".$language_ext[$language];
if (isset($OJ_APPENDCODE) && $OJ_APPENDCODE && file_exists($append_file)) {
  $source .= "\n".file_get_contents($append_file);
}

// (7) 소스 길이 검사
$len = strlen($source);
if ($len < 2) {
  $view_errors = $MSG_TOO_SHORT."<br>";
  require "template/".$OJ_TEMPLATE."/error.php";
  exit(0);
}
if ($len > 65536) {
  $view_errors = $MSG_TOO_LONG."<br>";
  require "template/".$OJ_TEMPLATE."/error.php";
  exit(0);
}

// (8) lastlang 쿠키 저장 (안전 세터)
safe_setcookie('lastlang', (string)$language, time()+360000, "/");

// (9) 클라이언트 IP
$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
  $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
  $tmp_ip = explode(',', $REMOTE_ADDR);
  $ip = htmlentities($tmp_ip[0], ENT_QUOTES, "UTF-8");
}

// (10) 제출간격 제한
if (!$OJ_BENCHMARK_MODE) {
  $now10 = strftime("%Y-%m-%d %X", time()-10);
  $res = pdo_query("SELECT `in_date` FROM `solution` WHERE `user_id`=? AND in_date>? ORDER BY `in_date` DESC LIMIT 1", $user_id, $now10);
  if ($res && count($res)==1) {
    $view_errors = $MSG_BREAK_TIME."<br>";
    require "template/".$OJ_TEMPLATE."/error.php";
    exit(0);
  }
}

// (11) 실제 INSERT (언어 마스크 허용 확인)
if (~$OJ_LANGMASK & (1<<$language)) {
  $nick = "Guest";
  $r = pdo_query("SELECT nick FROM users WHERE user_id=?", $user_id);
  if ($r && isset($r[0][0])) $nick = $r[0][0];

  if (!isset($pid)) {
    // 단일 문제
    $sql = "INSERT INTO solution(problem_id,user_id,nick,in_date,language,ip,code_length,result)
            VALUES(?,?,?,NOW(),?,?,?,14)";
    $insert_id = pdo_query($sql, $id, $user_id, $nick, $language, $ip, $len);
  } else {
    // 대회 문제
    $sql = "INSERT INTO solution(problem_id,user_id,nick,in_date,language,ip,code_length,contest_id,num,result)
            VALUES(?,?,?,NOW(),?,?,?,?,?,14)";

    // NOIP + 한 번만 허용 옵션 처리
    if ((stripos($title,$OJ_NOIP_KEYWORD)!==false) && isset($OJ_OI_1_SOLUTION_ONLY) && $OJ_OI_1_SOLUTION_ONLY) {
      $delete = pdo_query("DELETE FROM solution WHERE contest_id=? AND user_id=? AND num=?", $cid, $user_id, $pid);
      if ($delete>0) {
        pdo_query("UPDATE problem p INNER JOIN (SELECT problem_id pid ,count(1) ac FROM solution WHERE problem_id=? AND result=4) s ON p.problem_id=s.pid SET p.accepted=s.ac;", abs($id));
        pdo_query("UPDATE problem p INNER JOIN (SELECT problem_id pid ,count(1) submit FROM solution WHERE problem_id=?) s ON p.problem_id=s.pid SET p.submit=s.submit;", abs($id));
      }
    }

    $insert_id = pdo_query($sql, abs($id), $user_id, $nick, $language, $ip, $len, $cid, $pid); // test_run때 id 음수였던 것 abs로 정규화
  }

  // 소스 저장
  pdo_query("INSERT INTO `source_code_user`(`solution_id`,`source`) VALUES(?,?)", $insert_id, $source_user);
  pdo_query("INSERT INTO `source_code`(`solution_id`,`source`) VALUES(?,?)", $insert_id, $source);

  if ($test_run) {
    pdo_query("INSERT INTO `custominput`(`solution_id`,`input_text`) VALUES(?,?)", $insert_id, $input_text);
  } else {
    pdo_query("UPDATE problem SET submit=submit+1 WHERE problem_id=?", abs($id));
    if (isset($cid) && $cid>0) {
      pdo_query("UPDATE contest_problem SET c_submit=c_submit+1 WHERE contest_id=? AND num=?", $cid, $pid);
    }
  }

  // 대기열로 상태 대기(0)
  pdo_query("UPDATE solution SET result=0 WHERE solution_id=?", $insert_id);

  // Redis 큐
  if ($OJ_REDIS) {
    $redis = new Redis();
    $redis->connect($OJ_REDISSERVER, $OJ_REDISPORT);
    if (isset($OJ_REDISAUTH)) $redis->auth($OJ_REDISAUTH);
    $redis->lpush($OJ_REDISQNAME, $insert_id);
    $redis->close();
  }
}

// UDP 트리거
if (isset($OJ_UDP) && $OJ_UDP) {
  trigger_judge($insert_id); // my_func.inc.php
}

// 벤치마크 모드면 바로 출력
if ($OJ_BENCHMARK_MODE) {
  echo $insert_id;
  exit(0);
}

// 캐시 무효화
$statusURI = strstr($_SERVER['REQUEST_URI'], "submit", true)."status.php";
if (isset($cid)) $statusURI .= "?cid=$cid";

$sid = "";
if (isset($_SESSION[$OJ_NAME.'_'.'user_id'])) $sid .= session_id().$_SERVER['REMOTE_ADDR'];
if (isset($_SERVER["REQUEST_URI"])) $sid .= $statusURI;
$sid = md5($sid);
$file = "cache/cache_$sid.html";

if ($OJ_MEMCACHE) {
  $mem = new Memcache();
  if ($OJ_SAE) $mem = memcache_init();
  else $mem->connect($OJ_MEMSERVER, $OJ_MEMPORT);
  $mem->delete($file, 0);
} elseif (file_exists($file)) {
  unlink($file);
}

// 리다이렉트 / 결과 반환
$statusURI = "status.php?user_id=".$_SESSION[$OJ_NAME.'_'.'user_id'];
if (isset($cid)) $statusURI .= "&cid=$cid&fixed=";

if (!$test_run) {
  header("Location: $statusURI");
} else {
  if (isset($_GET['ajax'])) {
    echo $insert_id;
  } else {
    ?>
    <script>window.parent.setTimeout("fresh_result('<?php echo $insert_id; ?>')",1000);</script>
    <?php
  }
}
?>
