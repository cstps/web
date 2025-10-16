<?php
// admin/migrate_problem_templates.php

// 1) 경로 안전: 스크립트 기준으로 include
$ROOT = dirname(__DIR__); // /home/judge/src/web
require_once($ROOT . "/include/db_info.inc.php");
require_once($ROOT . "/include/const.inc.php");

set_time_limit(0);

// 2) 토큰 포맷 파서: //"언어"// ~ 다음 //"언어"// 이전까지
function parse_blocks($blob, $language_names) {
  $res = []; // lang => code
  if (!$blob) return $res;

  foreach ($language_names as $lang) {
    $tok = "//".$lang."//";
    $p = strpos($blob, $tok);
    if ($p === false) continue;
    $p += strlen($tok);

    // 다음 언어 토큰 또는 문자열 끝까지
    $next = strlen($blob);
    foreach ($language_names as $l2) {
      if ($l2 === $lang) continue;
      $tp = strpos($blob, "//".$l2."//", $p);
      if ($tp !== false && $tp < $next) $next = $tp;
    }
    $code = trim(substr($blob, $p, $next - $p));
    if ($code !== '') $res[$lang] = $code;
  }
  return $res;
}

// 3) 마이그레이션
$rows = pdo_query("SELECT problem_id, front_code, rear_code FROM problem");
$done = 0; $ins = 0;

foreach ($rows as $r) {
  $pid = (int)$r['problem_id'];

  // DB에 엔티티로 저장된 경우를 고려해 디코드
  $front_raw = html_entity_decode((string)($r['front_code'] ?? ''), ENT_QUOTES, 'UTF-8');
  $rear_raw  = html_entity_decode((string)($r['rear_code']  ?? ''), ENT_QUOTES, 'UTF-8');

  $fmap = parse_blocks($front_raw, $language_name);
  $rmap = parse_blocks($rear_raw,  $language_name);

  foreach ($fmap as $lang => $code) {
    $code = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $code);
    pdo_query(
      "REPLACE INTO problem_template(problem_id, lang, kind, content) VALUES(?,?, 'front', ?)",
      $pid, $lang, $code
    );
    $ins++;
  }
  foreach ($rmap as $lang => $code) {
    $code = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $code);
    pdo_query(
      "REPLACE INTO problem_template(problem_id, lang, kind, content) VALUES(?,?, 'rear', ?)",
      $pid, $lang, $code
    );
    $ins++;
  }
  $done++;
}

echo "Migrated templates for {$done} problems. Rows upserted: {$ins}\n";
