<?php require_once("admin-header.php");
require_once("../include/check_get_key.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'vip']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}
?>
<?php $id=intval($_GET['id']);
$sql="SELECT `exam_mode` FROM `setting` WHERE `id`=?";
$result=pdo_query($sql,$id);
$row=$result[0];
$exam_mode=$row[0];
echo $exam_mode;

if ($exam_mode=='Y') $sql="update `setting` set `exam_mode`='N' where `id`=?";
else $sql="update `setting` set `exam_mode`='Y' where `id`=?";
pdo_query($sql,$id) ;
?>
<script language=javascript>
	history.go(-1);
</script>
