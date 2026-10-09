<?php

require_once(__DIR__ . '/const.inc.php');
require_once(__DIR__ . '/code_template_functions.inc.php');

function addproblem(
  $title,
  $time_limit,
  $memory_limit,
  $description,
  $input,
  $output,
  $sample_input,
  $sample_output,
  $hint,
  $source,
  $spj,
  $OJ_DATA,
  $front_code,
  $rear_code,
  $ban_code,
  $pro_point,
  $creator = '',
  $templates = null
) {

  $sql = "INSERT INTO `problem` (`title`,`time_limit`,`memory_limit`,`description`,`input`,`output`,`sample_input`,`sample_output`,`hint`,`source`,`creator`,`spj`,`in_date`,`defunct`,`front_code`, `rear_code`, `ban_code`, `pro_point`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),'Y',?,?,?,?)";
  
  $pid =
    pdo_query(
      $sql,
      $title,
      $time_limit,
      $memory_limit,
      $description,
      $input,
      $output,
      $sample_input,
      $sample_output,
      $hint,
      $source,
      $creator,
      $spj,
      $front_code,
      $rear_code,
      $ban_code,
      $pro_point
    );

  if (
    $pid === false ||
    intval($pid) <= 0
  ) {
    error_log(
      '[addproblem] 문제 INSERT 실패'
    );

    return false;
  }

  $pid =
    intval($pid);

  global $language_name;

  if (is_array($templates)) {

    $template_sync_result =
      oj_save_problem_templates(
        $pid,
        $templates
      );

  } else {

    // 기존 FPS 가져오기 등 legacy 호출은 그대로 지원한다.
    $template_sync_result =
      oj_sync_problem_templates_from_legacy(
        $pid,
        $front_code,
        $rear_code,
        $language_name
      );
  }

  if ($template_sync_result === false) {
    // 기존 problem 컬럼에는 정상 저장되었으므로 문제 생성은 유지한다.
    // 템플릿 동기화 실패는 로그에 남기고 문제 생성 자체는 유지한다.
    error_log(
      '[addproblem] problem_id=' .
        $pid .
        ' 템플릿 동기화 실패'
    );
  }

  echo "&nbsp;&nbsp;- Problem ID $pid added!<br>";

  if (isset($_POST['contest_id']) && intval($_POST['contest_id'])>0) {
    $cid = intval($_POST['contest_id']);
    $sql = "SELECT count(*) FROM `contest_problem` WHERE `contest_id`=?";
    $result = pdo_query($sql, $cid);
    $row = $result[0];
    $num = $row[0];
    
    echo "&nbsp;&nbsp;- Contest Problem Num = ".$num.":";

    $sql = "INSERT INTO `contest_problem` (`problem_id`,`contest_id`,`num`) VALUES(?,?,?)";	
    pdo_query($sql, $pid, $cid, $num);
  }

  $basedir = "$OJ_DATA/$pid";

  if (!isset($OJ_SAE) || !$OJ_SAE) {
    //echo "[$title]data in $basedir";
  }
  return $pid;
}

function mkdata($pid, $filename, $input, $OJ_DATA)
{
  $basedir = "$OJ_DATA/$pid";

  $fp = @fopen(
    $basedir . "/" . $filename,
    "w"
  );

  if (!$fp) {
    echo "- Error while opening " .
      $basedir .
      "/" .
      $filename;

    return false;
  }

  $normalized_input =
    preg_replace(
      "(\r\n)",
      "\n",
      (string)$input
    );

  if ($normalized_input === null) {
    fclose($fp);

    return false;
  }

  $written =
    fputs(
      $fp,
      $normalized_input
    );

  $closed =
    fclose($fp);

  if (
    $written === false ||
    $closed === false
  ) {
    return false;
  }

  return true;
}
?>
