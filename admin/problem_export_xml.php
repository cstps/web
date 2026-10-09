<?php

require_once __DIR__ . '/admin-init.php';

require_once __DIR__ . '/../include/const.inc.php';
require_once __DIR__ . '/../include/code_template_functions.inc.php';

function fixcdata($content) {
  $content = str_replace("\x1a","",$content);   // remove some strange \x1a [SUB] char from datafile
  return str_replace("]]>","]]]]><![CDATA[>",$content);
}

function getTestFileIn($pid, $testfile,$OJ_DATA) {
  if ($testfile != "")
    return file_get_contents("$OJ_DATA/$pid/".$testfile.".in");
  else
    return "";
}

function getTestFileOut($pid, $testfile,$OJ_DATA) {
  if ($testfile != "")
    return file_get_contents();
  else
    return "";
}

function printTestCases($pid,$OJ_DATA) {
  if (strstr($OJ_DATA,"saestor:")) {
    // echo "<debug>$pid</debug>";
    $store = new SaeStorage();
    $ret = $store->getList("data", "$pid",100,0 );

    foreach ($ret as $file) {
      //echo "<debug>$file</debug>";

      if (!strstr($file,"sae-dir-tag")) {
        $pinfo = pathinfo($file);

        if (isset($pinfo['extension']) &&$pinfo['extension']=="in" && $pinfo['basename']!="sample.in") {
          $f = basename($pinfo['basename'],".".$pinfo ['extension']);
          
          $outfile = "$pid/".$f.".out";
          $infile = "$pid/".$f.".in";

          if ($store->fileExists("data",$infile)) {
            echo "<test_input><![CDATA[".fixcdata($store->read("data",$infile))."]]></test_input>\n";
          }

          if ($store->fileExists("data",$outfile)) {
            echo "<test_output><![CDATA[".fixcdata($store->read("data",$outfile))."]]></test_output>\n";
          }
          //break;
        }
      }
    }
  
  }
  else {
    $ret = "";
    //$pdir = opendir("$OJ_DATA/$pid/");
    $files = scandir("$OJ_DATA/$pid/"); //sorting file names by ascending order with default scandir function

    //while ($file=readdir($pdir)) {
    foreach ($files as $file) {
      $pinfo = pathinfo($file);
      
      if (isset($pinfo['extension']) && $pinfo['extension']=="in" && $pinfo['basename']!="sample.in") {
        $ret = basename($pinfo['basename'], ".".$pinfo['extension']);

        $outfile = "$OJ_DATA/$pid/".$ret.".out";
        $infile = "$OJ_DATA/$pid/".$ret.".in";

        if (file_exists($infile)) {
          echo "<test_input name=\"".$ret."\"><![CDATA[".fixcdata(file_get_contents($infile))."]]></test_input>\n";
        }

        if (file_exists($outfile)) {
          echo "<test_output name=\"".$ret."\"><![CDATA[".fixcdata(file_get_contents($outfile))."]]></test_output>\n";
        }
        //break;
      }
    }
    
    //closedir($pdir);
    return $ret;
  }
}


class Solution {
  var $language = "";
  var $source_code = "";  
}

function getSolution($pid,$lang) {
  $ret = new Solution();

  $language_name = $GLOBALS['language_name'];

  $sql = "SELECT `solution_id`,`language` FROM solution WHERE problem_id=? AND result=4 AND language=? LIMIT 1";
  //echo $sql;

  $result = pdo_query($sql,$pid,$lang);

  if ($result && $row=$result[0]) {
    $solution_id = $row[0];
    $ret->language = $language_name[$row[1]];
    $sql = "SELECT source FROM source_code WHERE solution_id=?";
    $result = pdo_query( $sql,$solution_id ) ;

    if ($row = $result[0]){
      $ret->source_code = $row['source'];
    }
  }

  return $ret;
}

