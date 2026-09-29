<?php

require_once(__DIR__ . "/admin-init.php");

$admin_page_title = "관리자 홈";
$admin_active_menu = "dashboard";

require(__DIR__ . "/admin-layout-start.php");

?>

<div class="admin-page">

  <header class="admin-page-header">

    <div>

      <h1 class="admin-page-title">
        관리자 홈
      </h1>

      <p class="admin-page-description">
        1024.kr의 문제, 대회, 사용자 및 시스템 기능을 관리합니다.
      </p>

    </div>

    <div class="admin-page-header-actions">

      <a
        class="admin-btn admin-btn-secondary"
        href="../status.php">
        사이트 현황 보기
      </a>

    </div>

  </header>

  <div class="admin-dashboard-grid">

    <!-- 기본 바로가기 -->
    <section class="admin-card admin-dashboard-card">

      <div class="admin-dashboard-card-header">

        <div>
          <h2 class="admin-dashboard-card-title">
            바로가기
          </h2>

          <p class="admin-dashboard-card-description">
            사이트의 주요 화면으로 이동합니다.
          </p>
        </div>

      </div>

      <div class="admin-dashboard-actions">

        <a
          class="admin-dashboard-action"
          href="../status.php">

          <span class="admin-dashboard-action-title">
            채점 현황
          </span>

          <span class="admin-dashboard-action-description">
            전체 제출과 채점 결과를 확인합니다.
          </span>

        </a>

        <a
          class="admin-dashboard-action"
          href="../">

          <span class="admin-dashboard-action-title">
            사이트 홈
          </span>

          <span class="admin-dashboard-action-description">
            1024.kr 사용자 화면으로 이동합니다.
          </span>

        </a>

      </div>

    </section>


    <!-- 공지 관리 -->
    <?php if (oj_can_manage_admin_notice()) { ?>

      <section class="admin-card admin-dashboard-card">

        <div class="admin-dashboard-card-header">

          <div>
            <h2 class="admin-dashboard-card-title">
              공지 관리
            </h2>

            <p class="admin-dashboard-card-description">
              공지사항과 IT NEWS를 관리합니다.
            </p>
          </div>

        </div>

        <div class="admin-dashboard-actions">


          <a class="admin-dashboard-action" href="news_list.php">
            <span class="admin-dashboard-action-title">
              공지사항 목록
            </span>
            <span class="admin-dashboard-action-description">
              등록된 공지사항을 확인하고 수정합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="news_add_page.php">
            <span class="admin-dashboard-action-title">
              공지사항 추가
            </span>
            <span class="admin-dashboard-action-description">
              새로운 공지사항을 작성합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="coding_news_list.php">
            <span class="admin-dashboard-action-title">
              IT NEWS
            </span>
            <span class="admin-dashboard-action-description">
              IT NEWS 게시물을 관리합니다.
            </span>
          </a>

        </div>

      </section>

    <?php } ?>


    <!-- 사용자 관리 -->
    <?php if (oj_can_view_admin_users()) { ?>

      <section class="admin-card admin-dashboard-card">

        <div class="admin-dashboard-card-header">

          <div>
            <h2 class="admin-dashboard-card-title">
              사용자 관리
            </h2>

            <p class="admin-dashboard-card-description">
              사용자 계정과 권한을 관리합니다.
            </p>
          </div>

        </div>

        <div class="admin-dashboard-actions">

          <a class="admin-dashboard-action" href="user_list.php">
            <span class="admin-dashboard-action-title">
              사용자 목록
            </span>
            <span class="admin-dashboard-action-description">
              사용자 정보와 비밀번호를 관리합니다.
            </span>
          </a>


          <?php if (oj_can_manage_admin_users()) { ?>

            <a class="admin-dashboard-action" href="user_add.php">
              <span class="admin-dashboard-action-title">
                사용자 추가
              </span>
              <span class="admin-dashboard-action-description">
                사용자 계정을 새로 등록합니다.
              </span>
            </a>

            <a class="admin-dashboard-action" href="school_admin.php">
              <span class="admin-dashboard-action-title">
                학교 관리
              </span>
              <span class="admin-dashboard-action-description">
                학교 정보를 확인하고 관리합니다.
              </span>
            </a>

            <a class="admin-dashboard-action" href="privilege_list.php">
              <span class="admin-dashboard-action-title">
                권한 목록
              </span>
              <span class="admin-dashboard-action-description">
                사용자에게 부여된 권한을 확인합니다.
              </span>
            </a>


          <?php } ?>

        </div>

      </section>

    <?php } ?>


    <!-- 문제 관리 -->
    <?php if (oj_can_view_admin_problems()) { ?>

      <section class="admin-card admin-dashboard-card">

        <div class="admin-dashboard-card-header">

          <div>
            <h2 class="admin-dashboard-card-title">
              문제 관리
            </h2>

            <p class="admin-dashboard-card-description">
              문제를 등록하고 문제은행을 관리합니다.
            </p>
          </div>

        </div>

        <div class="admin-dashboard-actions">

          <a class="admin-dashboard-action" href="problem_list.php">
            <span class="admin-dashboard-action-title">
              문제 목록
            </span>
            <span class="admin-dashboard-action-description">
              등록된 문제를 검색하고 확인합니다.
            </span>
          </a>

          <?php
          if (oj_can_create_admin_problems()) {
          ?>

            <a class="admin-dashboard-action" href="problem_add_page.php">
              <span class="admin-dashboard-action-title">
                문제 추가
              </span>
              <span class="admin-dashboard-action-description">
                새로운 문제와 테스트 데이터를 등록합니다.
              </span>
            </a>

            <a class="admin-dashboard-action" href="problem_import.php">
              <span class="admin-dashboard-action-title">
                문제 가져오기
              </span>
              <span class="admin-dashboard-action-description">
                FPS 형식의 문제를 가져옵니다.
              </span>
            </a>

            <a class="admin-dashboard-action" href="problem_export.php">
              <span class="admin-dashboard-action-title">
                문제 내보내기
              </span>
              <span class="admin-dashboard-action-description">
                선택한 문제를 파일로 내보냅니다.
              </span>
            </a>

          <?php } ?>

          <?php if (oj_is_admin()) { ?>

            <a class="admin-dashboard-action" href="problem_copy.php">
              <span class="admin-dashboard-action-title">
                문제 복사
              </span>$postkey_session_name =
              <span class="admin-dashboard-action-description">
                외부 문제를 현재 문제은행으로 복사합니다.
              </span>
            </a>


          <?php } ?>

        </div>

      </section>

    <?php } ?>


    <!-- 대회 및 수업 -->
    <?php if (oj_can_manage_admin_contests()) { ?>

      <section class="admin-card admin-dashboard-card">

        <div class="admin-dashboard-card-header">

          <div>
            <h2 class="admin-dashboard-card-title">
              대회 및 수업
            </h2>

            <p class="admin-dashboard-card-description">
              대회와 Course 수업을 관리합니다.
            </p>
          </div>

        </div>

        <div class="admin-dashboard-actions">

          <a class="admin-dashboard-action" href="contest_list.php">
            <span class="admin-dashboard-action-title">
              대회 목록
            </span>
            <span class="admin-dashboard-action-description">
              등록된 대회를 확인하고 관리합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="contest_add.php">
            <span class="admin-dashboard-action-title">
              대회 생성
            </span>
            <span class="admin-dashboard-action-description">
              새로운 대회 또는 평가를 생성합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="../course_list.php">
            <span class="admin-dashboard-action-title">
              수업 목록
            </span>
            <span class="admin-dashboard-action-description">
              Course와 차시를 관리합니다.
            </span>
          </a>

          <?php if (oj_is_admin()) { ?>

            <a class="admin-dashboard-action" href="user_set_ip.php">
              <span class="admin-dashboard-action-title">
                로그인 IP 설정
              </span>
              <span class="admin-dashboard-action-description">
                대회 참가자의 로그인 IP를 관리합니다.
              </span>
            </a>

            <a class="admin-dashboard-action" href="team_generate.php">
              <span class="admin-dashboard-action-title">
                팀 계정 생성
              </span>
              <span class="admin-dashboard-action-description">
                대회용 팀 계정을 생성합니다.
              </span>
            </a>

          <?php } ?>

        </div>

      </section>

    <?php } ?>


    <!-- 시스템 -->
    <?php if (oj_can_manage_admin_system()) { ?>

      <section class="admin-card admin-dashboard-card">

        <div class="admin-dashboard-card-header">

          <div>
            <h2 class="admin-dashboard-card-title">
              시스템 관리
            </h2>

            <p class="admin-dashboard-card-description">
              채점과 서버 운영 기능을 관리합니다.
            </p>
          </div>

        </div>

        <div class="admin-dashboard-actions">

          <a class="admin-dashboard-action" href="rejudge.php">
            <span class="admin-dashboard-action-title">
              재채점
            </span>
            <span class="admin-dashboard-action-description">
              지정한 제출 또는 문제를 다시 채점합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="source_give.php">
            <span class="admin-dashboard-action-title">
              소스 권한 관리
            </span>
            <span class="admin-dashboard-action-description">
              제출 코드 열람 권한을 관리합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="../online.php">
            <span class="admin-dashboard-action-title">
              접속 사용자
            </span>
            <span class="admin-dashboard-action-description">
              현재 접속 중인 사용자를 확인합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="update_db.php">
            <span class="admin-dashboard-action-title">
              DB 업데이트
            </span>
            <span class="admin-dashboard-action-description">
              데이터베이스 구조 업데이트를 실행합니다.
            </span>
          </a>

          <a class="admin-dashboard-action" href="setdbinfo.php">
            <span class="admin-dashboard-action-title">
              DB 설정
            </span>
            <span class="admin-dashboard-action-description">
              데이터베이스 연결 설정을 관리합니다.
            </span>
          </a>

        </div>

      </section>

    <?php } ?>

  </div>

</div>

<?php
require(__DIR__ . "/admin-layout-end.php");
?>
