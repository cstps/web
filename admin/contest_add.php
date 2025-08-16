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
  $private = $_POST['private'];
  $password = $_POST['password'];
  $description = $_POST['description'];
  $exam_mode = isset($_POST['exam_mode']) ? intval($_POST['exam_mode']) : 0;  

  $lang = isset($_POST['lang']) ? $_POST['lang'] : [];
  $langmask = 0;
  foreach($lang as $t){
    $langmask += 1<<$t;
  } 

  $langmask = ((1<<count($language_ext))-1)&(~$langmask);

  $sql = "INSERT INTO `contest`(`title`,`start_time`,`end_time`,`codevisible`,`private`,`langmask`,`description`,`password`,`user_id`,`exam_mode`)
        VALUES(?,?,?,?,?,?,?,?,?,?)";

  $description = str_replace("<p>", "", $description); 
  $description = str_replace("</p>", "<br />", $description);
  $description = str_replace(",", "&#44; ", $description);
  $user_id=$_SESSION[$OJ_NAME.'_'.'user_id'];

  $cid = pdo_query($sql,$title,$starttime,$endtime,$codevisible,$private,$langmask,$description,$password,$user_id, $exam_mode);
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
    $title = $row['title'];

    $codevisible = $row['codevisible'];
    $private = $row['private'];
    $langmask = $row['langmask'];
    $description = $row['description'];
    $starttime = $row['start_time'];
    $endtime = $row['end_time'];
    $exam_mode = $row['exam_mode'];

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

  <div class="container">
    <form method=POST>
    <p align=left>
      <?php echo "<h3>".$MSG_CONTEST."-".$MSG_TITLE."</h3>"?>
      <input class="input input-xxlarge" style="width:100%;" type=text name=title value="<?php echo isset($title)?$title:""?>"><br><br>
    </p>
    <p align=left>
      <?php echo $MSG_CONTEST.$MSG_Start?>:
      <input class=input-large type=date name='startdate' value=
      <?php 
      if (isset($_GET['cid'])){
          echo date('Y-m-d',strtotime($starttime));
      }
      else{
          echo date('Y').'-'. date('m').'-'.date('d');
      } 
      ?> size=4 >
      Hour: <input class=input-mini type=text name=shour size=2 value=
      <?php 
      if (isset($_GET['cid'])){
        echo date('H',strtotime($starttime));
      }else{
        echo date('H');
      }
      ?>
      >&nbsp;
      Minute: <input class=input-mini type=text name=sminute value=
      <?php
      if (isset($_GET['cid'])){
        echo date('i',strtotime($starttime));
      }
      else{
          echo "00";
      }         
      ?>
      size=2 >
    </p>
    <p align=left>
      <?php echo $MSG_CONTEST.$MSG_End?>:
      <input class=input-large type=date name='enddate' value=
      <?php 
      if (isset($_GET['cid'])){
        echo date('Y-m-d',strtotime($endtime));
      }
      else{
          echo date('Y').'-'. date('m').'-'.date('d');
      }         
      ?> size=4 >
      Hour: <input class=input-mini type=text name=ehour size=2 value=
      <?php 
        if (isset($_GET['cid'])){
          echo date('H',strtotime($endtime));
        }
        else{
            echo (date('H')+4)%24;
        }
      ?>>&nbsp;
      Minute: <input class=input-mini type=text name=eminute value=
      <?php
      if (isset($_GET['cid'])){
        echo date('i',strtotime($endtime));
      }
      else{
          echo "00";
      }         
      ?>
      size=2 >
    </p>
    <br>
    <p align=left>
      <?php echo $MSG_CONTEST."-".$MSG_PROBLEM_ID?>
      <?php echo "( Add problemIDs with coma , )"?><br>
      <input id="plist" onchange="showTitles()" class=input-xxlarge placeholder="Example:1000,1001,1002" type=text style="width:100%" name=cproblem value="<?php echo isset($plist)?$plist:""?>">
      <div id="ptitles"></div>
    </p>
    <br>
    <p align=left>
      <?php echo "<h4>".$MSG_CONTEST."-".$MSG_Description."</h4>"?>
      <textarea class=kindeditor rows=13 name=description cols=80><?php echo isset($description)?$description:""?></textarea>
      <br>
      <table width="100%">
        <tr>
          <td rowspan=2>
            <p aligh=left>
              <?php echo $MSG_CONTEST."-".$MSG_LANG?>
              <?php echo "( Add PLs with Ctrl+click )"?><br>
              <?php echo $MSG_PLS_ADD?><br>
              <select name="lang[]" multiple="multiple" style="height:220px">
              <?php
              $lang_count = count($language_ext);
              $lang = (~((int)$langmask))&((1<<$lang_count)-1);

              if(isset($_COOKIE['lastlang'])) $lastlang=$_COOKIE['lastlang'];
              else $lastlang = 0;

              for($i=0; $i<$lang_count; $i++){
                echo "<option value=$i ".( $lang&(1<<$i)?"selected":"").">".$language_name[$i]."</option>";
              }
              ?>
              </select>
            </p>
          </td>
          <td height="10px">
            <p align=left>
              <?php echo $MSG_CONTEST."-".$MSG_Public?>:
              <select name=private style="width:80px;">
                <option value=0 <?php echo (isset($private)&&$private=='0')?'selected=selected':''?>><?php echo $MSG_Public?></option>
                <option value=1 <?php echo (isset($private)&&$private=='1')?'selected=selected':''?>><?php echo $MSG_Private?></option>
              </select>
              <?php echo $MSG_CONTEST."-".$MSG_CodePublic?>:
              <select name=codevisible style="width:80px;">
                <option value=0 <?php echo (isset($codevisible)&&$codevisible=='0')?'selected=selected':''?>><?php echo $MSG_CodePublic?></option>
                <option value=1 <?php echo (isset($codevisible)&&$codevisible=='1')?'selected=selected':''?>><?php echo $MSG_CodePrivate?></option>
              </select>
              <?php echo $MSG_CONTEST."-".$MSG_EXAMMODE?>:
              <select name="exam_mode" style="width:80px;">
                <option value=0 <?php echo (isset($exam_mode)&&$exam_mode=='0')?'selected=selected':''?>><?php echo $MSG_EXAMMODEOFF?></option>
                <option value=1 <?php echo (isset($exam_mode)&&$exam_mode=='1')?'selected=selected':''?>><?php echo $MSG_EXAMMODEON?></option>
              </select>
              <?php echo $MSG_CONTEST."-".$MSG_PASSWORD?>:
              <input type=text name=password style="width:80px;" value="">
            </p>
          </td>
        </tr>
        <tr>
          <td height="*">
            <p align=left>
              <?php echo $MSG_CONTEST."-".$MSG_USER?>
              <?php echo "( Add private contest's userIDs with newline &#92;n )"?>
              <br>
              <textarea name='ulist' rows='10' style='width:100%;' placeholder='user1<?php echo "\n"?>user2<?php echo "\n"?>user3<?php echo "\n"?>
              <?php echo $MSG_PRIVATE_USERS_ADD?><?php echo "\n"?>'><?php if(isset($ulist)){ echo $ulist;}?></textarea>
            </p>
          </td>
        </tr>
      </table>

      <div align=center>
        <?php require_once("../include/set_post_key.php");?>
        <input type=submit value='<?php echo $MSG_SAVE?>' name=submit>
      </div>
    </p>
  </form>
