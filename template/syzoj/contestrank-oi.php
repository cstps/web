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
.time-wrapper {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 10px;
  margin-bottom: 10px;
}
</style>

<div style="margin-bottom:40px;">
  <!-- 제목은 가운데 정렬 -->
  <h1 style="text-align: center;">OI Mode RankList -- <?php echo $title ?></h1>

  <!-- 시간은 오른쪽 정렬 -->
  <div class="time-wrapper">
    <div class="time-label endtime">
      종료시간：<span id="endtime"><?php echo date("Y-m-d H:i:s", $end_time) ?></span>
    </div>
    <div class="time-label nowtime">
      현재시간：<span id="nowdate"><?php echo date("Y-m-d H:i:s")?></span>
    </div>
  </div>

  <div style="clear: both;"></div>

  <?php if ($can_see_all) echo "<a href=contestrank.xls.php?cid=$cid>Download</a>"; ?>
</div>

<?php
include("template/" .
    $OJ_TEMPLATE .
    "/contestrank_oi_table.php"
);
?>


<script>

  // ============================================================
  // SSE - 제출/채점 결과 변경 시 OI 랭킹 갱신
  // ============================================================

  // ============================================================
  // 랭킹 표 AJAX 갱신
  // ============================================================

  let rankingRefreshRunning = false;

  async function refreshRankingTable(version) {

    // 이미 갱신 중이면 중복 AJAX 방지
    if (rankingRefreshRunning) {
      return;
    }

    rankingRefreshRunning = true;

    try {

      const url =
          new URL(
              "contestrank-oi.php",
              window.location.href
          );


      url.searchParams.set(
          "cid",
          "<?php echo intval($cid); ?>"
      );


      url.searchParams.set(
          "ajax",
          "1"
      );


      if (version !== undefined) {

          url.searchParams.set(
              "rank_version",
              version
          );
      }


      const response = await fetch(
          url.toString(),
          {
              method: "GET",
              credentials: "same-origin",
              cache: "no-store"
          }
      );

      if (!response.ok) {
        throw new Error(
          "HTTP " + response.status
        );
      }

      const html =
        await response.text();


      // 받아온 전체 HTML을 브라우저 메모리에서 분석
      const parser =
        new DOMParser();

      const newDocument =
        parser.parseFromString(
          html,
          "text/html"
        );


      const newRankingArea =
        newDocument.getElementById(
          "ranking-area"
        );

      const currentRankingArea =
        document.getElementById(
          "ranking-area"
        );


      if (
        !newRankingArea ||
        !currentRankingArea
      ) {

        throw new Error(
          "ranking-area를 찾을 수 없습니다."
        );
      }


      // ==========================================
      // 실제 화면에서는 랭킹 영역만 교체
      // ==========================================

      currentRankingArea.innerHTML =
        newRankingArea.innerHTML;


      console.log(
        "Ranking table refreshed:",
        version
      );

    }
    catch (error) {

      console.error(
        "Ranking table refresh failed:",
        error
      );

    }
    finally {

      rankingRefreshRunning = false;

    }
  }

  if (typeof(EventSource) !== "undefined") {

    const rankingEventSource =
      new EventSource(
        "contest_process_stream.php?cid=<?php echo intval($cid); ?>"
      );


    rankingEventSource.addEventListener(
      "connected",
      function(event) {

        console.log(
          "OI Ranking SSE connected:",
          event.data
        );

      }
    );


    let rankingRefreshTimer = null;
    let latestRankingVersion = 0;


    rankingEventSource.addEventListener(
      "ranking_update",
      function(event) {

        console.log(
          "Ranking update:",
          event.data
        );


        // SSE 데이터에서 judge_count 버전 추출
        try {

          const data =
            JSON.parse(event.data);

          if (
            data &&
            data.version !== undefined
          ) {
            latestRankingVersion =
              data.version;
          }

        }
        catch (e) {

          console.error(
            "Ranking SSE data parse failed:",
            e
          );

        }


        // 연속 이벤트가 들어오면
        // 마지막 이벤트 기준으로 한 번만 갱신
        if (rankingRefreshTimer) {
          clearTimeout(
            rankingRefreshTimer
          );
        }


        rankingRefreshTimer =
          setTimeout(
            function() {

              refreshRankingTable(
                latestRankingVersion
              );

            },
            1500
          );

      }
    );


    rankingEventSource.onerror =
      function() {

        console.log(
          "OI Ranking SSE reconnecting..."
        );

      };

  }

</script>

<script type="text/javascript">
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
