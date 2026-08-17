<?php
require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

require_once('./include/course_functions.inc.php');
require_once('./include/contest_access.inc.php');

$view_title = $MSG_SUBMIT;


if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	if (isset($OJ_GUEST) && $OJ_GUEST) {
		$_SESSION[$OJ_NAME.'_'.'user_id'] = "Guest";
	}
	else {
		$view_errors = "<a href=loginpage.php>$MSG_Login</a>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
}

$problem_id = 1000;
if (isset($_GET['id'])) {
	$id = intval($_GET['id']);
	$sample_sql = "SELECT sample_input,sample_output,problem_id FROM problem WHERE problem_id = ?";
}
else if (isset($_GET['cid']) && isset($_GET['pid'])) {
	$cid = intval($_GET['cid']);
	$pid = intval($_GET['pid']);

		// ============================================================
	// Contest 공통 접근 권한 확인
	// ============================================================

	if (!contest_can_access($cid)) {

		$view_errors = "Not Invited!";

		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}


	// 대회 비공개로 변경될 경우 제출 문제에서 제출이 안되도록 코드 시작
	$now = date('Y/m/d H:i:s D', time());

	if (
		isset($_SESSION[$OJ_NAME.'_administrator']) ||
		isset($_SESSION[$OJ_NAME.'_m'.$cid])
	) {

		$sql =
			"SELECT langmask, private, defunct
			 FROM contest
			 WHERE contest_id=?";

	}
	else {

		$sql =
			"SELECT langmask, private, defunct
			 FROM contest
			 WHERE defunct='N'
			   AND contest_id=?
			   AND start_time<='$now'
			   AND '$now'<end_time";
	}

	$result = pdo_query($sql,$cid);
	$rows_cnt = count($result);
	if ($rows_cnt==0) {
		$view_errors = "<title>$MSG_CONTEST</title><h2>No such Contest!</h2>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}

	// 대회 비공개로 변경될 경우 제출 문제에서 제출이 안되도록 코드 끝
	$psql =
		"SELECT problem_id
		FROM contest_problem
		WHERE contest_id=?
		AND num=?";

	$data =
		pdo_query(
			$psql,
			$cid,
			$pid
		);

	if (
		!$data ||
		count($data) != 1
	) {

		$view_errors =
			"<h2>No Such Problem!</h2>";

		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}

	$problem_id =
		intval(
			isset($data[0]['problem_id'])
				? $data[0]['problem_id']
				: $data[0][0]
		);

	$sample_sql = "SELECT p.sample_input, p.sample_output, p.problem_id FROM problem p WHERE problem_id = ? ";
}
else {
	$view_errors = "<h2>No Such Problem!</h2>";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}

$view_src = "";
// ============================================================
// 수업용 OJ - 문제 해결 사고과정 기록
// ============================================================

// 풀이 계획
$view_plan_text = "";

// AI 사용 여부
$view_ai_used = 0;

// AI 활용 유형
// none, understand, idea, syntax, debug, generate, explain
$view_ai_usage_type = "none";

// AI에게 질문한 내용
$view_ai_prompt = "";

// 이전 제출 후 수정/회고
$view_reflection = "";

// 사고과정 기록 기능 활성화 여부
// 우선 전체 문제에서 활성화하여 테스트
$view_process_mode = true;


if (isset($_GET['sid'])) {
  	$sid = intval($_GET['sid']);
	$sql = "SELECT * FROM `solution` WHERE `solution_id`=?";
	$result = pdo_query($sql,$sid);

	$row = $result[0];
	$cid = intval($row['contest_id']);
	$sproblem_id= intval($row['problem_id']);
	$language_num = intval($row['language']);
	$contest_id=$cid;
	if ($row && $row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id'])
		$ok = true;

		$need_check_using=true;
		if ( $contest_id > 0 ){
			$sql="select start_time,end_time from contest where contest_id=?";
			$result=pdo_query($sql,$contest_id);
			if($result){
				$row=$result[0];
				$start_time = strtotime($row['start_time']);
				$end_time = strtotime($row['end_time']);
				$now=time();
				if( $end_time < $now ){ // 当前提交，属于已经结束的比赛，考察是否有进行中的比赛在使用。
					$need_check_using=true;
					
				}else{			// 属于进行中的比赛，可以看
							
		//			echo $now.'-'.$end_time;
					$need_check_using=false;
				
				}
			}

		}else{ //非比赛提交.考察是否有进行中的比赛在使用
		//			echo $now.'+'.$end_time;
			if(isset($_SESSION[$OJ_NAME.'_'.'source_browser']))
				$need_check_using=false;
			else
				$need_check_using=true;
		}
		// 检查是否使用中
		//echo $now.'*'.$end_time;
		$now = strftime("%Y-%m-%d %H:%M", time());
		$sql="select contest_id from contest where contest_id in (select contest_id from contest_problem where problem_id=?) 
									and start_time < '$now' and end_time > '$now' ";
		if($need_check_using){
			//echo $sql;
			$result=pdo_query($sql,$sproblem_id);
			if(count($result)>0  && !isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){
					$view_errors = "<center>";
					$view_errors .= "<h3>$MSG_CONTEST_ID : ".$result[0][0]."</h3>";
					$view_errors .= "<p> $MSG_SOURCE_NOT_ALLOWED_FOR_EXAM </p>";
					$view_errors .= "<br>";
					$view_errors .= "</center>";
					$view_errors .= "<br><br>";
					require("template/".$OJ_TEMPLATE."/error.php");
					exit(0);
			}

		}
	if (isset($_SESSION[$OJ_NAME.'_'.'source_browser'])) {
		$ok = true;
	}
	else {
		if (isset($OJ_EXAM_CONTEST_ID)) {
			if ($cid < $OJ_EXAM_CONTEST_ID && !isset($_SESSION[$OJ_NAME.'_'.'source_browser'])) {

					$view_errors = "<center>";
					$view_errors .= "<h3>$MSG_CONTEST_ID : ".$OJ_EXAM_CONTEST_ID."+ </h3>";
					$view_errors .= "<p> $MSG_SOURCE_NOT_ALLOWED_FOR_EXAM </p>";
					$view_errors .= "<br>";
					$view_errors .= "</center>";
					$view_errors .= "<br><br>";
					require("template/".$OJ_TEMPLATE."/error.php");
					exit(0);
			}
		}
	}

	if ($ok == true) {
		$sql = "SELECT `source` FROM `source_code_user` WHERE `solution_id`=?";
		$result = pdo_query($sql,$sid);

		$row = $result[0];

		if ($row){
			$view_src = $row['source'];
			// front_code와 rear_code는 삭제해서 보여주기	
			// 언어별로 추가된 코드 제거하기
			$sqltmp = "SELECT * from `problem` where `problem_id`='$id'";
			$resulttmp = pdo_query($sqltmp,$id);
		
			$front_code = $resulttmp[0]['front_code'];
			$rear_code = $resulttmp[0]['rear_code'];
			
			if($front_code || $rear_code){
				// 미리작성된 코드는 제거 
				// 언어별로 추가된 모드 코드 제거하기
				$cnt_language = count($language_name);
                for($i=0;$i<$cnt_language;$i++){
                  $find_str = "//".$language_name[$i]."//";
                  $front_code_print ="";
                  $rear_code_print ="";
                  //front code 내용 확인하여 언어별 분리
                  if(strpos($front_code,$find_str)!==false){
                    $split_str = explode($find_str,$front_code);
                    $front_code_print = explode("//",$split_str[1])[0];
					$view_src = str_replace($front_code_print,"", $view_src);
                  }
                  //rear code 내용 확인하여 언어별 분리
                  if(strpos($rear_code,$find_str)!==false){
                    $split_str = explode($find_str,$rear_code);
                    $rear_code_print = explode("//",$split_str[1])[0];
					$view_src = str_replace($rear_code_print,"", $view_src);
                  }
				}
				//빈줄 제거
				$view_src= preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $view_src);
			}
			
		}
		$sql = "SELECT langmask FROM contest WHERE contest_id=?";

		$result = pdo_query($sql,$cid);
		$row = $result[0];

		if ($row)
			$_GET['langmask'] = $row['langmask'];
	}
}

if (isset($id))
	$problem_id = $id;

// ============================================================
// Edit 모드에서는 기존 제출 언어를 기본 선택 언어로 사용
// ============================================================

if (
	isset($_GET['sid']) &&
	isset($language_num)
) {
	$lastlang = intval($language_num);
}
else {
	$lastlang = 0;
}


$view_sample_input = "1 2";
$view_sample_output = "3";

if (isset($sample_sql)) {
	//echo $sample_sql;
	if (isset($_GET['id'])) {
		$result = pdo_query($sample_sql,$id);
	}
	else {
	  $result = pdo_query($sample_sql,$problem_id);
	}

	if($result == false)
	{
		$view_errors = "<h2>No Such Problem!</h2>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}

	$row = $result[0];
	$view_sample_input = $row[0];
	$view_sample_output = $row[1];
	$problem_id = $row[2];
}

// ============================================================
// 기본 선택 언어
//
// Edit 모드:
//   기존 제출의 language 사용
//
// 새 제출:
//   기존처럼 cookie 또는 최근 사용 언어 사용
// ============================================================

if (
	isset($_GET['sid']) &&
	isset($language_num)
) {
	$lastlang = intval($language_num);
}
else {
	$lastlang = 0;
}


if (!$view_src) {

	if (isset($_COOKIE['lastlang']) && $_COOKIE['lastlang']!="undefined") {
		$lastlang = intval($_COOKIE['lastlang']);
	}
	else {
		$sql = "SELECT language FROM solution WHERE user_id=? ORDER BY solution_id DESC LIMIT 1";
		$result = pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id']);

		if (count($result)>0) {
			$lastlang = $result[0][0];
		}
		else {
			$lastlang = 0;
		}
		//echo "last=$lastlang";
	}
	$template_file = "$OJ_DATA/$problem_id/template.".$language_ext[$lastlang];

	if (file_exists($template_file)) {
		$view_src = file_get_contents($template_file);
	}
}

$sql = "SELECT count(1) FROM `solution` WHERE result<4";
$result = mysql_query_cache($sql);

$row = $result[0];

if ($row[0]>10) {
	$OJ_VCODE = true;
	//$OJ_TEST_RUN=false;
	//echo "$row[0]";
}
// ============================================================
// 수업용 OJ - 첫 제출 / 재제출 판단
//
// 핵심:
// solution 테이블의 과거 제출 여부가 아니라
// solution_process에 "최초 풀이계획"이 존재하는지를 기준으로 판단
// ============================================================

$view_is_resubmit = false;

$view_previous_solution_id = 0;
$view_previous_result = null;
$view_previous_result_text = "";

$view_previous_plan_text = "";
$view_previous_reflection = "";

$current_user_id = $_SESSION[$OJ_NAME.'_'.'user_id'];


// ============================================================
// 현재 일반 문제 / 대회 문제 구분
// ============================================================

$current_contest_id = 0;

if (isset($cid) && intval($cid) > 0) {
	$current_contest_id = intval($cid);
}


// ============================================================
// 1. 최초 풀이계획이 이미 존재하는지 확인
//
// solution 테이블이 아니라 solution_process를 기준으로 함.
//
// 이유:
// 사고과정 기능 도입 전에 제출했던 기존 solution이 있어도
// 학생에게 최초 풀이계획을 한 번은 작성하도록 하기 위함.
// ============================================================

if ($current_contest_id > 0) {

	$first_process = pdo_query(
		"SELECT id, solution_id, plan_text
		 FROM solution_process
		 WHERE user_id=?
		 AND problem_id=?
		 AND contest_id=?
		 AND plan_text IS NOT NULL
		 AND TRIM(plan_text) <> ''
		 ORDER BY id ASC
		 LIMIT 1",

		$current_user_id,
		$problem_id,
		$current_contest_id
	);

}
else {

	$first_process = pdo_query(
		"SELECT id, solution_id, plan_text
		 FROM solution_process
		 WHERE user_id=?
		 AND problem_id=?
		 AND (contest_id=0 OR contest_id IS NULL)
		 AND plan_text IS NOT NULL
		 AND TRIM(plan_text) <> ''
		 ORDER BY id ASC
		 LIMIT 1",

		$current_user_id,
		$problem_id
	);

}


// ============================================================
// 최초 풀이계획이 존재하면 재제출 모드
// ============================================================

if ($first_process && count($first_process) > 0) {

	$view_is_resubmit = true;

	$view_previous_plan_text =
		$first_process[0]['plan_text'];


	// ========================================================
	// 2. 가장 최근 제출 찾기
	// ========================================================

	if ($current_contest_id > 0) {

		$previous_solution = pdo_query(
			"SELECT solution_id, result
			 FROM solution
			 WHERE user_id=?
			 AND problem_id=?
			 AND contest_id=?
			 ORDER BY solution_id DESC
			 LIMIT 1",

			$current_user_id,
			$problem_id,
			$current_contest_id
		);

	}
	else {

		$previous_solution = pdo_query(
			"SELECT solution_id, result
			 FROM solution
			 WHERE user_id=?
			 AND problem_id=?
			 AND (contest_id=0 OR contest_id IS NULL)
			 ORDER BY solution_id DESC
			 LIMIT 1",

			$current_user_id,
			$problem_id
		);

	}


	// ========================================================
	// 직전 제출 정보
	// ========================================================

	if ($previous_solution && count($previous_solution) > 0) {

		$view_previous_solution_id =
			intval($previous_solution[0]['solution_id']);

		$view_previous_result =
			intval($previous_solution[0]['result']);


		// 결과 문자열
		if (
			isset($judge_result) &&
			isset($judge_result[$view_previous_result])
		) {

			$view_previous_result_text =
				$judge_result[$view_previous_result];

		}
		else {

			$view_previous_result_text =
				"Result ".$view_previous_result;

		}


		// ====================================================
		// 직전 제출의 수정 내용
		// ====================================================

		$previous_reflection = pdo_query(
			"SELECT reflection
			 FROM solution_process
			 WHERE solution_id=?
			 LIMIT 1",

			$view_previous_solution_id
		);


		if (
			$previous_reflection &&
			count($previous_reflection) > 0 &&
			isset($previous_reflection[0]['reflection'])
		) {

			$view_previous_reflection =
				$previous_reflection[0]['reflection'];

		}

	}

}


// ============================================================
// 최초 풀이계획이 하나도 없다면
//
// 기존 solution 제출 기록이 아무리 많아도
// 사고과정 기능에서는 "첫 제출"로 취급
// ============================================================

else {

	$view_is_resubmit = false;

	$view_previous_solution_id = 0;
	$view_previous_result = null;
	$view_previous_result_text = "";

	$view_previous_plan_text = "";
	$view_previous_reflection = "";

}


/////////////////////////Template
require("template/".$OJ_TEMPLATE."/submitpage.php");