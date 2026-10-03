<?php
$cache_time = 10;
$OJ_CACHE_SHARE = false;

require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');

$view_title = "Welcome To Online Judge";

if(!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
  header("location:loginpage.php");
  exit(0);
}

require_once("./include/const.inc.php");
require_once("./include/course_functions.inc.php");

if(!isset($_GET['sid'])){
  echo "No such code!\n";
  require_once("oj-footer.php");
  exit(0);
}

function is_valid($str2){
  global $_SESSION,$OJ_NAME,$OJ_FRIENDLY_LEVEL;
  if(isset($_SESSION[$OJ_NAME.'_'.'source_browser'])) return true;
    //return true; // 如果希望能让任何人都查看对比和RE,放开行首注释，并设定$OJ_SHOW_DIFF=true; if you fail to view diff , try remove the // at beginning of this line.
  if($OJ_FRIENDLY_LEVEL>3) return true;

  $n = strlen($str2);
  $str = str_split($str2);
  $m = 1;
  for($i=0; $i<$n; $i++){
    if(is_numeric($str[$i]))
      $m++;
  }
  return $n/$m>3;
}

if(!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
  $view_errors = $MSG_WARNING_ACCESS_DENIED ;
  require("template/".$OJ_TEMPLATE."/error.php");
  exit(0);
}

$ok = false;
$id = strval(intval($_GET['sid']));

$sql = "SELECT * FROM `solution` WHERE `solution_id`=?";
$result = pdo_query($sql,$id);
$row = $result[0];
$lang = $row['language'];
$contest_id = intval($row['contest_id']);
$isRE = $row['result']==10;


// ============================================================
// Course 수행모드 학생의 상세 실행정보 차단
//
// 채점 결과 자체는 status.php에서 확인할 수 있다.
// 다만 Runtime/WA 상세정보는 테스트 데이터나
// 정답 추론에 이용될 수 있으므로 수행모드에서는 제한한다.
//
// 관리자 계열은 기존 권한을 유지한다.
// ============================================================

$current_user =
    isset($_SESSION[$OJ_NAME.'_'.'user_id'])
        ? trim(
            (string)$_SESSION[
                $OJ_NAME.'_'.'user_id'
            ]
        )
        : '';

$is_reinfo_privileged = (
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_source_browser']) ||
    (
        $contest_id > 0 &&
        isset(
            $_SESSION[
                $OJ_NAME.'_m'.$contest_id
            ]
        )
    )
);

$is_course_performance_student = (
    $current_user !== '' &&
    !$is_reinfo_privileged &&
    course_should_restrict_student_history(
        $current_user
    )
);

$is_own_solution = (
    $row &&
    isset($row['user_id']) &&
    $row['user_id'] === $current_user
);

