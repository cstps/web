<?php
        $OJ_CACHE_SHARE=false;
        $cache_time=10;
        require_once('./include/cache_start.php');
    require_once('./include/db_info.inc.php');
        require_once('./include/setlang.php');
        $view_title= $MSG_CONTEST.$MSG_RANKLIST;
        $title="";
        require_once("./include/const.inc.php");
        require_once("./include/my_func.inc.php");
// 수행평가 모드 체크
$exam_check_sql = "SELECT `id`,`exam_mode`,`register`FROM `setting` ";
$exam_result = pdo_query($exam_check_sql);
$exam_mode = $exam_result[0]['exam_mode'];
if( ($exam_mode =='Y' && !isset($_SESSION[$OJ_NAME."_source_browser"]))){
	$view_errors = "수행평가 모드입니다.";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}
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
class TM{
        var $solved=0;
        var $time=0;
        var $p_wa_num;
        var $p_ac_sec;
        var $p_pass_rate;
        var $user_id;
        var $nick;
	var $total;
        function TM(){
                $this->solved=0;
                $this->time=0;
                $this->p_wa_num=array(0);
                $this->p_ac_sec=array(0);
                $this->p_pass_rate=array(0);
		$this->total=0;
        }
        function Add($pid, $sec, $res) {
                global $score_map;

                // 기본 배점은 100 (score_map에 없을 경우)
                $score = isset($score_map[$pid]) ? $score_map[$pid] : 100;

                // 이미 맞힌 경우 중복 계산 방지
                if (isset($this->p_ac_sec[$pid]) && $this->p_ac_sec[$pid] > 0)
                        return;

                // 정답이 아닌 경우 (pass_rate < 0.99)
                if ($res * 100 < 99) {
                        // 이전 점수
                        $old_rate = isset($this->p_pass_rate[$pid]) ? $this->p_pass_rate[$pid] : 0.0;
                        if ($res > $old_rate) {
                        $this->total += ($res - $old_rate) * $score;
                        $this->p_pass_rate[$pid] = $res;
                        }

                        // 오답 카운트 증가
                        if (isset($this->p_wa_num[$pid]))
                        $this->p_wa_num[$pid]++;
                        else
                        $this->p_wa_num[$pid] = 1;

                } else {
                        // 정답 처리
                        $this->p_ac_sec[$pid] = $sec;
                        $this->solved++;

                        if (!isset($this->p_wa_num[$pid]))
                        $this->p_wa_num[$pid] = 0;

                        // 기존 pass_rate 점수 제거 (있다면)
                        if (isset($this->p_pass_rate[$pid]))
                        $this->total -= $this->p_pass_rate[$pid] * $score;

                        // 정답 시 배점만큼 점수 부여
                        $this->p_pass_rate[$pid] = 1.0;
                        $this->total += $score;

                        // 시간 패널티 누적
                        $this->time += $sec + $this->p_wa_num[$pid] * 60;
                }
        }

}

function s_cmp($A,$B){
//      echo "Cmp....<br>";
        if ($A->total!=$B->total) return $A->total<$B->total;
        else {
		if($A->solved!=$B->solved)
			return $A->solved<$B->solved;
		else
			return $A->time>$B->time;
	}
}

// contest start time
if (!isset($_GET['cid'])) die("No Such Contest!");
$cid=intval($_GET['cid']);
// 문제별 배점 로딩
$score_map = array();
$sql = "SELECT num, score FROM contest_problem WHERE contest_id = ?";
$score_result = pdo_query($sql, $cid);
foreach ($score_result as $row) {
    $score_map[intval($row['num'])] = intval($row['score'] ?? 100); // 기본값 100점
}


if($OJ_MEMCACHE){
		$sql="SELECT `start_time`,`title`,`end_time` FROM `contest` WHERE `contest_id`='$cid'";
        require("./include/memcache.php");
        $result = mysql_query_cache($sql);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
}else{
		$sql="SELECT `start_time`,`title`,`end_time` FROM `contest` WHERE `contest_id`=?";
        $result = pdo_query($sql,$cid);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
}


