<?php
require_once("admin-header.php");

if (!(isset($_SESSION[$OJ_NAME . '_' . 'administrator']) || isset($_SESSION[$OJ_NAME . '_' . 'password_setter']))) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

$school_path = "../school_list.json";
$school_list = file_exists($school_path) ? json_decode(file_get_contents($school_path), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do']) && $_POST['do'] === 'add') {
  require_once("../include/check_post_key.php");
  $new_school = trim($_POST['new_school']);
  if ($new_school !== '') {
    if (!in_array($new_school, $school_list)) {
      $school_list[] = $new_school;
      sort($school_list, SORT_STRING);
      if (file_put_contents($school_path, json_encode(array_values($school_list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false) {
        $message = "<div class='alert alert-success'>학교 추가 완료!</div>";
      } else {
        $message = "<div class='alert alert-danger'>파일 저장 실패!</div>";
      }
    } else {
      $message = "<div class='alert alert-info'>이미 존재하는 학교입니다.</div>";
    }
  }
}
?>


<title><?php echo $MSG_SCHOOL_MANAGE; ?></title>
<hr>
<center><h3><?php echo $MSG_SCHOOL_MANAGE; ?></h3></center>

<div class='container'>
  <?php if (isset($message)) echo $message; ?>

  <!-- 학교 검색 -->
  <div class="form-group">
    <label>학교 검색</label>
    <input type="text" id="school-search" class="form-control" placeholder="학교명을 입력하세요">
    <div id="search-result" style="margin-top: 0.5em; font-weight: bold;"></div>
  </div>

  <!-- 학교 추가 -->
  <form action="school_admin.php" method="post" class="form-horizontal">
    <div class="form-group">
      <label for="new_school">학교 추가</label>
      <input type="text" name="new_school" id="new_school" class="form-control" placeholder="예: 서울고등학교" required>
    </div>
    <div class="form-group" style="margin-top: 1em;">
      <?php require_once("../include/set_post_key.php"); ?>
      <button name="do" value="add" class="btn btn-success">추가</button>
    </div>
  </form>
</div>

<script>
  const schoolList = <?php echo json_encode($school_list, JSON_UNESCAPED_UNICODE); ?>;

  $('#school-search').on('input', function () {
    const keyword = $(this).val().trim();
    const resultEl = $('#search-result');
    if (keyword.length === 0) {
      resultEl.html('');
      return;
    }
    const matches = schoolList.filter(name => name.includes(keyword));
    if (matches.length > 0) {
      const htmlList = matches.map(m => '<li>' + m + '</li>').join('');
      resultEl.html('✔️ <b>' + matches.length + '</b>개 일치<br><ul>' + htmlList + '</ul>');
    } else {
      resultEl.html('❌ 목록에 없음');
    }
  });
</script>

