<?php $show_title="에러메시지 - $OJ_NAME"; ?>
<?php require_once(__DIR__ . "/../../oj-header.php"); ?>
<div class="ui negative icon message">
  <i class="remove icon"></i>
  <div class="content">
    <div class="header" style="margin-bottom: 10px; ">
      <?php echo $view_errors?>
    </div>
      <!-- <p><%= err.details %></p> -->
    <p>
        <!-- <a href="<%= err.nextUrls[text] %>" style="margin-right: 5px; "><%= text %></a> -->
      <button onClick="history.back()">이전 페이지로 돌아가기</button>
    </p>
  </div>
</div>

<?php require_once(__DIR__ . "/../../oj-footer.php"); ?>
