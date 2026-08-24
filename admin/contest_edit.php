<?php
header("Cache-control:private");

require_once("../include/db_info.inc.php");
require_once("../lang/$OJ_LANG.php");
require_once("../include/const.inc.php");
require_once("admin-header.php");

if (
    !isset($_SESSION[$OJ_NAME.'_administrator']) &&
    !isset($_SESSION[$OJ_NAME.'_contest_creator'])
) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

include_once("kindeditor.php");

$view_error = '';
$cid = 0;


// ============================================================
// 1. 저장 처리
// ============================================================

if (isset($_POST['startdate'])) {

    require_once("../include/check_post_key.php");

    $cid =
        isset($_POST['cid'])
            ? intval($_POST['cid'])
            : 0;

    if ($cid <= 0) {
        echo "No such Contest!";
        exit(1);
    }


    // --------------------------------------------------------
    // Contest 존재 / 수정 권한 확인
    //
    // 관리자 또는 실제 m{cid} 권한 보유자만 수정 가능
    // contest_creator 전역 권한만으로 타인의 대회를 수정하지 못하게 한다.
    // --------------------------------------------------------

    $contest_rows = pdo_query(
        "SELECT
            contest_id,
            user_id
         FROM contest
         WHERE contest_id = ?
         LIMIT 1",
        $cid
    );

    if (
        !$contest_rows ||
        !isset($contest_rows[0]['contest_id'])
    ) {
        echo "No such Contest!";
        exit(1);
    }

    $can_edit =
        isset($_SESSION[$OJ_NAME.'_administrator']) ||
        isset($_SESSION[$OJ_NAME.'_m'.$cid]);

    if (!$can_edit) {

        $privilege_rows = pdo_query(
            "SELECT 1
             FROM privilege
             WHERE user_id = ?
               AND rightstr = ?
             LIMIT 1",
            $_SESSION[$OJ_NAME.'_user_id'],
            "m".$cid
        );

        $can_edit =
            $privilege_rows &&
            isset($privilege_rows[0][0]);
    }

    if (!$can_edit) {
        echo "<h3>이 대회를 수정할 권한이 없습니다.</h3>";
        exit(1);
    }


    // --------------------------------------------------------
    // 입력값
    // --------------------------------------------------------

    $starttime =
        $_POST['startdate']." ".
        intval($_POST['shour']).":".
        intval($_POST['sminute']).":00";

    $endtime =
        $_POST['enddate']." ".
        intval($_POST['ehour']).":".
        intval($_POST['eminute']).":00";

    $title =
        isset($_POST['title'])
            ? trim($_POST['title'])
            : '';

    $codevisible =
        isset($_POST['codevisible'])
            ? intval($_POST['codevisible'])
            : 0;

    $private =
        isset($_POST['private'])
            ? intval($_POST['private'])
            : 0;

    $password =
        isset($_POST['password'])
            ? trim($_POST['password'])
            : '';

    // 비공개 대회는 참가자 권한만 사용
    if ($private === 1) {
        $password = '';
    }

    $description =
        isset($_POST['description'])
            ? $_POST['description']
            : '';

    $exam_mode =
        isset($_POST['exam_mode'])
            ? intval($_POST['exam_mode'])
            : 0;

    $allow_copy =
        isset($_POST['allow_copy'])
            ? intval($_POST['allow_copy'])
            : 1;

    if (!in_array($allow_copy, array(0, 1), true)) {
        $allow_copy = 1;
    }


    // --------------------------------------------------------
    // 언어
    // --------------------------------------------------------

    $lang =
        isset($_POST['lang']) &&
        is_array($_POST['lang'])
            ? $_POST['lang']
            : array();

    $langmask = 0;

    foreach ($lang as $t) {

        $t = intval($t);

        if ($t >= 0 && $t < count($language_ext)) {
            $langmask += 1 << $t;
        }
    }

    $langmask =
        ((1 << count($language_ext)) - 1) &
        (~$langmask);


    // --------------------------------------------------------
    // Contest 기본정보 수정
    // --------------------------------------------------------

    $description =
        str_replace("<p>", "", $description);

    $description =
        str_replace("</p>", "<br />", $description);

    $description =
        str_replace(",", "&#44;", $description);


    pdo_query(
        "UPDATE contest
         SET
            title = ?,
            description = ?,
            start_time = ?,
            end_time = ?,
            codevisible = ?,
            private = ?,
            langmask = ?,
            password = ?,
            exam_mode = ?,
            allow_copy = ?
         WHERE contest_id = ?",
        $title,
        $description,
        $starttime,
        $endtime,
        $codevisible,
        $private,
        $langmask,
        $password,
        $exam_mode,
        $allow_copy,
        $cid
    );


    // --------------------------------------------------------
    // 문제 구성 갱신
    // --------------------------------------------------------

    pdo_query(
        "DELETE FROM contest_problem
         WHERE contest_id = ?",
        $cid
    );

    $plist =
        isset($_POST['cproblem'])
            ? trim($_POST['cproblem'])
            : '';

    $pieces =
        array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(',', $plist)
                ),
                function ($x) {
                    return $x !== '';
                }
            )
        );

    $cpoints =
        isset($_POST['cpoint']) &&
        is_array($_POST['cpoint'])
            ? $_POST['cpoint']
            : array();


    pdo_query(
        "UPDATE solution
         SET num = -1
         WHERE contest_id = ?",
        $cid
    );


    $num = 0;

    foreach ($pieces as $i => $piece) {

        $pid =
            intval($piece);

        if ($pid <= 0) {
            continue;
        }


        $score =
            (
                isset($cpoints[$i]) &&
                $cpoints[$i] !== '' &&
                is_numeric($cpoints[$i])
            )
                ? intval($cpoints[$i])
                : 100;


        $has = pdo_query(
            "SELECT problem_id
             FROM problem
             WHERE problem_id = ?
             LIMIT 1",
            $pid
        );


        if (
            !$has ||
            !isset($has[0]['problem_id'])
        ) {

            echo "Problem not exists: ".
                htmlspecialchars(
                    $piece,
                    ENT_QUOTES,
                    'UTF-8'
                ).
                "<br>\n";

            continue;
        }


        pdo_query(
            "INSERT INTO contest_problem
            (
                contest_id,
                problem_id,
                num,
                score
            )
            VALUES (?, ?, ?, ?)",
            $cid,
            $pid,
            $num,
            $score
        );


        pdo_query(
            "UPDATE contest_problem
             SET c_accepted =
             (
                SELECT COUNT(1)
                FROM solution
                WHERE problem_id = ?
                  AND contest_id = ?
                  AND result = 4
             )
             WHERE problem_id = ?
               AND contest_id = ?",
            $pid,
            $cid,
            $pid,
            $cid
        );


        pdo_query(
            "UPDATE contest_problem
             SET c_submit =
             (
                SELECT COUNT(1)
                FROM solution
                WHERE problem_id = ?
                  AND contest_id = ?
             )
             WHERE problem_id = ?
               AND contest_id = ?",
            $pid,
            $cid,
            $pid,
            $cid
        );


        pdo_query(
            "UPDATE solution
             SET num = ?
             WHERE contest_id = ?
               AND problem_id = ?",
            $num,
            $cid,
            $pid
        );


        $num++;
    }


    // --------------------------------------------------------
    // 참가자 권한 갱신
    //
    // 비공개 대회 참가자 목록(c{cid})
    // --------------------------------------------------------

    pdo_query(
        "DELETE FROM privilege
         WHERE rightstr = ?",
        "c".$cid
    );


    $ulist_raw =
        isset($_POST['ulist'])
            ? trim($_POST['ulist'])
            : '';

    if ($ulist_raw !== '') {

        $users =
            preg_split(
                '/\r\n|\r|\n/',
                $ulist_raw
            );

        foreach ($users as $uid) {

            $uid = trim($uid);

            if ($uid === '') {
                continue;
            }

            pdo_query(
                "INSERT INTO privilege
                (
                    user_id,
                    rightstr
                )
                VALUES (?, ?)",
                $uid,
                "c".$cid
            );
        }
    }


    header(
        "Location: contest_list.php"
    );

    exit();
}


