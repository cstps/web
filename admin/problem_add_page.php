<html>
<head>
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Cache-Control" content="no-cache">
  <meta http-equiv="Content-Language" content="zh-cn">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Problem Add</title>
</head>
<hr>


<?php 
  require_once("../include/db_info.inc.php");
  require_once("../include/const.inc.php");
  require_once("admin-header.php");
  if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator']) || isset($_SESSION[$OJ_NAME.'_'.'problem_editor']))) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
  }
  echo "<center><h3>".$MSG_PROBLEM."-".$MSG_ADD."</h3></center>";
  include_once("kindeditor.php") ;
?>

<body leftmargin="30" >
  <div class="container">
    <form method=POST id=problemAdd action=problem_add.php onsubmit='do_submit()'>
      <input type=hidden name=problem_id value="New Problem">
        <p align=left>
          <?php echo "<h3>".$MSG_TITLE."</h3>"?>
          <input class="input input-xxlarge" style="width:80%;" type=text name=title><br><br>
        </p>
        <p align=left>
          <?php echo $MSG_Time_Limit?><br>
          <input class="input input-mini" type=number min="0.001" max="300" step="0.001" name=time_limit size=20 value=1> sec<br><br>
          <?php echo $MSG_Memory_Limit?><br>
          <input class="input input-mini" type=number min="1" max="1024" step="1" name=memory_limit size=20 value=128> MB<br><br>
        </p>
 
        <p align=left>
          <?php echo "<h4>".$MSG_Description."</h4>"?>
          <textarea class="kindeditor" rows=13 name=description cols=80></textarea><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Input."</h4>"?>
          <textarea class="kindeditor" rows=13 name=input cols=80></textarea><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Output."</h4>"?>
          <textarea  class="kindeditor" rows=13 name=output cols=80></textarea><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Sample_Input."</h4>"?>
          <textarea  class="input input-large" style="width:80%;" rows=13 name=sample_input></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Sample_Output."</h4>"?>
          <textarea  class="input input-large" style="width:80%;" rows=13 name=sample_output></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Test_Input."</h4>"?>
          <?php echo "(".$MSG_HELP_MORE_TESTDATA_LATER.")"?><br>
          <textarea class="input input-large" style="width:80%;" rows=13 name=test_input></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Test_Output."</h4>"?>
          <?php echo "(".$MSG_HELP_MORE_TESTDATA_LATER.")"?><br>
          <textarea class="input input-large" style="width:80%;" rows=13 name=test_output></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_HINT."</h4>"?>
          <textarea class="kindeditor" rows=13 name=hint cols=80></textarea><br>
        </p>
        <p>
          <?php echo "<h4>".$MSG_SPJ."</h4>"?>
          <?php echo "(".$MSG_HELP_SPJ.")"?><br>
          <?php echo "No "?><input type=radio name=spj value='0' checked><?php echo "/ Yes "?><input type=radio name=spj value='1'><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_SOURCE."(//로 구분)</h4>"?>
          <textarea name=source style="width:80%;" rows=1></textarea><br><br>
        </p>
        <p align=left>
          <?php echo "<h4>".$MSG_Creator."(문제 출제자와 등록자가 다른 경우만 작성) </h4>"?>
          <textarea name=creator style="width:80%;" rows=1></textarea><br><br>
        </p>
        
        <p align=left>  
        <?php echo "<h4>".$MSG_FRONT_CODE."(언어별 분리 //C// 코드 //Python// 코드 )</h4>"?>
          <?php 
            echo "<span>";
            for($i=0;$i<count($language_name);$i++){
              echo $language_name[$i]."//";
            }
            echo "</span>";
          ?>
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
          <input name=ban_code style="width:80%;" ></input><br><br>
        </p>
        <p align=left> 
          <?php echo "<h4>".$MSG_PRO_POINT."(정수로 입력)</h4>"?>
          <input class="input input-mini" type=number min="1" max="300" step="1" name=pro_point size=20 value=1>점<br><br>
        </p>
        <div align=center>
          <?php require_once("../include/set_post_key.php");?>
          <input type=submit value='<?php echo $MSG_SAVE?>' name=submit>
        </div>
      </input>
    </form>
  </div>
  <script>
    
  function do_submit(){
    if(typeof(editorFrontCode) != "undefined"){ 
      $("#front_code_source").val(editorFrontCode.getValue());
    }
    if(typeof(editorRearCode) != "undefined"){ 
      $("#rear_code_source").val(editorRearCode.getValue());
    }
    document.getElementById("problemAdd").target="_self";
    document.getElementById("problemAdd").submit();
  }
  </script>
  <?php if($OJ_ACE_EDITOR){ ?>
  <script src="../ace/ace.js"></script>
  <script src="../ace/ext-language_tools.js"></script>
  <script>
      ace.require("../ace/ext/language_tools");
      var editorFrontCode = ace.edit("front_code");
      editorFrontCode.setTheme("ace/theme/chrome");
      editorFrontCode.session.setMode("ace/mode/c_cpp");
      editorFrontCode.setOptions({
        enableSnippets: true,
        enableLiveAutocompletion: false,
      });
      var editorRearCode = ace.edit("rear_code");
      editorRearCode.setTheme("ace/theme/chrome");
      editorRearCode.session.setMode("ace/mode/c_cpp");
      editorRearCode.setOptions({
        enableSnippets: true,
        enableLiveAutocompletion: false,
      });
  </script>
  <?php }?>
</body>
</html>
