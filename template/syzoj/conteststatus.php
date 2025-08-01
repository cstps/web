<?php $show_title="$OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<script src="template/<?php echo $OJ_TEMPLATE?>/js/textFit.min.js"></script>
<div class="padding">

  <!-- <form action="" class="ui mini form" method="get" role="form" id="form"> -->
  <form id=simform class="ui mini form" action="status.php" method="get">

    <input type="hidden" name="cid" value="<?php echo $cid; ?>" />
    <div class="inline fields" style="margin-bottom: 25px; white-space: nowrap; ">
      <label style="font-size: 1.2em; margin-right: 1px; ">제목：</label>
      <div class="field"><input name="problem_id" style="width: 100px; " type="text" value="<?php echo  htmlspecialchars($problem_id, ENT_QUOTES) ?>"></div>
        <label style="font-size: 1.2em; margin-right: 1px; ">사용자ID：</label>
        <div class="field"><input name="user_id" style="width: 100px; " type="text" value="<?php echo  htmlspecialchars($user_id, ENT_QUOTES) ?>"></div>

        <label style="font-size: 1.2em; margin-right: 1px; ">언어：</label>
        <select class="form-control" size="1" name="language" style="width: 110px;font-size: 1em ">
          <option value="-1">All</option>
          <?php
          if(isset($_GET['language'])){
            $selectedLang=intval($_GET['language']);
          }else{
            $selectedLang=-1;
          }
          $lang_count=count($language_ext);
          $langmask=$OJ_LANGMASK;
          $lang=(~((int)$langmask))&((1<<($lang_count))-1);
          for($i=0;$i<$lang_count;$i++){
            if($lang&(1<<$i))
            echo"<option value=$i ".( $selectedLang==$i?"selected":"").">
            ".$language_name[$i]."
            </option>";
          }
          ?>
        </select>
        <label style="font-size: 1.2em; margin-right: 1px;margin-left: 10px; ">상태：</label>
        <select class="form-control" size="1" name="jresult" style="width: 110px;">
          <?php if (isset($_GET['jresult'])) $jresult_get=intval($_GET['jresult']);
          else $jresult_get=-1;
          if ($jresult_get>=12||$jresult_get<0) $jresult_get=-1;
          if ($jresult_get==-1) echo "<option value='-1' selected>All</option>";
          else echo "<option value='-1'>All</option>";
          for ($j=0;$j<12;$j++){
          $i=($j+4)%12;
          if ($i==$jresult_get) echo "<option value='".strval($jresult_get)."' selected>".$jresult[$i]."</option>";
          else echo "<option value='".strval($i)."'>".$jresult[$i]."</option>";
          }
          echo "</select>";
          ?>
          <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){
            if(isset($_GET['showsim']))
            $showsim=intval($_GET['showsim']);
            else
            $showsim=0;
            echo "<label style=\"font-size: 1.2em; margin-right: 1px;margin-left: 10px; \">유사도：</label>";
          echo "
          <select id=\"appendedInputButton\" class=\"form-control\" name=showsim onchange=\"document.getElementById('simform').submit();\" style=\"width: 110px;\">
          <option value=0 ".($showsim==0?'selected':'').">All</option>
          <option value=50 ".($showsim==50?'selected':'').">50</option>
          <option value=60 ".($showsim==60?'selected':'').">60</option>
          <option value=70 ".($showsim==70?'selected':'').">70</option>
          <option value=80 ".($showsim==80?'selected':'').">80</option>
          <option value=90 ".($showsim==90?'selected':'').">90</option>
          <option value=100 ".($showsim==100?'selected':'').">100</option>
          </select>";
          }
          ?>
      <button class="ui labeled icon mini button" type="submit" style="margin-left: 20px;">
        <i class="search icon"></i>
        검색
      </button>
      <?php   
        if(isset($_SESSION[$OJ_NAME.'_'.'source_browser'])){
      ?>
        <button onclick="selectPerson()" class="ui labeled icon mini button" style="margin-left: 20px;">
        <i class="result icon"></i>
        발표자
        </button>
     <?php } ?>
    </div>
  </form>


  <form id=simform class=form-inline action="status.php" method="get">






  <table id="result-tab" class="ui very basic center aligned table" style="white-space: nowrap; " id="table">
    <thead>
      <tr>
        <th>번호</th>
        <th>사용자</th>
        <th><?php echo $MSG_NICK?></th>
        <th>제목</th>
        <th>결과</th>
        <th>메모리</th>
        <th>시간</th>
        <th>코드</th>
        <th>코드길이</th>
        <th>제출 시간</th>
        <!-- <th>채점</th> -->
      </tr>
    </thead>
    <tbody>
      <!-- <tr v-for="item in items" :config="displayConfig" :show-rejudge="false" :data="item" is='submission-item'>
          </tr> -->
    <?php
    foreach($view_status as $row){
    $i=0;
    echo "<tr>";
    foreach($row as $table_cell){
      if($i>3&&$i!=8)
        echo "<td class='hidden-xs'><b>";
      else
        echo "<td><b>";
      echo $table_cell;
      echo "</b></td>";
      $i++;
    }
    echo "</tr>\n";
    }
    ?>

    </tbody>
  </table>
  <div style="margin-bottom: 30px; ">

  <div style="text-align: center; ">
        <div class="ui pagination menu" style="box-shadow: none; ">
          <a class="icon item" href="<?php echo "status.php?".$str2;?>" id="page_prev">
    첫페이지
          </a>
          <?php
      if (isset($_GET['prevtop']))
      echo "<a class=\"item\" href=\"status.php?".$str2."&top=".intval($_GET['prevtop'])."\">이전 페이지</a>";
      else
      echo "<a class=\"item\" href=\"status.php?".$str2."&top=".($top+20)."\">이전 페이지</a>";

      ?>

          <a class="icon item" href="<?php echo "status.php?".$str2."&top=".$bottom."&prevtop=$top"; ?>" id="page_next">
            다음 페이지
          </a>
        </div>
  </div>
</div>

<script>
        var i = 0;
        var judge_result = [<?php
        foreach ($judge_result as $result) {
                echo "'$result',";
        } ?>
        ''];

        var judge_color = [<?php
        foreach ($judge_color as $result) {
                echo "'$result',";
        } ?>
        ''];
</script>
        <script src="template/bs3/auto_refresh.js?v=0.43" ></script>
        <script>
        function selectPerson() { // 발표자 선택해서 알림으로 보여주기
          var rows = document.getElementById("result-tab").getElementsByTagName("tr");
          var cnt = Math.floor(Math.random() * (rows.length-1)) + 1;
          var cells = rows[cnt].getElementsByTagName("td");
          //var cell_1 = cells[1].firstChild.firstChild.firstChild.data;
          var cell_1 = cells[1].firstChild.firstChild.firstChild;
          var wnd = window.open("", "발표자", "width=300,height=50,left=400,top=200");
wnd.document.write("<html><head><title>발표자!</title></head><body> <h1>발표자</h1><h1 style='font-size:50pt;color:red;padding:5px;border:1px solid black;'>"+cell_1.nodeValue+"</h1></body></html>");
          

        }
      </script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