function fixurl($img_url) {
  $img_url = html_entity_decode($img_url,ENT_QUOTES,"UTF-8");

  if (substr($img_url,0,4)!="http") {
    if (substr($img_url,0,1)=="/") {
      $ret = 'http://'.$_SERVER['HTTP_HOST'].':'.$_SERVER["SERVER_PORT"].$img_url;
    }
    else {
      $path = dirname($_SERVER['PHP_SELF']);
      $ret = 'http://'.$_SERVER['HTTP_HOST'].':'.$_SERVER["SERVER_PORT"].$path."/../".$img_url;
    }

  }
  else {
    $ret = $img_url;
  }

  return  $ret;
}

function image_base64_encode($img_url) {
  $img_url = fixurl($img_url);

  if (substr($img_url,0,4)!="http")
    return false;

  $handle = @fopen($img_url, "rb");

  if ($handle) {
    $contents = stream_get_contents($handle);
    $encoded_img = base64_encode($contents);
    fclose($handle);
    return $encoded_img;
  }
  else
    return false;
}

function getImages($content) {
  preg_match_all("<[iI][mM][gG][^<>]+[sS][rR][cC]=\"?([^ \"\>]+)/?>",$content,$images);
  return $images;
}

function fixImageURL(&$html,&$did) {
  $images = getImages($html);
  $imgs = array_unique($images[1]);

  foreach ($imgs as $img) {
    $html = str_replace($img,fixurl($img),$html); 
    //print_r($did);

    if (!in_array($img,$did)) {
      $base64 = image_base64_encode($img);
      if ($base64) {
        echo "<img><src><![CDATA[";
        echo fixurl($img);
        echo "]]></src><base64><![CDATA[";
        echo $base64;
        echo "]]></base64></img>";   
      }
      array_push($did,$img);
    }
  }     
}


if (
  !oj_can_create_admin_problems() &&
  !oj_can_manage_admin_contests()
) {
  http_response_code(403);
  exit('문제를 내보낼 권한이 없습니다.');
}