// ============================================================
// 2. 수정 화면용 Contest 조회
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;

if ($cid <= 0) {
    echo "No such Contest!";
    exit(0);
}


$result = pdo_query(
    "SELECT *
     FROM contest
     WHERE contest_id = ?
     LIMIT 1",
    $cid
);

if (
    !$result ||
    !isset($result[0]['contest_id'])
) {
    echo "No such Contest!";
    exit(0);
}


$row = $result[0];


// ------------------------------------------------------------
// GET 단계에서도 수정 권한 확인
// ------------------------------------------------------------

$can_edit =
    isset($_SESSION[$OJ_NAME.'_administrator']) ||
    isset($_SESSION[$OJ_NAME.'_m'.$cid]);

if (!$can_edit) {

    $privilege_rows = pdo_query(
        "SELECT 1
         FROM privilege
         WHERE user_id = ?
           AND rightstr = ?
         LIMIT 1",
        $_SESSION[$OJ_NAME.'_user_id'],
        "m".$cid
    );

    $can_edit =
        $privilege_rows &&
        isset($privilege_rows[0][0]);
}

if (!$can_edit) {
    echo "<h3>이 대회를 수정할 권한이 없습니다.</h3>";
    exit(1);
}


// ------------------------------------------------------------
// 기본값
// ------------------------------------------------------------

