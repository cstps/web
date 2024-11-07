<?php
          if($pr_flag){
            $show_title="P$id - ".$row['title']." - $OJ_NAME";
          }else{
            $id=$row['problem_id'];
            $show_title="문제 ".$PID[$pid].": ".$row['title']." - $OJ_NAME";
          }
?>
<?php include("template/$OJ_TEMPLATE/header.php");?>

<style>
.ace_cursor {
  border-left-width: 1px !important;
  color: #000 !important;
}

#languages-menu::-webkit-scrollbar, #testcase-menu::-webkit-scrollbar {
    width: 0px;
    background: transparent;
}

div[class*=ace_br] {
    border-radius: 0 !important;
}
.copy {
    font-size: 12px;
    color: #4d4d4d;
    background-color: white;
    padding: 2px 8px;
    margin: 8px;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.05);
}
.code{
  display:inline-block;
  vertical-align: top;
}
</style>

<script src="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE/"?>js/ace.min.js"></script>
<script src="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE/"?>clipboard.min.js"></script>
<div class="ui center aligned grid">
    <div class="row">
      <h1 class="ui header">
        <?php
          if($pr_flag){
            echo "$id: ".$row['title'];
            // <%= problem.title %><% if (problem.allowedEdit && !problem.is_public) { %><span class="ui tiny red label">未公开</span><% } %>";
            //echo "<title>$MSG_PROBLEM".$row['problem_id']."--". $row['title']."</title>";
            //echo "<center><h2><strong>$id: ".$row['title']."</strong></h2>";
          }else{
            $id=$row['problem_id'];
            //echo "<title>$MSG_PROBLEM ".$PID[$pid].": ".$row['title']." </title>";
            echo "문제 ".$PID[$pid].": ".$row['title'];
          }
          if($row['defunct']=="Y")
          echo "<span class=\"ui tiny red label\">목록없음</span>";
        ?>
      </h1>
    </div>
      <div class="row" style="margin-top: -15px">
          <span class="ui label yellow">메모리：<?php echo $row['memory_limit']; ?> MB</span>
          <span class="ui label purple">시간：<?php echo $row['time_limit']; ?> S</span>
          <!-- <span class="ui label">문제 타입：interaction</span> -->
          <!-- <span class="ui label">input file: <%= problem.file_io_input_name %></span>
          <span class="ui label">output file: <%= problem.file_io_output_name %></span> -->
          <!-- echo "<br><span class=green>$MSG_SUBMIT: </span>".$row['submit']."&nbsp;&nbsp;";
          echo "<span class=green>$MSG_SOVLED: </span>".$row['accepted']."<br>"; -->
          <span class="ui label">표준 입력 및 출력</span>
      </div>
      <div class="row" style="margin-top: -23px">
          <span class="ui label">문제유형</span>
          <span class="ui label">채점방법：<?php if($row['spj']) echo "Special Judge"; else echo "일반" ; ?></span>
          <span class="ui label"><?php echo $MSG_Creator; ?>：<span id='creator'></span></span>
      </div>
      <div class="row" style="margin-top: -23px">
          <span class="ui label">제출：<?php echo $row['submit']; ?></span>
          <span class="ui label">통과：<?php echo $row['accepted']; ?></span>
      </div>