if (isset($_GET['cid'])) {
  require_once __DIR__ . '/../include/check_get_key.php';

  $cid = filter_var(
    $_GET['cid'],
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 1))
  );

  if (!is_int($cid)) {
    http_response_code(400);
    exit('대회 번호가 올바르지 않습니다.');
  }

  $contest_rows = pdo_query(
    'SELECT contest_id FROM contest WHERE contest_id = ? LIMIT 1',
    $cid
  );

  if ($contest_rows === false) {
    http_response_code(500);
    exit('대회 정보를 조회하지 못했습니다.');
  }

  if (count($contest_rows) === 0) {
    http_response_code(404);
    exit('대회를 찾을 수 없습니다.');
  }

  $filename = '-contest-' . $cid;
  $result = pdo_query(
    'SELECT problem.*
     FROM problem
     INNER JOIN contest_problem
       ON contest_problem.problem_id = problem.problem_id
     WHERE contest_problem.contest_id = ?
     ORDER BY contest_problem.num, problem.problem_id
     LIMIT 101',
    $cid
  );
} elseif (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  isset($_POST['do']) &&
  $_POST['do'] === 'do'
) {
  require_once __DIR__ . '/../include/check_post_key.php';

  $in = isset($_POST['in']) ? trim((string)$_POST['in']) : '';

  if ($in !== '') {
    $parts = explode(',', $in);
    $ids = array();

    foreach ($parts as $part) {
      $part = trim($part);

      if (!preg_match('/^[1-9][0-9]*$/D', $part)) {
        http_response_code(400);
        exit('문제 번호 목록이 올바르지 않습니다.');
      }

      $pid = filter_var(
        $part,
        FILTER_VALIDATE_INT,
        array('options' => array('min_range' => 1))
      );

      if ($pid === false) {
        http_response_code(400);
        exit('문제 번호가 너무 큽니다.');
      }

      $ids[$pid] = $pid;
    }

    $ids = array_values($ids);

    if (count($ids) > 100) {
      http_response_code(400);
      exit('한 번에 최대 100개 문제를 선택할 수 있습니다.');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $result = pdo_query(
      'SELECT * FROM problem WHERE problem_id IN (' .
      $placeholders . ') ORDER BY problem_id',
      ...$ids
    );

    $filename = '-selected';

    if (
      is_array($result) &&
      count($result) !== count($ids)
    ) {
      http_response_code(404);
      exit('선택한 문제 중 존재하지 않는 번호가 있습니다.');
    }
  } else {
    $start = filter_var(
      isset($_POST['start']) ? $_POST['start'] : null,
      FILTER_VALIDATE_INT,
      array('options' => array('min_range' => 1))
    );
    $end = filter_var(
      isset($_POST['end']) ? $_POST['end'] : null,
      FILTER_VALIDATE_INT,
      array('options' => array('min_range' => 1))
    );

    if (
      !is_int($start) ||
      !is_int($end) ||
      $start > $end ||
      $end - $start >= 100
    ) {
      http_response_code(400);
      exit('시작·끝 문제 번호를 확인해 주세요. 범위는 최대 100개입니다.');
    }

    $result = pdo_query(
      'SELECT * FROM problem
       WHERE problem_id >= ? AND problem_id <= ?
       ORDER BY problem_id',
      $start,
      $end
    );

    $filename = '-' . $start . '-' . $end;
  }
} else {
  http_response_code(400);
  exit('내보내기 요청이 올바르지 않습니다.');
}

if ($result === false) {
  http_response_code(500);
  exit('문제를 조회하지 못했습니다.');
}

if (!is_array($result)) {
  http_response_code(500);
  exit('문제 조회 결과가 올바르지 않습니다.');
}

if (count($result) > 100) {
  http_response_code(400);
  exit('한 번에 최대 100개 문제를 내보낼 수 있습니다.');
}

if (count($result) === 0) {
  http_response_code(404);
  exit('내보낼 문제가 없습니다.');
}

// 문제의 표시용 출제자와 별개로 실제 p{문제번호} 권한을 검사한다.
// 일부만 허용된 선택도 전체 요청을 중단한다.
foreach ($result as $problem_row) {
  if (
    !oj_can_manage_problem(
      (int)$problem_row['problem_id']
    )
  ) {
    http_response_code(403);
    exit('선택한 문제 중 내보낼 권한이 없는 문제가 있습니다.');
  }
}

if (isset($_POST['submit']) && $_POST['submit']== "Export")
  header('Content-Type:text/xml');
else {
  header("content-type:application/file");
  header("content-disposition:attachment;filename=\"fps-".$_SESSION[$OJ_NAME.'_'.'user_id'].$filename.".xml\"");
}
?>

<!DOCTYPE fps PUBLIC 
  "-//freeproblemset//An opensource XML standard for Algorithm Contest Problem Set//EN"
  "http://hustoj.com/fps.current.dtd" >

<fps version="1.4" url="https://github.com/zhblue/freeproblemset/">
  <generator name="HUSTOJ" url="https://github.com/zhblue/hustoj/" />
  <?php
  foreach ($result as  $row) {

    // --------------------------------------------------------
    // problem_template을 코드 템플릿의 원본으로 사용한다.
    //
    // FPS/XML 형식은 기존 front_code/rear_code 형식을
    // 그대로 유지하여 다른 HUSTOJ와의 호환성을 보존한다.
    // --------------------------------------------------------

    $export_front_code =
      isset($row['front_code'])
        ? (string)$row['front_code']
        : '';

    $export_rear_code =
      isset($row['rear_code'])
        ? (string)$row['rear_code']
        : '';

    $problem_templates =
      oj_get_problem_templates(
        intval($row['problem_id'])
      );

    if (
      is_array($problem_templates) &&
      !empty($problem_templates)
    ) {
      $structured_templates =
        array(
          'front' => array(),
          'rear' => array()
        );

      foreach (
        $problem_templates
        as $language_id => $template
      ) {
        foreach (
          array('front', 'rear')
          as $kind
        ) {
          $content =
            isset($template[$kind])
              ? (string)$template[$kind]
              : '';

          if ($content === '') {
            continue;
          }

          $structured_templates[$kind][$language_id] =
            array(
              'language_id' =>
                intval($language_id),

              'lang' =>
                isset($template['lang'])
                  ? (string)$template['lang']
                  : '',

              'kind' =>
                $kind,

              'content' =>
                $content
            );
        }
      }

      $legacy_templates =
        oj_build_legacy_problem_templates(
          $structured_templates,
          $language_name
        );

      $export_front_code =
        $legacy_templates['front'];

      $export_rear_code =
        $legacy_templates['rear'];
    }
  ?>

  <item>
    <title><![CDATA[<?php echo $row['title']?>]]></title>
    <time_limit unit="s"><![CDATA[<?php echo $row['time_limit']?>]]></time_limit>
    <memory_limit unit="mb"><![CDATA[<?php echo $row['memory_limit']?>]]></memory_limit>

    <?php
    $did = array();
    fixImageURL($row['description'],$did);
    fixImageURL($row['input'],$did);
    fixImageURL($row['output'],$did);
    fixImageURL($row['hint'],$did);
    ?>

    <description><![CDATA[<?php echo $row['description']?>]]></description>
    <input><![CDATA[<?php echo $row['input']?>]]></input> 
    <output><![CDATA[<?php echo $row['output']?>]]></output>
    <sample_input><![CDATA[<?php echo $row['sample_input']?>]]></sample_input>
    <sample_output><![CDATA[<?php echo $row['sample_output']?>]]></sample_output>
    <?php printTestCases($row['problem_id'],$OJ_DATA)?>
    <hint><![CDATA[<?php echo $row['hint']?>]]></hint>
    <source><![CDATA[<?php echo fixcdata($row['source'])?>]]></source>
    <creator><![CDATA[<?php echo fixcdata($row['creator'])?>]]></creator>
    
    <front_code><![CDATA[<?php echo fixcdata($export_front_code)?>]]></front_code>
    <rear_code><![CDATA[<?php echo fixcdata($export_rear_code)?>]]></rear_code>
    <ban_code><![CDATA[<?php echo fixcdata($row['ban_code'])?>]]></ban_code>
    <pro_point><![CDATA[<?php echo fixcdata($row['pro_point'])?>]]></pro_point>
    

    <?php
    $pid = $row['problem_id'];
    for ($lang=0; $lang<count($language_ext); $lang++) {
      $solution = getSolution($pid,$lang);

      if ($solution->language)
    {?>
        <solution language="<?php echo $solution->language?>"><![CDATA[<?php echo fixcdata($solution->source_code)?>]]></solution>
    <?php 
    }
    
    $pta = array("prepend","template","append");
    
    foreach ($pta as $pta_file) {
      $append_file = "$OJ_DATA/$pid/$pta_file.".$language_ext[$lang];
      //echo "<filename value=\"$lang  $append_file $language_ext[$lang]\"/>";
    
      if (file_exists($append_file)) { ?>
        <<?php echo $pta_file?> language="<?php echo $language_name[$lang]?>"><![CDATA[<?php echo fixcdata(file_get_contents($append_file))?>]]></<?php echo $pta_file?>>
        <?php 
      }
    }
  }
?>

<?php
  if ($row['spj'] != 0) {
    $filec = "$OJ_DATA/".$row['problem_id']."/spj.c";
    $filecc = "$OJ_DATA/".$row['problem_id']."/spj.cc";

    if (file_exists($filec)) {
      echo "<spj language=\"C\"><![CDATA[";
      echo fixcdata(file_get_contents($filec));
      echo "]]></spj>";
    }
    else if (file_exists($filecc)) {
      echo "<spj language=\"C++\"><![CDATA[";
      echo fixcdata(file_get_contents ($filecc ));
      echo "]]></spj>";
    }
    $filec = "$OJ_DATA/".$row['problem_id']."/tpj.c";
    $filecc = "$OJ_DATA/".$row['problem_id']."/tpj.cc";

    if (file_exists($filec)) {
      echo "<tpj language=\"C\"><![CDATA[";
      echo fixcdata(file_get_contents($filec));
      echo "]]></tpj>";
    }
    else if (file_exists($filecc)) {
      echo "<tpj language=\"C++\"><![CDATA[";
      echo fixcdata(file_get_contents ($filecc ));
      echo "]]></tpj>";
    }
  }
?>
</item>

<?php }
echo "</fps>";
?>
