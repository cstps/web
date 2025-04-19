<?php
require("admin-header.php");
require_once("../include/set_get_key.php");

if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator'])
        || isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])
        || isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])
        )){
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}


if(isset($OJ_LANG)){
  require_once("../lang/$OJ_LANG.php");
}
?>

<title>Problem List</title>
<hr>
<center><h3><?php echo $MSG_PROBLEM."-".$MSG_LIST?></h3></center>

<div class='container'>

<?php
$sql = "SELECT COUNT('problem_id') AS ids FROM `problem`";
$result = pdo_query($sql);
$row = $result[0];

$ids = intval($row['ids']);

$idsperpage = 50;
$pages = intval(ceil($ids/$idsperpage));

if(isset($_GET['page'])){ $page = intval($_GET['page']);}
else{ $page = 1;}

$pagesperframe = 5;
$frame = intval(ceil($page/$pagesperframe));

$spage = ($frame-1)*$pagesperframe+1;
$epage = min($spage+$pagesperframe-1, $pages);

$sid = ($page-1)*$idsperpage;

$sql = "";
if(isset($_GET['keyword']) && $_GET['keyword']!=""){
  $keyword = $_GET['keyword'];
  $keyword = "%$keyword%";
  $sql = "SELECT `problem_id`,`title`,`accepted`,`in_date`,`defunct` FROM `problem` WHERE (problem_id LIKE ?) OR (title LIKE ?) OR (description LIKE ?) OR (source LIKE ?)";
  $result = pdo_query($sql,$keyword,$keyword,$keyword,$keyword);
}else{
  $sql = "SELECT `problem_id`,`title`,`accepted`,`in_date`,`defunct` FROM `problem` ORDER BY `problem_id` DESC LIMIT $sid, $idsperpage";
  $result = pdo_query($sql);
}
?>

<center>
<form action=problem_list.php class="form-search form-inline">
  <input type="text" name=keyword value="<?php echo htmlentities($_GET['keyword'],ENT_QUOTES,"utf-8")?>" class="form-control search-query" placeholder="<?php echo $MSG_PROBLEM_ID.', '.$MSG_TITLE.', '.$MSG_Description.', '.$MSG_SOURCE?>">
  <button type="submit" class="form-control"><?php echo $MSG_SEARCH?></button>
</form>
</center>

<center style="margin: 15px;">
  <div class="btn-group" role="group">
    <a class="btn btn-<?php echo isset($_GET['my']) ? 'secondary' : 'primary'; ?>" 
       href="problem_list.php">
      전체 문제 보기
    </a>
    <a class="btn btn-<?php echo isset($_GET['my']) ? 'primary' : 'secondary'; ?>" 
       href="problem_list.php?my=1">
      내가 만든 문제만 보기
    </a>
  </div>
</center>

<?php
/*
echo "<select class='input-mini' onchange=\"location.href='problem_list.php?page='+this.value;\">";
for ($i=1;$i<=$cnt;$i++){
        if ($i>1) echo '&nbsp;';
        if ($i==$page) echo "<option value='$i' selected>";
        else  echo "<option value='$i'>";
        echo $i+9;
        echo "**</option>";
}
echo "</select>";
*/
?>

<center>
<table width=100% border=1 style="text-align:center;">
  <form method=post action=contest_add.php>
