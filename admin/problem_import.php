<?php

require_once __DIR__ . '/admin-init.php';

if (!oj_can_create_admin_problems()) {
  http_response_code(403);
  exit('문제를 가져올 권한이 없습니다.');
}

$admin_page_title = '문제 가져오기';
$admin_active_menu = 'problem-import';

require_once __DIR__ . '/admin-layout-start.php';

function writable($path)
{
  if (!is_dir($path) || !is_writable($path)) {
    return false;
  }

  $test_file = tempnam($path, 'oj_write_');

  if ($test_file === false) {
    return false;
  }

  return @unlink($test_file);
}

$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');


?>

<?php
$show_form = true;

$data_error = '';

if (!isset($OJ_SAE) || !$OJ_SAE) {

  if (!writable($OJ_DATA)) {

    $data_error =
      '문제 데이터 디렉터리에 파일을 저장할 수 없습니다. ' .
      '서버의 디렉터리 권한과 open_basedir 설정을 확인해 주세요.';

    $show_form = false;
  }

  $upload_dir =
    dirname(__DIR__) . '/upload';

  if (!is_dir($upload_dir)) {

    if (
      !mkdir($upload_dir, 0770, true) &&
      !is_dir($upload_dir)
    ) {

      $data_error =
        '업로드 디렉터리를 생성할 수 없습니다.';

      $show_form = false;
    }
  }

  if (
    $show_form &&
    !writable($upload_dir)
  ) {

    $data_error =
      '업로드 디렉터리에 파일을 저장할 수 없습니다.';

    $show_form = false;
  }
}
?>


<div class="admin-page">

  <div class="admin-page-header">

    <div>

      <h1 class="admin-page-title">
        문제 가져오기
      </h1>

      <div class="admin-page-description">
        FPS/XML 형식의 문제 파일이나 ZIP 묶음을 가져옵니다.
      </div>

    </div>

    <a
      href="problem_list.php"
      class="admin-btn admin-btn-secondary">
      문제 목록
    </a>

  </div>


  <?php if ($data_error !== '') { ?>

    <div class="admin-alert admin-alert-error">
      <?php
      echo htmlspecialchars(
        $data_error,
        ENT_QUOTES,
        'UTF-8'
      );
      ?>
    </div>

  <?php } ?>


  <div class="admin-form-card">

    <div class="admin-form-card-header">

      <span class="admin-form-step">
        1
      </span>

      <div>

        <div class="admin-form-card-title">
          가져오기 파일
        </div>

        <div class="admin-form-card-desc">
          XML, FPS 또는 ZIP 파일을 선택합니다.
        </div>

      </div>

    </div>


    <?php if ($show_form) { ?>

      <form
        action="problem_import_xml.php"
        method="post"
        enctype="multipart/form-data">

        <div class="admin-form-field">

          <label
            class="admin-form-label"
            for="fps">
            문제 파일
          </label>

          <input
            type="file"
            id="fps"
            name="fps"
            accept=".xml,.fps,.zip"
            required>

          <div class="admin-form-help">
            지원 형식: XML, FPS, ZIP /
            최대 업로드 파일: 15MB
          </div>

        </div>


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
            문제 가져오기
          </button>

        </div>

      </form>

    <?php } ?>

  </div>


  <div class="admin-form-card">

    <div class="admin-form-card-header">

      <span class="admin-form-step">
        i
      </span>

      <div>

        <div class="admin-form-card-title">
          가져오기 안내
        </div>

        <div class="admin-form-card-desc">
          업로드 파일과 ZIP 파일은 서버에서 안전성 검사를 거친 후 처리됩니다.
        </div>

      </div>

    </div>


    <div class="admin-form-help">

      <div>
        PHP upload_max_filesize:
        <strong>
          <?php
          echo htmlspecialchars(
            $upload_max_filesize,
            ENT_QUOTES,
            'UTF-8'
          );
          ?>
        </strong>
      </div>

      <div>
        PHP post_max_size:
        <strong>
          <?php
          echo htmlspecialchars(
            $post_max_size,
            ENT_QUOTES,
            'UTF-8'
          );
          ?>
        </strong>
      </div>

      <div>
        ZIP 파일은 루트에 있는 XML/FPS 파일만 가져옵니다.
      </div>

    </div>

  </div>

</div>
<?php
require_once __DIR__ . '/admin-layout-end.php';
?>