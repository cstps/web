<?php $show_title="최근대회 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>

    <table class="ui very basic center aligned table">
      <thead>
        <tr>
        <th>OJ</th>
        <th>대회이름</th>
        <th>시작시간</th>
        <th>주</th>
        <th>Access</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $odd=true;
        foreach($rows as $row) {
        ?>
          <tr>
            <td><?php echo$row['oj']?></td>
            <td><a href="<?php echo$row['link']?>" target="_blank"><?php echo$row['name']?></a></td>
            <td><?php echo$row['start_time']?></td>
            <td><?php echo$row['week']?></td>
            <td><?php echo$row['access']?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    <div>최근 대회 정보：<a href="http://contests.acmicpc.info/contests.json" target="_blank">http://contests.acmicpc.info/contests.json</a>&nbsp;&nbsp;&nbsp;&nbsp;作者：<a href="http://contests.acmicpc.info"  target="_blank" >doraemonok</a></div>

<?php include("template/$OJ_TEMPLATE/footer.php");?>