$starttime =
    $row['start_time'];

$endtime =
    $row['end_time'];

$codevisible =
    intval($row['codevisible']);

$private =
    intval($row['private']);

$password =
    isset($row['password'])
        ? $row['password']
        : '';

$langmask =
    intval($row['langmask']);

$description =
    $row['description'];

$title =
    $row['title'];

$exam_mode =
    intval($row['exam_mode']);

$allow_copy =
    isset($row['allow_copy'])
        ? intval($row['allow_copy'])
        : 1;


// ------------------------------------------------------------
// 문제 / 점수
// ------------------------------------------------------------

$plist = '';

$score_list = array();

$problem_rows = pdo_query(
    "SELECT
        problem_id,
        score
     FROM contest_problem
     WHERE contest_id = ?
     ORDER BY num",
    $cid
);

if (!is_array($problem_rows)) {
    $problem_rows = array();
}

foreach ($problem_rows as $problem) {

    if ($plist !== '') {
        $plist .= ',';
    }

    $plist .= intval(
        $problem['problem_id']
    );

    $score_list[] =
        isset($problem['score'])
            ? intval($problem['score'])
            : 100;
}


// ------------------------------------------------------------
// 참가자 목록
// ------------------------------------------------------------

$ulist = '';

$user_rows = pdo_query(
    "SELECT user_id
     FROM privilege
     WHERE rightstr = ?
     ORDER BY user_id",
    "c".$cid
);

if (!is_array($user_rows)) {
    $user_rows = array();
}

