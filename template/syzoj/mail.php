<?php $show_title="사용자정보 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<table><form method=post action=mail.php>
<tr><td>From:<?php echo htmlentities($from_user,ENT_QUOTES,"UTF-8")?>
 To:<input name=to_user size=10 value="<?php if ($from_user==$_SESSION[$OJ_NAME.'_user_id']||$from_user=="") echo $to_user ;else echo $from_user;?>">
Title:<input name=title size=20 value="<?php echo $title?>">
<input type=submit value=<?php echo $MSG_SUBMIT?>></td>
</tr>
<tr><td>
<textarea name=content rows=10 cols=80 class="input input-xxlarge"></textarea>
</td></tr>
</form>
</table>
<table border=1>
<tr><td>Mail ID<td>From:Title<td>Date</tr>
<tbody>
<?php
$cnt=0;
foreach($view_mail as $row){
if ($cnt)
echo "<tr class='oddrow'>";
else
echo "<tr class='evenrow'>";
foreach($row as $table_cell){
echo "<td>";
echo "\t".$table_cell;
echo "</td>";
}
echo "</tr>";
$cnt=1-$cnt;
}
?>
</tbody>
</table>
</center> 
      </div>

    </div> <!-- /container -->


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <?php include("template/$OJ_TEMPLATE/footer.php");?>
	    
  </body>
</html>