// 수행모드에서는 일반 상세 채점정보를 숨긴다.
// 단, 본인의 실행 오류(RE)는 오류 종류와 메시지만 안전하게 보여준다.
// 서버 경로, 소스코드 줄, 테스트 데이터 정보는 노출하지 않는다.
if (
    $is_course_performance_student &&
    !($is_own_solution && $isRE)
) {
    $view_errors =
        "<h2>수행모드에서는 상세 실행정보를 볼 수 없습니다.</h2>".
        "<p>채점 결과는 제출 현황에서 확인할 수 있습니다.</p>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}

if((isset($_SESSION[$OJ_NAME.'_'.'user_id']) && $row && ($row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']))||isset($_SESSION[$OJ_NAME.'_'.'source_browser']))
{
  $ok = true;
}

$view_reinfo = "";

// 수행모드 학생의 본인 Runtime Error는
// 오류의 핵심 메시지만 추출하여 보여준다.
if (
    $is_course_performance_student &&
    $is_own_solution &&
    $isRE
) {
    $sql =
        "SELECT `error` ".
        "FROM `runtimeinfo` ".
        "WHERE `solution_id`=?";

    $runtime_result = pdo_query(
        $sql,
        $id
    );

    $runtime_error = '';

    if (
        isset($runtime_result[0]) &&
        isset($runtime_result[0]['error'])
    ) {
        $runtime_error =
            (string)$runtime_result[0]['error'];
    }

    $safe_runtime_error = '';

    // Python의 처리되지 않은 예외에서
    // 예외 이름과 설명만 추출한다.
    if (
        $lang == 6 &&
        preg_match(
            '/^([A-Za-z_][A-Za-z0-9_.]*(?:Error|Exception):[^\\r\\n]*)/m',
            $runtime_error,
            $matches
        )
    ) {
        $safe_runtime_error = $matches[1];
    }

    if ($safe_runtime_error === '') {
        $safe_runtime_error =
            '프로그램 실행 중 오류가 발생했습니다.';
    }

    $view_reinfo = htmlentities(
        $safe_runtime_error,
        ENT_QUOTES,
        'UTF-8'
    );

    require(
        "template/".
        $OJ_TEMPLATE.
        "/reinfo.php"
    );

    if (
        file_exists(
            './include/cache_end.php'
        )
    ) {
        require_once(
            './include/cache_end.php'
        );
    }

    exit(0);
}
if(  ($ok && $OJ_FRIENDLY_LEVEL>2) ||
    (
      isset($_SESSION[$OJ_NAME.'_'.'source_browser']) || ($ok&&$lang!=3&&$contest_id==0&& // 防止打表过数据弱的题目
  !(                                                                                   // 默认禁止java和比赛中查看WA对比和RE详情
    (isset($OJ_EXAM_CONTEST_ID)&&$OJ_EXAM_CONTEST_ID>0)||                              // 如果希望教学中无论练习或比赛均开放数据对比与运行错误，可以将这里
    (isset($OJ_ON_SITE_CONTEST_ID)&&$OJ_ON_SITE_CONTEST_ID>0)                          // 的所有条件简化为 $ok，即63行到69行简化为: if($ok){
  ))
     )                   // if you want a friendly WA and RE, change line 63-69 to "if($ok){"
  ){

  if($row['user_id']!=$_SESSION[$OJ_NAME.'_'.'user_id']){
    $view_mail_link= "<a href='mail.php?to_user=".htmlentities($row['user_id'],ENT_QUOTES,"UTF-8")."&title=$MSG_SUBMIT $id'>Mail the auther</a>";
  }

  $sql = "SELECT `error` FROM `runtimeinfo` WHERE `solution_id`=?";
  $result = pdo_query($sql,$id);

  if(isset($result[0])){
    $row = $result[0];
  }

  if($OJ_SHOW_DIFF && $row && ($ok||$isRE) && ($OJ_TEST_RUN||is_valid($row['error'])||$ok)){
    $view_reinfo = htmlentities(str_replace("\n\r","\n",$row['error']),ENT_QUOTES,"UTF-8");
    // 관리자(administrator)는 모두 보이도록 합니다.
    if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'source_browser']))){
      if($OJ_SHOW_DIFF_MIN){// 채점결과 최소정보만 보여주기 21.12.20
          $str1 = "time_space_table:";
          $str2 = "==============================\n========[";
          if(strpos($view_reinfo,$str1)!==false){
            if( strpos($view_reinfo,$str2)!==false) {
              $tmp = explode($str2,$view_reinfo)[0];
              $tmp = $tmp."일부만 보여줍니다\n";
              $view_reinfo =  $tmp.explode("time_space_table:",$view_reinfo)[1];
            }
            else{
              $view_reinfo =  explode("time_space_table:",$view_reinfo)[1];
              $view_reinfo .="일부만 보여줍니다";
            }
            $ERROR_E =["AC","WA","TLE","RE","PE"];
            $ERROR_K =["정답","틀림","시간초과","실행오류","표현오류"];
            for($tmp=0;$tmp<count($ERROR_E);$tmp++){
              $view_reinfo = str_replace($ERROR_E[$tmp],$ERROR_K[$tmp],$view_reinfo);
            }
          }else{ // 너무 길어 다 안 보일 경우
            $view_reinfo = explode("==============================\n========[",$view_reinfo)[0];
            $view_reinfo .="일부만 보여줍니다";
          }
        }
    }
  }
  else{
    $view_errors = $MSG_WARNING_ACCESS_DENIED;
    //$view_reinfo = "出于数据保密原因，当前错误提示不可查看，如果希望能让任何人都查看对比和运行错误,请管理员配置\$OJ_SHOW_DIFF=true;<br>然后编辑本文件，开放18行首注释，令is_valid总是返回true。 <br>\n Sorry , not available (RE:".$isRE.",OJ_SHOW_DIFF:".$OJ_SHOW_DIFF.",TR:".$OJ_TEST_RUN.",valid:".is_valid($row['error']).")";
  }
}
else{
  $view_errors = $MSG_WARNING_ACCESS_DENIED;
  require("template/".$OJ_TEMPLATE."/error.php");
  exit(0);
}

/////////////////////////Template

if($OJ_SHOW_DIFF==false){
  $view_errors = $MSG_WARNING_ACCESS_DENIED;
  require("template/".$OJ_TEMPLATE."/error.php");
  exit(0);
}
else{
  require("template/".$OJ_TEMPLATE."/reinfo.php");
}
/////////////////////////Common foot
if(file_exists('./include/cache_end.php')){
  require_once('./include/cache_end.php');
}
?>
