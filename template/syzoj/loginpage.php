<?php $show_title="로그인 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>

<?php
$err = $_GET['err'] ?? '';
$errMap = [
  'vcode'    => '확인 코드가 올바르지 않습니다.',
  'auth'     => '아이디 또는 비밀번호가 올바르지 않습니다.',
  'cookie1'  => '쿠키 검증 오류(-1). 다시 로그인해 주세요.',
  'cookie2'  => '쿠키 검증 오류(-2). 다시 로그인해 주세요.'
];
if (isset($errMap[$err])) {
    echo '<div class="ui error message" style="max-width: 450px; margin: 1rem auto;">'
       . htmlspecialchars($errMap[$err], ENT_QUOTES, 'UTF-8')
       . '</div>';
}
?>

<div class="ui middle aligned center aligned grid" style="height: 500px;">
  <div class="row">
    <div class="column" style="max-width: 450px">
      <h2 class="ui image header">
        <div class="content" style="margin-bottom: 10px;">로그인</div>
      </h2>

      <form class="ui large form" id="login" action="login.php" method="post" role="form" onsubmit="return jsMd5();">
        <div class="ui existing segment">
          <div class="field">
            <div class="ui left icon input">
              <i class="user icon"></i>
              <input name="user_id" placeholder="사용자ID" type="text" id="username" required>
            </div>
          </div>
          <div class="field">
            <div class="ui left icon input">
              <i class="lock icon"></i>
              <input name="password" placeholder="비밀번호" type="password" id="password" required>
            </div>
          </div>

          <?php if ($OJ_VCODE) { ?>
          <div class="field">
            <div class="ui left icon input">
              <i class="lock icon"></i>
              <input name="vcode" placeholder="확인코드" type="text" required>
              <img id="vcode-img" onclick="this.src='vcode.php?'+Math.random()" height="30px" alt="captcha">
            </div>
          </div>
          <?php } ?>

          <button name="submit" type="submit" class="ui fluid large submit button">로그인</button>
        </div>
        <div class="ui error message"></div>
      </form>

      <div class="ui message">
        <a href="registerpage.php">회원가입</a>
        <!-- <a href="lostpassword.php">비밀번호 찾기</a> -->
      </div>
    </div>
  </div>
</div>

<!-- 외부 CDN 대신 로컬 경로 권장 -->
<script src="/include/md5-min.js"></script>
<script>
  function jsMd5(){
    var pw = document.querySelector("input[name=password]");
    if(!pw || !pw.value) return false;
    if (typeof hex_md5 === 'function') pw.value = hex_md5(pw.value);
    return true;
  }
  <?php if ($OJ_VCODE) { ?>
  document.addEventListener('DOMContentLoaded', function(){
    var img = document.getElementById('vcode-img');
    if (img) img.src = "vcode.php?" + Math.random();
  });
  <?php } ?>
</script>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
