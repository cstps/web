<?php require("admin-header.php");
require_once("../include/db_info.inc.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'vip']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}
if (isset($OJ_LANG)) {
	require_once("../lang/$OJ_LANG.php");
}
$sql = "SELECT `id`,`exam_mode`,`register`FROM `setting` ";
$result = pdo_query($sql);
$row = $result[0];
?>

<title>기본 설정 변경</title>
<hr>
<center><h3><?php echo $MSG_SETDBINFO?></h3></center>

<div class='container'>

<center>
  <table width=100% border=1 style="text-align:center;">
    <tr style='height:22px;'>
      <td>ID</td>
      <td>수행평가모드</td>
      <td>OJ_TEMPLATE</td>
      <td>OJ_CE_PENALTY</td>
      <td>OJ_LANGMASK</td>
      <td>OJ_REGISTER</td>
      
    </tr>
    <?php
        echo "<tr style='height:22px;'>";
        echo "<td>".$row['id']."</td>";
        echo "<td><a href=setdb_change.php?id=".$row['id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey'].">".($row['exam_mode']=="Y"?"<span class=green>On</span>":"<span class=red>Off</span>")."</a>"."</td>";
        echo "<td>".$OJ_TEMPLATE."</td>";
        echo "<td>".$OJ_CE_PENALTY."</td>";
        echo "<td>".$OJ_LANGMASK."</td>";
        echo "<td>".$row['register']."</td>";
        echo "</tr>";
    ?>
  </table>
</center>
