<?php

if (isset($OJ_LANG)) {
  require_once("../lang/$OJ_LANG.php");
}

?>


<nav class="admin-side-nav">

  <div class="admin-nav-group">

    <div class="admin-nav-title">
      관리자
    </div>

    <div class="admin-nav-items">

      <a
        href="help.php"
        target="main"
        class="admin-nav-link active">
        관리자 홈
      </a>

      <a
        href="../status.php"
        target="_top"
        class="admin-nav-link"
        title="<?php echo $MSG_HELP_SEEOJ; ?>">
        사이트 보기
      </a>

    </div>

  </div>
  <!-- 공지사항 관리 -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_vip'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        공지 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link"
          href="setmsg.php"
          target="main"
          title="<?php echo $MSG_HELP_SETMESSAGE; ?>">
          공지 메시지 설정
        </a>

        <a
          class="admin-nav-link"
          href="news_list.php"
          target="main"
          title="<?php echo $MSG_HELP_NEWS_LIST; ?>">
          공지사항 목록
        </a>

        <a
          class="admin-nav-link"
          href="news_add_page.php"
          target="main"
          title="<?php echo $MSG_HELP_ADD_NEWS; ?>">
          공지사항 추가
        </a>

      </div>

    </div>

  <?php
  }
  ?>

  <!-- IT NEWS -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_vip'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        IT NEWS
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link"
          href="coding_news_list.php"
          target="main"
          title="<?php echo $MSG_HELP_NEWS_LIST; ?>">
          IT NEWS 목록
        </a>

        <a
          class="admin-nav-link"
          href="coding_news_add_page.php"
          target="main"
          title="<?php echo $MSG_HELP_ADD_NEWS; ?>">
          IT NEWS 추가
        </a>

      </div>

    </div>

  <?php
  }
  ?>

  <!-- 사용자 관리 -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_password_setter'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        사용자 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link"
          href="user_list.php"
          target="main"
          title="<?php echo $MSG_HELP_USER_LIST; ?>">
          사용자 목록
        </a>

        <a
          class="admin-nav-link"
          href="user_add.php"
          target="main"
          title="<?php echo $MSG_HELP_USER_ADD; ?>">
          사용자 추가
        </a>

        <a
          class="admin-nav-link"
          href="changepass.php"
          target="main"
          title="<?php echo $MSG_HELP_SETPASSWORD; ?>">
          비밀번호 변경
        </a>

        <a
          class="admin-nav-link"
          href="school_admin.php"
          target="main"
          title="<?php echo $MSG_SCHOOL_MANAGE; ?>">
          학교 관리
        </a>


        <?php
        if (
          isset(
            $_SESSION[$OJ_NAME . '_administrator']
          )
        ) {
        ?>

          <a
            class="admin-nav-link"
            href="privilege_list.php"
            target="main"
            title="<?php echo $MSG_HELP_PRIVILEGE_LIST; ?>">
            권한 목록
          </a>

          <a
            class="admin-nav-link"
            href="privilege_add.php"
            target="main"
            title="<?php echo $MSG_HELP_ADD_PRIVILEGE; ?>">
            권한 추가
          </a>

        <?php
        }
        ?>

      </div>

    </div>

  <?php
  }
  ?>

  <!-- 문제 관리 -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_problem_editor']) ||
    isset($_SESSION[$OJ_NAME . '_contest_creator'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        문제 관리
      </div>

      <div class="admin-nav-items">

        <a
          href="problem_list.php"
          target="main"
          class="admin-nav-link">
          문제 목록
        </a>


        <?php
        if (
          isset($_SESSION[$OJ_NAME . '_administrator']) ||
          isset($_SESSION[$OJ_NAME . '_problem_editor'])
        ) {
        ?>

          <a
            href="problem_add_page.php"
            target="main"
            class="admin-nav-link">
            문제 추가
          </a>

          <a
            href="problem_import.php"
            target="main"
            class="admin-nav-link">
            문제 가져오기
          </a>

          <a
            href="problem_export.php"
            target="main"
            class="admin-nav-link">
            문제 내보내기
          </a>

        <?php
        }
        ?>
        <?php
        if (
          isset(
            $_SESSION[$OJ_NAME . '_administrator']
          )
        ) {
        ?>

          <a
            href="problem_copy.php"
            target="main"
            class="admin-nav-link">
            문제 복사
          </a>

          <a
            href="problem_changeid.php"
            target="main"
            class="admin-nav-link">
            문제 번호 변경
          </a>

        <?php
        }
        ?>

      </div>

    </div>

  <?php
  }
  ?>

  <!-- 대회 관리 -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_contest_creator'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        대회 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link"
          href="contest_list.php"
          target="main"
          title="<?php echo $MSG_HELP_CONTEST_LIST; ?>">
          대회 목록
        </a>
                
        <a
          class="admin-nav-link"
          href="contest_add.php"
          target="main"
          title="<?php echo $MSG_HELP_ADD_CONTEST; ?>">
          대회 생성
        </a>

        <?php
        if (
          isset($_SESSION[$OJ_NAME . '_administrator'])
        ){
          ?> 
        <a
          class="admin-nav-link"
          href="user_set_ip.php"
          target="main"
          title="<?php echo $MSG_SET_LOGIN_IP; ?>">
          로그인 IP 설정
        </a>
        <?php 
        }
        ?>
      </div>

    </div>

  <?php
  }
  ?>

  <!-- 수업 관리 -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_contest_creator'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        수업 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link admin-nav-link-external"
          href="../course_list.php"
          target="_top"
          title="수업 관리 화면으로 이동">
          <span>수업 목록</span>
          <span class="admin-nav-external-mark">↗</span>
        </a>

      </div>

    </div>

  <?php
  }
  ?>

  <!-- 시스템 관리 -->
  <?php
  if (
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_vip'])
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        시스템
      </div>

      <div class="admin-nav-items">

        <?php
        if (
          isset(
            $_SESSION[$OJ_NAME . '_administrator']
          )
        ) {
        ?>

          <a
            class="admin-nav-link"
            href="rejudge.php"
            target="main">
            재채점
          </a>

          <a
            class="admin-nav-link"
            href="source_give.php"
            target="main">
            소스 권한 관리
          </a>

          <a
            class="admin-nav-link"
            href="../online.php"
            target="main">
            접속 사용자
          </a>

          <a
            class="admin-nav-link"
            href="update_db.php"
            target="main">
            DB 업데이트
          </a>

          <a
            class="admin-nav-link"
            href="backup.php"
            target="main">
            백업
          </a>

        <?php
        }
        ?>


        <a
          class="admin-nav-link"
          href="setdbinfo.php"
          target="main">
          DB 설정
        </a>

      </div>

    </div>

  <?php
  }
  ?>

  <?php
  if (
    isset(
      $_SESSION[$OJ_NAME . '_administrator']
    )
  ) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        참고 자료
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link"
          href="https://github.com/zhblue/hustoj/"
          target="_blank"
          rel="noopener noreferrer">
          HUSTOJ
        </a>

        <a
          class="admin-nav-link"
          href="https://github.com/zhblue/hustoj/blob/master/wiki/FAQ.md"
          target="_blank"
          rel="noopener noreferrer">
          관리자 FAQ
        </a>

        <a
          class="admin-nav-link"
          href="https://github.com/zhblue/freeproblemset/"
          target="_blank"
          rel="noopener noreferrer">
          FreeProblemSet
        </a>

      </div>

    </div>

  <?php
  }
  ?>
  <!-- 이동
<?php if (isset($_SESSION[$OJ_NAME . '_' . 'administrator']) && !$OJ_SAE) { ?>
  <a href="problem_copy.php" target="main" title="Create your own data"><font color="eeeeee">CopyProblem</font></a> <br>
  <a href="problem_changeid.php" target="main" title="Danger,Use it on your own risk"><font color="eeeeee">ReOrderProblem</font></a>
<?php } ?>
-->
</nav>

<script>
  document.addEventListener('DOMContentLoaded', function() {

    var links =
      document.querySelectorAll(
        '.admin-nav-link[target="main"]'
      );

    links.forEach(function(link) {

      link.addEventListener('click', function() {

        links.forEach(function(item) {
          item.classList.remove('active');
        });

        this.classList.add('active');
      });

    });

  });
</script>