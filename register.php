<?php 
require_once("./include/db_info.inc.php");
$sql="SELECT `register` FROM `setting` ";
$result = pdo_query($sql);
$row =  $result[0];
if( $row['register']==0) { // 회원가입 off인 경우

	$view_errors = "<center>";
	$view_errors .= "<h3>비공개 회원가입</h3>";
	$view_errors .= "<p>비공개 회원가입으로 운영되고 있습니다.</p>";
	$view_errors .= "<br>";
	$view_errors .= "<span class=text-success>선생님이 안내한 아이디를 이용해서 로그인 하세요</span>";
	$view_errors .= "</center>";
	$view_errors .= "<br><br>";
	
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}
require_once("./include/my_func.inc.php");
if(isset($OJ_CSRF)&&$OJ_CSRF)require_once("./include/csrf_check.php");
$err_str="";
$err_cnt=0;
$len;
$user_id=trim($_POST['user_id']);
$len=strlen($user_id);
$email=trim($_POST['email']);
$school=trim($_POST['school']);
$vcode=trim($_POST['vcode']);
if($vcode!= $_SESSION[$OJ_NAME.'_'."vcode"]||$vcode==""||$vcode==null){
	$_SESSION[$OJ_NAME.'_'."vcode"]=null;
	$err_str=$err_str."확인코드가 틀렸습니다.\\n";
	$err_cnt++;
}
if($OJ_LOGIN_MOD!="hustoj"){
	$err_str=$err_str."회원가입을 허락하지 않습니다.\\n";
	$err_cnt++;
}

if($len>20){ 
	$err_str=$err_str."아이디가 너무 길어요!\\n";
	$err_cnt++;
}else if ($len<3){
	$err_str=$err_str." $MSG_WARNING_USER_ID_SHORT\\n";
	$err_cnt++;
}
if (!is_valid_user_name($user_id)){
	$err_str=$err_str."사용자 아이디는 영문자+숫자만 가능!\\n";
	$err_cnt++;
}
$nick=trim($_POST['nick']);
$len=strlen($nick);
if ($len>100){
	$err_str=$err_str."별명이 너무 길어요!\\n";
	$err_cnt++;
}else if ($len==0) $nick=$user_id;
if (strcmp($_POST['password'],$_POST['rptpassword'])!=0){
	$err_str=$err_str."$MSG_WARNING_REPEAT_PASSWORD_DIFF!\\n";
	$err_cnt++;
	
}
if (strlen($_POST['password'])<6){
	$err_cnt++;
	$err_str=$err_str."$MSG_WARNING_PASSWORD_SHORT \\n";
}
$len=strlen($_POST['school']);
if ($len>100){
	$err_str=$err_str."소속/학교 이름이 너무 김!\\n";
	$err_cnt++;
}
$len=strlen($_POST['email']);
if ($len>100){
	$err_str=$err_str."메일주소가 너무 김!\\n";
	$err_cnt++;
}
if ($err_cnt>0){
	print "<script language='javascript'>\n";
	print "alert('";
	print $err_str;
	print "');\n location.href = 'https://1024.kr/registerpage.php';\n</script>";
	exit(0);
}
$password=pwGen($_POST['password']);
$sql="SELECT `user_id` FROM `users` WHERE `users`.`user_id` = ?";
$result=pdo_query($sql,$user_id);
$rows_cnt=count($result);
if ($rows_cnt == 1){
	print "<script language='javascript'>\n";
	print "alert('User Existed!\\n');\n";
	print "history.go(-1);\n</script>";
	exit(0);
}
if ($domain==$DOMAIN && $OJ_NAME==$user_id){
        print "<script language='javascript'>\n";
        print "alert('User Existed!\\n');\n";
        print "history.go(-1);\n</script>";
        exit(0);
}
$nick=(htmlentities ($nick,ENT_QUOTES,"UTF-8"));
$school=(htmlentities ($school,ENT_QUOTES,"UTF-8"));
$email=(htmlentities ($email,ENT_QUOTES,"UTF-8"));
$ip = ($_SERVER['REMOTE_ADDR']);
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])&&!empty(trim($_SERVER['HTTP_X_FORWARDED_FOR']))) {
    $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
    $tmp_ip = explode(',', $REMOTE_ADDR);
    $ip = (htmlentities($tmp_ip[0], ENT_QUOTES, "UTF-8"));
} else if (isset($_SERVER['HTTP_X_REAL_IP'])&&!empty(trim($_SERVER['HTTP_X_REAL_IP']))) {
    $REMOTE_ADDR = $_SERVER['HTTP_X_REAL_IP'];
    $tmp_ip = explode(',', $REMOTE_ADDR);
    $ip = (htmlentities($tmp_ip[0], ENT_QUOTES, "UTF-8"));
}
if(isset($OJ_REG_NEED_CONFIRM)&&$OJ_REG_NEED_CONFIRM) $defunct="Y";
else $defunct="N";
$sql="INSERT INTO `users`("
."`user_id`,`email`,`ip`,`accesstime`,`password`,`reg_time`,`nick`,`school`,`defunct`)"
."VALUES(?,?,?,NOW(),?,NOW(),?,?,?)";
//echo "$sql:$user_id,$email,$ip,$password,$nick,$school,$defunct";
$rows=pdo_query($sql,$user_id,$email,$ip,$password,$nick,$school,$defunct);// or die("Insert Error!\n");
//echo $rows;
$sql="INSERT INTO `loginlog` VALUES(?,?,?,NOW())";
pdo_query($sql,$user_id,"no save",$ip);

if(!isset($OJ_REG_NEED_CONFIRM)||!$OJ_REG_NEED_CONFIRM){
		$_SESSION[$OJ_NAME.'_'.'user_id']=$user_id;
		$sql="SELECT `rightstr` FROM `privilege` WHERE `user_id`=?";
		//echo $sql."<br />";
		$result=pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id']);
		foreach ($result as $row){
			$_SESSION[$OJ_NAME.'_'.$row['rightstr']]=true;
			//echo $_SESSION[$OJ_NAME.'_'.$row['rightstr']]."<br />";
		}
		$_SESSION[$OJ_NAME.'_'.'ac']=Array();
		$_SESSION[$OJ_NAME.'_'.'sub']=Array();
	        if($OJ_SaaS_ENABLE && $domain==$DOMAIN)    create_subdomain($cid,"syzoj",3);
}
?>
<script>window.location.href = 'https://1024.kr/';</script>
