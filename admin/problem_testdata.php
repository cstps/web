<?php

require_once(__DIR__ . '/../include/db_info.inc.php');
require_once(__DIR__ . '/../include/my_func.inc.php');
require_once(__DIR__ . '/../include/permission_functions.inc.php');


// ============================================================
// Problem test-data manager
//
// Supported operations:
// - Upload .in/.out text files
// - List and download existing files
// - Delete selected files
//
// Deliberately unsupported:
// - Arbitrary path browsing
// - Server-side file editing
// - Rename/move/archive/shell execution
// ============================================================


function oj_testdata_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function oj_testdata_abort($status_code, $message)
{
    http_response_code((int)$status_code);

    $safe_message = oj_testdata_escape($message);

    echo '<!DOCTYPE html>';
    echo '<html lang="ko">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>요청을 처리할 수 없습니다</title>';
    echo '</head>';
    echo '<body>';
    echo '<h1>요청을 처리할 수 없습니다</h1>';
    echo '<p>' . $safe_message . '</p>';
    echo '<p><a href="problem_list.php">문제 목록으로 이동</a></p>';
    echo '</body>';
    echo '</html>';

    exit;
}


function oj_testdata_valid_filename($filename)
{
    if (!is_string($filename)) {
        return false;
    }

    if (
        $filename === '' ||
        strlen($filename) > 124 ||
        basename($filename) !== $filename
    ) {
        return false;
    }

    return preg_match(
        '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(?:in|out)\z/D',
        $filename
    ) === 1;
}


function oj_testdata_problem_directory($data_root, $pid, $create)
{
    $problem_directory =
        $data_root . DIRECTORY_SEPARATOR . (string)$pid;

    if (
        file_exists($problem_directory) ||
        is_link($problem_directory)
    ) {
        if (
            !is_dir($problem_directory) ||
            is_link($problem_directory)
        ) {
            return false;
        }
    } elseif ($create) {
        if (
            !mkdir($problem_directory, 0755, true) &&
            !is_dir($problem_directory)
        ) {
            return false;
        }

        @chmod($problem_directory, 0755);
    } else {
        return null;
    }

    $real_directory = realpath($problem_directory);

    if (
        $real_directory === false ||
        dirname($real_directory) !== $data_root
    ) {
        return false;
    }

    return $real_directory;
}


function oj_testdata_existing_file($problem_directory, $filename)
{
    if (
        $problem_directory === null ||
        $problem_directory === false ||
        !oj_testdata_valid_filename($filename)
    ) {
        return false;
    }

    $candidate =
        $problem_directory . DIRECTORY_SEPARATOR . $filename;

    if (
        is_link($candidate) ||
        !is_file($candidate)
    ) {
        return false;
    }

    $real_file = realpath($candidate);

    if (
        $real_file === false ||
        dirname($real_file) !== $problem_directory
    ) {
        return false;
    }

    return $real_file;
}


function oj_testdata_human_size($bytes)
{
    $bytes = max(0, (int)$bytes);
    $units = array('B', 'KB', 'MB', 'GB');
    $unit_index = 0;
    $value = (float)$bytes;

    while (
        $value >= 1024 &&
        $unit_index < count($units) - 1
    ) {
        $value /= 1024;
        $unit_index++;
    }

    if ($unit_index === 0) {
        return number_format($value, 0) . ' ' . $units[$unit_index];
    }

    return number_format($value, 1) . ' ' . $units[$unit_index];
}


function oj_testdata_upload_error_message($error_code)
{
    $messages = array(
        UPLOAD_ERR_INI_SIZE => 'PHP 업로드 제한을 초과했습니다.',
        UPLOAD_ERR_FORM_SIZE => '폼 업로드 제한을 초과했습니다.',
        UPLOAD_ERR_PARTIAL => '파일이 일부만 전송되었습니다.',
        UPLOAD_ERR_NO_FILE => '선택된 파일이 없습니다.',
        UPLOAD_ERR_NO_TMP_DIR => '서버의 임시 디렉터리가 없습니다.',
        UPLOAD_ERR_CANT_WRITE => '서버가 임시 파일을 저장하지 못했습니다.',
        UPLOAD_ERR_EXTENSION => 'PHP 확장 기능이 업로드를 중단했습니다.'
    );

    return isset($messages[$error_code])
        ? $messages[$error_code]
        : '알 수 없는 업로드 오류가 발생했습니다.';
}


