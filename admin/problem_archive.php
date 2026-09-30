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

// 요청 형식: archive:1815 또는 restore:1815
$change =
  isset($_POST['archive_change']) &&
  is_string($_POST['archive_change'])
  ? $_POST['archive_change']
  : '';

if (
  preg_match(
    '/^(archive|restore):([1-9][0-9]*)$/D',
    $change,
    $matches
  ) !== 1
) {
  http_response_code(400);
  exit('잘못된 보관·복원 요청입니다.');
}

$action = $matches[1];
$problem_id = filter_var(
  $matches[2],
  FILTER_VALIDATE_INT,
  array('options' => array('min_range' => 1))
);

if ($problem_id === false) {
  http_response_code(400);
  exit('잘못된 문제 번호입니다.');
}

if (!oj_can_manage_problem($problem_id)) {
  http_response_code(403);
  exit('이 문제를 보관하거나 복원할 권한이 없습니다.');
}

$user_id =
  isset($_SESSION[$OJ_NAME . '_user_id'])
  ? trim((string)$_SESSION[$OJ_NAME . '_user_id'])
  : '';

if ($user_id === '') {
  http_response_code(403);
  exit('로그인 사용자 정보를 확인할 수 없습니다.');
}

$rows = pdo_query(
  "SELECT problem_id, is_archived
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

if ($action === 'archive') {
  // 상태 변경 시점에도 대회 연결 여부를 검사합니다.
  $result = pdo_query(
    "UPDATE problem
     SET is_archived=1,
         defunct='Y',
         archived_at=NOW(),
         archived_by=?
     WHERE problem_id=?
       AND is_archived=0
       AND NOT EXISTS (
         SELECT 1
         FROM contest_problem cp
         WHERE cp.problem_id=?
       )",
    $user_id,
    $problem_id,
    $problem_id
  );
} else {
  // 복원해도 자동으로 공개하지 않습니다.
  $result = pdo_query(
    "UPDATE problem
     SET is_archived=0,
         defunct='Y',
         archived_at=NULL,
         archived_by=NULL
     WHERE problem_id=?
       AND is_archived=1",
    $problem_id
  );
}

if ($result === false) {
  http_response_code(500);
  exit('문제의 보관 상태를 변경하지 못했습니다.');
}

// UPDATE의 반환값 대신 실제 저장 상태를 확인합니다.
$verify = pdo_query(
  "SELECT is_archived
   FROM problem
   WHERE problem_id=?",
  $problem_id
);

if ($verify === false) {
  http_response_code(500);
  exit('변경 결과를 확인하지 못했습니다.');
}

if (count($verify) === 0) {
  http_response_code(404);
  exit('존재하지 않는 문제입니다.');
}

$expected = $action === 'archive' ? 1 : 0;

if ((int)$verify[0]['is_archived'] !== $expected) {
  http_response_code(409);
  exit('보관하지 못했습니다. 대회 연결 여부와 현재 상태를 확인해 주세요.');
}

// 목록의 조회 범위를 유지합니다.
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

$location = 'problem_list.php';

if (count($return_params) > 0) {
  $location .= '?' . http_build_query($return_params);
}

header('Location: ' . $location, true, 303);
exit;
