<?php require("admin-header.php");
require_once("../include/db_info.inc.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}
if (isset($OJ_LANG)) {
	require_once("../lang/$OJ_LANG.php");
}

?>

<title>기본 설정 변경</title>
<hr>
<center><h3><?php echo $MSG_SETDBINFO?></h3></center>

<div class='container'>

<center>
  <table width=100% border=1 style="text-align:center;">
    <tr style='height:22px;'>
      <td>OJ_TEMPLATE</td>
      <td>OJ_CE_PENALTY</td>
      <td>OJ_LANGMASK</td>
      <td>OJ_REGISTER</td>
      <td>OJ_OI_MODE</td>
    </tr>
    <?php
        echo "<tr style='height:22px;'>";
        echo "<td>".$OJ_TEMPLATE."</td>";
        echo "<td>".$OJ_CE_PENALTY."</td>";
        echo "<td>".$OJ_LANGMASK."</td>";
        echo "<td>".$OJ_REGISTER."</td>";
        echo "<td><label class='switch'>
        <input type='checkbox' name='switch'>
        <div class='slider'></div>
      </label> ".$OJ_OI_MODE."</td>";
        //echo "<td><a href=news_df_change.php?id=".$row['news_id']."&getkey=".$_SESSION[$OJ_NAME.'_'.'getkey'].">".($row['defunct']=="N"?"<span class=green>On</span>":"<span class=red>Off</span>")."</a>"."</td>";
        //echo "<td><a href=news_add_page.php?cid=".$row['news_id'].">Copy</a></td>";
        echo "</tr>";
    ?>
  </table>
</center>
