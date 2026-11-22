<?php $show_title="사용자정보 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<style>
#avatar_container:before {
    content: "";
    display: block;
    padding-top: 100%;
    opacity: 0.5;
}
</style>
<div class="padding">
<div class="ui grid">
    <div class="row">
        <div class="five wide column">
            <div class="ui card" style="width: 100%; " id="user_card">
                <div class="image" id="avatar_container"
                     style="height:325px; display:flex; align-items:center; justify-content:center; background:#f3f4f6;"
                     title="기본 사용자 프로필">
                    <i class="user circle outline icon"
                       aria-hidden="true"
                       style="font-size:120px; color:#9ca3af; margin:0;"></i>
                </div>
                <div class="ui top attached block content">
                    <i class="check icon"></i>통과 : <?php echo $AC ?> 문제
                    <div style="float: right;"><i class="star icon"></i>순위: <?php echo $Rank ?></div>
                </div>
                
            </div>

        </div>
        <div class="eleven wide column">
            <div class="ui grid">
                <div class="row">
                    <div class="sixteen wide column">
                        <div class="ui grid">
                            <div class="eight wide column">
                                <div class="ui grid">
                                    <div class ="row">
                                        <div class="column">
                                            <h4 class="ui top attached block header">별명</h4>
                                            <div class="ui bottom attached header"><?php echo htmlspecialchars((string)$nick, ENT_QUOTES, 'UTF-8'); ?>
                                                
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="column">
                                           <h4 class="ui top attached block header">사용자ID</h4>
                                           <div class="ui bottom attached header"><?php echo htmlspecialchars((string)$user, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>
                                      <div class="row">
                                          <div class="column">
                                              <h4 class="ui top attached block header">Email</h4>
                                              <div class="ui bottom attached header" class="font-content"><?php echo htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8'); ?></div>
                                          </div>
                                      </div>
                                    <div class="row">
                                        <div class="column">
                                            <h4 class="ui top attached block header">소속 또는 학교</h4>
                                            
                                            <div class="ui bottom attached segment" class="font-content"><?php echo htmlspecialchars((string)$school, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>


                                    <!-- <div class="row">
                                        <div class="column">
                                            <h4 class="ui top attached block header">注册于</h4>
                                            <div class="ui bottom attached segment" class="font-content">
                                                <%= syzoj.utils.formatDate(show_user.register_time) %>
                                            </div>
                                        </div>
                                    </div> -->
                                </div>
                            </div>
                            <div class="eight wide column">
                                <div class="ui grid">
                                  <div class="row">
                                      <div class="column">
                                          <h4 class="ui top attached block header">통계</h4>
                                          <div class="ui bottom attached segment">
                                            <div id="pie_chart_legend"></div>
                                            <div style="width: 100%; height: 260px;  "><canvas style="width: 100%; height: 100%; " id="pie_chart"></canvas></div>
                                          </div>
                                      </div>
                                  </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="row">
            <div class="wide column">
                <h4 class="ui top attached block header">통과한 문제</h4>
                <div class="ui bottom attached segment">
                    <script language='javascript'>
                        function p(id,c,point){
                        document.write("<a href=problem.php?id="+id+">"+id+"( "+point+"점) </a>");
                        }
                        <?php $sql="SELECT `problem_id`,count(1) from solution where `user_id`=? and result=4 group by `problem_id` ORDER BY `problem_id` ASC";
                            
                        if ($result=pdo_query($sql,$user)){ 
                            foreach($result as $row){
                            // 문제에서 점수 부분을 구한다. 
                            $sql_pro_point = "SELECT `pro_point` from problem where `problem_id`=? ";
                            $result_pro_point = pdo_query($sql_pro_point,$row[0]);                                                        
                            // pdo_query 에서 쿼리의  ?에 해당하는 값을 pdo_query()의 두번째 매개변수로 전달하여 쿼리를 실행하면 결과는
                            // 연관배열 형태로 돌려주기 때문에  key와 value로 값을 참조하고 기본 array로 반환하기 때문에 [0][키값] 형태로
                            // 작성되어야 한다. 
                            $point = $result_pro_point[0]['pro_point'];
                            echo "p($row[0],$row[1],$point);";                                                        
                            }
                        }
                        ?>
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
$(function () {
  $('#user_card .image').dimmer({
    on: 'hover'
  });


  var pie = new Chart(document.getElementById('pie_chart').getContext('2d'), {
    aspectRatio: 1,
    type: 'pie',
    data: {
      datasets: [
        {
          data: [
            <?php
              foreach($view_userstat as $row){
              echo $row[1].",\n";
              }
            ?>
          ],
          backgroundColor: [
            "#32CD32",
            "#FA8072",
            "#DC143C",
            "#FF9912",
            "#8A2BE2",
            "#4169E1",
            "#DB7093",
            "#082E54",
            "#FFFF00",
          ]
        }
      ],
      labels: [
        <?php
          foreach($view_userstat as $row){
          echo "\"".$jresult[$row[0]]."\",\n";
          }
        ?>
      ]
    },
    options: {
      responsive: true,
      legend: {
        display: false
      },
      legendCallback: function (chart) {
  			var text = [];
  			text.push('<ul style="list-style: none; padding-left: 20px; margin-top: 0; " class="' + chart.id + '-legend">');

  			var data = chart.data;
  			var datasets = data.datasets;
  			var labels = data.labels;

  			if (datasets.length) {
  				for (var i = 0; i < datasets[0].data.length; ++i) {
  					text.push('<li style="font-size: 12px; width: 50%; display: block; color: #666; "><span style="width: 10px; height: 10px; display: inline-block; border-radius: 50%; margin-right: 5px; background-color: ' + datasets[0].backgroundColor[i] + '; "></span>');
  					if (labels[i]) {
  						text.push(labels[i]);
						text.push(' : ' + datasets[0].data[i]);
  					}
  					text.push('</li>');
  				}
  			}

  			text.push('</ul>');
  			return text.join('');
  		}
    },
  });

  document.getElementById('pie_chart_legend').innerHTML = pie.generateLegend();
});
</script>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