</div>  
<div class="ui grid">
  <div class="row"> 
    <div class="column">
      <div class="ui buttons">

          <?php
            if($pr_flag){
              echo "<a class=\"small ui primary button\" href=\"submitpage.php?id=$id\">제출</a>";
              echo "<a class=\"small ui positive button\" href=\"status.php?problem_id=$id\">채점기록</a>";
              echo "<a class=\"small ui orange button\" href=\"problemstatus.php?id=$id\">통계</a>";
			        echo "<a class=\"small ui pink button\" href=\"discuss.php?pid=$id\">$MSG_BBS</a>";
            }else{
              echo "<a href=\"contest.php?cid=$cid\" class=\"ui orange button\">대회로 돌아가기</a>";
              echo "<a class=\"small ui primary button\" href=\"submitpage.php?cid=$cid&pid=$pid&langmask=$langmask\">제출</a>";
              echo "<a class=\"small ui positive button\" href=\"status.php?problem_id=$PID[$pid]&cid=$cid\">채점기록</a>";
            }
          ?>
          
      </div>
     
      <?php
        if ( isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'."p".$row['problem_id']])  ) {  //only  the original editor can edit this  problem
        
        require_once("include/set_get_key.php");
      ?>
      
        <div class="ui buttons right floated">
            <a class="small ui button" href="admin/problem_edit.php?id=<?php echo $id?>&getkey=<?php echo $_SESSION[$OJ_NAME.'_'.'getkey']?>">문제수정</a>
            <a class="small ui button" href='javascript:phpfm(<?php echo $row['problem_id'];?>)'>채점데이터</a>
        </div>
      <?php }?>
    </div>
  </div>
  <?php
    if($row['front_code'] || $row['rear_code'] || $row['ban_code']){ ?>
      <div class="row">
        <div class="column">
          <div class="ui bottom attached segment font-content">
            <?php 
              function preCodePrint($ln,$fc,$rc){
                echo "<span><code class='lang-c'><center>".$ln."</center>";
                if($fc!=""){
                  echo "<pre>".$fc."</pre>";
                }
                echo "<pre class='ui label brown'> 여기에 알맞는 코드</pre>";
                if($rc!=""){
                  echo "<pre>".$rc."</pre>";
                }
                echo "</code></span>";
              }
              // front_code, rear_code 정보 제공 c언어와  python으로 분리
              if($row['front_code'] || $row['rear_code']){
                echo "<div class='code'><center><h3>"."[미리 작성된 코드]"."</h3></center>";
                echo "";
                //htmlentities를 통해 특수문자를 <>& 등을 html에 보이도록 설정
                $front_code = htmlentities($row['front_code'] );
                $rear_code = htmlentities($row['rear_code'] );
                // 분리하기 위해 해당 언어별로 주석표시가 있는지 본다. //Python//
                $cnt_language = count($language_name);
                for($i=0;$i<$cnt_language;$i++){
                  $find_str = "//".$language_name[$i]."//";
                  $lang_name_print="";
                  $front_code_print ="";
                  $rear_code_print ="";
                  //front code 내용 확인하여 언어별 분리
                  if(strpos($front_code,$find_str)!==false){
                    $split_str = explode($find_str,$front_code);
                    $lang_name_print = $language_name[$i];
                    $front_code_print = explode("//",$split_str[1])[0];
                  }
                  //rear code 내용 확인하여 언어별 분리
                  if(strpos($rear_code,$find_str)!==false){
                    $split_str = explode($find_str,$rear_code);
                    $lang_name_print = $language_name[$i];
                    $rear_code_print = explode("//",$split_str[1])[0];
                  }
                  if($lang_name_print!==""){
                    preCodePrint($lang_name_print, $front_code_print, $rear_code_print);
                    //구분선 
                    echo "<span> </span>";
                  }

                }
                echo "</div>";
              } 
              // ban_code 정보 제공
              if($row['ban_code']){
                $ban_code = explode("/", $row['ban_code']);
                echo "<span> </span><span><div class='code'><center><h3>[".$MSG_BAN_CODE."]</h3></center>";
                foreach($ban_code as $ban_word)
                  echo "<code class='ui red label'>".$ban_word."</code>";
                echo "</div></span>";
              }
            ?>
          </div>
        </div>
      </div>
  <?php } ?>
  <div class="row">
    <div class="column">
      <h4 class="ui top attached block header">문제설명</h4>
      <div class="ui bottom attached segment font-content"><?php echo $row['description'];?>
      </div>      
    </div>
  </div>
  
  <?php if($row['input']){ ?>
    <div class="row">
      <div class="column">
          <h4 class="ui top attached block header">입력조건</h4>
          <div class="ui bottom attached segment font-content"><?php echo $row['input']; ?></div>
      </div>
    </div>
  <?php }?>
  <?php if($row['output']){ ?>
    <div class="row">
        <div class="column">
          <h4 class="ui top attached block header">출력조건</h4>
          <div class="ui bottom attached segment font-content"><?php echo $row['output']; ?></div>
        </div>
    </div>
  <?php }?>

  <?php
    $sinput=str_replace("<","&lt;",$row['sample_input']);
    $sinput=str_replace(">","&gt;",$sinput);
    $soutput=str_replace("<","&lt;",$row['sample_output']);
    $soutput=str_replace(">","&gt;",$soutput);
  ?>
  <?php if(strlen($sinput)){ ?>
    <div class="row">
        <div class="column">
          <h4 class="ui top attached block header">입력예시
          <span class="copy" id="copyin" data-clipboard-text="<?php echo ($sinput); ?>">복사</span>
          </h4>
          <!-- <span class=copy id=\"copyin\" data-clipboard-text=\"".($sinput)."\">复制</span> -->
          <div class="ui bottom attached segment font-content">
            <!-- <pre><?php echo ($sinput); ?></pre> -->
            <pre style="margin-top: 0; margin-bottom: 0; "><code class="lang-plain"><?php echo ($sinput); ?></code></pre>
          </div>
        </div>
    </div>
  <?php }?>
  <?php if(strlen($soutput)){ ?>
    <div class="row">
        <div class="column">
          <h4 class="ui top attached block header">출력예시
          <span class="copy" id="copyout" data-clipboard-text="<?php echo ($soutput); ?>">복사</span>
          </h4>
          <!-- <span class=copy id=\"copyout\" data-clipboard-text=\"".($soutput)."\">复制</span> -->
          <div class="ui bottom attached segment font-content">
            <!-- <div class="ui existing segment"> -->
              <pre style="margin-top: 0; margin-bottom: 0; "><code class="lang-plain"><?php echo ($soutput); ?></code></pre>
            <!-- </div> -->
          </div>
        </div>
    </div>
  <?php }?>
  <?php if($row['hint']){ ?>
    <div class="row">
        <div class="column">
          <h4 class="ui top attached block header">힌트</h4>
          <div class="ui bottom attached segment font-content"><?php echo $row['hint']; ?></div>
        </div>
    </div>
  <?php }?>
  <?php
    $tcolor=0;
  ?>
  <?php if($row['source']){
    $cats=explode("//",$row['source']);
  ?>
    <div class="row">
      <div class="column">
        <h4 class="ui block header top attached" id="show_tag_title_div" style="margin-bottom: 0; margin-left: -1px; margin-right: -1px; ">
        출처
        </h4>
        <div class="ui bottom attached segment" id="show_tag_div">

          <?php foreach($cats as $cat){ 
            $label_theme=$category_color[$tcolor%count($category_color)];
            $tcolor++;
            ?>
            <a href="<?php echo "problemset.php?search2=".htmlentities(urlencode($cat),ENT_QUOTES,'utf-8') ?>" class="ui medium <?php echo $label_theme; ?> label">
              <?php echo htmlentities($cat,ENT_QUOTES,'utf-8'); ?>
            </a>
          <?php } ?>

        </div>
      </div>
    </div>
  <?php } ?>
  
    
