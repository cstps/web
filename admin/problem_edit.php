<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Edit Problem</title>
</head>
<hr>

<?php
require_once("../include/db_info.inc.php");
require_once("../include/const.inc.php");
require_once("admin-header.php");
require_once("../include/my_func.inc.php");

if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor']))) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

echo "<center><h3>"."Edit-".$MSG_PROBLEM."</h3></center>";
include_once("kindeditor.php") ;
?>

<body leftmargin="30" >
  <div class="container">
    <?php
    if (isset($_GET['id'])) {
      ;//require_once("../include/check_get_key.php");
    ?>
    <form id=problemEdit action="problem_edit.php" method="post" onsubmit='do_submit()'>
      <?php
      $sql = "SELECT * FROM `problem` WHERE `problem_id`=?";
      $result = pdo_query($sql,intval($_GET['id']));
      $row = $result[0];
      ?>

      <input type=hidden name=problem_id value='<?php echo $row['problem_id']?>'>
      <p align=left>
  
          <h3>
          <?php echo $row['problem_id']?>: <input class="input input-xxlarge" style='width:75%' type=text name=title value='<?php echo htmlentities($row['title'],ENT_QUOTES,"UTF-8")?>'>
          </h3>
        
      </p>
        <p align=left>
          <?php echo $MSG_Time_Limit?><br>
          <input class="input input-mini" type=number min="0.001" max="300" step="0.001" name=time_limit size=20 value="<?php echo $row['time_limit']?>"> sec<br><br>
          <?php echo $MSG_Memory_Limit?><br>
          <input class="input input-mini" type=number min="1" max="1024" step="1" name=memory_limit size=20 value="<?php echo $row['memory_limit']?>"> MB<br><br>
        </p>
      <p align=left>
        <?php echo "<h4>".$MSG_Description."</h4>"?>
        <textarea class="kindeditor" rows=13 name=description cols=60><?php echo htmlentities($row['description'],ENT_QUOTES,"UTF-8")?></textarea><br>
      </p>

      <p align=left>
        <?php echo "<h4>".$MSG_Input."</h4>"?>
        <textarea class="kindeditor" rows=13 name=input cols=60><?php echo htmlentities($row['input'],ENT_QUOTES,"UTF-8")?></textarea><br>
      </p>

      <p align=left>
        <?php echo "<h4>".$MSG_Output."</h4>"?>
        <textarea  class="kindeditor" rows=13 name=output cols=60><?php echo htmlentities($row['output'],ENT_QUOTES,"UTF-8")?></textarea><br>
      </p>

      <p align=left>
        <?php echo "<h4>".$MSG_Sample_Input."</h4>"?>
        <textarea  class="input input-large" style="width:80%;" rows=13 name=sample_input><?php echo htmlentities($row['sample_input'],ENT_QUOTES,"UTF-8")?></textarea><br><br>
      </p>

      <p align=left>
        <?php echo "<h4>".$MSG_Sample_Output."</h4>"?>
        <textarea  class="input input-large" style="width:80%;" rows=13 name=sample_output><?php echo htmlentities($row['sample_output'],ENT_QUOTES,"UTF-8")?></textarea><br><br>
      </p>

      <p align=left>
        <?php echo "<h4>".$MSG_HINT."</h4>"?>
        <textarea class="kindeditor" rows=13 name=hint cols=30><?php echo htmlentities($row['hint'],ENT_QUOTES,"UTF-8")?></textarea><br>
      </p>

      <p>
        <?php echo "<h4>".$MSG_SPJ."</h4>"?>
        <?php echo "(".$MSG_HELP_SPJ.")"?><br>
        <?php echo "No "?><input type=radio name=spj value='0' <?php echo $row['spj']=="0"?"checked":""?>>
        <?php echo "/ Yes "?><input type=radio name=spj value='1' <?php echo $row['spj']=="1"?"checked":""?>><br><br>
      </p>

      <p align=left>
        <?php echo "<h4>".$MSG_SOURCE."</h4>"?>
        <textarea name=source style="width:80%;" rows=1><?php echo htmlentities($row['source'],ENT_QUOTES,"UTF-8")?></textarea>
      </p>


      <!-- ace editor front_code , rear_code accept -->
      <p align=left>  
          <?php echo "<h4>".$MSG_FRONT_CODE."(언어별 분리 //C// 코드 //Python// 코드 )</h4>"?>
          <?php 
            echo "<h6>";
            for($i=0;$i<count($language_name);$i++){
              echo $language_name[$i]."//";
            }
            echo "</h6>";
          ?>
          <?php if($OJ_ACE_EDITOR){ ?>
          <pre style="width:80%;height:20%" cols=180 rows=3 id="front_code" ><?php echo htmlentities($row['front_code'],ENT_QUOTES,"UTF-8")?></pre><br>
          <input type=hidden id="front_code_source" name=front_code value=""/>
        <?php }else{ ?>
          <textarea style="width:80%;height:20%" cols=180 rows=4 id="front_code" name=front_code></textarea><br>
        <?php }?>
      </p>
        <p align=left> 
          <?php echo "<h4> ".$MSG_REAR_CODE."</h4>"?>
          <?php if($OJ_ACE_EDITOR){ ?>
          <pre style="width:80%;height:20%" cols=180 rows=5 id="rear_code"><?php echo htmlentities($row['rear_code'],ENT_QUOTES,"UTF-8")?></pre><br>
          <input type=hidden id="rear_code_source" name=rear_code value=""/>
        <?php }else{ ?>
          <textarea style="width:80%;height:20%" cols=180 rows=4 id="rear_code" name=rear_code></textarea><br>
        <?php }?>
        </p>
        <p align=left> 
          <?php echo "<h4>".$MSG_BAN_CODE."(/로 구분해서 입력 ex: for/if )</h4>"?>
          <input name=ban_code style="width:80%;" value =<?php echo htmlentities($row['ban_code'],ENT_QUOTES,"UTF-8")?> ></input>
        </p>
        <p align=left> 
          <?php echo "<h4>".$MSG_PRO_POINT."(정수로 입력)</h4>"?>
          <input class="input input-mini" type=number min="1" max="300" step="1" name=pro_point size=20 value="<?php echo $row['pro_point'] ?>">점<br><br>
        </p>

      
      <div align=center>
        <?php require_once("../include/set_post_key.php");?>
        <input type=submit value='<?php echo $MSG_SAVE?>' name=submit>
      </div>
    </form>

    <?php
    }
    else {
      require_once("../include/check_post_key.php");
      
      $id = intval($_POST['problem_id']);

      if (!(isset($_SESSION[$OJ_NAME.'_'."p$id"]) || isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor']) )) exit();  

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
      if ($sample_input=="") $sample_input="\n";
      if ($sample_output=="") $sample_output="\n";

      $hint = $_POST['hint'];
      $hint = str_replace("<p>", "", $hint); 
      $hint = str_replace("</p>", "<br />", $hint);
      $hint = str_replace(",", "&#44;", $hint);

      $source = $_POST['source'];
      $spj = $_POST['spj'];

      // 앞뒤, 금지어, 포인트 추가
      // 빈줄 제거
      $front_code= preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $_POST['front_code']);
      $rear_code= preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $_POST['rear_code']);
      
      $ban_code = $_POST['ban_code'];
      $pro_point = $_POST['pro_point'];

      /* php 7.4 버전부터  get_magic_quotes_gpc() 삭제되어 false가 되어 더 이상 실행되지 않는다. 
      // DB작업을 하기 위해 쿼리를 작성할 때 따옴표가 문자열에 있으면 오류가 발생한다. 
      // 이럴때 addslashes()- 쿼리안의 따옴표를 예외문자로 \'로 처리, stripslashes() - 쿼리안에 예외문자 백슬래시 제거
      // 이럴 한번에 하는 함수가 magic_quotes_gpc() 이고 get_magic_quotes_gpc()은 설정값을 확인
      if (get_magic_quotes_gpc()) {
        $title = stripslashes($title);
        $time_limit = stripslashes($time_limit);
        $memory_limit = stripslashes($memory_limit);
        $description = stripslashes($description);
        $input = stripslashes($input);
        $output = stripslashes($output);
        $sample_input = stripslashes($sample_input);
        $sample_output = stripslashes($sample_output);
        //$test_input = stripslashes($test_input);
        //$test_output = stripslashes($test_output);
        $hint = stripslashes($hint);
        $source = stripslashes($source); 
        $spj = stripslashes($spj);
        $front_code = stripslashes($front_code);
        $rear_code = stripslashes($rear_code);
        $ban_code = stripslashes($ban_code);
        $pro_point = stripslashes($pro_point);  
      }
      */
      $title = ($title);
      $description = RemoveXSS($description);
      $input = RemoveXSS($input);
      $output = RemoveXSS($output);
      $hint = RemoveXSS($hint);
      $basedir = $OJ_DATA."/$id";

      $front_code = RemoveXSS($front_code);
      $rear_code = RemoveXSS($rear_code);
      $ban_code = RemoveXSS($ban_code);


      echo "Problem Updated!<br>";

      if ($sample_input && file_exists($basedir."/sample.in")) {
        //mkdir($basedir);
        $fp = fopen($basedir."/sample.in","w");
        fputs($fp,preg_replace("(\r\n)","\n",$sample_input));
        fclose($fp);

        $fp = fopen($basedir."/sample.out","w");
        fputs($fp,preg_replace("(\r\n)","\n",$sample_output));
        fclose($fp);
      }

      $spj = intval($spj);

      $sql = "UPDATE `problem` SET `title`=?,`time_limit`=?,`memory_limit`=?, `description`=?,`input`=?,`output`=?,`sample_input`=?,`sample_output`=?,`hint`=?,`source`=?,`spj`=?,`in_date`=NOW(),`front_code`=?,`rear_code`=?,`ban_code`=?,`pro_point`=? WHERE `problem_id`=?";

      @pdo_query($sql,$title,$time_limit,$memory_limit,$description,$input,$output,$sample_input,$sample_output,$hint,$source,$spj,$front_code, $rear_code, $ban_code, $pro_point,$id);

      

      echo "Edit OK!<br>";
      echo "<a href='../problem.php?id=$id'>See The Problem!</a>";
    }
    ?>
  </div>
  <script>
    
  function do_submit(){
    if(typeof(editorFrontCode) != "undefined"){ 
      $("#front_code_source").val(editorFrontCode.getValue());
    }
    if(typeof(editorRearCode) != "undefined"){ 
      $("#rear_code_source").val(editorRearCode.getValue());
    }
    document.getElementById("problemEdit").target="_self";
    document.getElementById("problemEdit").submit();
  }
  </script>
  
  <?php if($OJ_ACE_EDITOR){ // ACE 에디터를 적용하여 front , rear 코드 형태가 잘 보이도록 21.12.23 ?>
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
        enableLiveAutocompletion: false,
      });
      var editorRearCode = ace.edit("rear_code");
      editorRearCode.setTheme("ace/theme/chrome");
      editorRearCode.session.setMode("ace/mode/c_cpp");
      editorRearCode.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: false,
      });


  </script>
  <?php }?>
</body>
</html>
