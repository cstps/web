<html>
<head>
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Content-Language" content="zh-cn">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>New Problem</title>
</head>
<body leftmargin="30">
<center>
<?php require_once("../include/db_info.inc.php");?>

<?php require_once("admin-header.php");
if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']))){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}?>
<?php
include_once("kindeditor.php") ;
?>

<table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse"  width="100%" height="50">
<tr>
<td width="100"></td>
<td>
<p align="center"><font color="#333399" size="4">Welcome To Administrator's Page of Judge Online of ACM ICPC,<?php echo $OJ_NAME?>.</font></td>
<td width="100"></td>
</tr>
</table>
</center>
<hr>
<h1>Add New problem</h1>
<?php require_once("../include/simple_html_dom.php");
define('SERVER_CHARSET', 'UTF-8');

function getPartByMark($html,$mark1,$mark2){
  // mb_strpos 는 대상 문자열을 앞에서 부터 검색하여 찾고자 하는 문자열이 몇번째 위치에 있는지를
  // 리턴하는 함수인데, 한글과 같이 2byte 짜리 문자가 포함된 문자열에 대한 정확한 처리를 하기 위한 함수이며,
   $i=mb_strpos($html,$mark1);
   $j=mb_strpos($html,$mark2);

   //mb_substr은 문자열, 시작 지점, 길이, 현재 파일의 인코딩(=문자셋 혹은 charset)을 인수로 사용

  $descriptionHTML=mb_substr($html,$i+ mb_strlen($mark1),$j-($i+ mb_strlen($mark1)),SERVER_CHARSET);
  
   return $descriptionHTML;
}
  $url="http://www.judgeon.net/problem.php?id=".$_POST ['url'];

  if (!$url) $url=$_GET['url'];
  if (strpos($url, "http") === false){
	echo "Please Input like http://hustoj.com/oj/problem.php?id=1000";
	exit(1);
  }   
    
  if (get_magic_quotes_gpc ()) {
	$url = stripslashes ( $url);
  }
  $baseurl=substr($url,0,strrpos($url,"/")+1);
//  echo $baseurl;
  $html = file_get_html($url);
  foreach($html->find('img') as $element)
        $element->src=$baseurl.$element->src;
  $element=$html->find('h2',0);
  $html_original = $html;
  $index = mb_strpos($element->plaintext,":");

  // $title=mb_substr($element->plaintext,$index+2,mb_strlen($element->plaintext)-$index-1);
  $title=mb_substr($element->plaintext,$index-4,mb_strlen($element->plaintext));
  
  $i=1;
  $sample_outputHTML=$sample_inputHTML=$descriptionHTML="";
  $inputHTML=$outputHTML="";
  $hintHTML=$sourceHTML ="";
  $test_inputHTML=$test_outputHTML="";
  $html=$html->innertext;
 // echo $i."-".strlen($html);
// descriptionHTML
  if(strpos($html,"<h2>문제 설명</h2>")>0){
 	 $descriptionHTML=getPartByMark($html,"<h2>문제 설명</h2>","<h2>입력</h2>");
  }else if(strpos($html,"<strong>문제 설명</strong>")>0){
    $descriptionHTML=getPartByMark($html,"<strong>문제 설명</strong>","<strong>입력</strong>");
  }
 // echo $i."-".strlen($descriptionHTML);
// inputHTML
  if(strpos($html,"<h2>입력</h2>")>0){
   $inputHTML=getPartByMark($html,"<h2>입력</h2>","<h2>출력</h2>");
  }else if(strpos($html,"<strong>입력</strong>")>0){
    $inputHTML=getPartByMark($html,"<strong>입력</strong>","<strong>출력</strong>");
   }
  //outputHTML
  if(strpos($html,"<h2>출력</h2>")>0){
    if(strpos($html,"<h2>입력예시</h2>")>0){
      $outputHTML=getPartByMark($html,"<h2>출력</h2>","<h2>입력예시</h2>");
    }else{
      $outputHTML=getPartByMark($html,"<h2>출력</h2>","<h2>출력예시</h2>");
    }
   }
   //sample_inputHTML
   
   if(strpos($html,"<h2>입력예시</h2>")>0){
      $sample_inputHTML=$html_original->find('span.sampledata',0)->plaintext;
      $sample_outputHTML=$html_original->find('span.sampledata',1)->plaintext; 
   }else{
      $sample_outputHTML=$html_original->find('span.sampledata',0)->plaintext;
   }
   if(strpos($html,"<h2>도움말</h2>")>0){
    $hintHTML=getPartByMark($html,"<h2>도움말</h2>","<h2>출처</h2>");
   }
   if(strpos($html,"<h2>출처</h2>")>0){
    $sourceHTML =$html_original->find('[href*=problemset.php?search]',0)->plaintext;
   }
