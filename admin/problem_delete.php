<?php

require_once __DIR__ . '/admin-init.php';

if (
  !isset($_SERVER['REQUEST_METHOD']) ||
  $_SERVER['REQUEST_METHOD'] !== 'GET'
) {
  header('Allow: GET');
  http_response_code(405);
  exit('GET 요청만 허용됩니다.');
}

$problem_id = filter_var(
  isset($_GET['id']) && is_string($_GET['id'])
    ? $_GET['id']
    : '',
  FILTER_VALIDATE_INT,
  array('options' => array('min_range' => 1))
);

if ($problem_id === false) {
  http_response_code(400);
  exit('잘못된 문제 번호입니다.');
}

if (!oj_can_manage_problem($problem_id)) {
  http_response_code(403);
  exit('이 문제를 삭제할 권한이 없습니다.');
}

$rows = pdo_query(
  "SELECT problem_id, title
   FROM problem
   WHERE problem_id=?",
  $problem_id
);

if ($rows === false) {
  http_response_code(500);
  exit('문제 정보를 확인하지 못했습니다.');
}

if (count($rows) === 0) {
  http_response_code(404);
  exit('존재하지 않는 문제입니다.');
}

$problem = $rows[0];

$counts = pdo_query(
  "SELECT
     (SELECT COUNT(*) FROM solution
      WHERE problem_id=?) AS submissions,
     (SELECT COUNT(*) FROM contest_problem
      WHERE problem_id=?) AS contest_links,
     (SELECT COUNT(*) FROM solution_process
      WHERE problem_id=?) AS process_rows,
     (SELECT COUNT(*) FROM teacher_process_note
      WHERE problem_id=?) AS note_rows",
  $problem_id,
  $problem_id,
  $problem_id,
  $problem_id
);

if ($counts === false || count($counts) !== 1) {
  http_response_code(500);
  exit('문제의 연결 기록을 확인하지 못했습니다.');
}

$counts = $counts[0];
$can_delete = true;

foreach ($counts as $key => $value) {
  // PDO의 숫자 인덱스 항목도 같은 값이므로 결과에 영향이 없습니다.
  if ((int)$value > 0) {
    $can_delete = false;
  }
}

$labels = array(
  'submissions' => '제출 기록',
  'contest_links' => '대회 연결',
  'process_rows' => '풀이 과정',
  'note_rows' => '교사 메모'
);

$return_scope =
  isset($_GET['scope']) && $_GET['scope'] === 'all'
  ? 'all'
  : '';

$return_archived =
  isset($_GET['archived']) && $_GET['archived'] === '1'
  ? '1'
  : '';

$return_params = array();

if ($return_scope === 'all') {
  $return_params['scope'] = 'all';
}

if ($return_archived === '1') {
  $return_params['archived'] = '1';
}

$return_url = 'problem_list.php';

if (count($return_params) > 0) {
  $return_url .= '?' . http_build_query($return_params);
}

$admin_page_title = '문제 영구 삭제';
$admin_active_menu = 'problem_list';

require_once __DIR__ . '/admin-layout-start.php';
?>

<div class="admin-page">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">문제 영구 삭제</h1>
      <div class="admin-page-description">
        삭제 조건과 대상 문제를 확인합니다.
      </div>
    </div>
  </div>

  <div class="admin-form-card">
    <div class="admin-form-card-header">
      <span class="admin-form-step">1</span>
      <div>
        <div class="admin-form-card-title">
          <?php echo (int)$problem_id; ?>번 ·
          <?php
          echo htmlspecialchars(
            (string)$problem['title'],
            ENT_QUOTES,
            'UTF-8'
          );
          ?>
        </div>
      </div>
    </div>

    <?php foreach ($labels as $key => $label) { ?>
      <p>
        <?php echo $label; ?>:
        <strong><?php echo (int)$counts[$key]; ?>건</strong>
      </p>
    <?php } ?>

    <?php if (!$can_delete) { ?>
      <div class="admin-alert admin-alert-error">
        연결 기록이 있는 문제는 영구 삭제할 수 없습니다.
        기록을 유지하려면 보관 기능을 사용해 주세요.
      </div>
    <?php } else { ?>
      <div class="admin-form-help">
        삭제하면 문제 정보, 코드 템플릿, 관리 권한,
        테스트 데이터가 제거됩니다. 화면에서 복원할 수 없습니다.
      </div>
    <?php } ?>

    <form action="problem_del.php" method="post">
      <input
        type="hidden"
        name="problem_id"
        value="<?php echo (int)$problem_id; ?>">

      <input
        type="hidden"
        name="return_scope"
        value="<?php echo $return_scope; ?>">

      <input
        type="hidden"
        name="return_archived"
        value="<?php echo $return_archived; ?>">

      <?php if ($can_delete) { ?>
        <div class="admin-form-field">
          <label
            class="admin-form-label"
            for="confirm-problem-id">
            확인을 위해 문제 번호를 다시 입력해 주세요.
          </label>

          <input
            class="admin-form-input"
            id="confirm-problem-id"
            name="confirm_problem_id"
            type="number"
            min="1"
            step="1"
            required>
        </div>
      <?php } ?>

      <?php
      require __DIR__ . '/../include/set_post_key.php';
      ?>

      <div class="admin-form-actions">
        <a
          class="admin-btn admin-btn-secondary"
          href="<?php
          echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8');
          ?>">
          문제 목록으로 돌아가기
        </a>

        <button
          class="admin-btn admin-btn-primary"
          type="submit"
          <?php echo $can_delete ? '' : 'disabled'; ?>>
          영구 삭제
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/admin-layout-end.php'; ?>
