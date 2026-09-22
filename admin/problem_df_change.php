<?php

require_once(__DIR__ . "/admin-init.php");


// ============================================================
// POST 요청만 허용
// ============================================================

if (
  !isset($_SERVER['REQUEST_METHOD']) ||
  $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
  header('Allow: POST');
  http_response_code(405);

  exit('POST 요청만 허용됩니다.');
}


// ============================================================
// CSRF 검사
// ============================================================

require_once(
  __DIR__ .
  "/../include/check_post_key.php"
);


// ============================================================
// 요청값 검사
//
// visibility_change 형식:
// Y:1234 = 비공개로 변경
// N:1234 = 공개로 변경
// ============================================================

$visibility_change =
  isset($_POST['visibility_change'])
  ? trim((string)$_POST['visibility_change'])
  : '';

if (
  preg_match(
    '/^([YN]):([1-9][0-9]*)$/D',
    $visibility_change,
    $matches
  ) !== 1
) {
  http_response_code(400);

  exit('잘못된 상태 변경 요청입니다.');
}

$defunct =
  $matches[1];

$problem_id =
  intval($matches[2]);


// ============================================================
// 문제별 관리 권한 검사
// ============================================================

if (
  !oj_can_manage_problem(
    $problem_id
  )
) {
  http_response_code(403);

  exit('이 문제의 공개 상태를 변경할 권한이 없습니다.');
}


// ============================================================
// 문제 존재 여부 확인
// ============================================================

$problem_rows =
  pdo_query(
    "SELECT problem_id
         FROM problem
         WHERE problem_id=?",
    $problem_id
  );

if ($problem_rows === false) {
  http_response_code(500);

  exit('문제 정보를 확인하지 못했습니다.');
}

if (count($problem_rows) === 0) {
  http_response_code(404);

  exit('존재하지 않는 문제입니다.');
}


// ============================================================
// 공개 상태 변경
// ============================================================

$update_result =
  pdo_query(
    "UPDATE problem
         SET defunct=?
         WHERE problem_id=?",
    $defunct,
    $problem_id
  );

if ($update_result === false) {
  http_response_code(500);

  exit('문제 상태를 변경하지 못했습니다.');
}


// ============================================================
// 문제 목록으로 이동
// ============================================================

$return_params = array();

if (
  isset($_POST['return_scope']) &&
  $_POST['return_scope'] === 'all'
) {
  $return_params['scope'] = 'all';
}

$return_keyword =
  isset($_POST['return_keyword'])
  ? trim((string)$_POST['return_keyword'])
  : '';

if ($return_keyword !== '') {
  $return_params['keyword'] =
    $return_keyword;
}

$return_page =
  isset($_POST['return_page'])
  ? max(1, intval($_POST['return_page']))
  : 1;

if ($return_page > 1) {
  $return_params['page'] =
    $return_page;
}

$return_url =
  'problem_list.php';

if (count($return_params) > 0) {
  $return_url .=
    '?' .
    http_build_query($return_params);
}

header(
  'Location: ' . $return_url,
  true,
  303
);

exit;