function oj_testdata_set_flash($pid, $type, $message)
{
    global $OJ_NAME;

    $_SESSION[$OJ_NAME . '_problem_testdata_flash_' . (int)$pid] = array(
        'type' => $type,
        'message' => $message
    );
}


function oj_testdata_redirect($pid)
{
    header(
        'Location: problem_testdata.php?id=' . rawurlencode((string)(int)$pid)
    );
    exit;
}


// ============================================================
// Request and authorization
// ============================================================

$request_method = isset($_SERVER['REQUEST_METHOD'])
    ? strtoupper((string)$_SERVER['REQUEST_METHOD'])
    : 'GET';

$pid = 0;

if (isset($_POST['pid'])) {
    $pid = intval($_POST['pid']);
} elseif (isset($_GET['id'])) {
    $pid = intval($_GET['id']);
} elseif (isset($_GET['pid'])) {
    $pid = intval($_GET['pid']);
}

if ($pid <= 0) {
    oj_testdata_abort(400, '올바른 문제 번호가 필요합니다.');
}

if (!function_exists('oj_can_manage_problem_testdata')) {
    oj_testdata_abort(
        500,
        '테스트데이터 관리 권한 함수가 설치되지 않았습니다.'
    );
}

if (!oj_is_logged_in()) {
    oj_testdata_abort(401, '로그인이 필요합니다.');
}

if (!oj_can_manage_problem_testdata($pid)) {
    oj_testdata_abort(
        403,
        '본인이 생성한 문제의 테스트데이터만 관리할 수 있습니다.'
    );
}

$problem_rows = pdo_query(
    'SELECT problem_id, title FROM problem WHERE problem_id=?',
    $pid
);

if (!$problem_rows || !isset($problem_rows[0])) {
    oj_testdata_abort(404, '문제를 찾을 수 없습니다.');
}

$problem = $problem_rows[0];
$problem_title = isset($problem['title'])
    ? (string)$problem['title']
    : '';

$data_root = isset($OJ_DATA)
    ? realpath((string)$OJ_DATA)
    : false;

if (
    $data_root === false ||
    !is_dir($data_root)
) {
    oj_testdata_abort(500, '채점데이터 루트 디렉터리를 확인할 수 없습니다.');
}

$problem_directory =
    oj_testdata_problem_directory($data_root, $pid, false);

if ($problem_directory === false) {
    oj_testdata_abort(500, '문제 데이터 디렉터리가 안전하지 않습니다.');
}


// ============================================================
// Download
// ============================================================

$get_action = isset($_GET['action'])
    ? (string)$_GET['action']
    : '';

if (
    $request_method === 'GET' &&
    $get_action === 'download'
) {
    $filename = isset($_GET['file'])
        ? (string)$_GET['file']
        : '';

    $download_file =
        oj_testdata_existing_file($problem_directory, $filename);

    if ($download_file === false) {
        oj_testdata_abort(404, '다운로드할 파일을 찾을 수 없습니다.');
    }

    $file_size = filesize($download_file);

    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    if ($file_size !== false) {
        header('Content-Length: ' . (string)$file_size);
    }

    readfile($download_file);
    exit;
}


// ============================================================
// Mutating operations
// ============================================================

