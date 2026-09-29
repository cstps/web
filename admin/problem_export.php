<?php

require_once __DIR__ . '/admin-init.php';

if (!oj_can_create_admin_problems()) {
  http_response_code(403);
  exit('문제를 내보낼 권한이 없습니다.');
}

$admin_page_title = '문제 내보내기';
$admin_active_menu = 'problem-export';

require_once __DIR__ . '/admin-layout-start.php';
?>

<div class="admin-page">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">
        문제 내보내기
      </h1>

      <div class="admin-page-description">
        관리 권한이 있는 문제를 FPS XML 파일로 내려받습니다.
      </div>
    </div>

    <a
      href="problem_list.php"
      class="admin-btn admin-btn-secondary">
      문제 목록
    </a>
  </div>

  <div class="admin-form-card">

    <div class="admin-form-card-header">
      <span class="admin-form-step">1</span>

      <div>
        <div class="admin-form-card-title">
          내보낼 문제 선택
        </div>

        <div class="admin-form-card-desc">
          개별 번호 또는 연속 범위 중 하나를 입력합니다.
        </div>
      </div>
    </div>

    <form
      action="problem_export_xml.php"
      method="post">

      <div class="admin-form-field">
        <label
          class="admin-form-label"
          for="export-problem-ids">
          개별 문제 번호
        </label>

        <input
          class="admin-form-input"
          id="export-problem-ids"
          name="in"
          type="text"
          maxlength="1000"
          placeholder="1001, 1003, 1005">

        <div class="admin-form-help">
          쉼표로 구분해 최대 100개까지 입력합니다.
          이 칸에 번호가 있으면 아래 연속 범위는 사용하지 않습니다.
        </div>
      </div>

      <div class="admin-form-field">
        <div class="admin-form-label">
          연속 범위
        </div>

        <div class="admin-form-unit admin-export-range">
          <input
            class="admin-form-input"
            id="export-start"
            name="start"
            type="number"
            min="1"
            step="1"
            aria-label="연속 범위 시작 번호"
            placeholder="1001">

          <span class="admin-form-unit-label">부터</span>

          <input
            class="admin-form-input"
            id="export-end"
            name="end"
            type="number"
            min="1"
            step="1"
            aria-label="연속 범위 끝 번호"
            placeholder="1009">

          <span class="admin-form-unit-label">까지</span>
        </div>

        <div class="admin-form-help">
          시작과 끝을 모두 입력합니다.
          최대 100개 번호의 범위를 선택할 수 있습니다.
        </div>
      </div>

      <input
        type="hidden"
        name="do"
        value="do">

      <?php
      require_once(
        __DIR__ .
        '/../include/set_post_key.php'
      );
      ?>

      <div class="admin-form-actions">
        <a
          href="problem_list.php"
          class="admin-btn admin-btn-secondary">
          취소
        </a>

        <button
          type="submit"
          class="admin-btn admin-btn-primary">
          FPS XML 내려받기
        </button>
      </div>

    </form>

  </div>

  <div class="admin-form-card">
    <div class="admin-form-card-header">
      <span class="admin-form-step">i</span>

      <div>
        <div class="admin-form-card-title">
          내보내기 안내
        </div>

        <div class="admin-form-card-desc">
          선택한 문제 중 관리 권한이 없는 문제가 있으면
          전체 내보내기가 중단됩니다.
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/admin-layout-end.php'; ?>
