<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

////////////////////////////Common head
$cache_time = 2;
$OJ_CACHE_SHARE = false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
$view_title = "$MSG_STATUS";
// 로그인 하기 전에는 채점기록 숨기기 
if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	if (isset($OJ_GUEST) && $OJ_GUEST) {
		$_SESSION[$OJ_NAME.'_'.'user_id'] = "Guest";
	}
	else {
		$view_errors = "<button><a href=loginpage.php>$MSG_Login</a></button>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
}
function formatTimeLength($length) {
  $hour = 0;
  $minute = 0;
  $second = 0;
  $result = '';

  global $MSG_SECONDS, $MSG_MINUTES, $MSG_HOURS, $MSG_DAYS;

  if ($length>=60) {
    $second = $length%60;
    
    if ($second>0 && $second<10) {
      $result = '0'.$second.' '.$MSG_SECONDS;}
    else if ($second>0) {
      $result = $second.' '.$MSG_SECONDS;
    }

    $length = floor($length/60);
    if ($length >= 60) {
      $minute = $length%60;
      
      if ($minute==0) {
        if ($result != '') {
          $result = '00'.' '.$MSG_MINUTES.' '.$result;
        }
      }
      else if ($minute>0 && $minute<10) {
        if ($result != '') {
          $result = '0'.$minute.' '.$MSG_MINUTES.' '.$result;}
        }
        else {
          $result = $minute.' '.$MSG_MINUTES.' '.$result;
        }
        
        $length = floor($length/60);

        if ($length >= 24) {
          $hour = $length%24;

        if ($hour==0) {
          if ($result != '') {
            $result = '00'.' '.$MSG_HOURS.' '.$result;
          }
        }
        else if ($hour>0 && $hour<10) {
          if($result != '') {
            $result = '0'.$hour.' '.$MSG_HOURS.' '.$result;
          }
        }
        else {
          $result = $hour.' '.$MSG_HOURS.' '.$result;
        }

        $length = floor($length / 24);
        $result = $length .$MSG_DAYS.' '.$result;
      }
      else {
        $result = $length.' '.$MSG_HOURS.' '.$result;
      }
    }
    else {
      $result = $length.' '.$MSG_MINUTES.' '.$result;
    }
  }
  else {
    $result = $length.' '.$MSG_SECONDS;
  }
  return $result;
}

require_once("./include/my_func.inc.php");

if (isset($OJ_LANG)) {
  require_once("./lang/$OJ_LANG.php");
}

require_once("./include/const.inc.php");


$str2 = "";
$lock = false;
$lock_time = date("Y-m-d H:i:s",time());

$sql = "WHERE problem_id>0 ";