if ($request_method === 'POST') {
    require(__DIR__ . '/../include/check_post_key.php');

    $post_action = isset($_POST['action'])
        ? (string)$_POST['action']
        : '';

    if ($post_action === 'upload') {
        $problem_directory =
            oj_testdata_problem_directory($data_root, $pid, true);

        if ($problem_directory === false) {
            oj_testdata_set_flash(
                $pid,
                'error',
                '문제 데이터 디렉터리를 만들거나 확인할 수 없습니다.'
            );
            oj_testdata_redirect($pid);
        }

        $upload = isset($_FILES['test_files'])
            ? $_FILES['test_files']
            : null;

        if (
            !is_array($upload) ||
            !isset($upload['name']) ||
            !is_array($upload['name']) ||
            !isset($upload['error']) ||
            !is_array($upload['error']) ||
            !isset($upload['tmp_name']) ||
            !is_array($upload['tmp_name']) ||
            !isset($upload['size']) ||
            !is_array($upload['size'])
        ) {
            oj_testdata_set_flash($pid, 'error', '업로드할 파일을 선택해 주세요.');
            oj_testdata_redirect($pid);
        }

        $file_count = count($upload['name']);
        $max_file_count = 20;
        $max_file_size = 8 * 1024 * 1024;
        $max_total_size = 14 * 1024 * 1024;

        if ($file_count > $max_file_count) {
            oj_testdata_set_flash(
                $pid,
                'error',
                '한 번에 최대 20개 파일만 업로드할 수 있습니다.'
            );
            oj_testdata_redirect($pid);
        }

        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';
        $pending = array();
        $seen_names = array();
        $errors = array();
        $total_size = 0;

        for ($index = 0; $index < $file_count; $index++) {
            $filename = isset($upload['name'][$index])
                ? (string)$upload['name'][$index]
                : '';
            $error_code = isset($upload['error'][$index])
                ? intval($upload['error'][$index])
                : UPLOAD_ERR_NO_FILE;
            $temporary_file = isset($upload['tmp_name'][$index])
                ? (string)$upload['tmp_name'][$index]
                : '';
            $file_size = isset($upload['size'][$index])
                ? intval($upload['size'][$index])
                : 0;

            if ($error_code === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error_code !== UPLOAD_ERR_OK) {
                $errors[] =
                    ($filename !== '' ? $filename . ': ' : '') .
                    oj_testdata_upload_error_message($error_code);
                continue;
            }

            if (!oj_testdata_valid_filename($filename)) {
                $errors[] =
                    ($filename !== '' ? $filename : '(이름 없음)') .
                    ': 영문·숫자·점·밑줄·하이픈과 .in/.out 확장자만 허용합니다.';
                continue;
            }

            if (isset($seen_names[$filename])) {
                $errors[] = $filename . ': 같은 이름이 중복 선택되었습니다.';
                continue;
            }

            $seen_names[$filename] = true;

            if ($file_size < 0 || $file_size > $max_file_size) {
                $errors[] = $filename . ': 파일당 8MB 제한을 초과했습니다.';
                continue;
            }

            $total_size += $file_size;

            if ($total_size > $max_total_size) {
                $errors[] = '전체 파일 크기가 14MB 제한을 초과했습니다.';
                break;
            }

            if (!is_uploaded_file($temporary_file)) {
                $errors[] = $filename . ': 정상적인 HTTP 업로드 파일이 아닙니다.';
                continue;
            }

            $handle = @fopen($temporary_file, 'rb');

            if ($handle === false) {
                $errors[] = $filename . ': 임시 파일을 읽을 수 없습니다.';
                continue;
            }

            $sample = fread($handle, 8192);
            fclose($handle);

            if ($sample === false || strpos($sample, "\0") !== false) {
                $errors[] = $filename . ': 텍스트 채점데이터만 업로드할 수 있습니다.';
                continue;
            }

            $target_file =
                $problem_directory . DIRECTORY_SEPARATOR . $filename;

            if (is_link($target_file)) {
                $errors[] = $filename . ': 심볼릭 링크 대상은 덮어쓸 수 없습니다.';
                continue;
            }

            if (file_exists($target_file) && !$overwrite) {
                $errors[] =
                    $filename . ': 같은 이름의 파일이 이미 있습니다. ' .
                    '덮어쓰기를 선택한 뒤 다시 시도해 주세요.';
                continue;
            }

            $pending[] = array(
                'name' => $filename,
                'temporary_file' => $temporary_file,
                'target_file' => $target_file
            );
        }

        if (!$pending && !$errors) {
            $errors[] = '업로드할 파일을 선택해 주세요.';
        }

        if ($errors) {
            oj_testdata_set_flash(
                $pid,
                'error',
                implode("\n", $errors)
            );
            oj_testdata_redirect($pid);
        }

        $uploaded_count = 0;

        foreach ($pending as $item) {
            if (
                !move_uploaded_file(
                    $item['temporary_file'],
                    $item['target_file']
                )
            ) {
                $errors[] = $item['name'] . ': 파일 저장에 실패했습니다.';
                continue;
            }

            @chmod($item['target_file'], 0644);
            $uploaded_count++;
        }

        if ($errors) {
            oj_testdata_set_flash(
                $pid,
                'error',
                $uploaded_count . "개 파일을 저장했고 일부 파일은 실패했습니다.\n" .
                    implode("\n", $errors)
            );
        } else {
            oj_testdata_set_flash(
                $pid,
                'success',
                $uploaded_count . '개 파일을 저장했습니다.'
            );
        }

        oj_testdata_redirect($pid);
    }

    if ($post_action === 'delete') {
        $selected_files = isset($_POST['selected_files'])
            ? $_POST['selected_files']
            : array();

        if (!is_array($selected_files)) {
            $selected_files = array();
        }

        $selected_files = array_values(array_unique($selected_files));

        if (!$selected_files) {
            oj_testdata_set_flash($pid, 'error', '삭제할 파일을 선택해 주세요.');
            oj_testdata_redirect($pid);
        }

        if (count($selected_files) > 200) {
            oj_testdata_set_flash($pid, 'error', '한 번에 삭제할 수 있는 파일 수를 초과했습니다.');
            oj_testdata_redirect($pid);
        }

        $delete_targets = array();
        $errors = array();

        foreach ($selected_files as $filename) {
            $filename = (string)$filename;
            $existing_file =
                oj_testdata_existing_file($problem_directory, $filename);

            if ($existing_file === false) {
                $errors[] = $filename . ': 안전한 채점데이터 파일이 아닙니다.';
                continue;
            }

            $delete_targets[$filename] = $existing_file;
        }

        if ($errors) {
            oj_testdata_set_flash($pid, 'error', implode("\n", $errors));
            oj_testdata_redirect($pid);
        }

        $deleted_count = 0;

        foreach ($delete_targets as $filename => $delete_file) {
            if (!unlink($delete_file)) {
                $errors[] = $filename . ': 삭제하지 못했습니다.';
                continue;
            }

            $deleted_count++;
        }

        if ($errors) {
            oj_testdata_set_flash(
                $pid,
                'error',
                $deleted_count . '개 파일을 삭제했고 일부 파일은 실패했습니다.\n' .
                    implode("\n", $errors)
            );
        } else {
            oj_testdata_set_flash(
                $pid,
                'success',
                $deleted_count . '개 파일을 삭제했습니다.'
            );
        }

        oj_testdata_redirect($pid);
    }

    oj_testdata_set_flash($pid, 'error', '지원하지 않는 작업입니다.');
    oj_testdata_redirect($pid);
}


