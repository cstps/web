<?php

require_once(
  __DIR__ . "/admin-init.php"
);


// ============================================================
// 문제 생성 권한
// ============================================================

if (!oj_can_create_admin_problems()) {
  http_response_code(403);

  exit('문제를 가져올 권한이 없습니다.');
}

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

require_once("../include/check_post_key.php");


// ============================================================
// 업로드 파일 기본 검사
// ============================================================

if (
  !isset($_FILES['fps']) ||
  !is_array($_FILES['fps'])
) {
  http_response_code(400);

  exit('업로드할 FPS/XML 파일이 없습니다.');
}

if (
  !isset($_FILES['fps']['error']) ||
  intval($_FILES['fps']['error']) !== UPLOAD_ERR_OK
) {
  http_response_code(400);

  exit('파일 업로드에 실패했습니다.');
}

if (
  !isset($_FILES['fps']['tmp_name']) ||
  !is_uploaded_file($_FILES['fps']['tmp_name'])
) {
  http_response_code(400);

  exit('올바른 업로드 파일이 아닙니다.');
}

// ============================================================
// 업로드 파일 확장자 검사
// ============================================================

$uploaded_name =
  isset($_FILES['fps']['name'])
  ? (string)$_FILES['fps']['name']
  : '';

$uploaded_extension =
  strtolower(
    pathinfo(
      $uploaded_name,
      PATHINFO_EXTENSION
    )
  );

$allowed_extensions =
  array(
    'xml',
    'fps',
    'zip'
  );

if (
  !in_array(
    $uploaded_extension,
    $allowed_extensions,
    true
  )
) {
  http_response_code(400);

  exit('XML, FPS, ZIP 파일만 업로드할 수 있습니다.');
}


// ============================================================
// 업로드 파일 크기 검사
//
// PHP 업로드 제한은 16M.
// multipart 요청 여유를 위해 실제 파일은 15MiB로 제한한다.
// ============================================================

$max_upload_size =
  15 * 1024 * 1024;

$uploaded_size =
  isset($_FILES['fps']['size'])
  ? intval($_FILES['fps']['size'])
  : 0;

if (
  $uploaded_size <= 0 ||
  $uploaded_size > $max_upload_size
) {
  http_response_code(400);

  exit('업로드 파일은 15MB 이하만 허용됩니다.');
}

require_once("../include/const.inc.php");

function image_save_file(
  $filepath,
  $base64_encoded_img
) {

  $decoded =
    base64_decode(
      (string)$base64_encoded_img,
      true
    );

  if ($decoded === false) {
    return false;
  }


  // 디코딩된 이미지 하나당 최대 10MiB
  $max_image_size =
    10 * 1024 * 1024;

  $image_size =
    strlen($decoded);

  if (
    $image_size <= 0 ||
    $image_size > $max_image_size
  ) {
    return false;
  }


  // 실제 이미지 형식 확인
  $image_info =
    getimagesizefromstring(
      $decoded
    );

  if (
    $image_info === false ||
    !isset($image_info[2])
  ) {
    return false;
  }


  $allowed_types =
    array(
      IMAGETYPE_JPEG => array(
        'jpg',
        'jpeg'
      ),
      IMAGETYPE_PNG => array(
        'png'
      ),
      IMAGETYPE_GIF => array(
        'gif'
      ),
      IMAGETYPE_BMP => array(
        'bmp'
      )
    );

  $image_type =
    intval($image_info[2]);

  if (
    !isset(
      $allowed_types[$image_type]
    )
  ) {
    return false;
  }


  // 파일 확장자와 실제 이미지 형식이 일치하는지 검사
  $extension =
    strtolower(
      pathinfo(
        $filepath,
        PATHINFO_EXTENSION
      )
    );

  if (
    !in_array(
      $extension,
      $allowed_types[$image_type],
      true
    )
  ) {
    return false;
  }


  $dirpath =
    dirname($filepath);

  if (
    !is_dir($dirpath) &&
    !mkdir(
      $dirpath,
      0755,
      true
    )
  ) {
    return false;
  }


  $written =
    file_put_contents(
      $filepath,
      $decoded,
      LOCK_EX
    );

  if (
    $written === false ||
    $written !== $image_size
  ) {
    return false;
  }


  return true;
}

