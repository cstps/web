<?php
 

	$cache_time=10;
	$OJ_CACHE_SHARE=false;
	require_once('./include/cache_start.php');
        require_once('./include/db_info.inc.php');
	require_once('./include/setlang.php');
	$view_title= "Welcome To Online Judge";
	if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])) {
		  header("location:loginpage.php");
		   exit(0);
	 }
require_once("./include/const.inc.php");
require_once("./include/course_functions.inc.php");
if (!isset($_GET['sid'])){
	$view_errors= "No such code!\n";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
	
}
function is_valid($str2){
    $n=strlen($str2);
    $str=str_split($str2);
    $m=1;
    for($i=0;$i<$n;$i++){
    	if(is_numeric($str[$i])) $m++;
    }
    return $n/$m>3;
}
if(!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	$view_errors= $MSG_WARNING_ACCESS_DENIED ;
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}

$ok=false;
$id=intval($_GET['sid']);
$sql="SELECT * FROM `solution` WHERE `solution_id`=?";
$result=pdo_query($sql,$id);
 $row=$result[0];

$current_user =
    isset($_SESSION[$OJ_NAME.'_'.'user_id'])
        ? trim((string)$_SESSION[$OJ_NAME.'_'.'user_id'])
        : '';

$is_course_performance_student =
    (
        $current_user !== '' &&
        course_should_restrict_student_history(
            $current_user
        ) &&
        !isset($_SESSION[$OJ_NAME.'_'.'source_browser']) &&
        !isset($_SESSION[$OJ_NAME.'_'.'administrator'])
    );

// 수행모드 학생은 본인의 제출이라도
// 수행모드 시작 전 CE 기록은 다시 열람할 수 없다.
if (
    $is_course_performance_student &&
    $row &&
    isset($row['user_id']) &&
    $row['user_id'] === $current_user &&
    isset($row['in_date']) &&
    course_should_restrict_student_record(
        $current_user,
        $row['in_date']
    )
) {
    $view_errors =
        "<h2>수행모드에서는 기존 컴파일 오류 기록을 볼 수 없습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}

if ($row && $row['user_id']==$_SESSION[$OJ_NAME.'_'.'user_id']) $ok=true;
if (isset($_SESSION[$OJ_NAME.'_'.'source_browser'])) $ok=true;
if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])) $ok=true;
$view_reinfo="";
if ($ok==true){
	if($row['user_id']!=$_SESSION[$OJ_NAME.'_'.'user_id'])
		$view_mail_link= "<a href='mail.php?to_user={$row['user_id']}&title=$MSG_SUBMIT $id'>Mail the auther</a>";
	
	$sql="SELECT `error` FROM `compileinfo` WHERE `solution_id`=?";
	$result=pdo_query($sql,$id);
	 $row=$result[0];
	if($row&&is_valid($row['error']))	
		$view_reinfo= htmlentities(str_replace("\n\r","\n",$row['error']),ENT_QUOTES,"UTF-8");
	
        
	
}else{
	
	$view_errors= $MSG_WARNING_ACCESS_DENIED;
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
	
}

/////////////////////////Template
require("template/".$OJ_TEMPLATE."/ceinfo.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
	require_once('./include/cache_end.php');
?>

