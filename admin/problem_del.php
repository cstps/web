<?php

require_once __DIR__ . '/admin-init.php';

if (
  !isset($_SERVER['REQUEST_METHOD']) ||
  $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
  header('Allow: POST');
  http_response_code(405);
  exit('POST 요청만 허용됩니다.');
}

require_once __DIR__ . '/../include/check_post_key.php';

$problem_id = filter_var(
  isset($_POST['problem_id']) && is_string($_POST['problem_id'])
    ? $_POST['problem_id']
    : '',
  FILTER_VALIDATE_INT,
  array('options' => array('min_range' => 1))
);

$confirmation = filter_var(
  isset($_POST['confirm_problem_id']) &&
  is_string($_POST['confirm_problem_id'])
    ? $_POST['confirm_problem_id']
    : '',
  FILTER_VALIDATE_INT,
  array('options' => array('min_range' => 1))
);

if (
  $problem_id === false ||
  $confirmation === false ||
  $confirmation !== $problem_id
) {
  http_response_code(400);
  exit('삭제할 문제 번호와 확인 번호가 일치하지 않습니다.');
}

if (!oj_can_manage_problem($problem_id)) {
  http_response_code(403);
  exit('이 문제를 삭제할 권한이 없습니다.');
}

// 기존 PDO 연결을 초기화합니다.
if (pdo_query('SELECT 1') === false || !($dbh instanceof PDO)) {
  http_response_code(500);
  exit('데이터베이스에 연결하지 못했습니다.');
}

$data_root = isset($OJ_DATA)
  ? realpath((string)$OJ_DATA)
  : false;

if (
  $data_root === false ||
  !is_dir($data_root) ||
  !is_readable($data_root) ||
  !is_writable($data_root)
) {
  http_response_code(500);
  exit('문제 데이터 디렉터리를 처리할 수 없습니다.');
}

$data_path = $data_root . DIRECTORY_SEPARATOR . $problem_id;
$staged_path = null;

// 심볼릭 링크를 따라가며 다른 디렉터리를 삭제하지 않습니다.
function oj_delete_problem_data_tree($path)
{
  if (is_link($path) || !is_dir($path)) {
    if (!unlink($path)) {
      throw new RuntimeException('데이터 파일 삭제 실패');
    }
    return;
  }

  $entries = scandir($path);

  if ($entries === false) {
    throw new RuntimeException('데이터 디렉터리 조회 실패');
  }

  foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
      continue;
    }

    oj_delete_problem_data_tree(
      $path . DIRECTORY_SEPARATOR . $entry
    );
  }

  if (!rmdir($path)) {
    throw new RuntimeException('데이터 디렉터리 삭제 실패');
  }
}

try {
  // 연결 기록이 없는 범위에도 잠금이 적용되도록 설정합니다.
  $dbh->exec(
    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'
  );
  $dbh->beginTransaction();

  $statement = $dbh->prepare(
    'SELECT problem_id
     FROM problem
     WHERE problem_id=?
     FOR UPDATE'
  );
  $statement->execute(array($problem_id));
  $problem = $statement->fetch(PDO::FETCH_ASSOC);
  $statement->closeCursor();

  if ($problem === false) {
    throw new RuntimeException(
      '존재하지 않는 문제입니다.',
      404
    );
  }

  $references = array(
    'solution' => '제출 기록',
    'contest_problem' => '대회 연결',
    'solution_process' => '풀이 과정',
    'teacher_process_note' => '교사 메모'
  );

  foreach ($references as $table => $label) {
    // 테이블명은 위의 고정 목록에서만 가져옵니다.
    $statement = $dbh->prepare(
      'SELECT problem_id
       FROM `' . $table . '`
       WHERE problem_id=?
       LIMIT 1
       FOR UPDATE'
    );
    $statement->execute(array($problem_id));
    $reference = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if ($reference !== false) {
      throw new RuntimeException(
        $label . '이 있는 문제는 영구 삭제할 수 없습니다.',
        409
      );
    }
  }

  if (is_link($data_path)) {
    throw new RuntimeException(
      '문제 데이터 경로가 심볼릭 링크이므로 삭제할 수 없습니다.',
      409
    );
  }

  if (file_exists($data_path)) {
    if (!is_dir($data_path)) {
      throw new RuntimeException(
        '문제 데이터 경로가 디렉터리가 아닙니다.',
        409
      );
    }

    $target_path =
      $data_root .
      DIRECTORY_SEPARATOR .
      '.delete-' .
      $problem_id .
      '-' .
      bin2hex(random_bytes(12));

    if (!rename($data_path, $target_path)) {
      throw new RuntimeException(
        '문제 데이터 디렉터리를 이동하지 못했습니다.',
        500
      );
    }

    $staged_path = $target_path;
  }

  $statement = $dbh->prepare(
    'DELETE FROM problem_template WHERE problem_id=?'
  );
  $statement->execute(array($problem_id));

  $statement = $dbh->prepare(
    'DELETE FROM privilege WHERE rightstr=?'
  );
  $statement->execute(array('p' . $problem_id));

  $statement = $dbh->prepare(
    'DELETE FROM problem WHERE problem_id=?'
  );
  $statement->execute(array($problem_id));

  if ($statement->rowCount() !== 1) {
    throw new RuntimeException('문제 삭제 결과를 확인하지 못했습니다.');
  }

  $dbh->commit();
} catch (Throwable $error) {
  $recovery_failed = false;

  try {
    if ($dbh->inTransaction()) {
      $dbh->rollBack();
    }
  } catch (Throwable $rollback_error) {
    $recovery_failed = true;
    error_log('[problem_delete] rollback: ' . $rollback_error->getMessage());
  }

  if ($staged_path !== null) {
    if (
      file_exists($data_path) ||
      is_link($data_path) ||
      !rename($staged_path, $data_path)
    ) {
      $recovery_failed = true;
    }
  }

  error_log(
    '[problem_delete] problem_id=' .
    $problem_id . ' ' . $error->getMessage()
  );

  $status = (int)$error->getCode();
  if (!in_array($status, array(404, 409, 500), true)) {
    $status = 500;
  }

  http_response_code($status);

  if ($recovery_failed) {
    exit('삭제 처리 중 복구에 실패했습니다. 서버 로그와 데이터 상태를 확인해 주세요.');
  }

  $message =
    $error instanceof PDOException
    ? 'DB 삭제 처리에 실패했습니다. 서버 로그를 확인해 주세요.'
    : $error->getMessage();

  exit(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

// DB 삭제가 확정된 뒤 임시 디렉터리의 파일을 제거합니다.
if ($staged_path !== null) {
  try {
    oj_delete_problem_data_tree($staged_path);
  } catch (Throwable $error) {
    error_log(
      '[problem_delete] problem_id=' .
      $problem_id .
      ' remaining_data=' .
      $staged_path .
      ' ' .
      $error->getMessage()
    );

    http_response_code(500);
    exit('문제 정보는 삭제됐지만 일부 데이터 파일 정리에 실패했습니다. 서버 로그를 확인해 주세요.');
  }
}

$return_params = array();

if (
  isset($_POST['return_scope']) &&
  $_POST['return_scope'] === 'all'
) {
  $return_params['scope'] = 'all';
}

if (
  isset($_POST['return_archived']) &&
  $_POST['return_archived'] === '1'
) {
  $return_params['archived'] = '1';
}

$return_params['deleted'] = $problem_id;

header(
  'Location: problem_list.php?' .
  http_build_query($return_params),
  true,
  303
);
exit;
