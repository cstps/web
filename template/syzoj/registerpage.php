<?php $show_title="회원가입 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<div class="padding">
  <h1>회원가입</h1>
  <div class="ui error message" id="error" data-am-alert hidden>
    <p id="error_info"></p>
  </div>
          <form action="register.php" method="post" role="form" class="ui form">
                <div class="field">
                    <label for="username">사용자ID*</label>
                    <input name="user_id" class="form-control" placeholder="ID는 영어만 또는 영어+숫자만 최대크기 20이하." type="text">
                </div>
                <div class="field">
                    <label for="username">별명</label>
                    <input name="nick" placeholder="없으면 생략가능" type="text">
                </div>
                <div class="two fields">
                    <div class="field">
                    <label class="ui header">비밀번호*</label>
                      <input name="password" placeholder="6글자 이상" type="password">
                    </div>
                    <div class="field">
                      <label class="ui header">비밀번호확인*</label>
                      <input name="rptpassword" placeholder="동일한 비밀번호 한번 더" type="password">
                    </div>
                </div>
                <div class="field">
                    <label for="username">소속/학교</label>
                    <input name="school" placeholder="없으면 생략가능" type="text" value="">
                </div>
                <div class="field">
                    <label for="email">이메일</label>
                    <input name="email" placeholder="없으면 생략가능" type="text">
                </div>                
                  <div class="field">
                    <label for="vcode">확인코드*</label>
                    <input name="vcode" class="form-control" placeholder="" type="text">
                    <img alt="click to change" src="vcode.php" onclick="this.src='vcode.php?'+Math.random()" height="30px">
                  </div>
                
                <button name="submit" type="submit" class="ui button">가입</button>
                <button name="submit" type="reset" class="ui button">초기화</button>
            </form>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