// ============================================================
// File inventory
// ============================================================

$files = array();
$total_bytes = 0;
$pair_extensions = array();

if ($problem_directory !== null) {
    $iterator = new FilesystemIterator(
        $problem_directory,
        FilesystemIterator::SKIP_DOTS
    );

    foreach ($iterator as $item) {
        $filename = $item->getFilename();

        if (
            $item->isLink() ||
            !$item->isFile() ||
            !oj_testdata_valid_filename($filename)
        ) {
            continue;
        }

        $size = $item->getSize();
        $modified_time = $item->getMTime();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $stem = substr($filename, 0, -strlen($extension) - 1);

        if (!isset($pair_extensions[$stem])) {
            $pair_extensions[$stem] = array();
        }

        $pair_extensions[$stem][$extension] = true;

        $files[] = array(
            'name' => $filename,
            'size' => $size,
            'modified_time' => $modified_time,
            'extension' => $extension,
            'stem' => $stem
        );

        $total_bytes += $size;
    }
}

usort(
    $files,
    function ($left, $right) {
        return strnatcasecmp($left['name'], $right['name']);
    }
);

$flash_key = $OJ_NAME . '_problem_testdata_flash_' . $pid;
$flash = isset($_SESSION[$flash_key])
    ? $_SESSION[$flash_key]
    : null;