require_once ("../include/problem.php");

function getLang($language) {  
  $language_name = $GLOBALS['language_name'];
  $language_ext = $GLOBALS['language_ext'];

  for ($i=0; $i<count($language_name); $i++) {
    //echo "$language=$language_name[$i]=".($language==$language_name[$i]);
    // compatibility with other onlinejudge FPS implementation might using extension name as language 

    if ($language==$language_ext[$i]) {
      return $i;
    }

    // HUSTOJ classic using language_name
    if ($language==$language_name[$i]) {
      return $i;
    }
  }
  return $i;
}
function submitSolution(
  $pid,
  $solution,
  $language
) {
  global $OJ_NAME,
    $OJ_UDP,
    $OJ_REDIS,
    $OJ_REDISSERVER,
    $OJ_REDISPORT,
    $OJ_REDISAUTH,
    $OJ_REDISQNAME,
    $_SESSION;

  $language =
    getLang($language);
  $len = mb_strlen($solution,'utf-8');
  $user_id=$_SESSION[$OJ_NAME.'_'.'user_id'];
  $sql = "SELECT nick FROM users WHERE user_id=?";
  $nick = pdo_query($sql, $user_id);
  if ($nick) {
    $nick = $nick[0][0];
  }
  else {
    $nick = "Guest";
  }

  $sql = "INSERT INTO solution(problem_id,user_id,nick,in_date,language,ip,code_length,result) VALUES(?,?,?,NOW(),?,'127.0.0.1',?,14)";
  $insert_id = pdo_query($sql, $pid,$_SESSION[$OJ_NAME.'_'.'user_id'],$nick, $language, $len);
//  echo "submiting$language.....$insert_id";

  $sql = "INSERT INTO `source_code`(`solution_id`,`source`) VALUES(?,?)";
  pdo_query($sql ,$insert_id, $solution);

  $sql = "INSERT INTO source_code_user
  (
      solution_id,
      source,
      source_version
  )
  VALUES
  (
      ?,
      ?,
      1
  )";
  pdo_query($sql, $insert_id, $solution);
  // 정상 제출과 동일하게 채점 대기 상태로 변경
  pdo_query(
    "UPDATE solution
   SET result=0
   WHERE solution_id=?",
    $insert_id
  );

  pdo_query(
    "UPDATE problem
   SET submit=submit+1
   WHERE problem_id=?",
    $pid
  );


  // ============================================================
  // Redis 채점 큐
  // ============================================================

  if (
    isset($OJ_REDIS) &&
    $OJ_REDIS
  ) {

    $redis =
      new Redis();

    $redis->connect(
      $OJ_REDISSERVER,
      $OJ_REDISPORT
    );

    if (
      isset($OJ_REDISAUTH) &&
      $OJ_REDISAUTH !== ''
    ) {
      $redis->auth(
        $OJ_REDISAUTH
      );
    }

    $redis->lpush(
      $OJ_REDISQNAME,
      $insert_id
    );

    $redis->close();
  }


  // ============================================================
  // UDP 채점 트리거
  // ============================================================

  if (
    isset($OJ_UDP) &&
    $OJ_UDP
  ) {
    trigger_judge(
      $insert_id
    );
  }
}

function getValue($Node, $TagName) {
  return $Node->$TagName;
}

function getAttribute($Node, $TagName,$attribute) {
  return $Node->children()->$TagName->attributes()->$attribute;
}

function hasProblem($title) {
  //return false;	
  $md5 = md5($title);
  $sql = "SELECT 1 FROM problem WHERE md5(title)=?";  
  $result = pdo_query($sql, $md5);
  $rows_cnt = count($result);		
  //echo "row->$rows_cnt";			
  return ($rows_cnt>0);
}