foreach ($user_rows as $user) {

    if ($ulist !== '') {
        $ulist .= "\n";
    }

    $ulist .= $user['user_id'];
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Cache-Control" content="no-cache">
  <meta http-equiv="Content-Language" content="ko">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Contest</title>

  <style>
    .contest-edit-wrap {
      max-width: 1180px;
      margin: 0 auto;
      padding: 12px 8px 30px;
      box-sizing: border-box;
    }

    .contest-edit-header {
      margin: 10px 0 20px;
      text-align: center;
    }

    .contest-edit-card {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 18px 20px;
      margin-bottom: 18px;
      box-sizing: border-box;
    }

    .contest-edit-card h4 {
      margin: 0 0 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }

    .contest-edit-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 18px;
    }

    .contest-edit-field label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
    }

    .contest-edit-field input[type="text"],
    .contest-edit-field input[type="number"],
    .contest-edit-field input[type="date"],
    .contest-edit-field select,
    .contest-edit-field textarea {
      width: 100%;
      box-sizing: border-box;
      min-height: 34px;
    }

    .contest-edit-inline-time {
      display: grid;
      grid-template-columns: minmax(150px, 2fr) 80px 80px;
      gap: 8px;
    }

    .contest-edit-help {
      margin-top: 6px;
      color: #666;
      font-size: 0.92em;
      line-height: 1.5;
    }

    .contest-edit-problem-row {
      padding: 8px 10px;
      margin-top: 6px;
      border: 1px solid #eee;
      border-radius: 5px;
      background: #fafafa;
    }

    .contest-edit-actions {
      text-align: center;
      margin-top: 20px;
    }

    .contest-edit-actions input {
      min-width: 140px;
      margin: 0 4px;
      padding: 9px 16px;
    }

    @media (max-width: 800px) {
      .contest-edit-grid {
        grid-template-columns: 1fr;
      }

      .contest-edit-inline-time {
        grid-template-columns: 1fr 70px 70px;
      }

      .contest-edit-card {
        padding: 14px;
      }
    }
  </style>
</head>

<body>