<input type="hidden" name=keyword value="<?php echo htmlentities($_GET['keyword'],ENT_QUOTES,"utf-8")?>">
    <tr>
      <td width=60px><?php echo $MSG_PROBLEM_ID?><input type=checkbox style='vertical-align:2px;' onchange='$("input[type=checkbox]").prop("checked", this.checked)'></td>
      <td><?php echo $MSG_TITLE?></td>
      <td><?php echo $MSG_AC?></td>
      <td><?php echo $MSG_DATE?></td>
      <?php
      if(isset($_SESSION[$OJ_NAME.'_'.'administrator']) ||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){
        if(isset($_SESSION[$OJ_NAME.'_'.'administrator']) ||isset($_SESSION[$OJ_NAME.'_'.'problem_editor']))
          echo "<td>STATUS</td><td>DELETE</td>";
        echo "<td>EDIT</td><td>TESTDATA</td>";
      }
      ?>
    </tr>
        <tr>
      <td colspan=1 style="height:40px;">Checked to</td>
      <td colspan=7>
        <input type=submit name='problem2contest' value='New Contest'>
        <input type=submit name='enable' value='Available' onclick='$("form").attr("action","problem_df_change.php")'>
        <input type=submit name='disable' value='Reserved' onclick='$("form").attr("action","problem_df_change.php")'>
        <!-- <input type=submit name='plist' value='NewsProblemList' onclick='$("form").attr("action","news_add_page.php")'> -->
      </td>
    </tr>
    <?php
    $filtered_result = array();

    $is_admin = isset($_SESSION[$OJ_NAME.'_administrator']);
    
    if (isset($_GET['my']) && !$is_admin) {
      foreach ($result as $row) {
        $pid = $row['problem_id'];
        if (isset($_SESSION[$OJ_NAME.'_p'.$pid])) {
          $filtered_result[] = $row;
        }
      }
    } else {
      $filtered_result = $result;
    }

    foreach($filtered_result as $row){
      echo "<tr>";
        echo "<td>".$row['problem_id']." <input type=checkbox style='vertical-align:2px;' name='pid[]' value='".$row['problem_id']."'></td>";
        echo "<td><a href='../problem.php?id=".$row['problem_id']."'>".$row['title']."</a></td>";
        echo "<td>".$row['accepted']."</td>";
        echo "<td>".$row['in_date']."</td>";
        $pid = $row['problem_id'];
        $is_admin = isset($_SESSION[$OJ_NAME.'_administrator']);
        $is_owner = isset($_SESSION[$OJ_NAME.'_p'.$pid]);

        // STATUS / DELETE
        if ($is_admin || $is_owner) {
          echo "<td><a href='problem_df_change.php?id=$pid&getkey=".$_SESSION[$OJ_NAME.'_getkey']."'>"
              .($row['defunct']=="N"
                ? "<span title='click to reserve it' class='green'>Available</span>"
                : "<span class='red' title='click to be available'>Reserved</span>")
              ."</a></td>";

          if($OJ_SAE || function_exists("system")){
            echo "<td><a href='#' onclick='if(confirm(\"Delete?\")) location.href=\"problem_del.php?id=$pid&getkey=".$_SESSION[$OJ_NAME.'_getkey']."\"'>Delete</a></td>";
          } else {
            echo "<td>--</td>";
          }
        } else {
          echo "<td>--</td><td>--</td>"; // 권한 없으면 비워두기
        }

        // EDIT / TESTDATA
        if ($is_admin || $is_owner) {
          echo "<td><a href='problem_edit.php?id=$pid&getkey=".$_SESSION[$OJ_NAME.'_getkey']."'>Edit</a></td>";
          echo "<td><a href='javascript:phpfm($pid);'>TestData</a></td>";
        } else {
          echo "<td>--</td><td>--</td>";
        }
    echo "</tr>";
  }
?>
  </form>
</table>
</center>

<script src='../template/bs3/jquery.min.js' ></script>

<script>
function phpfm(pid){
  //alert(pid);
  $.post("phpfm.php",{'frame':3,'pid':pid,'pass':''},function(data,status){
    if(status=="success"){
      document.location.href="phpfm.php?frame=3&pid="+pid;
    }
  });
}
</script>
</div>

<?php
if(!(isset($_GET['keyword']) && $_GET['keyword']!=""))
{
  echo "<div style='display:inline;'>";
  echo "<nav class='center'>";
  echo "<ul class='pagination pagination-sm'>";

  echo "<li class='page-item'><a href='problem_list.php?page=1".(isset($_GET['my']) ? "&my=1" : "")."'>&lt;&lt;</a></li>";
  echo "<li class='page-item'><a href='problem_list.php?page=".($page==1?1:$page-1).(isset($_GET['my']) ? "&my=1" : "")."'>&lt;</a></li>";

  for($i=$spage; $i<=$epage; $i++){
    echo "<li class='".($page==$i?"active ":"")."page-item'><a title='go to page' href='problem_list.php?page=$i".(isset($_GET['my']) ? "&my=1" : "")."'>$i</a></li>";
  }

  echo "<li class='page-item'><a href='problem_list.php?page=".($page==$pages?$page:$page+1).(isset($_GET['my']) ? "&my=1" : "")."'>&gt;</a></li>";
  echo "<li class='page-item'><a href='problem_list.php?page=$pages".(isset($_GET['my']) ? "&my=1" : "")."'>&gt;&gt;</a></li>";


  echo "</ul>";
  echo "</nav>";
  echo "</div>";
}
?>

</div>