if (isset($_GET['cid'])) {
  $cid = intval($_GET['cid']);
  $sql = $sql." AND `contest_id`='$cid' and num>=0 ";
  $str2 = $str2."&cid=$cid";
  $sql_lock = "SELECT `start_time`,`title`,`end_time`, `codevisible`,`exam_mode` FROM `contest` WHERE `contest_id`=?";
  $result = pdo_query($sql_lock,$cid);
  $rows_cnt = count($result);
  $start_time = 0;
  $end_time = 0;


  if ($rows_cnt>0) {
    $row = $result[0];
    $start_time = strtotime($row[0]);
    $title = $row[1];
    $end_time = strtotime($row[2]);
    $codevisible = isset($row['codevisible']) ? intval($row['codevisible']) : 0;
    $exam_mode = isset($row['exam_mode']) ? intval($row['exam_mode']) : 0;
    
	$noip = (time()<$end_time) && (stripos($title,$OJ_NOIP_KEYWORD)!==false);
	if(isset($_SESSION[$OJ_NAME.'_'."administrator"])||
		isset($_SESSION[$OJ_NAME.'_'."m$cid"])||
		isset($_SESSION[$OJ_NAME.'_'."source_browser"])||
		isset($_SESSION[$OJ_NAME.'_'."contest_creator"])
	   ) $noip=false;
    if($noip){
      $view_errors =  "<h2> $MSG_NOIP_WARNING <a href=\"contest.php?cid=$cid\">대회로 돌아가기</a></h2>";
      $refererUrl = parse_url($_SERVER['HTTP_REFERER']);
      if($refererUrl['path']=="/submitpage.php") 
	$view_errors="<h2>성공적으로 제출됨!</h2><a href=\"contest.php?cid=$cid\">대회로 돌아가기</a></h2>";
      require("template/".$OJ_TEMPLATE."/error.php");
      exit(0);
    }
  }

  $lock_time = $end_time-($end_time-$start_time)*$OJ_RANK_LOCK_PERCENT;
  //$lock_time=date("Y-m-d H:i:s",$lock_time);
  $time_sql = "";
  //echo $lock.'-'.date("Y-m-d H:i:s",$lock);
  if (time()>$lock_time && time()<$end_time) {
    //$lock_time=date("Y-m-d H:i:s",$lock_time);
    //echo $time_sql;
    $lock = true;
  }
  else {
    $lock = false;
  }
        
  //require_once("contest-header.php");
}
else {
  //require_once("oj-header.php");
   if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])
	||isset($_SESSION[$OJ_NAME.'_'.'source_browser'])
	||(isset($_SESSION[$OJ_NAME.'_'.'user_id'])
	&&(isset($_GET['user_id'])&&$_GET['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']))
  ){
    if(isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){
                  $sql="WHERE problem_id>0  ";
    }else if ($_SESSION[$OJ_NAME.'_'.'user_id']!="guest")
                  $sql="WHERE (contest_id=0 or contest_id is null)  ";
    }else{
        $sql="WHERE problem_id>0 and (contest_id=0 or contest_id is null) ";
    }
}

$start_first = true;
$order_str = " ORDER BY `solution_id` DESC ";

// check the top arg
if (isset($_GET['top'])) {
  $top = strval(intval($_GET['top']));
  if ($top!=-1)
    $sql = $sql."AND `solution_id`<='".$top."' ";
}

// check the problem arg
$problem_id = "";
if (isset($_GET['problem_id']) && $_GET['problem_id']!="") {
  if (isset($_GET['cid'])) {
    $problem_id = htmlentities($_GET['problem_id'],ENT_QUOTES,'UTF-8');
    $num = array_search($problem_id,$PID);
    $problem_id = $PID[$num];
    $sql = $sql."AND `num`='".$num."' ";
    $str2 = $str2."&problem_id=".trim($problem_id);
  }
  else {
    $problem_id = strval(intval($_GET['problem_id']));
    if ($problem_id!='0') {
      $sql = $sql."AND `problem_id`='".$problem_id."' ";
      $str2 = $str2."&problem_id=".trim($problem_id);
    }
    else
      $problem_id = "";
  }
}



// check the user_id arg
$user_id = "";
// source_browser로도 대회에서 푼 학생의 채점현황을 모두 볼수 있도록 추가
if (isset($OJ_ON_SITE_CONTEST_ID)&&$OJ_ON_SITE_CONTEST_ID>0&&!isset($_SESSION[$OJ_NAME.'_'.'administrator'])&&!isset($_SESSION[$OJ_NAME.'_'.'source_browser'])) {
  $_GET['user_id'] = $_SESSION[$OJ_NAME.'_'.'user_id'];   
}

if (isset($_GET['user_id'])) {
  $user_id = trim($_GET['user_id']);
  if ( $user_id!="" && is_valid_user_name($user_id)) {
      $sql = $sql."AND `user_id`=? ";
      if ($str2!="")
      		$str2 = $str2."&";
      $str2 = $str2."user_id=".urlencode($user_id);
  }
  else{
	  $user_id = "";
  }
}

if (isset($_GET['language']))
  $language = intval($_GET['language']);
else
  $language = -1;

if ($language>count($language_ext) || $language<0)
  $language = -1;

if ($language!=-1) {
  $sql = $sql."AND `language`='".($language)."' ";
  $str2 = $str2."&language=".$language;
}

if (isset($_GET['jresult']))
  $result = intval($_GET['jresult']);
else
  $result = -1;

