<?php
   header("Cache-control:private"); 
?>
<html>
<head>
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Cache-Control" content="no-cache">
  <meta http-equiv="Content-Language" content="zh-cn">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Contest Add</title>
</head>
<hr>

<?php 
  require_once("../include/db_info.inc.php");
  require_once("../lang/$OJ_LANG.php");
  require_once("../include/const.inc.php");
  require_once("admin-header.php");
  if(!(isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator']))){
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
  }
  echo "<center><h3>".$MSG_CONTEST."-".$MSG_ADD."</h3></center>";
  include_once("kindeditor.php") ;
?>

<body leftmargin="30" >
<?php
$description = "";
if(isset($_POST['startdate'])){
  require_once("../include/check_post_key.php");

  $starttime = $_POST['startdate']." ".intval($_POST['shour']).":".intval($_POST['sminute']).":00";
  $endtime = $_POST['enddate']." ".intval($_POST['ehour']).":".intval($_POST['eminute']).":00";

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

  $lang = isset($_POST['lang']) ? $_POST['lang'] : [];
  $langmask = 0;
  foreach($lang as $t){
    $langmask += 1<<$t;
  } 

  $langmask = ((1<<count($language_ext))-1)&(~$langmask);

  $sql = "INSERT INTO `contest`
  (`title`,`start_time`,`end_time`,`codevisible`,`private`,`langmask`,`description`,`password`,`user_id`,`exam_mode`,`allow_copy`)
  VALUES(?,?,?,?,?,?,?,?,?,?,?)";

  $description = str_replace("<p>", "", $description); 
  $description = str_replace("</p>", "<br />", $description);
  $description = str_replace(",", "&#44; ", $description);
  $user_id=$_SESSION[$OJ_NAME.'_'.'user_id'];

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

  echo "Add Contest ".$cid;

  $sql = "DELETE FROM `contest_problem` WHERE `contest_id`=$cid";
  pdo_query($sql);

  $plist = trim($_POST['cproblem']);
  $pieces = array_filter(array_map('trim', explode(",",$plist)), function($x){ return $x!==''; });

  if(count($pieces)>0){
    $cpoints = isset($_POST['cpoint']) ? $_POST['cpoint'] : [];

    $sql_1 = "INSERT INTO `contest_problem`(`contest_id`,`problem_id`,`num`, `score`) VALUES (?,?,?,?)";
    $plist_join = "";
    $pid = 0;

    for($i = 0; $i < count($pieces); $i++){
      $problem_id = intval($pieces[$i]);
      $score = (isset($cpoints[$i]) && $cpoints[$i] !== '' && is_numeric($cpoints[$i])) ? intval($cpoints[$i]) : 100;

      $sql = "SELECT problem_id FROM problem WHERE problem_id=?";
      $has = pdo_query($sql, $problem_id);

      if(count($has) > 0) {
        if($plist_join) $plist_join .= ",";
        $plist_join .= $problem_id;
        pdo_query($sql_1, $cid, $problem_id, $pid, $score);
        $pid++;
      } else {
        print("Problem not exists: ".$problem_id."<br>\n");
      }
    }
    // 기본 공개/비공개 유지 (이전 주석 유지)
    // $sql = "UPDATE `problem` SET defunct='N' WHERE `problem_id` IN ($plist_join)";
    // pdo_query($sql) ;
  }

  $sql = "DELETE FROM `privilege` WHERE `rightstr`=?";
  pdo_query($sql,"c$cid");

  $sql = "INSERT INTO `privilege` (`user_id`,`rightstr`) VALUES(?,?)";
  pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id'],"m$cid");

  $_SESSION[$OJ_NAME.'_'."m$cid"] = true;
  $pieces = explode("\n", trim($_POST['ulist']));

  if(count($pieces)>0 && strlen($pieces[0])>0){
    $sql_1 = "INSERT INTO `privilege`(`user_id`,`rightstr`) VALUES (?,?)";
    for($i=0; $i<count($pieces); $i++){
      $uid = trim($pieces[$i]);
      if($uid!==''){
        pdo_query($sql_1,$uid,"c$cid") ;
      }
    }
  }
  echo "<script>window.location.href=\"contest_list.php\";</script>";
}
else{
  // ===== 복사(기존 cid로 열기) 또는 기타 모드 =====
  $score_prefill = []; // pid => score (복사 시 점수 사전채움용)
  if(isset($_GET['cid'])){
    $cid = intval($_GET['cid']);
    $sql = "SELECT * FROM contest WHERE `contest_id`=?";
    $result = pdo_query($sql,$cid);
    $row = $result[0];

    if (
        !$row ||
        !isset($row['contest_id'])
    ) {
        echo "<h3>존재하지 않는 대회입니다.</h3>";
        exit(1);
    }

    $current_user_id =
        $_SESSION[$OJ_NAME.'_user_id'];

    $is_admin =
        isset($_SESSION[$OJ_NAME.'_administrator']);

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
    $result = pdo_query($sql,$cid);
    foreach($result as $row){
      $pid = $row['problem_id'];
      $score = isset($row['score']) ? intval($row['score']) : 100;

      if($plist) $plist .= ',';
      $plist .= $pid;

      $score_prefill[$pid] = $score;
    }

    $ulist = "";
    $sql = "SELECT `user_id` FROM `privilege` WHERE `rightstr`=? order by user_id";
    $result = pdo_query($sql,"c$cid");

    foreach($result as $row){
      if($ulist) $ulist .= "\n";
      $ulist .= $row[0];
    }
  }
  else if(isset($_POST['problem2contest'])){
    $plist = "";
    sort($_POST['pid']);
    foreach($_POST['pid'] as $i){       
      if($plist)
        $plist.=','.intval($i);
      else
        $plist=$i;
    }
  }else if(isset($_GET['spid'])){
    //require_once("../include/check_get_key.php");
    $spid = intval($_GET['spid']);

    $plist = "";
    $sql = "SELECT `problem_id` FROM `problem` WHERE `problem_id`>=? ";
    $result = pdo_query($sql,$spid);
    foreach($result as $row){
      if($plist) $plist.=',';
      $plist.=$row[0];
    }
  }

  include_once("kindeditor.php") ;
?>

  <style>
    .contest-add-wrap {
      max-width: 1180px;
      margin: 0 auto;
      padding: 12px 8px 28px;
      box-sizing: border-box;
    }

    .contest-add-card {
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
      padding: 18px 20px;
      margin-bottom: 18px;
      box-sizing: border-box;
    }

    .contest-add-card h4 {
      margin: 0 0 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }

    .contest-add-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 18px;
    }

    .contest-add-grid-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px 18px;
    }

    .contest-add-field label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
    }

    .contest-add-field input[type="text"],
    .contest-add-field input[type="date"],
    .contest-add-field input[type="number"],
    .contest-add-field select,
    .contest-add-field textarea {
      width: 100%;
      box-sizing: border-box;
      min-height: 34px;
    }

    .contest-add-inline-time {
      display: grid;
      grid-template-columns: minmax(150px, 2fr) 80px 80px;
      gap: 8px;
      align-items: end;
    }

    .contest-add-help {
      color: #666;
      font-size: 0.92em;
      margin-top: 6px;
      line-height: 1.5;
    }

    .contest-add-problem-row {
      padding: 8px 10px;
      margin-top: 6px;
      border: 1px solid #eee;
      border-radius: 5px;
      background: #fafafa;
    }

    .contest-add-save {
      text-align: center;
      margin-top: 22px;
    }

    .contest-add-save input[type="submit"] {
      min-width: 160px;
      padding: 10px 18px;
      font-size: 1.05em;
    }

    @media (max-width: 800px) {
      .contest-add-grid,
      .contest-add-grid-3 {
        grid-template-columns: 1fr;
      }

      .contest-add-inline-time {
        grid-template-columns: 1fr 70px 70px;
      }

      .contest-add-card {
        padding: 14px;
      }
    }
  </style>

  <div class="container contest-add-wrap">
    <form method="POST">

      <!-- ====================================================
           1. 기본 정보
           ==================================================== -->
      <div class="contest-add-card">

        <h4>1. 기본 정보</h4>

        <div class="contest-add-field">
          <label><?php echo $MSG_CONTEST."-".$MSG_TITLE; ?></label>
          <input
            class="input input-xxlarge"
            type="text"
            name="title"
            value="<?php echo isset($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : ''; ?>"
            required
          >
        </div>

        <div class="contest-add-grid" style="margin-top:14px;">

          <div class="contest-add-field">
            <label><?php echo $MSG_CONTEST.$MSG_Start; ?></label>

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
                required
              >

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
                required
              >

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
                required
              >

            </div>
          </div>

          <div class="contest-add-field">
            <label><?php echo $MSG_CONTEST.$MSG_End; ?></label>

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
                required
              >

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
                required
              >

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
                required
              >

            </div>
          </div>

        </div>

        <div class="contest-add-field" style="margin-top:14px;">
          <label><?php echo $MSG_CONTEST."-".$MSG_Description; ?></label>
          <textarea
            class="kindeditor"
            rows="13"
            name="description"
            cols="80"
          ><?php echo isset($description) ? $description : ''; ?></textarea>
        </div>

      </div>


      <!-- ====================================================
           2. 문제 구성
           ==================================================== -->
      <div class="contest-add-card">

        <h4>2. 문제 구성</h4>

        <div class="contest-add-field">
          <label><?php echo $MSG_CONTEST."-".$MSG_PROBLEM_ID; ?></label>

          <input
            id="plist"
            class="input-xxlarge"
            type="text"
            name="cproblem"
            style="width:100%;"
            placeholder="예: 1000,1001,1002"
            value="<?php echo isset($plist) ? htmlspecialchars($plist, ENT_QUOTES, 'UTF-8') : ''; ?>"
            onchange="showTitles()"
          >

          <div class="contest-add-help">
            문제 번호를 쉼표(,)로 구분하여 입력하세요.
            입력한 순서대로 A, B, C... 문제로 구성됩니다.
          </div>

          <div id="ptitles" style="margin-top:10px;"></div>
        </div>

      </div>


      <!-- ====================================================
           3. 대회 운영 설정
           ==================================================== -->
      <div class="contest-add-card">

        <h4>3. 대회 운영 설정</h4>

        <div class="contest-add-grid">

          <div class="contest-add-field">
            <label>공개 여부</label>

            <select
              id="contest-private"
              name="private"
            >
              <option
                value="0"
                <?php echo (isset($private) && intval($private) === 0) ? 'selected' : ''; ?>
              >
                <?php echo $MSG_Public; ?>
              </option>

              <option
                value="1"
                <?php echo (isset($private) && intval($private) === 1) ? 'selected' : ''; ?>
              >
                <?php echo $MSG_Private; ?>
              </option>
            </select>

            <div class="contest-add-help">
              공개 대회는 누구나 접근할 수 있습니다.
              비공개 대회는 아래 참가자 목록에 등록되어
              참가 권한(c{cid})이 있는 사용자만 접근할 수 있습니다.
            </div>
          </div>


          <div class="contest-add-field">
            <label>제출 코드 공개</label>

            <select name="codevisible">
              <option
                value="0"
                <?php echo (isset($codevisible) && intval($codevisible) === 0) ? 'selected' : ''; ?>
              >
                <?php echo $MSG_CodePublic; ?>
              </option>

              <option
                value="1"
                <?php echo (isset($codevisible) && intval($codevisible) === 1) ? 'selected' : ''; ?>
              >
                <?php echo $MSG_CodePrivate; ?>
              </option>
            </select>
          </div>


          <div class="contest-add-field">
            <label>시험 모드</label>

            <select name="exam_mode">
              <option
                value="0"
                <?php echo (!isset($exam_mode) || intval($exam_mode) === 0) ? 'selected' : ''; ?>
              >
                <?php echo $MSG_EXAMMODEOFF; ?>
              </option>

              <option
                value="1"
                <?php echo (isset($exam_mode) && intval($exam_mode) === 1) ? 'selected' : ''; ?>
              >
                <?php echo $MSG_EXAMMODEON; ?>
              </option>
            </select>
          </div>


          <div class="contest-add-field">
            <label>다른 사용자의 대회 복사 허용</label>

            <select name="allow_copy">
              <option
                value="1"
                <?php
                  echo (
                    !isset($allow_copy) ||
                    intval($allow_copy) === 1
                  ) ? 'selected' : '';
                ?>
              >
                허용
              </option>

              <option
                value="0"
                <?php
                  echo (
                    isset($allow_copy) &&
                    intval($allow_copy) === 0
                  ) ? 'selected' : '';
                ?>
              >
                허용하지 않음
              </option>
            </select>

            <div class="contest-add-help">
              다른 Contest Creator가 이 대회의 설정과 문제 구성을 가져와
              새로운 대회를 만들 수 있는지 지정합니다.
            </div>
          </div>


          <div class="contest-add-field">
            <label><?php echo $MSG_CONTEST."-".$MSG_PASSWORD; ?></label>

            <input
              id="contest-password"
              type="text"
              name="password"
              value=""
              placeholder="공개 대회에서 필요한 경우에만 입력"
            >

            <div
              id="contest-password-help"
              class="contest-add-help"
            >
              공개 대회에 비밀번호를 설정하면 비밀번호를 아는 사용자만 접근할 수 있습니다.
              비공개 대회에서는 참가자 권한으로만 접근하므로 비밀번호를 사용할 수 없습니다.
            </div>
          </div>

        </div>

      </div>


      <!-- ====================================================
           4. 사용 언어
           ==================================================== -->
      <div class="contest-add-card">

        <h4>4. 사용 언어</h4>

        <div class="contest-add-field">

          <label><?php echo $MSG_CONTEST."-".$MSG_LANG; ?></label>

          <select
            name="lang[]"
            multiple="multiple"
            style="height:220px; width:100%;"
          >
          <?php
            $lang_count = count($language_ext);
            $lang = (~((int)$langmask)) & ((1 << $lang_count) - 1);

            if (isset($_COOKIE['lastlang'])) {
              $lastlang = $_COOKIE['lastlang'];
            } else {
              $lastlang = 0;
            }

            for ($i = 0; $i < $lang_count; $i++) {

              echo "<option value=\"".$i."\" ".
                   ($lang & (1 << $i) ? "selected" : "").
                   ">".

                   htmlspecialchars(
                     $language_name[$i],
                     ENT_QUOTES,
                     'UTF-8'
                   ).

                   "</option>";
            }
          ?>
          </select>

          <div class="contest-add-help">
            Ctrl(Windows) 또는 Command(macOS)를 누른 채 여러 언어를 선택할 수 있습니다.
          </div>

        </div>

      </div>


      <!-- ====================================================
           5. 참가자 설정
           ==================================================== -->
      <div class="contest-add-card">

        <h4>5. 참가자 설정</h4>

        <div class="contest-add-field">

          <label><?php echo $MSG_CONTEST."-".$MSG_USER; ?></label>

          <textarea
            name="ulist"
            rows="10"
            style="width:100%;"
            placeholder="user1&#10;user2&#10;user3"
          ><?php echo isset($ulist) ? htmlspecialchars($ulist, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>

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
          name="submit"
        >

      </div>

    </form>
  </div>


  <script>
    const SCORE_PREFILL =
      <?php echo json_encode(isset($score_prefill) ? $score_prefill : array()); ?>;


    async function showTitles() {

      const ts =
        document.querySelector("#ptitles");

      const plistInput =
        document.querySelector("#plist");

      if (!ts || !plistInput) {
        return;
      }


      const pids =
        plistInput.value
          .split(",")
          .map(function (value) {
            return value.trim();
          })
          .filter(function (value) {
            return value.length > 0;
          });


      ts.innerHTML = "";


      for (const v of pids) {

        const response =
          await fetch(
            "ajax.php",
            {
              method: "POST",

              headers: {
                "Content-Type":
                  "application/x-www-form-urlencoded"
              },

              body:
                new URLSearchParams(
                  {
                    pid: v,
                    m: "problem_get_title"
                  }
                )
            }
          );


        const title =
          await response.text();


        const row =
          document.createElement("div");

        row.className =
          "contest-add-problem-row";


        const score =
          (
            SCORE_PREFILL &&
            Object.prototype.hasOwnProperty.call(
              SCORE_PREFILL,
              v
            )
          )
            ? SCORE_PREFILL[v]
            : 100;


        row.innerHTML =
          "<strong>" +
          v +
          "</strong>: " +
          "<a href=\"../problem.php?id=" +
          encodeURIComponent(v) +
          "\" target=\"_blank\">" +
          title +
          "</a>" +
          " &nbsp; 점수: " +
          "<input type=\"number\" " +
          "name=\"cpoint[]\" " +
          "style=\"width:120px;\" " +
          "value=\"" +
          score +
          "\" min=\"0\" step=\"1\">";


        ts.appendChild(row);
      }
    }


    function synchronizeContestPrivacy() {

      const privateSelect =
        document.getElementById("contest-private");

      const passwordInput =
        document.getElementById("contest-password");

      const passwordHelp =
        document.getElementById("contest-password-help");


      if (!privateSelect || !passwordInput) {
        return;
      }


      const isPrivate =
        String(privateSelect.value) === "1";


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
      function () {

        showTitles();
        synchronizeContestPrivacy();

        const privateSelect =
          document.getElementById("contest-private");

        if (privateSelect) {

          privateSelect.addEventListener(
            "change",
            synchronizeContestPrivacy
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