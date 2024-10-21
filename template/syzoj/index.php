<?php $show_title="홈 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<div class="padding">
    <div class="ui three column grid">
        <div class="eleven wide column">
            <h4 class="ui top attached block header"><?php echo $MSG_NEWS;?></h4>
            <div class="ui bottom attached segment">
                <table class="ui very basic center aligned table">
                    <thead>
                        <tr>
                            <th width="60%"><?php echo $MSG_TITLE;?></th>
                            <th width="40%"><?php echo $MSG_SUBMIT_TIME;?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_news = "select * FROM `news` WHERE `defunct`!='Y' AND `title`!='faqs.cn' ORDER BY `importance` ASC,`time` DESC LIMIT 5";
                        $result_news = mysql_query_cache( $sql_news );
                        if ( $result_news ) {
                            foreach ( $result_news as $row ) {
                                echo "<tr>"."<td>"
                                    ."<a href=\"viewnews.php?id=".$row["news_id"]."\">"
                                    .$row["title"]."</a></td>"
                                    ."<td>".substr($row["time"],0,10)."</td>"."</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <h4 class="ui top attached block header"><?php echo $CODING_MSG_NEWS;?></h4>
            <div class="ui bottom attached segment">
                <table class="ui very basic center aligned table">
                    <thead>
                        <tr>
                            <th width="60%"><?php echo $MSG_TITLE;?></th>
                            <th width="40%"><?php echo $MSG_SUBMIT_TIME;?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_news = "select * FROM `coding_news` WHERE `defunct`!='Y' ORDER BY `importance` ASC,`time` DESC LIMIT 10";
                        $result_news = mysql_query_cache( $sql_news );
                        if ( $result_news ) {
                            foreach ( $result_news as $row ) {
                                echo "<tr>"."<td>"
                                    ."<a href=\"viewcodingnews.php?id=".$row["news_id"]."\">"
                                    .$row["title"]."</a></td>"
                                    ."<td>".substr($row["time"],0,10)."</td>"."</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <h4 class="ui top attached block header"><?php echo $MSG_RECENT_PROBLEM;?> </h4>
            <div class="ui bottom attached segment">
                <table class="ui very basic center aligned table">
                    <thead>
                        <tr>
                            <th width="60%"><?php echo $MSG_TITLE;?></th>
                            <th width="40%"><?php echo $MSG_SUBMIT_TIME;?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        $sql_problems = "select * FROM `problem` where defunct='N' ORDER BY `problem_id` DESC LIMIT 5";
                        $result_problems = mysql_query_cache( $sql_problems );
                        if ( $result_problems ) {
                            $i = 1;
                            foreach ( $result_problems as $row ) {
                                echo "<tr>"."<td>"
                                    ."<a href=\"problem.php?id=".$row["problem_id"]."\">"
                                    .$row["title"]."</a></td>"
                                    ."<td>".substr($row["in_date"],0,10)."</td>"."</tr>";
                            }
                        }
                    ?>
                    </tbody>
                </table>
            </div>
 
            
        </div>
        <div class="right floated five wide column">
        <h4 class="ui top attached block header"><?php echo $MSG_RECENT_CONTEST ;?></h4>
            <div class="ui bottom attached center aligned segment">
                <table class="ui very basic center aligned table">
                    <thead>
                        <tr>
                            <th><?php echo $MSG_CONTEST_NAME;?></th>
                            <th><?php echo $MSG_START_TIME;?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        $sql_contests = "select * FROM `contest` where defunct='N' ORDER BY `contest_id` DESC LIMIT 5";
                        $result_contests = mysql_query_cache( $sql_contests );
                        if ( $result_contests ) {
                            $i = 1;
                            foreach ( $result_contests as $row ) {
                                echo "<tr>"."<td>"
                                    ."<a href=\"contest.php?cid=".$row["contest_id"]."\">"
                                    .$row["title"]."</a></td>"
                                    ."<td>".$row["start_time"]."</td>"."</tr>";
                            }
                        }
                    ?>
                    </tbody>
                </table>
            </div>
            <h4 class="ui top attached block header"><?php echo $MSG_RANKLIST;?></h4>
            <div class="ui bottom attached segment">
                <table class="ui very basic aligned table" style="table-layout: fixed; ">
                    <thead>
                        <tr>
                            <th style="width: 20%; ">순위</th>
                            <th style="width: 55%; "><?php echo $MSG_USER_ID;?></th>
                            <th style="width: 25%; "><?php echo $MSG_SOVLED ;?></th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        $sql_users = "select * FROM `users` where defunct='N' ORDER BY `solved` DESC LIMIT 10";
                        $result_users = mysql_query_cache( $sql_users );


                        if ( $result_users ) {

                            $i = 1;
                            foreach ( $result_users as $row ) {
                                // 랭크 정보도 추가 
                                $solCnt = $row['solved']; // 통과 개수
                                
                                $rankX = 0; // 이미지 표시 위치
                                $rankY = 0; // 

                                $level_up_cnt = 0; // 레벨업하기 위한 문제 개수
                                $hobong = 0;        // 레벨의 호봉 최대치
                                
                                if($solCnt<50){
                                        // 10문제가 레벨업 기준값
                                        $level_up_cnt = 10;
                                        $rankX = ($solCnt - ($solCnt % $level_up_cnt))/$level_up_cnt;                             
                                }
                                else if($solCnt<98){
                                        // 8문제가 레벨업 기준값
                                        $level_up_cnt = 8;
                                        $rankX = (($solCnt-50) - (($solCnt-50)% $level_up_cnt))/$level_up_cnt;
                                        $rankY = 1;
                                }
                                else{
                                        // 6문제가 레벨업 기준값
                                        $level_up_cnt = 6;
                                        $rankX = (($solCnt-98) - (($solCnt-98)% $level_up_cnt))/$level_up_cnt;
                                        for($rankY = 2, $hobong = 6;$rankX>$level_up_cnt;){
                                                $rankX -=$level_up_cnt;
                                                $rankY++;
                                                $hobong++;
                                        }        
                                }

                                $rankX *=(-25);
                                $rankX .="px";
                                $rankY *=(-25);
                                $rankY .="px";

                                echo "<tr><td>".str_pad($i++,2,"0",STR_PAD_LEFT)."</td><td>"
                                    ."<div style='
                                    display:inline-block;
                                    width:25px;
                                    height:25px;
                                    background:url(../../../image/rank25.jpg);
                                    background-position: $rankX $rankY;
                                    '></div><a href=\"userinfo.php?user=".$row["user_id"]."\">"
                                    .$row["user_id"]."</a></td>"
                                    ."<td>".$row["solved"]."</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