if ($result>12 || $result<0)
  $result = -1;

if ($result!=-1 && !$lock) {
  $sql = $sql."AND `result`='".($result)."' ";
  $str2 = $str2."&jresult=".$result;
}

if ($OJ_SIM) {
  //$old=$sql;
  $sql = "select * from solution solution left join `sim` sim on solution.solution_id=sim.s_id ".$sql;
  if (isset($_GET['showsim']) && intval($_GET['showsim'])>0) {
    $showsim = intval($_GET['showsim']);
    $sql .= " and sim.sim>=$showsim";
    $str2 .= "&showsim=$showsim";
  }
  
  //$sql=$sql.$order_str." LIMIT 20";
}
else {
  $sql = "select * from `solution` ".$sql;
}

//echo $sql;

$sql = $sql.$order_str." LIMIT 50";
//echo $sql;


if (isset($_GET['user_id'])) {
  $result = pdo_query($sql,$user_id);
}else{
  $result = pdo_query($sql);
}
  
if ($result)
  $rows_cnt = count($result);
else
  $rows_cnt = 0;

$top = $bottom=-1;
$cnt = 0;
if ($start_first) {
  $row_start = 0;
  $row_add = 1;
}
else {
  $row_start = $rows_cnt-1;
  $row_add = -1;
}

$view_status = Array();