unset($_SESSION[$flash_key]);


// A caller that passed the exact per-problem permission check may enter the
// shared admin layout even without a global admin-menu privilege.
$admin_page_access_allowed = true;
$admin_page_title = '테스트데이터 관리';
$admin_active_menu = 'problem_list';

require(__DIR__ . '/admin-layout-start.php');
?>

<div class="admin-page admin-testdata-page">

    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">테스트데이터 관리</h1>
            <p class="admin-page-description">
                문제 <?php echo (int)$pid; ?> ·
                <?php echo oj_testdata_escape($problem_title); ?>
            </p>
        </div>

        <div class="admin-page-header-actions">
            <a
                class="admin-btn admin-btn-secondary"
                href="../problem.php?id=<?php echo (int)$pid; ?>">
                문제 보기
            </a>
            <a
                class="admin-btn admin-btn-secondary"
                href="problem_list.php">
                문제 목록
            </a>
        </div>
    </div>

    <?php if (is_array($flash) && isset($flash['message'])) { ?>
        <div
            class="admin-testdata-alert <?php
                                        echo isset($flash['type']) && $flash['type'] === 'success'
                                            ? 'is-success'
                                            : 'is-error';
                                        ?>"
            role="status">
            <?php echo nl2br(oj_testdata_escape($flash['message'])); ?>
        </div>
    <?php } ?>

    <form
        id="problem-testdata-form"
        method="post"
        enctype="multipart/form-data"
        action="problem_testdata.php">

        <input type="hidden" name="pid" value="<?php echo (int)$pid; ?>">

        <div class="admin-csrf-fields">
            <?php require(__DIR__ . '/../include/set_post_key.php'); ?>
        </div>

        <section class="admin-form-card" aria-labelledby="testdata-upload-title">
            <div class="admin-form-card-header">
                <span class="admin-form-step">1</span>
                <div>
                    <div class="admin-form-card-title" id="testdata-upload-title">
                        파일 업로드
                    </div>
                    <div class="admin-form-card-desc">
                        입력(.in)과 정답(.out) 파일을 함께 선택할 수 있습니다.
                    </div>
                </div>
            </div>

            <div class="admin-form-field">
                <label class="admin-form-label" for="test-files">
                    채점데이터 파일
                </label>
                <input
                    class="admin-form-input admin-testdata-file-input"
                    id="test-files"
                    type="file"
                    name="test_files[]"
                    accept=".in,.out,text/plain"
                    multiple>
                <p class="admin-form-help">
                    영문·숫자·점·밑줄·하이픈으로 된 .in/.out 파일만 허용합니다.
                    한 번에 최대 20개, 파일당 8MB, 전체 14MB까지 업로드할 수 있습니다.
                </p>
            </div>

            <label class="admin-testdata-check-option">
                <input type="checkbox" name="overwrite" value="1">
                <span>같은 이름의 기존 파일 덮어쓰기</span>
            </label>

            <div class="admin-form-actions">
                <button
                    class="admin-btn admin-btn-primary"
                    type="submit"
                    name="action"
                    value="upload">
                    선택 파일 업로드
                </button>
            </div>
        </section>

        <section class="admin-table-card" aria-labelledby="testdata-list-title">
            <div class="admin-testdata-table-header">
                <div>
                    <h2 id="testdata-list-title">등록 파일</h2>
                    <p>
                        <?php echo count($files); ?>개 ·
                        <?php echo oj_testdata_escape(oj_testdata_human_size($total_bytes)); ?>
                    </p>
                </div>

                <?php if ($files) { ?>
                    <button
                        class="admin-btn admin-btn-danger"
                        type="submit"
                        name="action"
                        value="delete"
                        id="delete-selected-files">
                        선택 삭제
                    </button>
                <?php } ?>
            </div>

            <?php if ($files) { ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="admin-col-check">
                                    <input
                                        type="checkbox"
                                        id="select-all-files"
                                        aria-label="전체 파일 선택">
                                </th>
                                <th>파일명</th>
                                <th>종류</th>
                                <th>짝 상태</th>
                                <th>크기</th>
                                <th>수정 시각</th>
                                <th>다운로드</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file) {
                                $pair = isset($pair_extensions[$file['stem']])
                                    ? $pair_extensions[$file['stem']]
                                    : array();
                                $is_complete_pair =
                                    isset($pair['in']) && isset($pair['out']);
                            ?>
                                <tr>
                                    <td>
                                        <input
                                            class="admin-testdata-file-checkbox"
                                            type="checkbox"
                                            name="selected_files[]"
                                            value="<?php echo oj_testdata_escape($file['name']); ?>"
                                            aria-label="<?php echo oj_testdata_escape($file['name']); ?> 선택">
                                    </td>
                                    <td class="admin-testdata-filename">
                                        <?php echo oj_testdata_escape($file['name']); ?>
                                    </td>
                                    <td>
                                        <span class="admin-badge <?php
                                                                    echo $file['extension'] === 'in'
                                                                        ? 'admin-testdata-badge-input'
                                                                        : 'admin-testdata-badge-output';
                                                                    ?>">
                                            .<?php echo oj_testdata_escape($file['extension']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="admin-badge <?php
                                                                    echo $is_complete_pair
                                                                        ? 'admin-badge-success'
                                                                        : 'admin-badge-muted';
                                                                    ?>">
                                            <?php echo $is_complete_pair ? '완료' : '짝 없음'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo oj_testdata_escape(
                                            oj_testdata_human_size($file['size'])
                                        ); ?>
                                    </td>
                                    <td>
                                        <?php echo oj_testdata_escape(
                                            date('Y-m-d H:i', $file['modified_time'])
                                        ); ?>
                                    </td>
                                    <td>
                                        <a
                                            class="admin-btn admin-btn-secondary admin-testdata-download"
                                            href="problem_testdata.php?id=<?php
                                                                            echo (int)$pid;
                                                                            ?>&amp;action=download&amp;file=<?php
                                                                            echo rawurlencode($file['name']);
                                                                            ?>">
                                            받기
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="admin-testdata-empty">
                    <strong>등록된 .in/.out 파일이 없습니다.</strong>
                    <span>위 업로드 영역에서 첫 채점데이터를 추가해 주세요.</span>
                </div>
            <?php } ?>
        </section>
    </form>
</div>

<script>
    (function() {
        'use strict';

        var form = document.getElementById('problem-testdata-form');
        var selectAll = document.getElementById('select-all-files');
        var fileCheckboxes = document.querySelectorAll(
            '.admin-testdata-file-checkbox'
        );

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                Array.prototype.forEach.call(fileCheckboxes, function(checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        }

        if (form) {
            form.addEventListener('submit', function(event) {
                var submitter = event.submitter;

                if (!submitter || submitter.value !== 'delete') {
                    return;
                }

                var selectedCount = document.querySelectorAll(
                    '.admin-testdata-file-checkbox:checked'
                ).length;

                if (selectedCount === 0) {
                    event.preventDefault();
                    window.alert('삭제할 파일을 선택해 주세요.');
                    return;
                }

                if (!window.confirm(
                        '선택한 ' + selectedCount + '개 파일을 삭제하시겠습니까?'
                    )) {
                    event.preventDefault();
                }
            });
        }
    })();
</script>

<?php
require(__DIR__ . '/admin-layout-end.php');
