<?php require_once("admin-header.php");
require_once("../include/check_get_key.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'vip']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}
?>
<?php 
$flag = $_GET['flag'];
$id=intval($_GET['id']);
$sql="SELECT `exam_mode`, `register` FROM `setting` WHERE `id`=$id";
$result=pdo_query($sql);
$row=$result[0];
$exam_mode=$row['exam_mode'];
$register=$row['register'];

$sql="";
if ($flag == '1') {// 수행모드 변경
	$exam_mode = 1-$exam_mode;
	$sql="update `setting` set `exam_mode`=$exam_mode where `id`=?";
}
else if ($flag =='5'){ // 회원가입 변경
	
	$register = 1-$register;
	$OJ_REGISTER=$register;
	$sql="update `setting` set `register`=$register where `id`=?";

}

pdo_query($sql,$id) ;

?>
<script language=javascript>
	history.go(-1);
</script>
