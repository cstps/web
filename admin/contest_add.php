<?php
header("Cache-control:private");
?>
<html>

<head>
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Cache-Control" content="no-cache">
  <meta http-equiv="Content-Language" content="zh-cn">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <link rel="stylesheet" href="contest-form.css">
  <title>Contest Add</title>
</head>
<hr>

<?php
require_once("../include/db_info.inc.php");
require_once("../lang/$OJ_LANG.php");
require_once("../include/const.inc.php");
require_once("admin-header.php");
if (!(isset($_SESSION[$OJ_NAME . '_' . 'administrator']) || isset($_SESSION[$OJ_NAME . '_' . 'contest_creator']))) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}
echo "<center><h3>" . $MSG_CONTEST . "-" . $MSG_ADD . "</h3></center>";
include_once("kindeditor.php");
?>

<body leftmargin="30">
  <?php
  $description = "";
  if (isset($_POST['startdate'])) {
    require_once("../include/check_post_key.php");

    $starttime = $_POST['startdate'] . " " . intval($_POST['shour']) . ":" . intval($_POST['sminute']) . ":00";
    $endtime = $_POST['enddate'] . " " . intval($_POST['ehour']) . ":" . intval($_POST['eminute']) . ":00";

    $title = $_POST['title'];
    $codevisible = $_POST['codevisible'];
    $private = isset($_POST['private']) ? intval($_POST['private']) : 0;
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // 비공개 대회는 참가자 권한(c{cid})만 사용한다.
    // 비밀번호와 참가자 권한이 혼동되지 않도록 비밀번호는 저장하지 않는다.
    if ($private === 1) {
      $password = '';
    }

    $description = $_POST['description'];
    $exam_mode = isset($_POST['exam_mode']) ? intval($_POST['exam_mode']) : 0;

    $allow_copy =
      isset($_POST['allow_copy'])
      ? intval($_POST['allow_copy'])
      : 1;

    if (!in_array($allow_copy, array(0, 1), true)) {
      $allow_copy = 1;
    }

    // ============================================================
    // 제출 가능 언어 검증
    //
    // 새 대회 생성 시 최소 1개 이상의 언어를 반드시 선택한다.
    // HUSTOJ langmask:
    //
    // bit = 0 : 허용
    // bit = 1 : 금지
    // ============================================================

    $lang =
      isset($_POST['lang']) &&
      is_array($_POST['lang'])
      ? $_POST['lang']
      : array();


    $lang_count =
      count($language_ext);


    $selected_language_ids =
      array();

    $selected_language_map =
      array();


    foreach ($lang as $language_id_raw) {

      if (
        !is_scalar($language_id_raw) ||
        filter_var(
          $language_id_raw,
          FILTER_VALIDATE_INT
        ) === false
      ) {

        echo "제출 언어 정보가 올바르지 않습니다.";
        exit(1);
      }


      $language_id =
        intval($language_id_raw);


      if (
        $language_id < 0 ||
        $language_id >= $lang_count
      ) {

        echo "존재하지 않는 제출 언어입니다.";
        exit(1);
      }


      if (
        isset(
          $selected_language_map[$language_id]
        )
      ) {
        continue;
      }


      $selected_language_map[$language_id] = true;

      $selected_language_ids[] =
        $language_id;
    }


    if (
      empty($selected_language_ids)
    ) {

      echo "제출 가능 언어를 하나 이상 선택하세요.";
      exit(1);
    }


    $allowed_language_mask = 0;


    foreach (
      $selected_language_ids
      as $language_id
    ) {

      $allowed_language_mask |=
        (1 << $language_id);
    }


    $all_language_mask =
      (1 << $lang_count) - 1;


    $langmask =
      $all_language_mask &
      (~$allowed_language_mask);


    $sql = "INSERT INTO `contest`
  (`title`,`start_time`,`end_time`,`codevisible`,`private`,`langmask`,`description`,`password`,`user_id`,`exam_mode`,`allow_copy`)
  VALUES(?,?,?,?,?,?,?,?,?,?,?)";

    $description = str_replace("<p>", "", $description);
    $description = str_replace("</p>", "<br />", $description);
    $description = str_replace(",", "&#44; ", $description);


    $user_id =
      $_SESSION[$OJ_NAME . '_user_id'];

    $is_admin =
      isset(
        $_SESSION[$OJ_NAME . '_administrator']
      );


    // ============================================================
    // 문제 ID 정리
    // ============================================================

    $plist =
      isset($_POST['cproblem'])
      ? trim($_POST['cproblem'])
      : '';

    $pieces =
      array_filter(
        array_map(
          'trim',
          explode(',', $plist)
        ),
        function ($value) {
          return $value !== '';
        }
      );


    // 중복 문제 제거
    $problem_ids = array();

    foreach ($pieces as $value) {

      $problem_id =
        intval($value);

      if (
        $problem_id > 0 &&
        !in_array(
          $problem_id,
          $problem_ids,
          true
        )
      ) {
        $problem_ids[] =
          $problem_id;
      }
    }


    // ============================================================
    // Problem allow_reuse 검증
    //
    // administrator
    // → 항상 사용 가능
    //
    // 문제 생성자
    // → allow_reuse=0이어도 사용 가능
    //
    // 다른 사용자
    // → 공개(defunct=N) + allow_reuse=1인 문제만 사용 가능
    // ============================================================

    foreach ($problem_ids as $problem_id) {

      $problem_rows =
        pdo_query(
          "SELECT
                p.problem_id,
                p.defunct,
                p.allow_reuse,

                EXISTS (
                    SELECT 1
                    FROM privilege pr
                    WHERE pr.user_id = ?
                      AND pr.rightstr =
                          CONCAT(
                              'p',
                              p.problem_id
                          )
                      AND pr.defunct = 'N'
                ) AS is_owner

             FROM problem p

             WHERE p.problem_id = ?

             LIMIT 1",
          $user_id,
          $problem_id
        );


      // 존재하지 않는 문제
      if (
        !$problem_rows ||
        !isset(
          $problem_rows[0]['problem_id']
        )
      ) {

        echo
        "존재하지 않는 문제입니다: " .
          intval($problem_id);

        exit(1);
      }


      $problem =
        $problem_rows[0];

      $is_owner =
        intval(
          $problem['is_owner']
        ) === 1;

      $is_public =
        strtoupper(
          trim(
            $problem['defunct']
          )
        ) === 'N';

      $allow_reuse =
        intval(
          $problem['allow_reuse']
        ) === 1;


      $can_use_problem =
        $is_admin ||
        $is_owner ||
        (
          $is_public &&
          $allow_reuse
        );


      if (!$can_use_problem) {

        if (
          !$is_owner &&
          !$allow_reuse
        ) {

          echo
          "문제 " .
            intval($problem_id) .
            "번은 문제 생성자가 다른 대회에서의 " .
            "사용을 허용하지 않았습니다.";
        } else {

          echo
          "문제 " .
            intval($problem_id) .
            "번을 이 대회에 사용할 권한이 없습니다.";
        }

        exit(1);
      }
    }

    $cid = pdo_query(
      $sql,
      $title,
      $starttime,
      $endtime,
      $codevisible,
      $private,
      $langmask,
      $description,
      $password,
      $user_id,
      $exam_mode,
      $allow_copy
    );

    echo "Add Contest " . $cid;

    $sql = "DELETE FROM `contest_problem` WHERE `contest_id`=$cid";
    pdo_query($sql);

    if (count($problem_ids) > 0) {
      $cpoints = isset($_POST['cpoint']) ? $_POST['cpoint'] : [];

      $sql_1 = "INSERT INTO `contest_problem`(`contest_id`,`problem_id`,`num`, `score`) VALUES (?,?,?,?)";
      $plist_join = "";
      $pid = 0;

      for (
        $i = 0;
        $i < count($problem_ids);
        $i++
      ) {

        $problem_id =
          $problem_ids[$i];
        $score =
          (
            isset(
              $cpoints[$problem_id]
            ) &&
            $cpoints[$problem_id] !== '' &&
            is_numeric(
              $cpoints[$problem_id]
            )
          )
          ? intval(
            $cpoints[$problem_id]
          )
          : 100;

        if ($plist_join) {
          $plist_join .= ",";
        }

        $plist_join .=
          $problem_id;

        pdo_query(
          $sql_1,
          $cid,
          $problem_id,
          $pid,
          $score
        );

        $pid++;
      }
      // 기본 공개/비공개 유지 (이전 주석 유지)
      // $sql = "UPDATE `problem` SET defunct='N' WHERE `problem_id` IN ($plist_join)";
      // pdo_query($sql) ;
    }

    $sql = "DELETE FROM `privilege` WHERE `rightstr`=?";
    pdo_query($sql, "c$cid");

    $sql = "INSERT INTO `privilege` (`user_id`,`rightstr`) VALUES(?,?)";
    pdo_query($sql, $_SESSION[$OJ_NAME . '_' . 'user_id'], "m$cid");

    $_SESSION[$OJ_NAME . '_' . "m$cid"] = true;
    $pieces = explode("\n", trim($_POST['ulist']));

    if (count($pieces) > 0 && strlen($pieces[0]) > 0) {
      $sql_1 = "INSERT INTO `privilege`(`user_id`,`rightstr`) VALUES (?,?)";
      for ($i = 0; $i < count($pieces); $i++) {
        $uid = trim($pieces[$i]);
        if ($uid !== '') {
          pdo_query($sql_1, $uid, "c$cid");
        }
      }
    }
    echo "<script>window.location.href=\"contest_list.php\";</script>";
  } else {
    // ===== 복사(기존 cid로 열기) 또는 기타 모드 =====
    $score_prefill = []; // pid => score (복사 시 점수 사전채움용)
    if (isset($_GET['cid'])) {
      $cid = intval($_GET['cid']);
      $sql = "SELECT * FROM contest WHERE `contest_id`=?";
      $result = pdo_query($sql, $cid);
      $row = $result[0];

      if (
        !$row ||
        !isset($row['contest_id'])
      ) {
        echo "<h3>존재하지 않는 대회입니다.</h3>";
        exit(1);
      }

      $current_user_id =
        $_SESSION[$OJ_NAME . '_user_id'];

      $is_admin =
        isset($_SESSION[$OJ_NAME . '_administrator']);

      $is_owner =
        isset($row['user_id']) &&
        trim($row['user_id']) === $current_user_id;

      $allow_copy =
        isset($row['allow_copy'])
        ? intval($row['allow_copy'])
        : 1;


      if (
        !$is_admin &&
        !$is_owner &&
        $allow_copy !== 1
      ) {

        echo "<h3>이 대회는 생성자가 복사를 허용하지 않았습니다.</h3>";
        exit(1);
      }

      $title = $row['title'];

      $codevisible = $row['codevisible'];
      $private = $row['private'];
      $langmask = $row['langmask'];
      $description = $row['description'];
      $starttime = $row['start_time'];
      $endtime = $row['end_time'];
      $exam_mode = $row['exam_mode'];

      $allow_copy =
        isset($row['allow_copy'])
        ? intval($row['allow_copy'])
        : 1;

      // 문제ID와 점수를 동시에 로드
      $plist = "";
      $sql = "SELECT `problem_id`, `score` FROM `contest_problem` WHERE `contest_id`=? ORDER BY `num`";
      $result = pdo_query($sql, $cid);
      foreach ($result as $row) {
        $pid = $row['problem_id'];
        $score = isset($row['score']) ? intval($row['score']) : 100;

        if ($plist) $plist .= ',';
        $plist .= $pid;

        $score_prefill[$pid] = $score;
      }

      $ulist = "";
      $sql = "SELECT `user_id` FROM `privilege` WHERE `rightstr`=? order by user_id";
      $result = pdo_query($sql, "c$cid");

      foreach ($result as $row) {
        if ($ulist) $ulist .= "\n";
        $ulist .= $row[0];
      }
    } else if (isset($_POST['problem2contest'])) {
      $plist = "";
      sort($_POST['pid']);
      foreach ($_POST['pid'] as $i) {
        if ($plist)
          $plist .= ',' . intval($i);
        else
          $plist = $i;
      }
    } else if (isset($_GET['spid'])) {
      //require_once("../include/check_get_key.php");
      $spid = intval($_GET['spid']);

      $plist = "";
      $sql = "SELECT `problem_id` FROM `problem` WHERE `problem_id`>=? ";
      $result = pdo_query($sql, $spid);
      foreach ($result as $row) {
        if ($plist) $plist .= ',';
        $plist .= $row[0];
      }
    }

    // ============================================================
    // 문제 선택기 초기값
    //
    // 대회 복사 / 문제 목록에서 새 대회 만들기 등의 경우
    // 기존 $plist 문제를 선택된 상태로 표시한다.
    // ============================================================

    $initial_selected_problems =
      array();


    if (
      isset($plist) &&
      trim($plist) !== ''
    ) {

      $initial_ids_raw =
        array_filter(
          array_map(
            'trim',
            explode(',', $plist)
          ),
          function ($value) {
            return $value !== '';
          }
        );


      $initial_ids =
        array();

      $initial_id_map =
        array();


      foreach (
        $initial_ids_raw
        as $problem_id_raw
      ) {

        $problem_id =
          intval($problem_id_raw);


        if (
          $problem_id <= 0 ||
          isset(
            $initial_id_map[$problem_id]
          )
        ) {
          continue;
        }


        $initial_id_map[$problem_id] = true;

        $initial_ids[] =
          $problem_id;
      }


      if (
        count($initial_ids) > 0
      ) {

        $placeholders =
          implode(
            ',',
            array_fill(
              0,
              count($initial_ids),
              '?'
            )
          );


        $initial_rows =
          pdo_query(
            "SELECT
                problem_id,
                title,
                source,
                defunct,
                allow_reuse
             FROM problem
             WHERE problem_id IN (
               $placeholders
             )",
            ...$initial_ids
          );


        $initial_row_map =
          array();


        if (
          is_array(
            $initial_rows
          )
        ) {

          foreach (
            $initial_rows
            as $problem
          ) {

            $problem_id =
              intval(
                $problem['problem_id']
              );


            $initial_row_map[$problem_id] =
              $problem;
          }
        }


        // 원래 선택 순서를 그대로 유지
        foreach (
          $initial_ids
          as $problem_id
        ) {

          if (
            !isset(
              $initial_row_map[$problem_id]
            )
          ) {
            continue;
          }


          $problem =
            $initial_row_map[$problem_id];


          $initial_selected_problems[] =
            array(

              'problem_id' =>
              $problem_id,

              'title' =>
              (string)$problem['title'],

              'source' =>
              (string)$problem['source'],

              'defunct' =>
              (string)$problem['defunct'],

              'allow_reuse' =>
              intval(
                $problem['allow_reuse']
              ),

              'score' =>
              isset(
                $score_prefill[$problem_id]
              )
                ? intval(
                  $score_prefill[$problem_id]
                )
                : 100
            );
        }
      }
    }


    include_once("kindeditor.php");
  ?>
    <div class="container contest-add-wrap">
      <form
        method="POST"
        id="contest-add-form">

        <!-- ====================================================
           1. 기본 정보
           ==================================================== -->
        <div class="contest-add-card">

          <h4>1. 기본 정보</h4>

          <div class="contest-add-field">
            <label><?php echo $MSG_CONTEST . "-" . $MSG_TITLE; ?></label>
            <input
              class="input input-xxlarge"
              type="text"
              name="title"
              value="<?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : ''; ?>"
              required>
          </div>

          <div class="contest-add-grid" style="margin-top:14px;">

            <div class="contest-add-field">
              <label><?php echo $MSG_CONTEST . $MSG_Start; ?></label>

              <div class="contest-add-inline-time">

                <input
                  class="input-large"
                  type="date"
                  name="startdate"
                  value="<?php
                          if (isset($_GET['cid'])) {
                            echo date('Y-m-d', strtotime($starttime));
                          } else {
                            echo date('Y-m-d');
                          }
                          ?>"
                  required>

                <input
                  class="input-mini"
                  type="number"
                  name="shour"
                  min="0"
                  max="23"
                  value="<?php
                          if (isset($_GET['cid'])) {
                            echo date('H', strtotime($starttime));
                          } else {
                            echo date('H');
                          }
                          ?>"
                  title="시"
                  required>

                <input
                  class="input-mini"
                  type="number"
                  name="sminute"
                  min="0"
                  max="59"
                  value="<?php
                          if (isset($_GET['cid'])) {
                            echo date('i', strtotime($starttime));
                          } else {
                            echo '00';
                          }
                          ?>"
                  title="분"
                  required>

              </div>
            </div>

            <div class="contest-add-field">
              <label><?php echo $MSG_CONTEST . $MSG_End; ?></label>

              <div class="contest-add-inline-time">

                <input
                  class="input-large"
                  type="date"
                  name="enddate"
                  value="<?php
                          if (isset($_GET['cid'])) {
                            echo date('Y-m-d', strtotime($endtime));
                          } else {
                            echo date('Y-m-d');
                          }
                          ?>"
                  required>

                <input
                  class="input-mini"
                  type="number"
                  name="ehour"
                  min="0"
                  max="23"
                  value="<?php
                          if (isset($_GET['cid'])) {
                            echo date('H', strtotime($endtime));
                          } else {
                            echo (date('H') + 4) % 24;
                          }
                          ?>"
                  title="시"
                  required>

                <input
                  class="input-mini"
                  type="number"
                  name="eminute"
                  min="0"
                  max="59"
                  value="<?php
                          if (isset($_GET['cid'])) {
                            echo date('i', strtotime($endtime));
                          } else {
                            echo '00';
                          }
                          ?>"
                  title="분"
                  required>

              </div>
            </div>

          </div>

          <div class="contest-add-field" style="margin-top:14px;">
            <label><?php echo $MSG_CONTEST . "-" . $MSG_Description; ?></label>
            <textarea
              class="kindeditor"
              rows="13"
              name="description"
              cols="80"><?php echo isset($description) ? $description : ''; ?></textarea>
          </div>

        </div>


        <!-- ====================================================
           2. 문제 구성
           ==================================================== -->
        <div class="contest-add-card">

          <h4>2. 문제 구성</h4>


          <input
            type="hidden"
            id="plist"
            name="cproblem"
            value="<?php
                    echo isset($plist)
                      ? htmlspecialchars(
                        $plist,
                        ENT_QUOTES,
                        'UTF-8'
                      )
                      : '';
                    ?>">


          <div class="contest-problem-selector">


            <div class="contest-problem-search">

              <input
                type="text"
                id="problem-search"
                placeholder="문제 번호·제목·출처 검색">

              <button
                type="button"
                id="problem-search-button">
                검색
              </button>

            </div>


            <div class="contest-problem-tabs">

              <button
                type="button"
                class="contest-problem-tab active"
                data-scope="my">
                내가 만든 문제
              </button>

              <button
                type="button"
                class="contest-problem-tab"
                data-scope="available">
                사용 가능한 전체 문제
              </button>

            </div>


            <div class="contest-add-help">
              한 번에 최대 50개까지 표시됩니다.
              검색을 다시 해도 이미 선택한 문제는 유지됩니다.
            </div>


            <div
              id="problem-search-results"
              class="contest-problem-results">
              문제를 불러오는 중입니다.
            </div>


            <div class="contest-selected-header">

              <strong>
                선택한 문제
              </strong>

              <span id="selected-problem-count">
                0개
              </span>

            </div>


            <div
              id="selected-problem-list"
              class="contest-selected-list"></div>

          </div>

        </div>


        <!-- ====================================================
     3. 대회 운영 설정
     ==================================================== -->
        <div class="contest-add-card">

          <h4>3. 대회 운영 설정</h4>

          <div class="contest-setting-grid">


            <!-- 공개 범위 -->
            <div class="contest-setting-box">

              <div class="contest-setting-title">
                공개 범위
              </div>

              <div class="contest-setting-description">
                사용자가 대회에 접근할 수 있는 범위를 설정합니다.
              </div>

              <div class="contest-choice-group">

                <input
                  type="radio"
                  id="private-public"
                  name="private"
                  value="0"
                  <?php
                  echo (
                    !isset($private) ||
                    intval($private) === 0
                  ) ? 'checked' : '';
                  ?>>

                <label for="private-public">
                  공개
                </label>


                <input
                  type="radio"
                  id="private-private"
                  name="private"
                  value="1"
                  <?php
                  echo (
                    isset($private) &&
                    intval($private) === 1
                  ) ? 'checked' : '';
                  ?>>

                <label for="private-private">
                  비공개
                </label>

              </div>

              <div class="contest-add-help">
                비공개 대회는 참가 권한이 있는 사용자만 접근할 수 있습니다.
              </div>

            </div>


            <!-- 제출 코드 공개 -->
            <div class="contest-setting-box">

              <div class="contest-setting-title">
                제출 코드
              </div>

              <div class="contest-setting-description">
                참가자의 제출 코드를 다른 사용자에게 공개할지 설정합니다.
              </div>

              <div class="contest-choice-group">

                <input
                  type="radio"
                  id="codevisible-public"
                  name="codevisible"
                  value="0"
                  <?php
                  echo (
                    !isset($codevisible) ||
                    intval($codevisible) === 0
                  ) ? 'checked' : '';
                  ?>>

                <label for="codevisible-public">
                  공개
                </label>


                <input
                  type="radio"
                  id="codevisible-private"
                  name="codevisible"
                  value="1"
                  <?php
                  echo (
                    isset($codevisible) &&
                    intval($codevisible) === 1
                  ) ? 'checked' : '';
                  ?>>

                <label for="codevisible-private">
                  비공개
                </label>

              </div>

            </div>


            <!-- 시험 모드 -->
            <div class="contest-setting-box">

              <div class="contest-setting-title">
                시험 모드
              </div>

              <div class="contest-setting-description">
                평가용 대회에서는 다른 사용자의 제출 상태 노출을 제한합니다.
              </div>

              <div class="contest-choice-group">

                <input
                  type="radio"
                  id="exam-mode-off"
                  name="exam_mode"
                  value="0"
                  <?php
                  echo (
                    !isset($exam_mode) ||
                    intval($exam_mode) === 0
                  ) ? 'checked' : '';
                  ?>>

                <label for="exam-mode-off">
                  일반
                </label>


                <input
                  type="radio"
                  id="exam-mode-on"
                  name="exam_mode"
                  value="1"
                  <?php
                  echo (
                    isset($exam_mode) &&
                    intval($exam_mode) === 1
                  ) ? 'checked' : '';
                  ?>>

                <label for="exam-mode-on">
                  시험 모드
                </label>

              </div>

            </div>


            <!-- 대회 복사 -->
            <div class="contest-setting-box">

              <div class="contest-setting-title">
                대회 복사
              </div>

              <div class="contest-setting-description">
                다른 Contest Creator가 이 대회의 구성을 가져갈 수 있는지 설정합니다.
              </div>

              <div class="contest-choice-group">

                <input
                  type="radio"
                  id="allow-copy-on"
                  name="allow_copy"
                  value="1"
                  <?php
                  echo (
                    !isset($allow_copy) ||
                    intval($allow_copy) === 1
                  ) ? 'checked' : '';
                  ?>>

                <label for="allow-copy-on">
                  허용
                </label>


                <input
                  type="radio"
                  id="allow-copy-off"
                  name="allow_copy"
                  value="0"
                  <?php
                  echo (
                    isset($allow_copy) &&
                    intval($allow_copy) === 0
                  ) ? 'checked' : '';
                  ?>>

                <label for="allow-copy-off">
                  허용하지 않음
                </label>

              </div>

            </div>

          </div>


          <!-- 공개 대회 비밀번호 -->
          <div
            class="contest-setting-password"
            style="margin-top:16px;">

            <label for="contest-password">
              대회 비밀번호
            </label>

            <input
              id="contest-password"
              type="text"
              name="password"
              value=""
              placeholder="필요한 경우에만 입력">

            <div
              id="contest-password-help"
              class="contest-add-help">
              공개 대회에 비밀번호를 설정하면 비밀번호를 아는 사용자만 접근할 수 있습니다.
            </div>

          </div>

        </div>


        <!-- ====================================================
        4. 사용 언어
        ==================================================== -->

        <div class="contest-add-card">

          <div class="contest-language-header">

            <h4>
              4. 제출 가능 언어
            </h4>

            <span
              id="selected-language-count"
              class="contest-language-count">
              0개 선택
            </span>

          </div>


          <div class="contest-add-help">
            이 대회에서 참가자가 제출할 수 있는 언어를 선택하세요.
            새 대회는 기본적으로 아무 언어도 선택되지 않습니다.
          </div>


          <?php

          $lang_count =
            count($language_ext);


          /*
   * 새 대회
   * → 아무 언어도 선택하지 않음
   *
   * 기존 대회 복사
   * → 원본 Contest의 언어 설정 유지
   */
          $enabled_language_mask = 0;


          if (
            isset($_GET['cid']) &&
            isset($langmask)
          ) {

            $enabled_language_mask =
              (~intval($langmask)) &
              (
                (1 << $lang_count) - 1
              );
          }

          ?>


          <div
            class="contest-language-options"
            id="contest-language-options">

            <?php

            for (
              $i = 0;
              $i < $lang_count;
              $i++
            ) {

              $checked =
                (
                  $enabled_language_mask &
                  (1 << $i)
                )
                ? 'checked'
                : '';

            ?>

              <div class="contest-language-item">

                <input
                  type="checkbox"
                  id="contest-language-<?php echo $i; ?>"
                  name="lang[]"
                  value="<?php echo $i; ?>"
                  <?php echo $checked; ?>>

                <label
                  for="contest-language-<?php echo $i; ?>">
                  <?php
                  echo htmlspecialchars(
                    $language_name[$i],
                    ENT_QUOTES,
                    'UTF-8'
                  );
                  ?>
                </label>

              </div>

            <?php
            }

            ?>

          </div>


          <div
            id="contest-language-error"
            class="contest-language-error"
            style="display:none;">
            제출 가능 언어를 하나 이상 선택하세요.
          </div>

        </div>


        <!-- ====================================================
           5. 참가자 설정
           ==================================================== -->
        <div class="contest-add-card">

          <h4>5. 참가자 설정</h4>

          <div class="contest-add-field">

            <label><?php echo $MSG_CONTEST . "-" . $MSG_USER; ?></label>

            <textarea
              name="ulist"
              rows="10"
              style="width:100%;"
              placeholder="user1&#10;user2&#10;user3"><?php echo isset($ulist) ? htmlspecialchars($ulist, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>

            <div class="contest-add-help">
              비공개 대회의 참가자 아이디를 한 줄에 하나씩 입력하세요.
              이 목록에 등록된 사용자에게만 참가 권한(c{cid})이 부여됩니다.
              공개 대회에서는 참가자 목록을 입력하지 않아도 됩니다.
            </div>

          </div>

        </div>


        <div class="contest-add-save">

          <?php require_once("../include/set_post_key.php"); ?>

          <input
            type="submit"
            value="<?php echo $MSG_SAVE; ?>"
            name="submit">

        </div>

      </form>
    </div>


    <script>
      const INITIAL_SELECTED_PROBLEMS =
        <?php
        echo json_encode(
          $initial_selected_problems,
          JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );
        ?>;


      const selectedProblems =
        new Map();


      let currentProblemScope =
        "my";


      // ============================================================
      // 초기 선택 문제
      // ============================================================

      INITIAL_SELECTED_PROBLEMS.forEach(
        function(problem) {

          const pid =
            String(
              problem.problem_id
            );


          selectedProblems.set(
            pid, {
              problem_id: Number(
                problem.problem_id
              ),

              title: String(
                problem.title || ""
              ),

              source: String(
                problem.source || ""
              ),

              defunct: String(
                problem.defunct || "N"
              ),

              allow_reuse: Number(
                problem.allow_reuse || 0
              ),

              score: (
                  problem.score !== undefined &&
                  problem.score !== null &&
                  problem.score !== ""
                ) ?
                Number(problem.score) : 100
            }
          );
        }
      );


      // ============================================================
      // A / B / C / ... / AA 변환
      // ============================================================

      function problemOrderLabel(index) {

        let number =
          index + 1;

        let result =
          "";


        while (number > 0) {

          number--;

          result =
            String.fromCharCode(
              65 + (number % 26)
            ) +
            result;

          number =
            Math.floor(
              number / 26
            );
        }


        return result;
      }


      // ============================================================
      // hidden cproblem 동기화
      // ============================================================

      function syncProblemListInput() {

        const input =
          document.getElementById(
            "plist"
          );


        if (!input) {
          return;
        }


        input.value =
          Array.from(
            selectedProblems.keys()
          ).join(",");
      }


      // ============================================================
      // 선택 개수
      // ============================================================

      function updateSelectedCount() {

        const count =
          document.getElementById(
            "selected-problem-count"
          );


        if (count) {

          count.textContent =
            selectedProblems.size +
            "개";
        }
      }


      // ============================================================
      // 검색 결과 체크상태 동기화
      // ============================================================

      function synchronizeSearchChecks() {

        document
          .querySelectorAll(
            ".contest-problem-result-check"
          )
          .forEach(
            function(checkbox) {

              checkbox.checked =
                selectedProblems.has(
                  String(
                    checkbox.value
                  )
                );
            }
          );
      }


      // ============================================================
      // 선택 문제 목록 렌더링
      // ============================================================

      function renderSelectedProblems() {

        const container =
          document.getElementById(
            "selected-problem-list"
          );


        if (!container) {
          return;
        }


        container.innerHTML =
          "";


        if (
          selectedProblems.size === 0
        ) {

          const empty =
            document.createElement(
              "div"
            );

          empty.className =
            "contest-problem-empty";

          empty.textContent =
            "선택된 문제가 없습니다.";

          container.appendChild(
            empty
          );

          syncProblemListInput();
          updateSelectedCount();
          synchronizeSearchChecks();

          return;
        }


        let index =
          0;


        selectedProblems.forEach(
          function(
            problem,
            pid
          ) {

            const row =
              document.createElement(
                "div"
              );

            row.className =
              "contest-selected-row";


            // A / B / C
            const order =
              document.createElement(
                "div"
              );

            order.className =
              "contest-selected-order";

            order.textContent =
              problemOrderLabel(
                index
              );

            row.appendChild(
              order
            );


            // 문제 제목
            const titleBox =
              document.createElement(
                "div"
              );

            titleBox.className =
              "contest-selected-title";


            const titleLink =
              document.createElement(
                "a"
              );

            titleLink.href =
              "../problem.php?id=" +
              encodeURIComponent(
                pid
              );

            titleLink.target =
              "_blank";

            titleLink.textContent =
              pid +
              " · " +
              problem.title;


            titleBox.appendChild(
              titleLink
            );


            if (problem.source) {

              const meta =
                document.createElement(
                  "div"
                );

              meta.className =
                "contest-problem-meta";

              meta.textContent =
                problem.source;

              titleBox.appendChild(
                meta
              );
            }


            row.appendChild(
              titleBox
            );


            // 점수
            const scoreBox =
              document.createElement(
                "div"
              );

            scoreBox.className =
              "contest-selected-score";


            const scoreInput =
              document.createElement(
                "input"
              );

            scoreInput.type =
              "number";

            scoreInput.name =
              "cpoint[" +
              pid +
              "]";

            scoreInput.min =
              "0";

            scoreInput.step =
              "1";

            scoreInput.value =
              problem.score;


            scoreInput.addEventListener(
              "input",
              function() {

                problem.score =
                  this.value;
              }
            );


            const scoreLabel =
              document.createElement(
                "span"
              );

            scoreLabel.textContent =
              "점";


            scoreBox.appendChild(
              scoreInput
            );

            scoreBox.appendChild(
              scoreLabel
            );

            row.appendChild(
              scoreBox
            );


            // 삭제
            const removeButton =
              document.createElement(
                "button"
              );

            removeButton.type =
              "button";

            removeButton.className =
              "contest-selected-remove";

            removeButton.textContent =
              "삭제";


            removeButton.addEventListener(
              "click",
              function() {

                selectedProblems.delete(
                  pid
                );

                renderSelectedProblems();
              }
            );


            row.appendChild(
              removeButton
            );


            container.appendChild(
              row
            );


            index++;
          }
        );


        syncProblemListInput();
        updateSelectedCount();
        synchronizeSearchChecks();
      }


      // ============================================================
      // 검색 결과 렌더링
      // ============================================================

      function renderProblemSearchResults(
        problems
      ) {

        const container =
          document.getElementById(
            "problem-search-results"
          );


        if (!container) {
          return;
        }


        container.innerHTML =
          "";


        if (
          !Array.isArray(problems) ||
          problems.length === 0
        ) {

          const empty =
            document.createElement(
              "div"
            );

          empty.className =
            "contest-problem-empty";

          empty.textContent =
            "검색 결과가 없습니다.";

          container.appendChild(
            empty
          );

          return;
        }


        problems.forEach(
          function(problem) {

            const pid =
              String(
                problem.problem_id
              );


            const row =
              document.createElement(
                "div"
              );

            row.className =
              "contest-problem-result";


            const checkbox =
              document.createElement(
                "input"
              );

            checkbox.type =
              "checkbox";

            checkbox.value =
              pid;

            checkbox.className =
              "contest-problem-result-check";

            checkbox.checked =
              selectedProblems.has(
                pid
              );


            checkbox.addEventListener(
              "change",
              function() {

                if (this.checked) {

                  if (
                    !selectedProblems.has(
                      pid
                    )
                  ) {

                    selectedProblems.set(
                      pid, {
                        problem_id: Number(
                          problem.problem_id
                        ),

                        title: String(
                          problem.title || ""
                        ),

                        source: String(
                          problem.source || ""
                        ),

                        defunct: String(
                          problem.defunct || "N"
                        ),

                        allow_reuse: Number(
                          problem.allow_reuse || 0
                        ),

                        score: 100
                      }
                    );
                  }

                } else {

                  selectedProblems.delete(
                    pid
                  );
                }


                renderSelectedProblems();
              }
            );


            row.appendChild(
              checkbox
            );


            const titleBox =
              document.createElement(
                "div"
              );

            titleBox.className =
              "contest-problem-result-title";


            const titleLink =
              document.createElement(
                "a"
              );

            titleLink.href =
              "../problem.php?id=" +
              encodeURIComponent(
                pid
              );

            titleLink.target =
              "_blank";

            titleLink.textContent =
              pid +
              " · " +
              String(
                problem.title || ""
              );


            titleBox.appendChild(
              titleLink
            );


            const meta =
              document.createElement(
                "div"
              );

            meta.className =
              "contest-problem-meta";


            const metaParts = [];


            if (problem.source) {

              metaParts.push(
                String(
                  problem.source
                )
              );
            }


            metaParts.push(
              "AC " +
              Number(
                problem.accepted || 0
              )
            );


            titleBox.appendChild(
              meta
            );

            meta.textContent =
              metaParts.join(
                " · "
              );


            row.appendChild(
              titleBox
            );


            // 재사용 표시
            if (
              Number(
                problem.allow_reuse
              ) === 0
            ) {

              const badge =
                document.createElement(
                  "span"
                );

              badge.className =
                "contest-problem-reuse";

              badge.textContent =
                "재사용 제한";

              row.appendChild(
                badge
              );
            }


            container.appendChild(
              row
            );
          }
        );
      }


      // ============================================================
      // 문제 검색
      // ============================================================

      async function searchContestProblems() {

        const searchInput =
          document.getElementById(
            "problem-search"
          );

        const resultContainer =
          document.getElementById(
            "problem-search-results"
          );


        if (
          !searchInput ||
          !resultContainer
        ) {
          return;
        }


        resultContainer.textContent =
          "문제를 불러오는 중입니다.";


        try {

          const params =
            new URLSearchParams({
              scope: currentProblemScope,

              search: searchInput.value.trim()
            });


          const response =
            await fetch(
              "contest_problem_search.php?" +
              params.toString(), {
                credentials: "same-origin"
              }
            );


          const data =
            await response.json();


          if (
            !response.ok ||
            !data.success
          ) {

            throw new Error(
              data.message ||
              "문제를 불러오지 못했습니다."
            );
          }


          renderProblemSearchResults(
            data.problems || []
          );

        } catch (error) {

          resultContainer.textContent =
            error.message ||
            "문제 검색 중 오류가 발생했습니다.";
        }
      }

      // ============================================================
      // 제출 언어 선택 상태
      // ============================================================

      function updateLanguageSelection() {

        const checkedLanguages =
          document.querySelectorAll(
            'input[name="lang[]"]:checked'
          );


        const countLabel =
          document.getElementById(
            "selected-language-count"
          );


        const errorBox =
          document.getElementById(
            "contest-language-error"
          );


        if (countLabel) {

          countLabel.textContent =
            checkedLanguages.length +
            "개 선택";
        }


        if (
          errorBox &&
          checkedLanguages.length > 0
        ) {

          errorBox.style.display =
            "none";
        }
      }

      function synchronizeContestPrivacy() {

        const privateInput =
          document.querySelector(
            'input[name="private"]:checked'
          );

        const passwordInput =
          document.getElementById(
            "contest-password"
          );

        const passwordHelp =
          document.getElementById(
            "contest-password-help"
          );


        if (
          !privateInput ||
          !passwordInput
        ) {
          return;
        }


        const isPrivate =
          String(
            privateInput.value
          ) === "1";



        passwordInput.disabled =
          isPrivate;


        if (isPrivate) {

          passwordInput.value = "";
          passwordInput.placeholder =
            "비공개 대회에서는 사용할 수 없습니다.";

          if (passwordHelp) {
            passwordHelp.innerHTML =
              "비공개 대회는 참가자 목록에 등록되어 참가 권한(c{cid})이 있는 사용자만 접근할 수 있습니다. " +
              "비밀번호는 사용하지 않습니다.";
          }

        } else {

          passwordInput.placeholder =
            "필요한 경우에만 입력";

          if (passwordHelp) {
            passwordHelp.innerHTML =
              "공개 대회에 비밀번호를 설정하면 비밀번호를 아는 사용자만 접근할 수 있습니다. " +
              "비밀번호가 없으면 일반 공개 대회로 운영됩니다.";
          }
        }
      }


      document.addEventListener(
        "DOMContentLoaded",
        function() {

          renderSelectedProblems();

          searchContestProblems();

          synchronizeContestPrivacy();


          // 검색 버튼
          const searchButton =
            document.getElementById(
              "problem-search-button"
            );


          if (searchButton) {

            searchButton.addEventListener(
              "click",
              searchContestProblems
            );
          }


          // Enter 검색
          const searchInput =
            document.getElementById(
              "problem-search"
            );


          if (searchInput) {

            searchInput.addEventListener(
              "keydown",
              function(event) {

                if (
                  event.key === "Enter"
                ) {

                  event.preventDefault();

                  searchContestProblems();
                }
              }
            );
          }


          // 내가 만든 문제 / 전체 문제
          document
            .querySelectorAll(
              ".contest-problem-tab"
            )
            .forEach(
              function(tab) {

                tab.addEventListener(
                  "click",
                  function() {

                    document
                      .querySelectorAll(
                        ".contest-problem-tab"
                      )
                      .forEach(
                        function(item) {
                          item.classList.remove(
                            "active"
                          );
                        }
                      );


                    this.classList.add(
                      "active"
                    );


                    currentProblemScope =
                      this.dataset.scope ||
                      "my";


                    searchContestProblems();
                  }
                );
              }
            );


          // 공개/비공개 설정
          // 제출 언어
          document
            .querySelectorAll(
              'input[name="lang[]"]'
            )
            .forEach(
              function(languageInput) {

                languageInput.addEventListener(
                  "change",
                  updateLanguageSelection
                );
              }
            );


          updateLanguageSelection();


          // 공개 / 비공개
          document
            .querySelectorAll(
              'input[name="private"]'
            )
            .forEach(
              function(privateInput) {

                privateInput.addEventListener(
                  "change",
                  synchronizeContestPrivacy
                );
              }
            );


          // 저장 전 제출 언어 검사
          const contestForm =
            document.getElementById(
              "contest-add-form"
            );


          if (contestForm) {

            contestForm.addEventListener(
              "submit",
              function(event) {

                const checkedLanguages =
                  document.querySelectorAll(
                    'input[name="lang[]"]:checked'
                  );


                if (
                  checkedLanguages.length === 0
                ) {

                  event.preventDefault();


                  const errorBox =
                    document.getElementById(
                      "contest-language-error"
                    );


                  if (errorBox) {

                    errorBox.style.display =
                      "block";

                    errorBox.scrollIntoView({
                      behavior: "smooth",
                      block: "center"
                    });
                  }


                  return;
                }
              }
            );
          }

        }
      );
    </script>

  <?php }
  require_once("../oj-footer.php");
  ?>
</body>

</html>