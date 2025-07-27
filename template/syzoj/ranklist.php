<?php $show_title = "순위 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<?php
$is_school_rank = isset($_GET['school']) && $_GET['school'] != '';
$current_school = $is_school_rank ? $_GET['school'] : '';
?>

<div class="padding"> 
  <!-- 탭 메뉴 -->
  <div class="ui top attached tabular menu">
    <a href="ranklist.php" class="item <?= !$is_school_rank ? 'active' : '' ?>">전체 랭킹</a>
    <a href="#school-tab" class="item <?= $is_school_rank ? 'active' : '' ?>" onclick="document.getElementById('school_select').scrollIntoView();">학교 랭킹</a>
  </div>

  <!-- Scope 링크 -->
  <div style="margin: 10px 0;">
    <a href="ranklist.php?scope=d">Day</a>
    <a href="ranklist.php?scope=w">Week</a>
    <a href="ranklist.php?scope=m">Month</a>
    <a href="ranklist.php?scope=y">Year</a>
  </div>

  <!-- prefix 검색 -->
  <form action="ranklist.php" class="ui mini form" method="get" role="form" style="margin-bottom: 25px; text-align: right;">
    <div class="ui action left icon input inline" style="width: 180px; margin-right: 77px;">
      <i class="search icon"></i>
      <input name="prefix" placeholder="<?php echo $MSG_USER?>" type="text" value="<?php echo htmlentities(isset($_GET['prefix'])?$_GET['prefix']:"",ENT_QUOTES,"utf-8") ?>">
      <button class="ui mini button" type="submit"><?php echo $MSG_SEARCH?></button>
    </div>
  </form>

  <!-- 학교 선택 드롭다운 -->
  <?php if (isset($schools)): ?>
    <form method="get" action="ranklist.php" style="margin-bottom: 20px;">
      <label for="school_select"><strong>학교 선택:</strong></label>
      <select id="school_select" name="school" class="ui dropdown" style="min-width:300px;">
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
document.getElementById('school_select')?.addEventListener('change', function() {
  if (this.value !== "") {
    window.location.href = "ranklist.php?school=" + encodeURIComponent(this.value);
  }
});
</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
