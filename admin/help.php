<?php require_once("admin-header.php");
  if(isset($OJ_LANG)){
    require_once("../lang/$OJ_LANG.php");
  }
?>
<html>
<head>
  <title><?php echo $MSG_ADMIN?></title>
</head>

<body>
  <br>
<table class="table">
  <tbody>
    <tr>
      <td><a class='btn btn-block btn-sm' href="../status.php" target="_top"><b><?php echo $MSG_SEEOJ?></b></a></td>
      <td><p><?php echo $MSG_HELP_SEEOJ?></p></td>
    </tr>

  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
    <tr>
      <td><center><a class='btn btn-info btn-sm' href="setmsg.php" target="main"><b><?php echo $MSG_NEWS."-".$MSG_SETMESSAGE?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_SETMESSAGE?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-info btn-sm' href="news_list.php" target="main"><b><?php echo $MSG_NEWS."-".$MSG_LIST?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_NEWS_LIST?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-info btn-sm' href="news_add_page.php" target="main"><b><?php echo $MSG_NEWS."-".$MSG_ADD?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_ADD_NEWS?></p></td>
    </tr>
  <?php }?>

  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset( $_SESSION[$OJ_NAME.'_'.'password_setter'] )){?>
    <tr>
      <td><center><a class='btn btn-primary btn-sm' href="user_list.php" target="main"><b><?php echo $MSG_USER."-".$MSG_LIST?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_USER_LIST?></p></td>
    </tr>
  <?php }?>
  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
    <tr>
      <td><center><a class='btn btn-primary btn-sm' href="user_add.php" target="main"><b><?php echo $MSG_USER."-".$MSG_ADD?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_USER_ADD?></p></td>
    </tr>
  <?php }?>
  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset( $_SESSION[$OJ_NAME.'_'.'password_setter'] )){?>
    <tr>
      <td><center><a class='btn btn-primary btn-sm' href="changepass.php" target="main"><b><?php echo $MSG_USER."-".$MSG_SETPASSWORD?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_SETPASSWORD?></p></td>
    </tr>
  <?php }?>

  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
    <tr>
      <td><center><a class='btn btn-primary btn-sm' href="privilege_list.php" target="main"><b><?php echo $MSG_USER."-".$MSG_PRIVILEGE."-".$MSG_LIST?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_PRIVILEGE_LIST?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-primary btn-sm' href="privilege_add.php" target="main"><b><?php echo $MSG_USER."-".$MSG_PRIVILEGE."-".$MSG_ADD?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_ADD_PRIVILEGE?></p></td>
    </tr>
  <?php }?>

  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])){?>
    <tr>
      <td><center><a class='btn btn-success btn-sm' href="problem_list.php" target="main"><b><?php echo $MSG_PROBLEM."-".$MSG_LIST?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_PROBLEM_LIST?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-success btn-sm' href="problem_add_page.php" target="main"><b><?php echo $MSG_PROBLEM."-".$MSG_ADD?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_ADD_PROBLEM?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-success btn-sm' href="problem_import.php" target="main"><b><?php echo $MSG_PROBLEM."-".$MSG_IMPORT?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_IMPORT_PROBLEM?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-success btn-sm' href="problem_export.php" target="main"><b><?php echo $MSG_PROBLEM."-".$MSG_EXPORT?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_EXPORT_PROBLEM?></p></td>
    </tr>
    <tr>
      <td><center><a a class='btn btn-success btn-sm' href="problem_copy.php" target="main" ><b><?php echo $MSG_PROBLEM."-".$MSG_EXTERN_PROBLEM?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_EXTERN_PROBLEM?></p></td>
    </tr>
    <tr>
      <td><center><a a class='btn btn-success btn-sm' href="problem_changeid.php" target="main" ><b><?php echo $MSG_PROBLEM."-".$MSG_CHANGENUM_PROBLEM?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_CHANGENUM_PROBLEM?></p></td>
    </tr>
  <?php }?>
  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])){?>
    <tr>
      <td><center><a class='btn btn-warning btn-sm' href="contest_list.php" target="main"><b><?php echo $MSG_CONTEST."-".$MSG_LIST?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_CONTEST_LIST?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-warning btn-sm' href="contest_add.php" target="main"><b><?php echo $MSG_CONTEST."-".$MSG_ADD?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_ADD_CONTEST?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-warning btn-sm' href="user_set_ip.php" target="main"><b><?php echo $MSG_CONTEST."-".$MSG_SET_LOGIN_IP?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_SET_LOGIN_IP?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-warning btn-sm' href="team_generate.php" target="main"><b><?php echo $MSG_CONTEST."-".$MSG_TEAMGENERATOR?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_TEAMGENERATOR?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-warning btn-sm' href="team_generate2.php" target="main"><b><?php echo $MSG_CONTEST."-".$MSG_TEAMGENERATOR?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_TEAMGENERATOR?></p></td>
    </tr>
  <?php }?>

  <?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])){?>
    <tr>
      <td><center><a class='btn btn-danger btn-sm' href="rejudge.php" target="main"><b><?php echo $MSG_SYSTEM."-".$MSG_REJUDGE?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_REJUDGE?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-danger btn-sm' href="source_give.php" target="main"><b><?php echo $MSG_SYSTEM."-".$MSG_GIVESOURCE?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_GIVESOURCE?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-danger btn-sm' href="../online.php" target="main"><b><?php echo $MSG_SYSTEM."-".$MSG_HELP_ONLINE?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_ONLINE?></p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-danger btn-sm' href="update_db.php" target="main"><b><?php echo $MSG_SYSTEM."-".$MSG_UPDATE_DATABASE?></b></a></center></td>
      <td><p><?php echo $MSG_HELP_UPDATE_DATABASE?></p></td>
    </tr>
    <tr>
      <td><a class='btn btn-block btn-sm' href="https://github.com/zhblue/hustoj/" target="_blank"><b>HUSTOJ</b></a></td>
      <td><p>HUSTOJ</p></td>
    </tr>
    <tr>
      <td><center><a class='btn btn-sm' target='_blank' href="https://github.com/zhblue/hustoj/blob/master/wiki/FAQ.md" target="main"><?php echo $MSG_ADMIN." ".$MSG_FAQ?></a></center></td>
      <td><p><?php echo $MSG_ADMIN." ".$MSG_FAQ?></p></td>
    </tr>
    <tr>
      <td><a class='btn btn-block btn-sm' href="https://github.com/zhblue/freeproblemset/" target="_blank"><b>FreeProblemSet</b></a></td>
      <td><p>FreeProblemSet</p></td>
    </tr>

    <!-- 제거
    <tr>
      <td><a class='btn btn-block btn-sm' href="http://tk.hustoj.com" target="_blank"><b>문제은행</b></a></td>
      <td><p></p></td>
    </tr>
    <tr>
      <td><a class='btn btn-block btn-sm' href="http://shang.qq.com/wpa/qunwpa?idkey=d52c3b12ddaffb43420d308d39118fafe5313e271769277a5ac49a6fae63cf7a" target="_blank">手机QQ加官方群23361372</a></td>
      <td><p></p></td>
    </tr>
  -->
  <?php }?>
  </tbody>
</table>
<!-- 위치 이동
<?php if (isset($_SESSION[$OJ_NAME.'_'.'administrator'])&&!$OJ_SAE){?>
  <a a class='btn btn-block btn-sm' href="problem_copy.php" target="main" title="Create your own data">CopyProblem</a> <br>
  <a a class='btn btn-block btn-sm' href="problem_changeid.php" target="main" title="Danger,Use it on your own risk">ReOrderProblem</a>
<?php }?>
-->
</body>
</html>
