<?php
require_once ("admin-header.php");
require_once("../include/check_post_key.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor']))) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

require_once ("../include/db_info.inc.php");
require_once ("../include/my_func.inc.php");
require_once ("../include/problem.php");

// contest_id
$title = $_POST['title'];
$title = str_replace(",", "&#44;", $title);
$time_limit = $_POST['time_limit'];
$memory_limit = $_POST['memory_limit'];

$description = $_POST['description'];
$description = str_replace("<p>", "", $description); 
$description = str_replace("</p>", "<br />", $description);
$description = str_replace(",", "&#44;", $description); 

$input = $_POST['input'];
$input = str_replace("<p>", "", $input); 
$input = str_replace("</p>", "<br />", $input); 
$input = str_replace(",", "&#44;", $input);

$output = $_POST['output'];
$output = str_replace("<p>", "", $output); 
$output = str_replace("</p>", "<br />", $output);
$output = str_replace(",", "&#44;", $output); 

$sample_input = $_POST['sample_input'];
$sample_output = $_POST['sample_output'];
$test_input = $_POST['test_input'];
$test_output = $_POST['test_output'];
/* don't do this , we will left them empty for not generating invalid test data files 
if ($sample_input=="") $sample_input="\n";
if ($sample_output=="") $sample_output="\n";
if ($test_input=="") $test_input="\n";
if ($test_output=="") $test_output="\n";
*/
$hint = $_POST['hint'];
$hint = str_replace("<p>", "", $hint); 
$hint = str_replace("</p>", "<br />", $hint); 
$hint = str_replace(",", "&#44;", $hint);

$source = $_POST['source'];

$spj = $_POST['spj'];

// 앞뒤, 금지어, 포인트 추가
//빈줄 제거
$front_code= preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $_POST['front_code']);
$rear_code= preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $_POST['rear_code']);

$front_code = htmlentities($front_code,ENT_QUOTES,"UTF-8");
$rear_code = htmlentities($rear_code,ENT_QUOTES,"UTF-8");
$ban_code = $_POST['ban_code'];
$pro_point = $_POST['pro_point'];

//php7.4에서 해당 기능 삭제됨
// if (get_magic_quotes_gpc()) {
//   $title = stripslashes($title);
//   $time_limit = stripslashes($time_limit);
//   $memory_limit = stripslashes($memory_limit);
//   $description = stripslashes($description);
//   $input = stripslashes($input);
//   $output = stripslashes($output);
//   $sample_input = stripslashes($sample_input);
//   $sample_output = stripslashes($sample_output);
//   $test_input = stripslashes($test_input);
//   $test_output = stripslashes($test_output);
//   $hint = stripslashes($hint);
//   $source = stripslashes($source);
//   $spj = stripslashes($spj);
//   $source = stripslashes($source);
//   $front_code = stripslashes($front_code);
//   $rear_code = stripslashes($rear_code);
//   $ban_code = stripslashes($ban_code);
//   $pro_point = stripslashes($pro_point);  
// }

$title = RemoveXSS($title);
$description = RemoveXSS($description);
$input = RemoveXSS($input);
$output = RemoveXSS($output);
$hint = RemoveXSS($hint);

$front_code = RemoveXSS($front_code);
$rear_code = RemoveXSS($rear_code);
$ban_code = RemoveXSS($ban_code);

//echo "->".$OJ_DATA."<-"; 
$pid = addproblem($title, $time_limit, $memory_limit, $description, $input, $output, $sample_input, $sample_output, $hint, $source, $spj, $OJ_DATA, $front_code, $rear_code, $ban_code, $pro_point);
$basedir = "$OJ_DATA/$pid";
mkdir($basedir);
if(strlen($sample_output) && !strlen($sample_input)) $sample_input = "0";
if(strlen($sample_input)) mkdata($pid, "sample.in", $sample_input, $OJ_DATA);
if(strlen($sample_output)) mkdata($pid, "sample.out", $sample_output, $OJ_DATA);
if(strlen($test_output) && !strlen($test_input)) $test_input = "0";
if(strlen($test_input)) mkdata($pid,"test.in", $test_input, $OJ_DATA);
if(strlen($test_output)) mkdata($pid,"test.out", $test_output, $OJ_DATA);

$sql = "INSERT INTO `privilege` (`user_id`,`rightstr`) VALUES(?,?)";
pdo_query($sql, $_SESSION[$OJ_NAME.'_'.'user_id'], "p$pid");
$_SESSION[$OJ_NAME.'_'."p$pid"] = true;
  
echo "&nbsp;&nbsp;- <a href='javascript:phpfm($pid);'>Add more TestData now!</a>";
/*  */
?>

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
