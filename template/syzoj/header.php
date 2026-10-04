<!DOCTYPE html>
<?php
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$url = basename($request_path);
$dir = basename(getcwd());
if ($dir == "discuss3") $path_fix = "../";
else $path_fix = "";
if (isset($OJ_NEED_LOGIN) && $OJ_NEED_LOGIN && (
    $url != 'loginpage.php' &&
    $url != 'lostpassword.php' &&
    $url != 'lostpassword2.php' &&
    $url != 'registerpage.php'
) && !isset($_SESSION[$OJ_NAME . '_' . 'user_id'])) {

    header("location:" . $path_fix . "loginpage.php");
    exit();
}

if ($OJ_ONLINE) {
    require_once($path_fix . 'include/online.php');
    $on = new online();
}

// ---------------------------------------------------------
// 수업·대회 메뉴 표시 정보
// ---------------------------------------------------------

$header_logged_in =
    isset($_SESSION[$OJ_NAME . '_user_id']);

$header_can_manage_course = false;

if ($header_logged_in) {

    if (isset($_SESSION[$OJ_NAME . '_administrator'])) {

        $header_can_manage_course = true;
    } else {

        $header_course_teacher_rows = pdo_query(
            "SELECT course_id
				 FROM course_teacher
				 WHERE user_id = ?
				   AND status = 1
				 LIMIT 1",
            $_SESSION[$OJ_NAME . '_user_id']
        );

        $header_can_manage_course = (
            $header_course_teacher_rows &&
            isset($header_course_teacher_rows[0]['course_id'])
        );
    }
}

$header_is_my_course_page =
    strpos($url, 'my_course_') === 0;

$header_is_course_manage_page =
    strpos($url, 'course_') === 0;

// $header_is_course_contest_menu = (
// 	$url === 'contest.php' ||
// 	$header_is_my_course_page ||
// 	$header_is_course_manage_page
// );
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
    <?php include("template/$OJ_TEMPLATE/css.php"); ?>
    <script src="<?php echo $OJ_CDN_URL ?>/include/jquery-latest.js"></script>

    </script>
</head>