</div>

<script>
  // 복사 모드일 때, 기존 contest_problem.score 값을 채우기 위함
  const SCORE_PREFILL = <?php echo json_encode(isset($score_prefill)?$score_prefill:[]); ?>;

  async function showTitles(){
      let ts = document.querySelector("#ptitles");
      let pids_raw = document.querySelector("#plist").value;
      let pids = pids_raw.split(",").map(s => s.trim()).filter(s => s.length>0);
      let html = "";

      for (let v of pids) {
          let response = await fetch("ajax.php", {
              method: 'POST',
              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: new URLSearchParams({"pid":v, "m":"problem_get_title"})
          });

          let title = await response.text();
          if (v.length!==0){
            const pre = (SCORE_PREFILL && SCORE_PREFILL[v]) ? SCORE_PREFILL[v] : 100;
            html += `${v}:<a href='../problem.php?id=${v}' target='_blank'>${title}</a> -> 점수 : 
            <input type="number" name="cpoint[]" style="width:150px;" value="${pre}" min="0" step="1">
            `;
            html +=`<br>\n`;
          }
      }

      ts.innerHTML = html;
  }

  document.addEventListener("DOMContentLoaded", function(){
      showTitles();
  });
</script>
<?php }
require_once("../oj-footer.php");
?>
</body>
</html>