?>
<form method=POST action=problem_add.php>
<p align=center><font size=4 color=#333399>Add a Problem</font></p>
<input type=hidden name=problem_id value=New Problem>
<p align=left>Problem Id:&nbsp;&nbsp;New Problem</p>
<p align=left>Title:<input type=text name=title size=71 value="<?php echo $title?>"></p>
<p align=left>Time Limit:<input type=text name=time_limit size=20 value=1>S</p>
<p align=left>Memory Limit:<input type=text name=memory_limit size=20 value=128>MByte</p>
<p align=left>Description:<br>
<textarea class="kindeditor" rows=13 name=description cols=80><?php echo $descriptionHTML;?></textarea>
</p>
<p align=left>Input:<br>
<textarea class="kindeditor" rows=13 name=input cols=80><?php echo $inputHTML;?></textarea>
</p>
</p>
<p align=left>Output:<br><!--<textarea rows=13 name=output cols=80></textarea>-->
<textarea class="kindeditor" rows=13 name=output cols=80><?php echo $outputHTML;?></textarea>
</p>
<p align=left>Sample Input:<br><textarea rows=13 name=sample_input cols=80><?php echo str_replace("  ","\n",$sample_inputHTML)?></textarea></p>
<p align=left>Sample Output:<br><textarea rows=13 name=sample_output cols=80><?php echo str_replace("  ","\n",$sample_outputHTML)?></textarea></p>
<p align=left>Test Input:<br><textarea rows=13 name=test_input cols=80><?php echo $test_inputHTML?></textarea></p>
<p align=left>Test Output:<br><textarea rows=13 name=test_output cols=80><?php echo $test_outputHTML?></textarea></p>
<p align=left>Hint:<br>
<textarea class="kindeditor" rows=13 name=hint cols=80><?php echo $hintHTML?></textarea>
</p>
<p>SpecialJudge: N<input type=radio name=spj value='0' checked>Y<input type=radio name=spj value='1'></p>
<p align=left>Source:<br><textarea name=source rows=1 cols=70><?php echo $sourceHTML?></textarea></p>
<p align=left>만든사람:<br><textarea name=creator rows=1 cols=70></textarea></p>
<p align=left>  
  
<?php echo "<h4>".$MSG_FRONT_CODE."</h4>"?>
  <?php if($OJ_ACE_EDITOR){ ?>
  <pre style="width:80%;height:200" cols=180 rows=5 id="front_code" ></pre><br>
  <input type=hidden id="front_code_source" name=front_code value=""/>
<?php }else{ ?>
  <textarea style="width:80%;height:200" cols=180 rows=5 id="front_code" name=front_code></textarea><br>
<?php }?>
</p>

<p align=left> 
  <?php echo "<h4> ".$MSG_REAR_CODE."</h4>"?>
  <?php if($OJ_ACE_EDITOR){ ?>
  <pre style="width:80%;height:200" cols=180 rows=5 id="rear_code" ></pre><br>
  <input type=hidden id="rear_code_source" name=rear_code value=""/>
  <?php }else{ ?>
    <textarea style="width:80%;height:200" cols=180 rows=5 id="rear_code" name=rear_code></textarea><br>
  <?php }?>
</p>
<p align=left> 
  <?php echo "<h4>".$MSG_BAN_CODE."(/로 구분해서 입력 ex: for/if )</h4>"?>
  <input name=ban_code style="width:100%;" ></input><br><br>
</p>
<p align=left> 
  <?php echo "<h4>".$MSG_PRO_POINT."(정수로 입력)</h4>"?>
  <input class="input input-mini" type=number min="1" max="300" step="1" name=pro_point size=20 value=1>점<br><br>
</p>
<p align=left>contest:
	<select  name=contest_id>
<?php $sql="SELECT `contest_id`,`title` FROM `contest` WHERE `start_time`>NOW() order by `contest_id`";
$result=pdo_query($sql);
echo "<option value=''>none</option>";
if (count($result)==0){
}else{
	foreach($result as $row)
			echo "<option value='{$row['contest_id']}'>{$row['contest_id']} {$row['title']}</option>";
}
?>
	</select>
</p>
<div align=center>
<?php require_once("../include/set_post_key.php");?>
<input type=submit value=Submit name=submit>
</div></form>
<p>
<?php if($OJ_ACE_EDITOR){ ?>
  <script src="../ace/ace.js"></script>
  <script src="../ace/ext-language_tools.js"></script>
  <script>
      ace.require("../ace/ext/language_tools");
      var editorFrontCode = ace.edit("front_code");
      editorFrontCode.setTheme("ace/theme/chrome");
      editorFrontCode.session.setMode("ace/mode/c_cpp");
      editorFrontCode.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
      });
      var editorRearCode = ace.edit("rear_code");
      editorRearCode.setTheme("ace/theme/chrome");
      editorRearCode.session.setMode("ace/mode/c_cpp");
      editorRearCode.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
      });
  </script>
  <?php }?>
</body>
</html>