<body style="position: relative; margin-top: 49px; height: calc(100% - 49px); overflow-y: overlay; ">
    <div class="ui fixed borderless menu" style="position: fixed; height: 49px; ">
        <div class="left menu">
            <?php if (isset($_SESSION[$OJ_NAME . '_' . 'user_id'])) { ?>
                <a href="/userinfo.php?user=<?php echo $_SESSION[$OJ_NAME . '_' . 'user_id'] ?>"
                    style="color: inherit; ">
                    <div
                        id="user-account-dropdown"
                        class="ui dropdown item">
                        <?php echo $_SESSION[$OJ_NAME . '_' . 'user_id']; ?>
                        <i class="dropdown icon"></i>
                        <div class="menu">
                            <a class="item" href="/mail.php"><?php echo $MSG_Message_Send; ?></a>
                            <a class="item" href="/modifypage.php"><i
                                    class="edit icon"></i><?php echo $MSG_REG_INFO; ?></a>
                            <?php if ($OJ_SaaS_ENABLE) { ?>
                                <?php if ($_SERVER['HTTP_HOST'] == $DOMAIN)
                                    echo  "<a class='item' href='http://" .  $_SESSION[$OJ_NAME . '_' . 'user_id'] . ".$DOMAIN'><i class='globe icon' ></i>MyOJ</a>"; ?>
                            <?php } ?>
                            <?php if (isset($_SESSION[$OJ_NAME . '_' . 'administrator']) || isset($_SESSION[$OJ_NAME . '_' . 'contest_creator']) || isset($_SESSION[$OJ_NAME . '_' . 'problem_editor']) || isset($_SESSION[$OJ_NAME . '_' . 'password_setter'])) { ?>
                                <a class="item" href="admin/"><i class="settings icon"></i><?php echo $MSG_ADMIN; ?></a>
                            <?php } ?>
                            <a class="item" href="logout.php"><i class="power icon"></i><?php echo $MSG_LOGOUT; ?></a>
                        </div>
                    </div>
                </a>
            <?php } else { ?>
                <div class="item">
                    <a class="ui button" style="margin-right: 0.5em; " href="/loginpage.php">
                        <?php echo $MSG_LOGIN ?>
                    </a>
                    <?php    // DB에서 확인하도록 수정
                    $sql = "SELECT `register` FROM `setting` ";
                    $reg_result = pdo_query($sql);
                    $reg_row =  $reg_result[0];

                    if ($reg_row['register'] == 1) { ?>
                        <a class="ui primary button" href="registerpage.php">
                            <?php echo $MSG_REGISTER ?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        <div class="ui container">
            <!-- <a class="header item" href="/"><span style="font-family: 'Exo 2'; font-size: 1.5em; font-weight: 500; "><?php echo $domain == $DOMAIN ? $OJ_NAME : ucwords($OJ_NAME) . "'s OJ" ?></span></a>
                        -->

            <a class="item <?php if ($url == "") echo "active"; ?>" href="/"><?php echo $MSG_HOME ?></a>
            <a class="item <?php if ($url == "problemset.php") echo "active"; ?>"
                href="/problemset.php"><?php echo $MSG_PROBLEMS ?> </a>
            <a class="item <?php if ($url == "drawproblemset.php") echo "active"; ?>"
                href="/drawproblemset.php"><?php echo $MSG_DRAWPROBLEMS ?> </a>

            <div
                id="course-contest-dropdown"
                class="ui dropdown item">
                🧑‍🏫수업·대회
                <i class="dropdown icon"></i>

                <div class="menu">

                    <?php
                    if ($header_logged_in) {
                    ?>

                        <a
                            class="item <?php
                                        if ($header_is_my_course_page) {
                                            echo 'active';
                                        }
                                        ?>"
                            href="/my_course_list.php">
                            <i class="book icon"></i>
                            내 수업
                        </a>

                    <?php
                    }
                    ?>

                    <a
                        class="item <?php
                                    if ($url === 'contest.php') {
                                        echo 'active';
                                    }
                                    ?>"
                        href="/contest.php<?php
                                                                    if ($header_logged_in) {
                                                                        echo '?my';
                                                                    }
                                                                    ?>">
                        <i class="trophy icon"></i>
                        대회 목록
                    </a>

                    <?php
                    if ($header_can_manage_course) {
                    ?>

                        <div class="divider"></div>

                        <a
                            class="item <?php
                                        if ($header_is_course_manage_page) {
                                            echo 'active';
                                        }
                                        ?>"
                            href="/course_list.php">
                            <i class="settings icon"></i>
                            수업 관리
                        </a>

                    <?php
                    }
                    ?>

                </div>

            </div>
            <a class="item <?php if ($url == "status.php") echo "active"; ?>" href="/status.php"><?php echo $MSG_STATUS ?></a>
            <a class="item <?php if ($url == "ranklist.php") echo "active"; ?>"
                href="/ranklist.php"><?php echo $MSG_RANKLIST ?></a>

            <a
                class="item"
                href="/class-share/admin/login.php">
                <i class="calendar icon"></i>행사관리
            </a>

            <?php
            // ------------------------------------------------------------
            // 유틸리티 메뉴 활성 상태
            //
            // /tools/ 아래의 모든 유틸리티 페이지를 같은 메뉴로 처리한다.
            // 기존 /tools.php 주소도 호환을 위해 활성 상태로 인정한다.
            // ------------------------------------------------------------

            $tools_request_path =
                parse_url(
                    isset($_SERVER['REQUEST_URI'])
                        ? $_SERVER['REQUEST_URI']
                        : '',
                    PHP_URL_PATH
                );

            $is_tools_page =
                $url === 'tools.php' ||
                strpos(
                    (string)$tools_request_path,
                    '/tools/'
                ) === 0;
            ?>

            <a
                class="item <?php echo $is_tools_page ? 'active' : ''; ?>"
                href="/tools/">
                <i class="wrench icon"></i><?php
                    echo isset($MSG_ULTILIST)
                        ? $MSG_ULTILIST
                        : "유틸리티";
                ?>
            </a>

            <!--<a class="item <?php //if ($url=="contest.php") echo "active";
                                ?>" href="/discussion/global"><i class="comments icon"></i> 讨论</a>-->
            <div
                id="help-dropdown"
                class="ui dropdown item"
                tabindex="0">

                <i class="question circle icon"></i>
                도움말
                <i class="dropdown icon"></i>

                <div class="menu">
                    <a
                        class="item <?php
                                    if ($url === "faqs.php") {
                                        echo "active";
                                    }
                                    ?>"
                        href="/faqs.php">
                        <i class="question icon"></i>
                        자주묻는질문
                    </a>

                    <?php if (isset($OJ_BBS) && $OJ_BBS): ?>
                        <a
                            class="item <?php
                                        if (
                                            $url === "discuss.php" ||
                                            $url === "bbs.php" ||
                                            $dir === "discuss3"
                                        ) {
                                            echo "active";
                                        }
                                        ?>"
                            href="/discuss.php">
                            <i class="comments icon"></i>
                            묻고 답하기
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (isset($_GET['cid'])) {
                $cid = intval($_GET['cid']);
            ?>
                <a id="back_to_contest" class="item active" href="/contest.php?cid=<?php echo $cid ?>"><i
                        class="arrow left icon"></i><?php echo $MSG_CONTEST . $MSG_PROBLEMS . $MSG_LIST ?></a>
            <?php } ?>

        </div>
        <script>
            jQuery(function($) {
                $(
                    '#course-contest-dropdown, ' +
                    '#user-account-dropdown, ' +
                    '#user-dev-dropdown, ' +
                    '#help-dropdown'
                ).dropdown({
                    on: 'click'
                });
            });
        </script>
    </div>
    <div style="margin-top: 28px; ">
        <div class="ui main container">