function mkpta($pid,$prepends,$node) {
  $language_ext = $GLOBALS['language_ext'];
  $OJ_DATA = $GLOBALS['OJ_DATA'];

  foreach ($prepends as $prepend) {
    $language = $prepend->attributes()->language;
    $lang = getLang($language);
    $file_ext = $language_ext[$lang];
    $basedir = "$OJ_DATA/$pid";
    $file_name = "$basedir/$node.$file_ext";
    file_put_contents($file_name,$prepend);
  }
}

function get_extension($file) {
  $info = pathinfo($file);
  return $info['extension'];
}

function normalize_test_name(
  $name,
  $fallback
) {
  $name =
    trim(
      (string)$name
    );

  if ($name === '') {
    return (string)$fallback;
  }

  $safe_name =
    preg_replace(
      '/[^A-Za-z0-9._-]/',
      '_',
      $name
    );

  if ($safe_name === null) {
    return false;
  }

  $safe_name =
    trim(
      $safe_name,
      '.'
    );

  if ($safe_name === '') {
    return (string)$fallback;
  }

  return $safe_name;
}

function import_fps($tempfile)
{
  global $OJ_NAME,
    $OJ_DATA,
    $OJ_SAE,
    $domain,
    $DOMAIN;

  $import_success = 0;
  $import_duplicate = 0;
  $import_failed = 0;

  $previous_internal_errors =
    libxml_use_internal_errors(true);

  libxml_clear_errors();

  $xmlDoc =
    simplexml_load_file(
      $tempfile,
      'SimpleXMLElement',
      LIBXML_NONET
    );

  libxml_clear_errors();

  libxml_use_internal_errors(
    $previous_internal_errors
  );

  if ($xmlDoc === false) {
    http_response_code(400);

    exit('올바른 FPS/XML 파일이 아닙니다.');
  }

  $searchNodes =
    $xmlDoc->xpath("/fps/item");

  if ($searchNodes === false) {
    http_response_code(400);

    exit('FPS/XML 구조를 확인하지 못했습니다.');
  }
  $spid = 0;
  
  foreach ($searchNodes as $searchNode) {
    //echo $searchNode->title,"\n";

    $title = $searchNode->title;

    $time_limit = $searchNode->time_limit;
    $unit = getAttribute($searchNode,'time_limit','unit');
    //echo $unit;

    if ($unit=='ms')
      $time_limit /= 1000;

    $memory_limit = getValue($searchNode,'memory_limit');
    $unit = getAttribute($searchNode,'memory_limit','unit');

    if ($unit=='kb')
      $memory_limit /= 1024;

    $description = getValue($searchNode,'description');
    $input = getValue($searchNode,'input');
    $output = getValue($searchNode,'output');
    $sample_input = getValue($searchNode,'sample_input');
    $sample_output = getValue($searchNode,'sample_output');
    //$test_input = getValue($searchNode,'test_input');
    //$test_output = getValue($searchNode,'test_output');
    $hint = getValue ($searchNode,'hint');
    $source = getValue ($searchNode,'source');				
    $creator = getValue($searchNode, 'creator'); // ✅ 추가

    $front_code = getValue ($searchNode,'front_code');				
    $rear_code = getValue ($searchNode,'rear_code');				
    $ban_code = getValue ($searchNode,'ban_code');				
    $pro_point = getValue ($searchNode,'pro_point');				

    $spjcode = getValue ($searchNode,'spj');
    if($spjcode) $spjlang=getAttribute($searchNode,'spj','language');
    $tpjcode = getValue ($searchNode,'tpj');
    if($tpjcode) $tpjlang=getAttribute($searchNode,'tpj','language');
    $spj = trim($spjcode.$tpjcode)?1:0;

    if (!hasProblem($title)) {
      $pid = addproblem(
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
        $pro_point
      );

      if (
        $pid === false ||
        intval($pid) <= 0
      ) {
        echo '<br>&nbsp;&nbsp;- 문제 생성 실패: ' .
          htmlspecialchars(
            (string)$title,
            ENT_QUOTES,
            'UTF-8'
          );

        continue;
      }

      $pid =
        intval($pid);

      $sql =
        "INSERT INTO `privilege`
         (`user_id`, `rightstr`)
         VALUES (?, ?)";

      $privilege_user =
        trim((string)$creator);

      if ($privilege_user === '') {
        $privilege_user =
          isset($_SESSION[$OJ_NAME . '_user_id'])
          ? (string)$_SESSION[$OJ_NAME . '_user_id']
          : '';
      }

      if ($privilege_user === '') {
        echo '<br>&nbsp;&nbsp;- 권한 등록 실패: 사용자 정보를 확인할 수 없습니다.';

        continue;
      }

      $privilege_result =
        pdo_query(
          $sql,
          $privilege_user,
          "p" . $pid
        );

      if ($privilege_result === false) {
        error_log(
          '[problem_import] problem_id=' .
            $pid .
            ' privilege 등록 실패'
        );

        echo '<br>&nbsp;&nbsp;- 경고: 문제는 생성되었지만 관리 권한 등록에 실패했습니다.';
      }


      if ($spid == 0) {
        $spid = $pid;
      }


      $basedir =
        rtrim($OJ_DATA, '/\\') .
        '/' .
        $pid;

      if (
        !is_dir($basedir) &&
        !mkdir(
          $basedir,
          0770,
          true
        ) &&
        !is_dir($basedir)
      ) {
        error_log(
          '[problem_import] problem_id=' .
            $pid .
            ' 데이터 디렉터리 생성 실패: ' .
            $basedir
        );

        echo '<br>&nbsp;&nbsp;- 문제 데이터 디렉터리를 생성하지 못했습니다: ' .
          intval($pid);

        continue;
      }

      // ========================================================
      // 샘플 데이터 저장
      // ========================================================

      $data_write_failed = false;

      if (
        strlen((string)$sample_input) > 0 &&
        !mkdata(
          $pid,
          "sample.in",
          $sample_input,
          $OJ_DATA
        )
      ) {
        error_log(
          '[problem_import] problem_id=' .
            $pid .
            ' sample.in 저장 실패'
        );

        $data_write_failed = true;
      }

      if (
        strlen((string)$sample_output) > 0 &&
        !mkdata(
          $pid,
          "sample.out",
          $sample_output,
          $OJ_DATA
        )
      ) {
        error_log(
          '[problem_import] problem_id=' .
            $pid .
            ' sample.out 저장 실패'
        );

        $data_write_failed = true;
      }

      // ========================================================
      // 테스트 입력/출력 개수 검증
      // ========================================================

      $testinputs =
        $searchNode->children()->test_input;

      $testoutputs =
        $searchNode->children()->test_output;

      $test_input_count =
        count($testinputs);

      $test_output_count =
        count($testoutputs);

      if (
        $test_input_count !==
        $test_output_count
      ) {
        error_log(
          '[problem_import] problem_id=' .
            $pid .
            ' 테스트 데이터 개수 불일치: input=' .
            $test_input_count .
            ', output=' .
            $test_output_count
        );

        echo '<br>&nbsp;&nbsp;- 경고: 문제 ' .
          intval($pid) .
          '의 테스트 입력/출력 개수가 일치하지 않습니다.';

        continue;
      }

      // ========================================================
      // 테스트 입력/출력 name 정합성 검사
      // ========================================================

      $used_test_names =
        array();

      for (
        $test_index = 0;
        $test_index < $test_input_count;
        $test_index++
      ) {

        $input_name =
          trim(
            (string)$testinputs[$test_index]['name']
          );

        $output_name =
          trim(
            (string)$testoutputs[$test_index]['name']
          );


        // 한쪽만 name이 있으면 잘못된 데이터로 본다.
        if (
          ($input_name === '') !==
          ($output_name === '')
        ) {
          error_log(
            '[problem_import] problem_id=' .
              $pid .
              ' 테스트 name 불일치: index=' .
              $test_index
          );

          echo '<br>&nbsp;&nbsp;- 경고: 문제 ' .
            intval($pid) .
            '의 테스트 입력/출력 이름이 일치하지 않습니다.';

          continue 2;
        }


        $fallback_name =
          'test' . $test_index;

        $safe_input_name =
          normalize_test_name(
            $input_name,
            $fallback_name
          );

        $safe_output_name =
          normalize_test_name(
            $output_name,
            $fallback_name
          );

        if (
          $safe_input_name === false ||
          $safe_output_name === false ||
          $safe_input_name !== $safe_output_name
        ) {
          error_log(
            '[problem_import] problem_id=' .
              $pid .
              ' 테스트 name 불일치: index=' .
              $test_index
          );

          echo '<br>&nbsp;&nbsp;- 경고: 문제 ' .
            intval($pid) .
            '의 테스트 입력/출력 이름이 일치하지 않습니다.';

          continue 2;
        }

        $safe_name =
          $safe_input_name;


        // 정규화 후 같은 이름이 겹치면 파일이 덮어써질 수 있으므로 차단
        if (isset($used_test_names[$safe_name])) {

          error_log(
            '[problem_import] problem_id=' .
              $pid .
              ' 중복 테스트 이름: ' .
              $safe_name
          );

          echo '<br>&nbsp;&nbsp;- 경고: 문제 ' .
            intval($pid) .
            '에 중복된 테스트 데이터 이름이 있습니다.';

          continue 2;
        }

        $used_test_names[$safe_name] =
          true;
      }

      // ========================================================
      // 테스트 입력 저장
      // ========================================================


      $testno = 0;

      foreach ($testinputs as $testNode) {

        $safe_name =
          normalize_test_name(
            (string)$testNode['name'],
            'test' . $testno
          );

        if ($safe_name === false) {
          $data_write_failed = true;
          break;
        }

        $filename =
          $safe_name . '.in';


        if (
          !mkdata(
            $pid,
            $filename,
            $testNode,
            $OJ_DATA
          )
        ) {
          error_log(
            '[problem_import] problem_id=' .
              $pid .
              ' ' .
              $filename .
              ' 저장 실패'
          );

          $data_write_failed = true;
          break;
        }

        $testno++;
      }

      unset($testinputs);


      // ========================================================
      // 테스트 출력 저장
      // ========================================================

      if (!$data_write_failed) {


        $testno = 0;

        foreach ($testoutputs as $testNode) {

          $safe_name =
            normalize_test_name(
              (string)$testNode['name'],
              'test' . $testno
            );

          if ($safe_name === false) {
            $data_write_failed = true;
            break;
          }

          $filename =
            $safe_name . '.out';


          if (
            !mkdata(
              $pid,
              $filename,
              $testNode,
              $OJ_DATA
            )
          ) {
            error_log(
              '[problem_import] problem_id=' .
                $pid .
                ' ' .
                $filename .
                ' 저장 실패'
            );

            $data_write_failed = true;
            break;
          }

          $testno++;
        }

        unset($testoutputs);
      }


      if ($data_write_failed) {

        echo '<br>&nbsp;&nbsp;- 경고: 문제 ' .
          intval($pid) .
          '의 테스트 데이터 저장에 실패했습니다.';

        continue;
      }


      $images =
        $searchNode->children()->img;
      $did = array();
      $testno = 0;

      foreach ($images as $img) {
      //	
        $src = getValue($img,"src");

        if (!in_array($src,$did)) {
          $base64 = getValue($img,"base64");
          $ext = pathinfo($src);
          $ext = strtolower($ext['extension']);

          $allowed_image_extensions =
            array(
              'jpeg',
              'jpg',
              'png',
              'gif',
              'bmp'
            );

          if (
            !in_array(
              $ext,
              $allowed_image_extensions,
              true
            )
          ) {
            http_response_code(400);

            exit('지원하지 않는 문제 이미지 형식입니다.');
          }

          $testno++;
          $ymd =
            $domain . "/" . date("Ymd");

          $save_path =
            $ymd . "/";

          // 새 파일명
          $new_file_name =
            date("YmdHis") .
            '_' .
            rand(10000, 99999) .
            '.' .
            $ext;

          $newpath =
            $save_path .
            $pid .
            "_" .
            $testno .
            "_" .
            $new_file_name;
        if ($OJ_SAE)
            $newpath = "saestor://web/upload/".$newpath;
        else
            $newpath="../upload/".$newpath;

          $image_saved =
            image_save_file(
              $newpath,
              $base64
            );

          if ($image_saved === false) {
            http_response_code(400);

            exit('문제 이미지 파일을 저장하지 못했습니다.');
          }

          $sql =
            "UPDATE problem
              SET description=replace(description,?,?)
              WHERE problem_id=?";

          pdo_query($sql,$src,$newpath,$pid);

          $sql = "UPDATE problem SET input=replace(input,?,?) WHERE problem_id=?";  
          pdo_query($sql,$src,$newpath,$pid);

          $sql = "UPDATE problem SET output=replace(output,?,?) WHERE problem_id=?";  
          pdo_query($sql,$src,$newpath,$pid);

          $sql = "UPDATE problem SET hint=replace(hint,?,?) WHERE problem_id=?";  
          pdo_query($sql,$src,$newpath,$pid);
          array_push($did,$src);
        }
      }

      if (!isset($OJ_SAE) || !$OJ_SAE) {
        if ($spj) {
		if($spjcode){
		  if($spjlang=="C++"){
			  $basedir = "$OJ_DATA/$pid";
			  $fp = fopen("$basedir/spj.cc","w");
			  fputs($fp, $spjcode);
			  fclose($fp);
			  ////system( " g++ -o $basedir/spj $basedir/spj.cc  ");
		  }else{
			    $fp = fopen("$basedir/spj.c","w");
			    fputs($fp, $spjcode);
			    fclose($fp);
			    ////system( " gcc -o $basedir/spj $basedir/spj.c  ");

		  }
		    if (!file_exists("$basedir/spj")) {
		      echo "you need to compile $basedir/spj.cc for spj[  g++ -o $basedir/spj $basedir/spj.cc   ]<br> and rejudge $pid";
		    }
		    else {
		      //unlink("$basedir/spj.cc");
		    }
  
		}
          $basedir = "$OJ_DATA/$pid";
	  if($tpjcode){
		  if($tpjlang=="C++"){
			  $fp = fopen("$basedir/tpj.cc","w");
			  fputs($fp, $tpjcode);
			  fclose($fp);
			  ////system( " g++ -o $basedir/spj $basedir/spj.cc  ");
		  }else{
			    $fp = fopen("$basedir/tpj.c","w");
			    fputs($fp, $spjcode);
			    fclose($fp);
			    ////system( " gcc -o $basedir/spj $basedir/spj.c  ");
		  }
	    if (!file_exists("$basedir/tpj")) {
	      echo "you need to compile $basedir/tpj.cc for tpj[  g++ -o $basedir/tpj $basedir/tpj.cc   ]<br> and rejudge $pid";
	    }
	    else {
	      //unlink("$basedir/spj.cc");
	    }
		  
	  }
        }
      }

      $solutions = $searchNode->children()->solution;

      foreach ($solutions as $solution) {
        $language = $solution->attributes()->language;
        submitSolution($pid,$solution,$language);
      }
      unset($solutions);

      $prepends = $searchNode->children()->prepend;
      mkpta($pid,$prepends,"prepend");

      $prepends = $searchNode->children()->template;
      mkpta($pid,$prepends,"template");

      $prepends = $searchNode->children()->append;
      mkpta($pid, $prepends, "append");

      $import_success++;
    } else {

      $import_duplicate++;

      echo '<br>&nbsp;&nbsp;- 이미 존재하는 문제로 건너뜀: ' .
        htmlspecialchars(
          (string)$title,
          ENT_QUOTES,
          'UTF-8'
        );
    }
  }

  unlink($tempfile);


  if ($spid>0) {
    require_once("../include/set_get_key.php");
  }
}