$last = 0;
for ($i=0; $i<$rows_cnt; $i++) {
  $row = $result[$i];
  //$view_status[$i]=$row;
  // 수행모드 + 본인 아님 → 전체 정보 숨김
  // 수행모드 + 본인 아님 → 전체 정보 숨김
  if (isset($exam_mode) && $exam_mode == 1 &&
      (!isset($_SESSION[$OJ_NAME.'_'.'user_id']) || $_SESSION[$OJ_NAME.'_'.'user_id'] !== $row['user_id']) &&
      !isset($_SESSION[$OJ_NAME.'_'.'administrator']) &&
      !isset($_SESSION[$OJ_NAME.'_'.'source_browser']) &&
      !isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) &&
      !isset($_SESSION[$OJ_NAME."_m$cid"])
  ) {
    $view_status[$i][0] = "수행모드"; // 번호
    $view_status[$i][1] = "----"; // 사용자
    $view_status[$i]['nick'] = "----"; // 별명
    $view_status[$i][2] = "----"; // 문제
    $view_status[$i][3] = "----"; // 결과
    $view_status[$i][4] = "----"; // 메모리
    $view_status[$i][5] = "----"; // 시간
    $view_status[$i][6] = "----"; // 코드
    $view_status[$i][7] = "----"; // 코드길이
    $view_status[$i][8] = "수행모드"; // 제출 시간
    // $view_status[$i][9] = "----"; // 채점기
    continue; // 이 행은 여기까지만 처리
  }

  if ($i==0 && $row['result']<4)
    $last = $row['solution_id'];

  if ($top==-1)
    $top = $row['solution_id'];
  
  $bottom = $row['solution_id'];
  $flag = (
    isset($_SESSION[$OJ_NAME.'_'.'administrator']) ||
    isset($_SESSION[$OJ_NAME.'_'.'source_browser']) ||
    isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) ||
    isset($_SESSION[$OJ_NAME."_m$cid"]) ||(
        $exam_mode == 0 // 수행모드 아님
        || (isset($_SESSION[$OJ_NAME.'_'.'user_id']) && $_SESSION[$OJ_NAME.'_'.'user_id'] === $row['user_id'])
    )
  );



  $cnt = 1-$cnt;
  
  $view_status[$i][0] = $row['solution_id'];
       

  if ($row['contest_id']>0) {

    if (isset($_SESSION[$OJ_NAME.'_'.'administrator']))
      $view_status[$i][1] = "<a href='contestrank.php?cid=".$row['contest_id']."&user_id=".$row['user_id']."#".$row['user_id']."' title='".$row['ip']."'>".$row['user_id']."</a>";
    else if($exam_mode ==0 || isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){
      $view_status[$i][1] = "<a href='contestrank.php?cid=".$row['contest_id']."&user_id=".$row['user_id']."#".$row['user_id']."'>".$row['user_id']."</a>";
    }
    else{
      $view_status[$i][1] = "수행모드";
    }
  }
  else {
    if (isset($_SESSION[$OJ_NAME.'_'.'administrator']))
      $view_status[$i][1] = "<a href='userinfo.php?user=".$row['user_id']."' title='".$row['nick']."[".$row['ip']."]'>".$row['user_id']."</a>";
    else
      $view_status[$i][1] = "<a href='userinfo.php?user=".$row['user_id']."' title='".$row['nick']."'>".$row['user_id']."</a>";
  }
  if(isset($_SESSION[$OJ_NAME.'_'.'administrator']))
    $view_status[$i]['nick']=$row['nick'];
  else
    $view_status[$i]['nick']="비공개";  // 일반 사용자는 별명 숨김

  if ($row['contest_id']>0) {
    if (isset($end_time) && time() < $end_time) {
      $view_status[$i][2] = "<div><a href='problem.php?cid=".$row['contest_id']."&pid=".$row['num']."'>";
      if (isset($cid)) {
        $view_status[$i][2] .= $PID[$row['num']];
      }
      else {
        $view_status[$i][2] .= $row['problem_id'];
      }
      $view_status[$i][2] .= "</div></a>";
    }
    else {
      $view_status[$i][2] = "<div class=center>";
      if (isset($cid)) {

        //check the problem will be use remained contest/exam
        $tpid = intval($row['problem_id']);
        $sql = "SELECT `problem_id` FROM `problem` WHERE `problem_id`=? AND `problem_id` IN (
          SELECT `problem_id` FROM `contest_problem` WHERE `contest_id` IN (
            SELECT `contest_id` FROM `contest` WHERE (`defunct`='N' AND now()<`end_time`)
          )
        )";

        $tresult = pdo_query($sql, $tpid);

        if (intval($tresult) != 0)   //if the problem will be use remaind contes/exam
          $view_status[$i][2] .= $PID[$row['num']]; //hide link
        else
          $view_status[$i][2] .= "<a href='problem.php?id=".$row['problem_id']."'>".$PID[$row['num']]."</a>";
      }
      else {
        $view_status[$i][2] .= "<a href='problem.php?id=".$row['problem_id']."'>".$row['problem_id']."</a>";
      }
      $view_status[$i][2] .= "</div>";
    }
  }
  else {
    $view_status[$i][2] = "<div class=center><a href='problem.php?id=".$row['problem_id']."'>".$row['problem_id']."</a></div>";
  }

  switch($row['result']) {
    case 4:
      $MSG_Tips = $MSG_HELP_AC; break;
    case 5:
      $MSG_Tips = $MSG_HELP_PE; break;
    case 6:
      $MSG_Tips = $MSG_HELP_WA; break;
    case 7:
      $MSG_Tips = $MSG_HELP_TLE; break;
    case 8:
      $MSG_Tips = $MSG_HELP_MLE; break;
    case 9:
      $MSG_Tips = $MSG_HELP_OLE; break;
    case 10:
      $MSG_Tips = $MSG_HELP_RE; break;
    case 11:
      $MSG_Tips = $MSG_HELP_CE; break;
    default:
      $MSG_Tips = "";
  }

  $AC_RATE = intval($row['pass_rate']*100);
  if (isset($OJ_MARK) && $OJ_MARK!="mark") {
    $mark = "";
  }
  else {
    if($AC_RATE > 99)
      $mark = "";
    else
      $mark = " "."(정답비율:".$AC_RATE."%)";
  }

  if ((!isset($_SESSION[$OJ_NAME.'_'.'user_id']) || $row['user_id']!=$_SESSION[$OJ_NAME.'_'.'user_id']) && !isset($_SESSION[$OJ_NAME.'_'.'source_browser']))
    $mark = "";

  $view_status[$i][3] = "<span class='hidden' style='display:none' result=".$row['result']."></span>";
  if (intval($row['result'])==11 && ((isset($_SESSION[$OJ_NAME.'_'.'user_id']) && $row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))) {
    $view_status[$i][3] .= "<a href=ceinfo.php?sid=".$row['solution_id']." class='".$judge_color[$row['result']]."' title='$MSG_Tips'>".$MSG_Compile_Error."</a>";
  }
  else if ((((intval($row['result'])==8 || intval($row['result'])==7 || intval($row['result'])==5 || intval($row['result'])==6) && ($OJ_SHOW_DIFF || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))) || $row['result']==10 || $row['result']==13) && ((isset($_SESSION[$OJ_NAME.'_'.'user_id']) && $row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))) {
    
    $view_status[$i][3] .= "<a href=reinfo.php?sid=".$row['solution_id']." class='".$judge_color[$row['result']]."' title='$MSG_Tips'>".$judge_result[$row['result']].$mark."</a>";
  }
  else {
    if (!$lock || $lock_time>$row['in_date'] || $row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']) {
      if ($OJ_SIM && $row['sim']>80 && $row['sim_s_id']!=$row['s_id']) {
        
        $view_status[$i][3] .= "<a href=reinfo.php?sid=".$row['solution_id']." class='".$judge_color[$row['result']]."' title='$MSG_Tips'>*".$judge_result[$row['result']];

        if ($row['result']!=4 && isset($row['pass_rate']) && $row['pass_rate']!=1)
          $view_status[$i][3] .= $mark."</a>";
        else
          $view_status[$i][3] .= "</a>";

        if( isset($_SESSION[$OJ_NAME.'_'.'source_browser'])) {
          $view_status[$i][3] .= "<a href=comparesource.php?left=".$row['sim_s_id']."&right=".$row['solution_id']." class='label label-info' target=original>".$row['sim_s_id']."(".$row['sim']."%)</a>";
        }
        else {
          $view_status[$i][3] .= "<span class='label label-info'>".$row['sim_s_id']."</span>";
        }
        
        if (isset($_GET['showsim']) && isset($row['sim_s_id'])) {
          $view_status[$i][3] .= "<span sid='".$row['sim_s_id']."' class='original'></span>";
        }
      }
      else {
        // 정답일 경우($row['result']==4) 정보를 보여줄 필요가 없음.
        if($row['result']==4){
          $view_status[$i][3] .= "<span class='".$judge_color[$row['result']]."' title='$MSG_Tips'>".$judge_result[$row['result']].$mark."</span>";
        }
        else {
          $view_status[$i][3] .= "<a href=reinfo.php?sid=".$row['solution_id']." class='".$judge_color[$row['result']]."' title='$MSG_Tips'>".$judge_result[$row['result']].$mark."</a>";
        }
      }
    }
    else {
      $view_status[$i][3] = "----";
    }
  }

  if (isset($_SESSION[$OJ_NAME.'_'.'http_judge'])) {
    $view_status[$i][3] .= "<form class='http_judge_form form-inline'> <input type=hidden name=sid value='".$row['solution_id']."'>";
    $view_status[$i][3] .= "</form>";
  }
  
  if ($flag) {
    if ($row['result']>=4) {
      $view_status[$i][4] = "<div id=center>".$row['memory']."KB</div>";
      $view_status[$i][5] = "<div id=center>".$row['time']."ms</div>";
      //echo "=========".$row['memory']."========";
    }
    else {
      $view_status[$i][4] = "---";
      $view_status[$i][5] = "---";
    }
    
    //echo $row['result'];
    if (!(isset($_SESSION[$OJ_NAME.'_'.'user_id']) && strtolower($row['user_id'])==strtolower($_SESSION[$OJ_NAME.'_'.'user_id']) 
    || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))) {
      $view_status[$i][6] = $language_name[$row['language']];
    }
    else {


      $is_owner = (isset($_SESSION[$OJ_NAME.'_'.'user_id']) && strtolower($row['user_id']) == strtolower($_SESSION[$OJ_NAME.'_'.'user_id']));
      $is_admin = isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']) || isset($_SESSION[$OJ_NAME."_m$cid"]);

      if ($flag) {
        // ✅ flag: 관리자, 출제자, 본인, 수행모드 아님 → 링크 허용
        $view_status[$i][6] = "<a target=_self href=showsource.php?id=".$row['solution_id']."'>".$language_name[$row['language']]."</a>";
      } else {
        // 🔒 수행모드 + 본인 아님 등 → 링크 없음
        $view_status[$i][6] = $language_name[$row['language']];
      }


      // $is_admin = isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']) || isset($_SESSION[$OJ_NAME."_m$cid"]);

      if ($row["problem_id"] > 0) {
        if ($row['contest_id'] > 0) {
          if ((isset($end_time) && time() < $end_time) || $is_admin) {
            if ($exam_mode == 0 || $is_admin) {
              if ($codevisible == 0 || $is_admin) {
                $view_status[$i][6] .= "/<a target=_self href=\"submitpage.php?cid=".$row['contest_id']."&pid=".$row['num']."&sid=".$row['solution_id']."\">Edit</a>";
              } else {
                $view_status[$i][6] .= "/제한";
              }
            }
            else if ($exam_mode == 1 && $is_owner) {
              $view_status[$i][6] .= "/<a target=_self href=\"submitpage.php?cid=".$row['contest_id']."&pid=".$row['num']."&sid=".$row['solution_id']."\">Edit</a>";
            }
            else {
              $view_status[$i][6] .= "/수행모드";
            }
          }
        }
        else {
        // 연습 문제인 경우
          if ($is_owner || $is_admin) {
              if ($row['contest_id'] > 0) {
                  // 대회 문제
                  $view_status[$i][6] .= "/<a target=_self href=\"submitpage.php?cid=".$row['contest_id']."&pid=".$row['num']."&sid=".$row['solution_id']."\">Edit</a>";
              } else {
                  // 일반 문제
                  $view_status[$i][6] .= "/<a target=_self href=\"submitpage.php?id=".$row['problem_id']."&sid=".$row['solution_id']."\">Edit</a>";
              }

          }
        }
      }
    }
    
    $view_status[$i][7] = $row['code_length']." bytes";
        
  } else {
    // 수행모드이고 본인 제출이 아닌 경우만 --- 처리
    if ($exam_mode == 1 && (!isset($_SESSION[$OJ_NAME.'_'.'user_id']) || $_SESSION[$OJ_NAME.'_'.'user_id'] !== $row['user_id'])) {
        $view_status[$i][4] = "----";
        $view_status[$i][5] = "----";
        $view_status[$i][6] = "----";
        $view_status[$i][7] = "----";
    } else {
        // 수행모드가 아니거나 본인 제출이면 메모리, 시간, 코드 언어, 길이 보여주기
        $view_status[$i][4] = $row['memory']."KB";
        $view_status[$i][5] = $row['time']."ms";
        $view_status[$i][6] = $language_name[$row['language']];
        $view_status[$i][7] = $row['code_length']." bytes";
    }
}


  if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])) {
    $view_status[$i][8] = $row['in_date']."[".(strtotime($row['judgetime'])-strtotime($row['in_date']))."]";
    // $view_status[$i][9] = $row['judger'];
  }
  else
    $view_status[$i][8]= $row['in_date'];
}
  function updateRankingCache($contest_id) {
    try {
        $sql = "INSERT INTO ranking_cache (contest_id, update_count)
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE
                last_update = CURRENT_TIMESTAMP,
                update_count = update_count + 1";
        pdo_query($sql, $contest_id);
    } catch (Exception $e) {
        echo "<pre>랭킹 갱신 중 오류: ".$e->getMessage()."</pre>";
    }
}


  // 제출이 있으면 랭킹 갱신 카운트 증가
  if ($rows_cnt > 0 && isset($cid) && intval($cid) > 0) {
      updateRankingCache($cid);
  }

?>

<?php
/////////////////////////Template
if (isset($_GET['cid']))
  require("template/".$OJ_TEMPLATE."/conteststatus.php");
else
  require("template/".$OJ_TEMPLATE."/status.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
  require_once('./include/cache_end.php');
?>
