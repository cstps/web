<?php $show_title="$MSG_CONTEST - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<div class="padding">
  <div class="ui grid" style="margin-bottom: 10px; ">
    <div class="row" style="white-space: nowrap; ">
      <div class="seven wide column">
        <?php
          $scope = isset($_GET['scope']) ? $_GET['scope'] : 'my';
          if ($scope !== 'my' && $scope !== 'all') $scope = 'my';
          $keyword_qs = isset($_GET['keyword']) ? '&keyword='.urlencode($_GET['keyword']) : '';
        ?>
        <!-- 탭 -->
        <div class="ui top attached tabular menu" style="margin-bottom:10px;">
          <a class="item <?php echo ($scope==='my'?'active':''); ?>" href="contest.php?my<?php echo $keyword_qs; ?>">내가 참여한 대회</a>
          <a class="item <?php echo ($scope==='all'?'active':''); ?>" href="contest.php?scope=all<?php echo $keyword_qs; ?>">전체대회</a>
        </div>

        <!-- 검색: GET으로 유지 -->
        <form method="get" action="contest.php" class="ui bottom attached segment" style="border:0; box-shadow:none;">
          <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
          <div class="ui search" style="width: 280px; height: 28px; margin-top: -5.3px; ">
            <span class="ui left label">현재시간：<span id=nowdate><?php echo date("Y-m-d H:i:s")?></span></span>
            <div class="ui left icon input" style="width: 100%; ">
              <input class="prompt" style="width: 100%; " type="text" value="<?php echo isset($_GET['keyword'])?htmlspecialchars($_GET['keyword']):''; ?>" placeholder=" 대회이름 …" name="keyword">
              <i class="search icon"></i>
            </div>
            <div class="results" style="width: 100%; "></div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div style="margin-bottom: 30px; ">
    <?php
      if(!isset($page)) $page=1;
      $page=intval($page);
      $section=8;
      $start=$page>$section?$page-$section:1;
      $end=$page+$section>$view_total_page?$view_total_page:$page+$section;

      $qs_scope = "scope=".$scope;
      $qs_kw    = isset($_GET['keyword']) ? "&keyword=".urlencode($_GET['keyword']) : "";
    ?>
    <div style="text-align: center; ">
      <div class="ui pagination menu" style="box-shadow: none; ">
        <a class="<?php if($page==1) echo "disabled "; ?>icon item"
           href="<?php if($page>1) echo "contest.php?".$qs_scope.$qs_kw."&page=".($page-1); ?>" id="page_prev">
          <i class="left chevron icon"></i>
        </a>
        <?php
          for ($i=$start;$i<=$end;$i++){
            $active = ($page==$i)?"active ":"";
            echo "<a class=\"{$active}item\" href=\"contest.php?{$qs_scope}{$qs_kw}&page={$i}\">{$i}</a>";
          }
        ?>
        <a class="<?php if($page==$view_total_page) echo "disabled "; ?> icon item"
           href="<?php if($page<$view_total_page) echo "contest.php?".$qs_scope.$qs_kw."&page=".($page+1); ?>" id="page_next">
          <i class="right chevron icon"></i>
        </a>
      </div>
    </div>
  </div>

  <table class="ui very basic center aligned table">
    <thead>
      <tr>
        <th><?php echo $MSG_CONTEST_ID?></th>
        <th><?php echo $MSG_CONTEST_NAME?></th>
        <th><?php echo $MSG_TIME?></th>
        <th><?php echo $MSG_CONTEST_OPEN?></th>
        <th><?php echo $MSG_CONTEST_CREATOR?></th>
      </tr>
    </thead>
    <tbody>
      <?php
        foreach($view_contest as $row){
          echo "<tr>";
          foreach($row as $table_cell){
            echo "<td>";
            echo "\t".$table_cell;
            echo "</td>";
          }
          echo "</tr>";
        }
      ?>
    </tbody>
  </table>
</div>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
<script>
var diff = new Date("<?php echo date("Y/m/d H:i:s")?>").getTime() - new Date().getTime();
function clock() {
    var x = new Date(new Date().getTime() + diff);
    var y = x.getFullYear();
    var mon = x.getMonth() + 1;
    var d = x.getDate();
    var h = x.getHours();
    var m = x.getMinutes();
    var s = x.getSeconds();
    var n = y + "-" + mon + "-" + d + " " + (h >= 10 ? h : "0" + h) + ":" + (m >= 10 ? m : "0" + m) + ":" + (s >= 10 ? s : "0" + s);
    document.getElementById('nowdate').innerHTML = n;
    setTimeout("clock()", 1000);
}
clock();
</script>
