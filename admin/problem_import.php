<?php

require_once __DIR__ . '/admin-init.php';

if (!oj_is_admin()) {
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

echo "<center><h3>" . $MSG_PROBLEM . "-" . $MSG_IMPORT . "</h3></center>";

?>

<div class="container">
  <br><br>
  <?php
  $show_form = true;

  if (!isset($OJ_SAE) || !$OJ_SAE) {
    if (!writable($OJ_DATA)) {
      echo "- You need to add  $OJ_DATA into your open_basedir setting of php.ini,<br>
        or you need to execute:<br>
        <b>chmod 775 -R $OJ_DATA && chgrp -R www-data $OJ_DATA</b><br>
        you can't use import function at this time.<br>";

      $show_form = false;
    }

    $upload_dir = dirname(__DIR__) . '/upload';

    if (!is_dir($upload_dir)) {
      if (!mkdir($upload_dir, 0770, true) && !is_dir($upload_dir)) {
        echo '<div class="admin-alert admin-alert-error">';
        echo '업로드 디렉터리를 생성할 수 없습니다.';
        echo '</div>';

        $show_form = false;
      }
    }

    if ($show_form && !writable($upload_dir)) {
      echo '<div class="admin-alert admin-alert-error">';
      echo '업로드 디렉터리에 파일을 저장할 수 없습니다.';
      echo '</div>';

      $show_form = false;
    }
  }
  ?>

  <?php if ($show_form) { ?>
    - Import Problem XML<br><br>
    <form class='form-inline' action='problem_import_xml.php' method=post enctype="multipart/form-data">
      <div class='form-group'>
        <input
          class="form-control"
          type="file"
          id="fps"
          name="fps"
          accept=".xml,.fps"
          required>
      </div>
      <br><br>
      <br><br><br>
      <center>
        <div class='form-group'>
          <button class='btn btn-default btn-sm' type=submit>Upload to HUSTOJ</button>
        </div>
      </center>
      <?php require_once("../include/set_post_key.php"); ?>
    </form>
  <?php } ?>

  <br><br>

  <p>
    업로드 제한:
    upload_max_filesize=<?php echo htmlspecialchars($upload_max_filesize); ?>,
    post_max_size=<?php echo htmlspecialchars($post_max_size); ?>
  </p>



</div>
<?php
require_once __DIR__ . '/admin-layout-end.php';
?>