<?php $show_title = "Contest RankList -- ".$title." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>
<style>
.submit_time {
    font-size: 0.8em;
    margin-top: 5px;
    color: #000;
}
</style>

<?php
$can_see_all = (
  isset($_SESSION[$OJ_NAME.'_administrator']) ||
  isset($_SESSION[$OJ_NAME."_m$cid"]) ||
  isset($_SESSION[$OJ_NAME.'_source_browser']) ||
  isset($_SESSION[$OJ_NAME.'_contest_creator'])
);
?>

<div style="margin-bottom:40px;">
  <h1 style="text-align: center;">Contest RankList -- <?php echo $title ?></h1>
  <?php if ($can_see_all) echo "<a href=contestrank.xls.php?cid=$cid>Download</a>"; ?>
</div>

<div class="padding" style="overflow-y:auto;">
  <?php if ($user_cnt > 0) { ?>
    <table class="ui very basic center aligned table" style="margin:30px">
      <thead>
        <tr>
          <th>순번</th>
          <th>사용자ID</th>
          <th>별명</th>
          <th>통과</th>
          <th>시간 패널티</th>
          <?php for ($i=0; $i<$pid_cnt; $i++) echo "<th><a href=problem.php?cid=$cid&pid=$i>$PID[$i]</a></th>"; ?>
          <th></th>
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

          // 핵심 분기: exam_mode가 아니면 다 보여주고, 수행평가면 본인/관리자만 상세 정보
          $can_see_detail = ($exam_mode != 1) || $can_see_all || $is_me;

          echo "<tr>";

          // 순위 표시
          echo "<td>";
          if ($nick[0] != "*") {
            if ($rank == 1) echo "<div class=\"ui yellow ribbon label\">";
            else if ($rank <= 3) echo "<div class=\"ui ribbon label\">";
            else if ($rank <= 5) echo "<div class=\"ui brown ribbon label\">";
            else echo "<div>";
            echo $rank++;
            echo "</div>";
          } else {
            echo "*";
          }
          echo "</td>";

          // 사용자 ID
          echo "<td>";
          if (isset($_GET['user_id']) && $uuid == $_GET['user_id']) echo "<td bgcolor=#ffff77>";
          echo "<a name=\"$uuid\" href=userinfo.php?user=$uuid>$uuid</a>";
          echo "</td>";

          // 닉네임
          echo "<td><a href=userinfo.php?user=$uuid>".htmlentities($nick, ENT_QUOTES, "UTF-8")."</a></td>";

          // 통과 문제 수
          echo "<td><a href=status.php?user_id=$uuid&cid=$cid>$usolved</a></td>";

          // 시간 패널티
          echo "<td>";
          echo $can_see_detail ? sec2str($U[$i]->time) : "-";
          echo "</td>";

          // 문제별 결과
          for ($j = 0; $j < $pid_cnt; $j++) {
            if (!isset($U[$i])) {
              echo "<td></td>";
              continue;
            }

            $wa = $U[$i]->p_wa_num[$j] ?? 0;
            $ac = $U[$i]->p_ac_sec[$j] ?? 0;

            if ($ac > 0) {
              if ($uuid == $first_blood[$j]) {
                echo "<td style=\"background: rgb(".(150+12*$wa).",255,".(150+8*$wa)."); position:relative;\">";
                echo "<div style=\"position:absolute;width:30%;margin-top:5%;margin-right:5%;height:30%;right:0px;top:0px;\">※1st</div>";
              } else {
                echo "<td style=\"background: rgb(".(150+12*$wa).",255,".(150+8*$wa).");\">";
              }

              echo "<span class=\"score score_10\">";
              echo $can_see_detail ? "+$wa" : "+";
              echo "</span>";

              echo "<div class=\"submit_time\">";
              echo $can_see_detail ? sec2str($ac) : "-";
              echo "</div>";
            }
            else if ($wa > 0) {
              echo "<td style=\"background: rgb(255,".(240-9*$wa).",".(240-9*$wa).");\">";
              echo "<span class=\"score score_0\">";
              echo $can_see_detail ? "-$wa" : "-";
              echo "</span>";
            }
            else {
              echo "<td>";
            }

            echo "</td>";
          }

          echo "<td></td>";
          echo "</tr>";
        }
        ?>
      </tbody>
    </table>
  <?php } else { ?>
    <div style="background-color: #fff; height: 18px; margin-top: -18px;"></div>
    <div class="ui placeholder segment" style="margin-top: 0px;">
      <div class="ui icon header">
        <i class="ui file icon" style="margin-bottom: 20px;"></i>
        제출한 참가자 없음
      </div>
    </div>
  <?php } ?>
</div>

<!-- 5초 간격으로 랭킹 정보 갱신 -->
<script type="text/javascript">
  setInterval(function() {
    $(".padding").load(location.href + " .padding>*", "");
  }, 5000);
</script>

<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
