<?php $show_title = "순위 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<?php
$is_school_rank = isset($_GET['school']) && $_GET['school'] != '';
$current_school = $is_school_rank ? $_GET['school'] : '';
?>

<div class="padding"> 
  <!-- 상단 탭 + prefix 검색 + Day 범위 필터 플렉스 박스 -->
  <div class="ui pointing secondary menu" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">

    <div style="display: flex; align-items: center; gap: 1em;">
      <!-- 전체 랭킹 탭 -->
      <a class="<?= !$is_school_rank ? 'active ' : '' ?>item" href="ranklist.php">전체 랭킹</a>

      <!-- 학교 랭킹 드롭다운 탭 -->
      <div class="ui dropdown item <?= $is_school_rank ? 'active' : '' ?>">
        <span>학교 랭킹</span> <i class="dropdown icon"></i>
        <div class="menu">
          <?php foreach ($schools as $row): 
            $school = htmlentities($row['school'], ENT_QUOTES, 'UTF-8'); ?>
            <a class="item" href="ranklist.php?school=<?= urlencode($school) ?>"><?= $school ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- prefix 검색창 -->
      <form action="ranklist.php" class="ui mini form" method="get" role="form" style="margin: 0;">
        <div class="ui action left icon input" style="width: 180px;">
          <i class="search icon"></i>
          <input name="prefix" placeholder="<?= $MSG_USER ?>" type="text"
                value="<?= htmlentities($_GET['prefix'] ?? "", ENT_QUOTES, "utf-8") ?>">
          <button class="ui mini button" type="submit"><?= $MSG_SEARCH ?></button>
        </div>
      </form>
    </div>

    <!-- Day/Week/Month/Year 버튼 그룹 -->
    <div class="ui buttons mini">
      <a href="ranklist.php?scope=d" class="ui button <?= $scope == 'd' ? 'blue' : '' ?>">Day</a>
      <a href="ranklist.php?scope=w" class="ui button <?= $scope == 'w' ? 'blue' : '' ?>">Week</a>
      <a href="ranklist.php?scope=m" class="ui button <?= $scope == 'm' ? 'blue' : '' ?>">Month</a>
      <a href="ranklist.php?scope=y" class="ui button <?= $scope == 'y' ? 'blue' : '' ?>">Year</a>
    </div>
  </div>



  <!-- 학교 선택 드롭다운 -->
  <?php if (isset($schools)): ?>
    <form method="get" action="ranklist.php" style="margin-bottom: 20px;">
      <label for="school_select"><strong>학교 선택:</strong></label>
      <span style="margin-left:10px; font-size:0.9em; color:#666;">
        ※ 학교 정보는 <a href="modifypage.php" target="_blank">개인정보 수정</a>에서 설정할 수 있습니다.
      </span>
      <br>
      <select id="school_select" name="school" class="ui dropdown" style="min-width:300px; margin-top: 5px;">
        <option value="">-- 학교 선택 --</option>
        <?php foreach ($schools as $row): 
          $school = htmlentities($row['school'], ENT_QUOTES, 'UTF-8'); ?>
          <option value="<?= $school ?>" <?= ($current_school == $school ? 'selected' : '') ?>><?= $school ?></option>
        <?php endforeach; ?>
      </select>
    </form>

  <?php endif; ?>

  <!-- 순위 테이블 -->
  <table class="ui very basic aligned table" style="table-layout: fixed;">
    <thead>
      <tr>
        <th style="width: 60px;"><?php echo $MSG_Number?></th>
        <th style="width: 180px;"><?php echo $MSG_USER?></th>
        <!-- 닉네임 열 제거됨 -->
        <th style="width: 100px;"><?php echo $MSG_SOVLED?></th>
        <th style="width: 100px;"><?php echo $MSG_SUBMIT?></th>
        <th style="width: 100px;"><?php echo $MSG_RATIO?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($view_rank as $row): ?>
        <tr>
          <?php foreach($row as $table_cell): ?>
            <td><?= $table_cell ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- 페이지네이션 -->
  <br>
  <div style="margin-bottom: 30px;">
    <div style="text-align: center;">
      <div class="ui pagination menu" style="box-shadow: none;">
        <?php
        for($i = 0; $i < $view_total; $i += $page_size) {
          $url = "./ranklist.php?start=" . strval($i);
          if ($is_school_rank) $url .= "&school=" . urlencode($current_school);
          if ($scope) $url .= "&scope=$scope";
          echo "<a class=\"icon item\" href='$url'>" . ($i+1) . "-" . ($i+$page_size) . "</a>";
        }
        ?>
      </div>
    </div>
  </div>
</div>

<script>
    $('.ui.dropdown').dropdown();
  document.getElementById('school_select')?.addEventListener('change', function() {
    if (this.value !== "") {
      window.location.href = "ranklist.php?school=" + encodeURIComponent(this.value);
    }
  });
</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
