<?php require("admin-header.php");

// 로그인 IP 변경은 사이트 관리자만 허용
if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit('로그인 IP를 변경할 권한이 없습니다.');
}

if (isset($OJ_LANG)) {
	require_once("../lang/$OJ_LANG.php");
}
?>

<title>User Set IP</title>
<hr>
<center><h3><?php echo $MSG_SET_LOGIN_IP?></h3></center>

<div class='container'>

<?php
require_once("../include/set_get_key.php");
$sql = "";
if (isset($_POST['do'])) {
    require_once("../include/check_post_key.php");

    if (
        !isset($_POST['user_id'], $_POST['ip']) ||
        !is_string($_POST['user_id']) ||
        !is_string($_POST['ip'])
    ) {
        http_response_code(400);
        exit('입력값이 올바르지 않습니다.');
    }

    $user_id = trim($_POST['user_id']);
    $ip = trim($_POST['ip']);

    if (
        $user_id === '' ||
        strlen($user_id) > 48
    ) {
        http_response_code(400);
        exit('사용자 ID가 올바르지 않습니다.');
    }

    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        http_response_code(400);
        exit('올바른 IPv4 또는 IPv6 주소를 입력해 주세요.');
    }

    // 실제 존재하는 활성 사용자만 변경 허용
    $users = pdo_query(
        "SELECT user_id FROM users
         WHERE user_id = ? AND defunct = 'N'
         LIMIT 1",
        $user_id
    );

    if ($users === false) {
        http_response_code(500);
        exit('사용자 정보를 확인할 수 없습니다.');
    }

    if (count($users) !== 1) {
        http_response_code(400);
        exit('존재하지 않거나 비활성화된 사용자입니다.');
    }

    $sql = "insert into loginlog (user_id,password,ip,time) value(?,?,?,now())";

    $result = pdo_query(
        $sql,
        $user_id,
        "set ip by " . $_SESSION[$OJ_NAME . "_user_id"],
        $ip
    );

    if ($result === false) {
        http_response_code(500);
        exit('로그인 IP 저장에 실패했습니다.');
    }

    echo "<center><h4 class='text-success'>User " .
        htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') .
        "'s Login IP Changed!</h4></center>";
}
?>

<form action=user_set_ip.php method=post class="form-horizontal">
	<div class="form-group">
		<label class="col-sm-offset-3 col-sm-3 control-label"><?php echo $MSG_USER_ID?></label>
		<?php if(isset($_GET['uid']) && is_string($_GET['uid'])) { ?>
		<div class="col-sm-3"><input name="user_id" class="form-control" value="<?php echo htmlspecialchars((string)$_GET['uid'], ENT_QUOTES, 'UTF-8'); ?>" type="text" required ></div>
<?php } else if(isset($_POST['user_id']) && is_string($_POST['user_id'])) { ?>
		<div class="col-sm-3"><input name="user_id" class="form-control" value="<?php echo htmlspecialchars((string)$_POST['user_id'], ENT_QUOTES, 'UTF-8'); ?>" type="text" required ></div>
		<?php } else { ?>
		<div class="col-sm-3"><input name="user_id" class="form-control" placeholder="<?php echo $MSG_USER_ID."*"?>" type="text" required ></div>
		<?php } ?>
	</div>

	<div class="form-group">
		<label class="col-sm-offset-3 col-sm-3 control-label"><?php echo "New IP"?></label>
		<?php if(isset($_POST['ip']) && is_string($_POST['ip'])) { ?>
		<div class="col-sm-3"><input name="ip" class="form-control" value="<?php echo htmlspecialchars((string)$_POST['ip'], ENT_QUOTES, 'UTF-8'); ?>" type="text"  autocomplete="off" required ></div>
		<?php } else { ?>
		<div class="col-sm-3"><input name="ip" class="form-control" placeholder="<?php echo "?.?.?.?*"?>" type="text"  autocomplete="off" required ></div>
		<?php } ?>
	</div>

	<div class="form-group">
		<?php require_once("../include/set_post_key.php");?>
		<div class="col-sm-offset-4 col-sm-2">
			<button name="do" type="hidden" value="do" class="btn btn-default btn-block" ><?php echo $MSG_SAVE; ?></button>
		</div>
		<div class="col-sm-2">
			<button name="submit" type="reset" class="btn btn-default btn-block"><?php echo $MSG_RESET?></button>
		</div>
	</div>
</form>

</div>

<?php
require("../oj-footer.php");
?>
