<?php $show_title="에러메시지 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<div class="ui negative icon message">
  <i class="remove icon"></i>
  <div class="content">
    <div class="header" style="margin-bottom: 10px; ">
      <?php echo $view_errors?>
    </div>
      <!-- <p><%= err.details %></p> -->
    <p>
        <!-- <a href="<%= err.nextUrls[text] %>" style="margin-right: 5px; "><%= text %></a> -->
      <a href="/">마지막페이지로 돌아가기</a>
    </p>
  </div>
</div>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
