<?php
	require_once("discuss_func.inc.php");
	echo "<title>1024 Online Judge WebBoard >> 새글 </title>";
	if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
		echo "<a href=loginpage.php>Please Login First</a>";
		require_once("template/$OJ_TEMPLATE/discuss.php");
		exit(0);
	}
	if(isset($_GET['pid']))
		$pid=intval($_GET['pid']);
	else	
		$pid="";
	if(isset($_GET['cid'])){
		$cid=intval($_GET['cid']);
		if($pid>0){
		  $pid=pdo_query("select num from contest_problem where problem_id=? and contest_id=?",$pid,$cid)[0][0];
		  $pid=$PID[$pid];
		}
	}else{
		$cid=0;
	}

	
?>
<script src="../tinymce/tinymce.min.js"></script>


<center>
<div style="width:90%; text-align:left">
<h2 style="margin:0px 10px">새글 작성하기<?php if (array_key_exists('cid',$_REQUEST) && $_REQUEST['cid']!='') echo ' For Contest '.intval($_REQUEST['cid']);?></h2>
<form action="post.php?action=new" method=post>
<input type=hidden name=cid value="<?php if (array_key_exists('cid',$_REQUEST)) echo intval($_REQUEST['cid']);?>">
<div style="margin:0px 10px">문제번호 : </div>
<div><input name=pid style="border:1px dashed #8080FF; width:100px; height:20px; font-size:75%;margin:0 10px; padding:2px 10px" value="<?php echo $pid;?>"></div>
<div style="margin:0px 10px">제목 : </div>
<div><input name=title style="border:1px dashed #8080FF; width:700px; height:20px; font-size:75%;margin:0 10px; padding:2px 10px"></div>
<div style="margin:0px 10px">내용 : </div>
<div><textarea name=content id="mytextarea" ></textarea></div>
<div><input type="submit" style="margin:5px 10px" value="제출"></input></div>
</form>
</div>
</center>
<script>
    tinymce.init({
        selector: '#mytextarea',
        height: 500,
    });
  </script>
<?php require_once("template/$OJ_TEMPLATE/discuss.php")?>