if ($uploaded_extension === 'zip') {

  echo "&nbsp;&nbsp;- ZIP 파일을 처리합니다.<br>";


  $zip =
    new ZipArchive();

  $zip_open_result =
    $zip->open($tempfile);

  if ($zip_open_result !== true) {
    http_response_code(400);

    exit('ZIP 파일을 열 수 없습니다.');
  }


  // ==========================================================
  // ZIP 내부 제한
  // ==========================================================

  $max_zip_entries =
    100;

  $max_zip_entry_size =
    15 * 1024 * 1024;

  $max_zip_total_size =
    30 * 1024 * 1024;


  if ($zip->numFiles > $max_zip_entries) {
    $zip->close();

    http_response_code(400);

    exit('ZIP 내부 파일 수가 너무 많습니다.');
  }


  $zip_total_size = 0;
  $imported_entries = 0;


  for (
    $i = 0;
    $i < $zip->numFiles;
    $i++
  ) {

    $entry =
      $zip->statIndex($i);

    if (
      $entry === false ||
      !isset($entry['name']) ||
      !isset($entry['size'])
    ) {
      $zip->close();

      http_response_code(400);

      exit('ZIP 내부 파일 정보를 확인하지 못했습니다.');
    }


    $entry_name =
      (string)$entry['name'];

    $entry_name =
      str_replace(
        '\\',
        '/',
        $entry_name
      );


    // 디렉터리는 건너뛴다.
    if (
      $entry_name === '' ||
      substr($entry_name, -1) === '/'
    ) {
      continue;
    }


    // 루트 디렉터리 파일만 처리한다.
    if (
      strpos(
        $entry_name,
        '/'
      ) !== false
    ) {
      continue;
    }


    // XML/FPS 파일만 처리한다.
    $entry_extension =
      strtolower(
        pathinfo(
          $entry_name,
          PATHINFO_EXTENSION
        )
      );

    if (
      !in_array(
        $entry_extension,
        array('xml', 'fps'),
        true
      )
    ) {
      continue;
    }


    $file_size =
      intval($entry['size']);

    if (
      $file_size <= 0 ||
      $file_size > $max_zip_entry_size
    ) {
      $zip->close();

      http_response_code(400);

      exit('ZIP 내부 파일 크기가 허용 범위를 초과합니다.');
    }


    $zip_total_size +=
      $file_size;

    if (
      $zip_total_size >
      $max_zip_total_size
    ) {
      $zip->close();

      http_response_code(400);

      exit('ZIP 압축 해제 전체 크기가 너무 큽니다.');
    }


    $file_content =
      $zip->getFromIndex($i);

    if (
      $file_content === false ||
      strlen($file_content) !== $file_size
    ) {
      $zip->close();

      http_response_code(400);

      exit('ZIP 내부 파일을 읽지 못했습니다.');
    }


    $entry_tempfile =
      tempnam(
        '/tmp',
        'fps'
      );

    if ($entry_tempfile === false) {
      $zip->close();

      http_response_code(500);

      exit('임시 파일을 생성하지 못했습니다.');
    }


    $written =
      file_put_contents(
        $entry_tempfile,
        $file_content
      );

    if (
      $written === false ||
      $written !== strlen($file_content)
    ) {
      @unlink($entry_tempfile);

      $zip->close();

      http_response_code(500);

      exit('임시 파일 저장에 실패했습니다.');
    }


    import_fps(
      $entry_tempfile
    );

    // import_fps()에서 삭제하지만
    // 남아 있는 경우에도 정리한다.
    if (file_exists($entry_tempfile)) {
      unlink($entry_tempfile);
    }

    $imported_entries++;
  }


  $zip->close();


  if ($imported_entries === 0) {
    http_response_code(400);

    exit('ZIP 루트에 가져올 XML/FPS 파일이 없습니다.');
  }
} else {

  import_fps(
    $tempfile
  );
}



?>