</div>
<!--
  <script type="text/javascript">
  var editor = ace.edit("editor");
  var lastSubmitted = '';

  editor.setTheme("ace/theme/tomorrow");
  editor.getSession().setMode("ace/mode/" + $('#languages-menu .item.active').data('mode'));
  editor.getSession().setUseSoftTabs(false);

  editor.container.style.lineHeight = 1.6;
  editor.container.style.fontSize = '14px';
  editor.container.style.fontFamily = "'Roboto Mono', 'Bitstream Vera Sans Mono', 'Menlo', 'Consolas', 'Lucida Console', monospace";
  editor.setShowPrintMargin(false);
  editor.renderer.updateFontSize();

  function submit_code() {
    if (!$('#submit_code input[name=answer]').val().trim() && !editor.getValue().trim()) return false;
    $('#submit_code input[name=language]').val($('#languages-menu .item.active').data('value'));
    lastSubmitted = editor.getValue();
    $('#submit_code input[name=code]').val(editor.getValue());
    return true;
  }

  $('#languages-menu')[0].scrollTop = $('#languages-menu .active')[0].offsetTop - $('#languages-menu')[0].firstElementChild.offsetTop;

  $(function () {
    $('#languages-menu .item').click(function() {
      $(this)
        .addClass('active')
        .closest('.ui.menu')
        .find('.item')
          .not($(this))
          .removeClass('active')
      ;
      editor.getSession().setMode("ace/mode/" + $(this).data('mode'));
    });
  });
  </script>
-->
  <script src="https://cdn.staticfile.org/css-element-queries/0.4.0/ResizeSensor.min.js"></script>

  
<?php include("template/$OJ_TEMPLATE/footer.php");?>

  <script>
  function phpfm(pid){
    //alert(pid);
    $.post("admin/phpfm.php",{'frame':3,'pid':pid,'pass':''},function(data,status){
      if(status=="success"){
        document.location.href="admin/phpfm.php?frame=3&pid="+pid;
      }
    });
  }

  $(document).ready(function(){
    $("#creator").load("problem-ajax.php?pid=<?php echo $id?>");
  });
  </script>   


  <script>
    var clipboardin=new Clipboard(copyin);
    clipboardin.on('success', function(e){
      $("#copyin").text("복사성공!"); 
          setTimeout(function () {$("#copyin").text("복사"); }, 1500);    
      console.log(e);
    });
    clipboardin.on('error', function(e){
      $("#copyin").text("복사에러!"); 
          setTimeout(function () {$("#copyin").text("복사"); }, 1500);
      console.log(e);
    });

    var clipboardout=new Clipboard(copyout);
    clipboardout.on('success', function(e){
      $("#copyout").text("복사성공!"); 
          setTimeout(function () {$("#copyout").text("복사"); }, 1500);    
      console.log(e);
    });
    clipboardout.on('error', function(e){
      $("#copyout").text("복사에러"); 
          setTimeout(function () {$("#copyout").text("복사"); }, 1500);
      console.log(e);
    });

  </script>