$start_time=0;
$end_time=0;
if ($rows_cnt>0){
//       $row=$result[0];

        if($OJ_MEMCACHE)
                $row=$result[0];
        else
                 $row=$result[0];
        $start_time=strtotime($row['start_time']);
        $end_time=strtotime($row['end_time']);
        $title=$row['title'];
        
}
if ($start_time==0){
        $view_errors= "No Such Contest";
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
}

if ($start_time>time()){
        $view_errors= "Contest Not Started!";
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
}
	$noip = (time()<$end_time) && (stripos($title,$OJ_NOIP_KEYWORD)!==false);
	if(isset($_SESSION[$OJ_NAME.'_'."administrator"])||
		isset($_SESSION[$OJ_NAME.'_'."m$cid"])||
		isset($_SESSION[$OJ_NAME.'_'."source_browser"])||
		isset($_SESSION[$OJ_NAME.'_'."contest_creator"])
	   ) $noip=false;
if ($noip) {
      $view_errors =  "<h2>$MSG_NOIP_WARNING</h2>";
      require("template/".$OJ_TEMPLATE."/error.php");
      exit(0);
}
if(!isset($OJ_RANK_LOCK_PERCENT)) 
$OJ_RANK_LOCK_PERCENT=1;
$lock=$end_time-($end_time-$start_time)*$OJ_RANK_LOCK_PERCENT;

//echo $lock.'-'.date("Y-m-d H:i:s",$lock);
$view_lock_time = $start_time + ($end_time - $start_time) * (1 - $OJ_RANK_LOCK_PERCENT);
$locked_msg = "";
if (time() > $view_lock_time && time() < $end_time + $OJ_RANK_LOCK_DELAY) {
    $locked_msg = "The board has been locked.";
}

if($OJ_MEMCACHE){
//        require("./include/memcache.php");
		$sql="SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`='$cid'";
        $result = mysql_query_cache($sql);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
}else{
		$sql="SELECT count(1) as pbc FROM `contest_problem` WHERE `contest_id`=?";
        $result = pdo_query($sql,$cid);
        if($result) $rows_cnt=count($result);
        else $rows_cnt=0;
}

if($OJ_MEMCACHE)
        $row=$result[0];
else
         $row=$result[0];

// $row=$result[0];
$pid_cnt=intval($row['pbc']);


require("./include/contest_solutions.php");
//echo $sql;
//$result=pdo_query($sql);

$user_cnt=0;
$user_name='';
$U=array();
for ($i=0;$i<$rows_cnt;$i++){
        
        $row=$result[$i];
      

        $n_user=$row['user_id'];
        if (strcmp($user_name,$n_user)){
                $user_cnt++;
                $U[$user_cnt]=new TM();

                $U[$user_cnt]->user_id=$row['user_id'];
                $U[$user_cnt]->nick=$row['nick'];

                $user_name=$n_user;
        }
	if($row['result']!=4 && $row['pass_rate']>=0.99) $row['pass_rate']=0;
        if(time()<$end_time+$OJ_RANK_LOCK_DELAY&&$lock<strtotime($row['in_date']))
        	   $U[$user_cnt]->Add($row['num'],strtotime($row['in_date'])-$start_time,0);
        else
        	   $U[$user_cnt]->Add($row['num'],strtotime($row['in_date'])-$start_time,$row['pass_rate']);
       
}
usort($U,"s_cmp");

////firstblood
$first_blood=array();
for($i=0;$i<$pid_cnt;$i++){
      $first_blood[$i]="";
}

if($OJ_MEMCACHE){
	$sql="select s.num,s.user_id from solution s ,
        (select num,min(solution_id) minId from solution where contest_id=$cid and result=4 GROUP BY num ) c where s.solution_id = c.minId";
        $fb = mysql_query_cache($sql);
        if($fb) $rows_cnt=count($fb);
        else $rows_cnt=0;
}else{
	$sql="select s.num,s.user_id from solution s ,
        (select num,min(solution_id) minId from solution where contest_id=? and result=4 GROUP BY num ) c where s.solution_id = c.minId";
        $fb = pdo_query($sql,$cid);
        if($fb) $rows_cnt=count($fb);
        else $rows_cnt=0;
}
foreach ($fb as $row){
         $first_blood[$row['num']]=$row['user_id'];
}



/////////////////////////Template
require("template/".$OJ_TEMPLATE."/contestrank-oi.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
        require_once('./include/cache_end.php');
?>
