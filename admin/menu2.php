<?php

if (!isset($admin_active_menu)) {
  $admin_active_menu = "";
}

$admin_internal_target = "_self";


if (!function_exists("admin_menu_link_class")) {

  function admin_menu_link_class(
    $menu_key,
    $extra_class = ""
  ) {

    global $admin_active_menu;

    $classes = array(
      "admin-nav-link"
    );

    if ($extra_class !== "") {
      $classes[] = $extra_class;
    }

    if (
      (string)$admin_active_menu ===
      (string)$menu_key
    ) {
      $classes[] = "active";
    }

    return implode(
      " ",
      $classes
    );
  }
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
        target="<?php echo $admin_internal_target; ?>"
        class="<?php echo admin_menu_link_class("dashboard"); ?>">
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
  if (oj_can_manage_admin_notice()) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        공지 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="<?php
          echo admin_menu_link_class(
            "news_list"
          );
          ?>"
          href="news_list.php"
          target="<?php echo $admin_internal_target; ?>"
          title="<?php echo $MSG_HELP_NEWS_LIST; ?>">
          공지사항 목록
        </a>

        <a
          class="<?php
          echo admin_menu_link_class(
            "news_add"
          );
          ?>"
          href="news_add_page.php"
          target="<?php echo $admin_internal_target; ?>"
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
  if (oj_can_manage_admin_notice()) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        IT NEWS
      </div>

      <div class="admin-nav-items">

        <a
          class="<?php
          echo admin_menu_link_class(
            "coding_news_list"
          );
          ?>"
          href="coding_news_list.php"
          target="<?php echo $admin_internal_target; ?>"
          title="<?php echo $MSG_HELP_NEWS_LIST; ?>">
          IT NEWS 목록
        </a>

        <a
          class="<?php
          echo admin_menu_link_class(
            "coding_news_add"
          );
          ?>"
          href="coding_news_add_page.php"
          target="<?php echo $admin_internal_target; ?>"
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
  if (oj_can_view_admin_users()) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        사용자 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="admin-nav-link"
          href="user_list.php"
          target="<?php echo $admin_internal_target; ?>"
          title="<?php echo $MSG_HELP_USER_LIST; ?>">
          사용자 목록
        </a>
        <a
          class="admin-nav-link"
          href="changepass.php"
          target="<?php echo $admin_internal_target; ?>"
          title="<?php echo $MSG_HELP_SETPASSWORD; ?>">
          비밀번호 변경
        </a>
        <?php
        if (oj_can_manage_admin_users()) {
        ?>
          <a
            class="admin-nav-link"
            href="user_add.php"
            target="<?php echo $admin_internal_target; ?>"
            title="<?php echo $MSG_HELP_USER_ADD; ?>">
            사용자 추가
          </a>
          <a
            class="admin-nav-link"
            href="school_admin.php"
            target="<?php echo $admin_internal_target; ?>"
            title="<?php echo $MSG_SCHOOL_MANAGE; ?>">
            학교 관리
          </a>

          <a
            class="admin-nav-link"
            href="privilege_list.php"
            target="<?php echo $admin_internal_target; ?>"
            title="<?php echo $MSG_HELP_PRIVILEGE_LIST; ?>">
            권한 목록
          </a>

          <a
            class="admin-nav-link"
            href="privilege_add.php"
            target="<?php echo $admin_internal_target; ?>"
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
  if (oj_can_view_admin_problems()) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        문제 관리
      </div>

      <div class="admin-nav-items">

        <a
          href="problem_list.php"
          class="<?php
                  echo admin_menu_link_class(
                    'problem_list'
                  );
                  ?>">
          문제 목록
        </a>


        <?php
        if (oj_can_create_admin_problems()) {
        ?>

          <a
            href="problem_add_page.php"
            target="<?php echo $admin_internal_target; ?>"
            class="<?php
                    echo admin_menu_link_class(
                      "problem_add"
                    );
                    ?>">
            문제 추가
          </a>

          <a
            href="problem_import.php"
            target="<?php echo $admin_internal_target; ?>"
            class="<?php echo admin_menu_link_class('problem-import'); ?>">
            문제 가져오기
          </a>

          <a
            href="problem_export.php"
            target="<?php echo $admin_internal_target; ?>"
            class="<?php echo admin_menu_link_class('problem-export'); ?>">
            문제 내보내기
          </a>

        <?php
        }
        ?>

        <?php
        if (oj_is_admin()) {
        ?>

          <a
            href="problem_copy.php"
            target="<?php echo $admin_internal_target; ?>"
            class="admin-nav-link">
            문제 복사
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
  if (oj_can_manage_admin_contests()) {
  ?>

    <div class="admin-nav-group">

      <div class="admin-nav-title">
        대회 관리
      </div>

      <div class="admin-nav-items">

        <a
          class="<?php
                  echo admin_menu_link_class(
                    'contest_list'
                  );
                  ?>"
          href="contest_list.php"
          target="<?php echo $admin_internal_target; ?>"
          title="<?php echo $MSG_HELP_CONTEST_LIST; ?>">
          대회 목록
        </a>

        <a
          class="<?php
                  echo admin_menu_link_class(
                    'contest_add'
                  );
                  ?>"
          href="contest_add.php"
          target="<?php echo $admin_internal_target; ?>"
          title="<?php echo $MSG_HELP_ADD_CONTEST; ?>">
          대회 생성
        </a>

        <?php
        if (oj_can_manage_admin_system()) {
        ?>
          <a
            class="admin-nav-link"
            href="user_set_ip.php"
            target="<?php echo $admin_internal_target; ?>"
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
  if (oj_can_manage_admin_contests()) {
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
  if (oj_can_manage_admin_system()) {
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
            target="<?php echo $admin_internal_target; ?>">
            재채점
          </a>

          <a
            class="admin-nav-link"
            href="source_give.php"
            target="<?php echo $admin_internal_target; ?>">
            소스 권한 관리
          </a>

          <a
            class="admin-nav-link"
            href="../online.php"
            target="<?php echo $admin_internal_target; ?>">
            접속 사용자
          </a>

          <a class="<?php echo admin_menu_link_class('system'); ?>" href="shorturl_list.php">단축 URL 관리</a>

          <a
            class="admin-nav-link"
            href="update_db.php"
            target="<?php echo $admin_internal_target; ?>">
            DB 업데이트
          </a>


        <?php
        }
        ?>


        <a
          class="admin-nav-link"
          href="setdbinfo.php"
          target="<?php echo $admin_internal_target; ?>">
          DB 설정
        </a>

      </div>

    </div>

  <?php
  }
  ?>

  <?php
  if (oj_can_manage_admin_system()) {
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

</nav>