<div class="container contest-edit-wrap">

  <div class="contest-edit-header">
    <h3><?php echo $MSG_CONTEST; ?> 수정</h3>
    <div>
      Contest ID:
      <strong><?php echo intval($cid); ?></strong>
    </div>
  </div>


  <form method="POST">

    <?php require_once("../include/set_post_key.php"); ?>

    <input
      type="hidden"
      name="cid"
      value="<?php echo intval($cid); ?>"
    >


    <!-- ======================================================
         1. 기본 정보
         ====================================================== -->

    <div class="contest-edit-card">

      <h4>1. 기본 정보</h4>


      <div class="contest-edit-field">

        <label>
          <?php echo $MSG_CONTEST."-".$MSG_TITLE; ?>
        </label>

        <input
          type="text"
          name="title"
          value="<?php
            echo htmlspecialchars(
                $title,
                ENT_QUOTES,
                'UTF-8'
            );
          ?>"
          required
        >

      </div>


      <div
        class="contest-edit-grid"
        style="margin-top:14px;"
      >

        <div class="contest-edit-field">

          <label>
            <?php echo $MSG_CONTEST.$MSG_Start; ?>
          </label>

          <div class="contest-edit-inline-time">

            <input
              type="date"
              name="startdate"
              value="<?php echo substr($starttime, 0, 10); ?>"
              required
            >

            <input
              type="number"
              name="shour"
              min="0"
              max="23"
              value="<?php echo substr($starttime, 11, 2); ?>"
              title="시"
              required
            >

            <input
              type="number"
              name="sminute"
              min="0"
              max="59"
              value="<?php echo substr($starttime, 14, 2); ?>"
              title="분"
              required
            >

          </div>

        </div>


        <div class="contest-edit-field">

          <label>
            <?php echo $MSG_CONTEST.$MSG_End; ?>
          </label>

          <div class="contest-edit-inline-time">

            <input
              type="date"
              name="enddate"
              value="<?php echo substr($endtime, 0, 10); ?>"
              required
            >

            <input
              type="number"
              name="ehour"
              min="0"
              max="23"
              value="<?php echo substr($endtime, 11, 2); ?>"
              title="시"
              required
            >

            <input
              type="number"
              name="eminute"
              min="0"
              max="59"
              value="<?php echo substr($endtime, 14, 2); ?>"
              title="분"
              required
            >

          </div>

        </div>

      </div>


      <div
        class="contest-edit-field"
        style="margin-top:14px;"
      >

        <label>
          <?php echo $MSG_CONTEST."-".$MSG_Description; ?>
        </label>

        <textarea
          class="kindeditor"
          rows="13"
          name="description"
          cols="80"
        ><?php
          echo htmlspecialchars(
              $description,
              ENT_QUOTES,
              'UTF-8'
          );
        ?></textarea>

      </div>

    </div>


    <!-- ======================================================
         2. 문제 구성
         ====================================================== -->

    <div class="contest-edit-card">

      <h4>2. 문제 구성</h4>


      <div class="contest-edit-field">

        <label>
          <?php echo $MSG_CONTEST."-".$MSG_PROBLEM_ID; ?>
        </label>

        <input
          id="plist"
          type="text"
          name="cproblem"
          value="<?php
            echo htmlspecialchars(
                $plist,
                ENT_QUOTES,
                'UTF-8'
            );
          ?>"
          placeholder="예: 1000,1001,1002"
          onchange="showTitles()"
        >

        <div class="contest-edit-help">
          문제 번호를 쉼표(,)로 구분하여 입력하세요.
          입력한 순서대로 A, B, C... 문제로 구성됩니다.
        </div>

        <div
          id="ptitles"
          style="margin-top:10px;"
        ></div>

      </div>

    </div>


    <!-- ======================================================
         3. 대회 운영 설정
         ====================================================== -->

    <div class="contest-edit-card">

      <h4>3. 대회 운영 설정</h4>


      <div class="contest-edit-grid">

        <div class="contest-edit-field">

          <label>공개 여부</label>

          <select
            id="contest-private"
            name="private"
          >

            <option
              value="0"
              <?php echo $private === 0 ? 'selected' : ''; ?>
            >
              <?php echo $MSG_Public; ?>
            </option>

            <option
              value="1"
              <?php echo $private === 1 ? 'selected' : ''; ?>
            >
              <?php echo $MSG_Private; ?>
            </option>

          </select>

          <div class="contest-edit-help">
            공개 대회는 누구나 접근할 수 있습니다.
            비공개 대회는 참가자 목록에 등록되어
            참가 권한(c{cid})이 있는 사용자만 접근할 수 있습니다.
          </div>

        </div>


        <div class="contest-edit-field">

          <label>제출 코드 공개</label>

          <select name="codevisible">

            <option
              value="0"
              <?php echo $codevisible === 0 ? 'selected' : ''; ?>
            >
              <?php echo $MSG_CodePublic; ?>
            </option>

            <option
              value="1"
              <?php echo $codevisible === 1 ? 'selected' : ''; ?>
            >
              <?php echo $MSG_CodePrivate; ?>
            </option>

          </select>

        </div>


        <div class="contest-edit-field">

          <label>시험 모드</label>

          <select name="exam_mode">

            <option
              value="0"
              <?php echo $exam_mode === 0 ? 'selected' : ''; ?>
            >
              <?php echo $MSG_EXAMMODEOFF; ?>
            </option>

            <option
              value="1"
              <?php echo $exam_mode === 1 ? 'selected' : ''; ?>
            >
              <?php echo $MSG_EXAMMODEON; ?>
            </option>

          </select>

        </div>


        <div class="contest-edit-field">

          <label>다른 사용자의 대회 복사 허용</label>

          <select name="allow_copy">

            <option
              value="1"
              <?php echo $allow_copy === 1 ? 'selected' : ''; ?>
            >
              허용
            </option>

            <option
              value="0"
              <?php echo $allow_copy === 0 ? 'selected' : ''; ?>
            >
              허용하지 않음
            </option>

          </select>

          <div class="contest-edit-help">
            다른 Contest Creator가 이 대회의 설정과 문제 구성을
            가져와 새 대회를 만들 수 있는지 지정합니다.
          </div>

        </div>


        <div class="contest-edit-field">

          <label>
            <?php echo $MSG_CONTEST."-".$MSG_PASSWORD; ?>
          </label>

          <input
            id="contest-password"
            type="text"
            name="password"
            value="<?php
              echo htmlspecialchars(
                  $password,
                  ENT_QUOTES,
                  'UTF-8'
              );
            ?>"
            placeholder="공개 대회에서 필요한 경우에만 입력"
          >

          <div
            id="contest-password-help"
            class="contest-edit-help"
          >
            공개 대회에서는 필요한 경우 비밀번호를 사용할 수 있습니다.
            비공개 대회에서는 참가자 권한만 사용합니다.
          </div>

        </div>

      </div>

    </div>


    <!-- ======================================================
         4. 사용 언어
         ====================================================== -->

    <div class="contest-edit-card">

      <h4>4. 사용 언어</h4>


      <div class="contest-edit-field">

        <label>
          <?php echo $MSG_CONTEST."-".$MSG_LANG; ?>
        </label>

        <select
          name="lang[]"
          multiple="multiple"
          style="height:220px;"
        >

        <?php

        $lang_count =
            count($language_ext);

        $lang =
            (~((int)$langmask)) &
            ((1 << $lang_count) - 1);


        for ($i = 0; $i < $lang_count; $i++) {

            echo
                "<option value=\"".$i."\" ".
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

        <div class="contest-edit-help">
          Ctrl(Windows) 또는 Command(macOS)를 누른 채 여러 언어를 선택할 수 있습니다.
        </div>

      </div>

    </div>


    <!-- ======================================================
         5. 참가자 설정
         ====================================================== -->

    <div class="contest-edit-card">

      <h4>5. 참가자 설정</h4>


      <div class="contest-edit-field">

        <label>
          <?php echo $MSG_CONTEST."-".$MSG_USER; ?>
        </label>

        <textarea
          name="ulist"
          rows="10"
          placeholder="user1&#10;user2&#10;user3"
        ><?php
          echo htmlspecialchars(
              $ulist,
              ENT_QUOTES,
              'UTF-8'
          );
        ?></textarea>

        <div class="contest-edit-help">
          비공개 대회의 참가자 아이디를 한 줄에 하나씩 입력하세요.
          공개 대회에서는 입력하지 않아도 됩니다.
        </div>

      </div>

    </div>


    <div class="contest-edit-actions">

      <input
        type="submit"
        value="<?php echo $MSG_SAVE; ?>"
        name="submit"
      >

      <input
        type="reset"
        value="Reset"
        name="reset"
      >

    </div>

  </form>

</div>


<script>
const prefilledScores =
  <?php echo json_encode($score_list); ?>;


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


    for (let i = 0; i < pids.length; i++) {

        const v =
            pids[i];


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


        const score =
            (
                typeof prefilledScores[i] !== "undefined"
            )
                ? prefilledScores[i]
                : 100;


        const row =
            document.createElement("div");

        row.className =
            "contest-edit-problem-row";


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
        document.getElementById(
            "contest-private"
        );

    const passwordInput =
        document.getElementById(
            "contest-password"
        );

    const passwordHelp =
        document.getElementById(
            "contest-password-help"
        );


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

            passwordHelp.textContent =
                "비공개 대회는 참가자 목록에 등록되어 참가 권한(c{cid})이 있는 사용자만 접근할 수 있습니다. 비밀번호는 사용하지 않습니다.";
        }

    }
    else {

        passwordInput.placeholder =
            "필요한 경우에만 입력";

        if (passwordHelp) {

            passwordHelp.textContent =
                "공개 대회에서는 필요한 경우 비밀번호를 사용할 수 있습니다. 비밀번호가 없으면 일반 공개 대회로 운영됩니다.";
        }
    }
}


document.addEventListener(
    "DOMContentLoaded",
    function () {

        showTitles();
        synchronizeContestPrivacy();


        const privateSelect =
            document.getElementById(
                "contest-private"
            );


        if (privateSelect) {

            privateSelect.addEventListener(
                "change",
                synchronizeContestPrivacy
            );
        }

    }
);
</script>

</body>
</html>