<!DOCTYPE html>
<?php 
	$url=basename($_SERVER['REQUEST_URI']);
	$dir=basename(getcwd());
	if($dir=="discuss3") $path_fix="../";
	else $path_fix="";
 	if(isset($OJ_NEED_LOGIN)&&$OJ_NEED_LOGIN&&(
                  $url!='loginpage.php'&&
                  $url!='lostpassword.php'&&
                  $url!='lostpassword2.php'&&
                  $url!='registerpage.php'
                  ) && !isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
 
           header("location:".$path_fix."loginpage.php");
           exit();
        }

	if($OJ_ONLINE){
		require_once($path_fix.'include/online.php');
		$on = new online();
	}
?>

<html lang="ko" style="position: fixed; width: 100%; overflow: hidden; ">

<head>
    <meta charset="utf-8">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta name="naver-site-verification" content="866e66a9030a529a02cccfa25e8268f6de840213" />
    <meta name="viewport" content="width=device-width, initial-scale=0.65">
    <meta name="description" content="online coding judge site for student">
    <!-- naver webmaster 24.10.15 -->
    <meta property="og:type" content="website"> 
    <meta property="og:title" content="1024 Online Judge Site">
    <meta property="og:description" content="초중고 학생 대상 실시간 코딩 채점 시스템">
    <meta property="og:image" content="./image/logo.png">
    <meta property="og:url" content="https://1024.kr">

    <title><?php echo $show_title ?></title>
    <?php include("template/$OJ_TEMPLATE/css.php");?>
    <script src="<?php echo $OJ_CDN_URL?>/include/jquery-latest.js"></script>

</script>
</head>

<body style="position: relative; margin-top: 49px; height: calc(100% - 49px); overflow-y: overlay; ">
    <div class="ui fixed borderless menu" style="position: fixed; height: 49px; ">
    <div class="left menu">
                    <?php if(isset($_SESSION[$OJ_NAME.'_'.'user_id'])) { ?>
                    <a href="<?php echo $path_fix?>/userinfo.php?user=<?php echo $_SESSION[$OJ_NAME.'_'.'user_id']?>"
                        style="color: inherit; ">
                        <div class="ui simple dropdown item">
                            <?php echo $_SESSION[$OJ_NAME.'_'.'user_id']; ?>
                            <i class="dropdown icon"></i>
                            <div class="menu">
                                <a class="item" href="<?php echo $path_fix?>mail.php"><?php echo $MSG_Message_Send;?></a>
                                <a class="item" href="<?php echo $path_fix?>modifypage.php"><i
                                        class="edit icon"></i><?php echo $MSG_REG_INFO;?></a>
                    <?php if ($OJ_SaaS_ENABLE){ ?>
                    <?php if($_SERVER['HTTP_HOST']==$DOMAIN)
                        echo  "<a class='item' href='http://".  $_SESSION[$OJ_NAME.'_'.'user_id'].".$DOMAIN'><i class='globe icon' ></i>MyOJ</a>";?>
                    <?php } ?>
                                <?php if(isset($_SESSION[$OJ_NAME.'_'.'administrator'])||isset($_SESSION[$OJ_NAME.'_'.'contest_creator'])||isset($_SESSION[$OJ_NAME.'_'.'problem_editor'])||isset($_SESSION[$OJ_NAME.'_'.'password_setter'])){ ?>
                                <a class="item" href="admin/"><i class="settings icon"></i><?php echo $MSG_ADMIN;?></a>
                                <?php } ?>
                                <a class="item" href="logout.php"><i class="power icon"></i><?php echo $MSG_LOGOUT;?></a>
                            </div>
                        </div>
                    </a>
                    <?php } else { ?>
                    <div class="item">
                        <a class="ui button" style="margin-right: 0.5em; " href="loginpage.php">
                        <?php echo $MSG_LOGIN?> 
                        </a>
                        <?php	// DB에서 확인하도록 수정
                            $sql="SELECT `register` FROM `setting` ";
                            $reg_result = pdo_query($sql);
                            $reg_row =  $reg_result[0];

                            if( $reg_row['register']==1){ ?>
                        <a class="ui primary button" href="registerpage.php">
                        <?php echo $MSG_REGISTER?> 
                        </a>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>    
        <div class="ui container">
           <!-- <a class="header item" href="/"><span style="font-family: 'Exo 2'; font-size: 1.5em; font-weight: 500; "><?php echo $domain==$DOMAIN?$OJ_NAME:ucwords($OJ_NAME)."'s OJ"?></span></a>
                        -->
                
	        <a class="item <?php if ($url=="") echo "active";?>" href="/"><?php echo $MSG_HOME?></a>
            <a class="item <?php if ($url=="problemset.php") echo "active";?>"
                href="<?php echo $path_fix?>problemset.php"><?php echo $MSG_PROBLEMS?> </a>
            <a class="item <?php if ($url=="drawproblemset.php") echo "active";?>"
                href="<?php echo $path_fix?>drawproblemset.php"><?php echo $MSG_DRAWPROBLEMS?> </a>
            <a class="item <?php if ($url=="category.php") echo "active";?>"
                href="<?php echo $path_fix?>category.php"><?php echo $MSG_SOURCE?></a>
            <a class="item <?php if ($url=="contest.php") echo "active";?>" href="<?php echo $path_fix?>contest.php<?php if(isset($_SESSION[$OJ_NAME."_user_id"])) echo "?my" ?>" >
                <?php echo $MSG_CONTEST?>
            </a>
            <a class="item <?php if ($url=="status.php") echo "active";?>" href="<?php echo $path_fix?>status.php"><?php echo $MSG_STATUS?></a>
            <a class="item <?php if ($url=="ranklist.php") echo "active";?>"
                href="<?php echo $path_fix?>ranklist.php"><?php echo $MSG_RANKLIST?></a>    
            <!-- 유틸리티 추가 -->
                
            
            <div class="ui simple dropdown item"><a class="item <?php if ($url=="pc.php") echo "active";?>" href="<?php echo $path_fix?>pc.php"><?php echo $MSG_ULTILIST?></a><i class="dropdown icon"></i>
            <ul class="menu">
                <a class="item" href="<?php echo $path_fix?>pc.php"><?php echo $MSG_POINTCHECK?></a>
                <a class="item" href="<?php echo $path_fix?>charcount.php"><?php echo $MSG_CHARCOUNT?></a>
                <a class="item" href="<?php echo $path_fix?>"><i class="edit icon"></i>개발중</a>
            </ul>
            </div>
            
            <!--<a class="item <?php //if ($url=="contest.php") echo "active";?>" href="/discussion/global"><i class="comments icon"></i> 讨论</a>-->
            <a class="item <?php if ($url=="faqs.php") echo "active";?>" href="<?php echo $path_fix?>faqs.php"></i> <?php echo $MSG_FAQ?></a>

              <?php if (isset($OJ_BBS)&& $OJ_BBS){ ?>
                  <a class='item' href="discuss.php"> <?php echo $MSG_BBS?></a>
              <?php }?>
            <?php if(isset($_GET['cid'])){
            	$cid=intval($_GET['cid']);
            ?>
            <a id="back_to_contest" class="item active" href="<?php echo $path_fix?>contest.php?cid=<?php echo $cid?>" ><i
                    class="arrow left icon"></i><?php echo $MSG_CONTEST.$MSG_PROBLEMS.$MSG_LIST?></a>
            <?php }?>
            
        </div>
    </div>
    <div style="margin-top: 28px; ">
        <div class="ui main container">
