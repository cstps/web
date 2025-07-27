<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="">
  <meta name="author" content="">
  <link rel="icon" href="../../favicon.ico">

  <title><?php echo $OJ_NAME ?></title>
  <?php include("template/$OJ_TEMPLATE/css.php"); ?>

  <!--[if lt IE 9]>
    <script src="template/<?php echo $OJ_TEMPLATE ?>/js/html5shiv.js"></script>
    <script src="template/<?php echo $OJ_TEMPLATE ?>/js/respond.min.js"></script>
  <![endif]-->
</head>

<?php include(dirname(__FILE__) . "/header.php"); ?>

<?php
$can_see_all = (
  isset($_SESSION[$OJ_NAME.'_administrator']) ||
  isset($_SESSION[$OJ_NAME."_m$cid"]) ||
  isset($_SESSION[$OJ_NAME.'_source_browser']) ||
  isset($_SESSION[$OJ_NAME.'_contest_creator'])
);
$formatted_end_time = is_numeric($end_time)
  ? date("Y-m-d H:i:s", $end_time)
  : date("Y-m-d H:i:s", strtotime($end_time));
?>
<style>
.time-label {
  display: inline-block;
  padding: 6px 12px;
  margin-left: 5px;
  border-radius: 6px;
  color: white;
  font-weight: bold;
  font-size: 0.9em;
}
.endtime {
  background-color: #e74c3c; /* 빨강 */
}
.nowtime {
  background-color: #3498db; /* 파랑 */
}
</style>

<div class="container">
  <div class="jumbotron">
    
    <h3>OI Mode RankList -- <?php echo $title ?></h3>
    <div style="float:right; margin-top: -30px; margin-right: 10px;">
      <span class="time-label endtime">종료시간: <span id="endtime"><?php echo $formatted_end_time; ?></span></span>
      <span class="time-label nowtime">현재시간: <span id="nowdate"><?php echo date("Y-m-d H:i:s") ?></span></span>
    </div>
    <div style="clear: both;"></div>
    <?php if ($can_see_all) echo "<a href='/contestrank.xls.php?cid=$cid'>Download</a>"; ?>


    <div style="overflow: auto">
      <table id="rank" class="ui very basic center aligned table">
        <thead>
          <tr>
            <th width=5%>Rank</th>
            <th width=10%>User</th>
            <th width=10%>Nick</th>
            <th width=5%>Solved</th>
            <th width=5%>Penalty</th>
            <th>Total</th>
            <?php
            for ($i = 0; $i < $pid_cnt; $i++) {
              $score = isset($score_map[$i]) ? $score_map[$i] : 100;
              echo "<th><a href='problem.php?cid=$cid&pid=$i'>$PID[$i]<br/>($score)</a></th>";
            }
            ?>
          </tr>
        </thead>
        <tbody>
        <?php
        $rank = 1; 
        for ($i = 0; $i < $user_cnt; $i++) {
          $uuid = $U[$i]->user_id;
          $nick = $U[$i]->nick;
          $usolved = $U[$i]->solved;
          $is_me = ($uuid == $_SESSION[$OJ_NAME.'_user_id']);
          $can_see_detail = ($exam_mode != 1) || $can_see_all || $is_me;

          echo $i & 1 ? "<tr class=oddrow align=center>\n" : "<tr class=evenrow align=center>\n";
          
          echo "<td>";
          if ($nick[0] != "*") echo $rank++;
          else echo "*";
          echo "</td>";

          echo "<td>";
          if (isset($_GET['user_id']) && $uuid == $_GET['user_id']) echo "<td bgcolor=#ffff77>";
          echo "<a name=\"$uuid\" href=userinfo.php?user=$uuid>$uuid</a>";
          echo "</td>";

          echo "<td><a href=userinfo.php?user=$uuid>" . htmlentities($nick, ENT_QUOTES, "UTF-8") . "</a></td>";
          echo "<td><a href=status.php?user_id=$uuid&cid=$cid>$usolved</a></td>";

          echo "<td>" . ($can_see_detail ? sec2str($U[$i]->time) : "-") . "</td>";
          echo "<td>" . ($can_see_detail ? round($U[$i]->total) : "-") . "</td>";


          for ($j = 0; $j < $pid_cnt; $j++) {
            $bg_color = "eeeeee";
            $score = isset($score_map[$j]) ? $score_map[$j] : 100;

            if (isset($U[$i]->p_ac_sec[$j]) && $U[$i]->p_ac_sec[$j] > 0) {
              $aa = 0x33 + $U[$i]->p_wa_num[$j] * 32;
              $aa = min($aa, 0xaa);
              $aa = dechex($aa);
              $bg_color = "$aa" . "ff" . "$aa";
              if ($uuid == $first_blood[$j]) $bg_color = "aaaaff";
            } else if (isset($U[$i]->p_wa_num[$j]) && $U[$i]->p_wa_num[$j] > 0) {
              $aa = 0xaa - $U[$i]->p_wa_num[$j] * 10;
              $aa = max($aa, 16);
              $aa = dechex($aa);
              $bg_color = "ff$aa$aa";
            }

            echo "<td class=well style='background-color:#$bg_color'>";
            if (isset($U[$i])) {
              if ($can_see_detail) {
                $wa = isset($U[$i]->p_wa_num[$j]) ? $U[$i]->p_wa_num[$j] : 0;
                $ac = isset($U[$i]->p_ac_sec[$j]) ? $U[$i]->p_ac_sec[$j] : 0;
                $submit_count = $ac > 0 ? $wa + 1 : $wa;

                if ($ac > 0) {
                  echo "$score($submit_count)";
                } else if ($wa > 0) {
                  $partial = intval(round($U[$i]->p_pass_rate[$j] * $score));
                  echo "($partial)($submit_count)";
                } else {
                  echo "";
                }
              } else {
                if (isset($U[$i]->p_ac_sec[$j]) && $U[$i]->p_ac_sec[$j] > 0)
                  echo "✔";
                else if (isset($U[$i]->p_wa_num[$j]) && $U[$i]->p_wa_num[$j] > 0)
                  echo "+";
                else
                  echo "";
              }
            }

            echo "</td>";
          }
          echo "</tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script type="text/javascript">
  setInterval(function () {
    $("#rank").load(location.href + " #rank>*", "");
  }, 5000);

  var diff = new Date("<?php echo date("Y/m/d H:i:s") ?>").getTime() - new Date().getTime();
  function clock() {
    var x = new Date(new Date().getTime() + diff);
    var y = x.getFullYear();
    var mon = x.getMonth() + 1;
    var d = x.getDate();
    var h = x.getHours();
    var m = x.getMinutes();
    var s = x.getSeconds();
    var n = y + "-" + (mon >= 10 ? mon : "0" + mon) + "-" + (d >= 10 ? d : "0" + d) + " " + (h >= 10 ? h : "0" + h) + ":" + (m >= 10 ? m : "0" + m) + ":" + (s >= 10 ? s : "0" + s);
    document.getElementById('nowdate').innerHTML = n;
    setTimeout(clock, 1000);
  }
  clock();


</script>

<?php include(dirname(__FILE__) . "/footer.php"); ?>
</html>
