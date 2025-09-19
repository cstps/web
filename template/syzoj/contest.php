<?php $show_title="Contest".$view_cid." - ".$view_title." - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
.ui.label.pointing.below.left::before { left: 12%; }
.ui.label.pointing.below.right::before { left: 88%; }
.ui.label.pointing.below.left { margin-bottom: 0; }
.ui.label.pointing.below.right { margin-bottom: 0; float: right; }
#back_to_contest { display: none; }
</style>

<div class="padding">
    <h1>Contest<?php echo $view_cid?> - <?php echo $view_title ?></h1>
    <div class="ui pointing below left label"><?php echo $view_start_time?></div>
    <div class="ui pointing below right label"><?php echo $view_end_time?></div>

    <div id="timer-progress" class="ui tiny indicating progress success" data-percent="50">
        <div class="bar" style="width: 0%; transition-duration: 300ms;"></div>
    </div>

    <div class="ui grid">
        <div class="row">
            <div class="column">
                <div class="ui buttons">
                    <?php
                        if ($exam_mode == 0 || isset($_SESSION[$OJ_NAME.'_administrator']) || isset($_SESSION[$OJ_NAME.'_contest_creator']) || isset($_SESSION[$OJ_NAME.'_source_browser']) || isset($_SESSION[$OJ_NAME.'_m'.$view_cid])) {
                    ?>
                    <a class="ui small blue button" href="contestrank.php?cid=<?php echo $view_cid?>">순위</a>
                    <a class="ui small yellow button" href="contestrank-oi.php?cid=<?php echo $view_cid?>">순위(점수반영)</a>
                    <a class="ui small positive button" href="status.php?cid=<?php echo $view_cid?>">제출정보</a>
                    <?php } else { ?>
                    <a class='ui small blue button'>수행평가 모드입니다. 세부적인 정보를 볼 수 없습니다.</a>
                    <?php } ?>
                </div>
                <div class="ui buttons right floated">
                    <?php
                        $now_ts = time();
                        if ($now_ts>$end_time)
                            echo "<span class=\"ui small button grey\">종료됨</span>";
                        else if ($now_ts<$start_time)
                            echo "<span class=\"ui small button red\">시작전</span>";
                        else
                            echo "<span class=\"ui small button green\">진행중</span>";

                        echo ($view_private=='0') ? "<span class=\"ui small button blue\">공개</span>" : "<span class=\"ui small button pink\">비공개</span>";
                    ?>
                    <span class="ui small button">현재시간：<span id=nowdate><?php echo date("Y-m-d H:i:s")?></span></span>
                </div>
            </div>
        </div>
        <?php if(!empty($view_description)){ ?>
        <div class="row">
            <div class="column">
                <h4 class="ui top attached block header">공지사항</h4>
                <div class="ui bottom attached segment font-content">
                    <?php echo $view_description?>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php
            if ($exam_mode == 1 && !isset($_SESSION[$OJ_NAME.'_administrator']) && !isset($_SESSION[$OJ_NAME.'_contest_creator']) && !isset($_SESSION[$OJ_NAME.'_source_browser']) && !isset($_SESSION[$OJ_NAME.'_m'.$view_cid])) {
                $th_status = "-";
                $th_accepted = "-";
                $th_submit = "-";
                $th_score = "-";
            } else {
                $th_status = "상태";
                $th_accepted = "정답";
                $th_submit = "제출";
                $th_score = "배점";
            }
        ?>

        <div class="row">
            <div class="column">
                <table class="ui selectable celled table">
                    <thead>
                        <tr>
                            <th class="one wide center aligned"><?php echo $th_status ?></th>
                            <th class="two wide center aligned">문제 번호</th>
                            <th class="center aligned">제목</th>
                            <th class="center aligned">출처</th>
                            <th class="one wide center aligned"><?php echo $th_accepted ?></th>
                            <th class="one wide center aligned"><?php echo $th_submit ?></th>
                            <th class="one wide center aligned"><?php echo $th_score ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            foreach($view_problemset as $row){
                                echo "<tr>";
                                foreach($row as $table_cell){
                                    echo "<td class=\"center aligned\">".$table_cell."</td>";
                                }
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    $('#timer-progress').progress({
        value: Date.now() / 1000 - <?php echo strtotime($view_start_time)?>,
        total: <?php echo (strtotime($view_end_time)- strtotime($view_start_time))?>
    });
});

setInterval(function() {
    $('#timer-progress').progress({
        value: Date.now() / 1000 - <?php echo strtotime($view_start_time)?>,
        total: <?php echo (strtotime($view_end_time)- strtotime($view_start_time))?>
    });
}, 5000);
</script>
<script src="include/sortTable.js"></script>
<script>
var diff = new Date("<?php echo date("Y/m/d H:i:s")?>").getTime() - new Date().getTime();
function clock() {
    var x = new Date(new Date().getTime() + diff);
    var y = x.getFullYear();
    var mon = x.getMonth() + 1;
    var d = x.getDate();
    var h = x.getHours();
    var m = x.getMinutes();
    var s = x.getSeconds();
    var n = y + "-" + mon + "-" + d + " " + (h >= 10 ? h : "0" + h) + ":" + (m >= 10 ? m : "0" + m) + ":" + (s >= 10 ? s : "0" + s);
    document.getElementById('nowdate').innerHTML = n;
    setTimeout("clock()", 1000);
}
clock();
</script>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
