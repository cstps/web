<?php
require_once("admin-header.php");

// ============================================================
// 접근 권한 확인
// administrator 또는 password_setter만 접근 가능
// ============================================================
if (
	!(
		isset($_SESSION[$OJ_NAME.'_'.'administrator']) ||
		isset($_SESSION[$OJ_NAME.'_'.'password_setter'])
	)
){
	echo "<a href='../loginpage.php'>Please Login First!</a>";
	exit(1);
}

if(isset($OJ_LANG)){
	require_once("../lang/$OJ_LANG.php");
}


// ============================================================
// 현재 로그인 사용자가 최고관리자인지 확인
//
// administrator : 모든 사용자 비밀번호 변경 가능
// password_setter : 보호 권한 사용자는 변경 불가
// ============================================================
$is_admin = isset($_SESSION[$OJ_NAME.'_'.'administrator']);


// ============================================================
// 보호 대상 사용자 확인 함수
//
// 다음 권한 중 하나라도 있으면 보호 대상
// administrator
// contest_creator
// problem_editor
//
// 단, 최고관리자는 이 함수 결과와 관계없이 변경 가능
// ============================================================
function isProtectedPasswordUser($user_id){

	$sql = "
		SELECT user_id, rightstr
		FROM privilege
		WHERE user_id=?
		AND rightstr IN (
			'administrator',
			'contest_creator',
			'problem_editor'
		)
		LIMIT 1
	";

	$result = pdo_query($sql, $user_id);

	// 해당 권한 행이 하나라도 존재하면 보호 대상
	if(is_array($result) && count($result) > 0){
		return true;
	}

	return false;
}
?>

<title>Set Password</title>

<hr>

<center>
	<h3>
		<?php echo $MSG_USER."-".$MSG_SETPASSWORD?>
	</h3>
</center>


<div class='container'>

<?php

// ============================================================
// 비밀번호 변경 POST 처리
// ============================================================
if(isset($_POST['do'])){

	require_once("../include/check_post_key.php");
	require_once("../include/my_func.inc.php");


	// --------------------------------------------------------
	// 입력값 확인
	// --------------------------------------------------------
	$user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : "";
	$passwd  = isset($_POST['passwd']) ? $_POST['passwd'] : "";


	if(get_magic_quotes_gpc()){

		$user_id = stripslashes($user_id);
		$passwd  = stripslashes($passwd);

	}


	// --------------------------------------------------------
	// 사용자 ID 또는 비밀번호가 비어 있는 경우
	// --------------------------------------------------------
	if($user_id == "" || $passwd == ""){

		echo "
		<center>
			<div class='alert alert-danger' style='max-width:700px;'>
				사용자 ID와 비밀번호를 입력하세요.
			</div>
		</center>
		";

	}
	else{


		// ====================================================
		// 1. 최고관리자가 아닌 경우
		// 보호 사용자 여부를 먼저 확인
		// ====================================================
		if(!$is_admin && isProtectedPasswordUser($user_id)){

			echo "
			<center>
				<div class='alert alert-danger' style='max-width:700px;'>

					<strong>비밀번호 변경 불가</strong>

					<br><br>

					사용자 :

					<strong>".
					htmlspecialchars(
						$user_id,
						ENT_QUOTES,
						'UTF-8'
					)
					."</strong>

					<br><br>

					이 사용자는 다음 보호 권한 중 하나를 가지고 있습니다.

					<br>

					administrator /
					contest_creator /
					problem_editor

					<br><br>

					password_setter 권한으로는
					비밀번호를 변경할 수 없습니다.

				</div>
			</center>
			";

		}

		// ====================================================
		// 2. 변경 가능한 경우
		// ====================================================
		else{

			$passwd_hash = pwGen($passwd);


			// =================================================
			// 최고관리자
			//
			// 모든 사용자 변경 가능
			// =================================================
			if($is_admin){

				$sql = "
					UPDATE users
					SET password=?
					WHERE user_id=?
				";

				$changed = pdo_query(
					$sql,
					$passwd_hash,
					$user_id
				);

			}


			// =================================================
			// password_setter
			//
			// administrator
			// contest_creator
			// problem_editor
			//
			// 변경 불가
			//
			// PHP 검사뿐만 아니라 UPDATE에서도 다시 차단
			// =================================================
			else{

				$sql = "
					UPDATE users
					SET password=?
					WHERE user_id=?

					AND NOT EXISTS (

						SELECT 1
						FROM privilege

						WHERE privilege.user_id = users.user_id

						AND privilege.rightstr IN (
							'administrator',
							'contest_creator',
							'problem_editor'
						)

					)
				";

				$changed = pdo_query(
					$sql,
					$passwd_hash,
					$user_id
				);

			}


			// =================================================
			// 변경 결과
			// =================================================
			if($changed == 1){

				echo "
				<center>
					<div class='alert alert-success' style='max-width:700px;'>

						<strong>".
						htmlspecialchars(
							$user_id,
							ENT_QUOTES,
							'UTF-8'
						)
						."</strong>

						사용자의 비밀번호가 변경되었습니다.

					</div>
				</center>
				";

			}
			else{

				echo "
				<center>
					<div class='alert alert-danger' style='max-width:700px;'>

						비밀번호를 변경하지 못했습니다.

						<br><br>

						사용자가 존재하지 않거나
						변경할 수 없는 권한을 가진 사용자입니다.

					</div>
				</center>
				";

			}

		}

	}

}


// ============================================================
// 화면에 표시할 대상 사용자 결정
// ============================================================
$target_user_id = "";

if(isset($_GET['uid'])){

	$target_user_id = trim($_GET['uid']);

}
else if(isset($_POST['user_id'])){

	$target_user_id = trim($_POST['user_id']);

}


// ============================================================
// 화면에서 비밀번호 입력 영역을 표시할 것인지 결정
//
// 최고관리자
// → 항상 변경 가능
//
// password_setter
// → 보호 사용자는 변경 불가
// ============================================================
$is_protected = false;

if(!$is_admin && $target_user_id != ""){

	if(isProtectedPasswordUser($target_user_id)){
		$is_protected = true;
	}

}

?>


<form
	action="changepass.php"
	method="post"
	class="form-horizontal"
>


	<!-- ======================================================
	     사용자 ID
	     ====================================================== -->

	<div class="form-group">

		<label class="col-sm-offset-3 col-sm-3 control-label">

			<?php echo $MSG_USER_ID?>

		</label>


		<?php if(isset($_GET['uid'])) { ?>


			<div class="col-sm-3">

				<input
					name="user_id"
					class="form-control"
					value="<?php
						echo htmlspecialchars(
							$_GET['uid'],
							ENT_QUOTES,
							'UTF-8'
						);
					?>"
					type="text"
					required
				>

			</div>


		<?php } else if(isset($_POST['user_id'])) { ?>


			<div class="col-sm-3">

				<input
					name="user_id"
					class="form-control"
					value="<?php
						echo htmlspecialchars(
							$_POST['user_id'],
							ENT_QUOTES,
							'UTF-8'
						);
					?>"
					type="text"
					required
				>

			</div>


		<?php } else { ?>


			<div class="col-sm-3">

				<input
					name="user_id"
					class="form-control"
					placeholder="<?php echo $MSG_USER_ID."*"?>"
					type="text"
					required
				>

			</div>


		<?php } ?>

	</div>



	<?php if($is_protected) { ?>


		<!-- ==================================================
		     password_setter가 보호 사용자를 연 경우
		     ================================================== -->

		<div class="form-group">

			<div class="col-sm-offset-3 col-sm-6">

				<div class="alert alert-warning">

					<strong>
						비밀번호 변경 제한
					</strong>

					<br><br>

					이 사용자는

					<strong>
						관리자 / 대회관리자 / 문제관리자
					</strong>

					권한 중 하나를 가지고 있습니다.

					<br><br>

					password_setter 권한으로는
					이 사용자의 비밀번호를 변경할 수 없습니다.

				</div>

			</div>

		</div>


	<?php } else { ?>


		<!-- ==================================================
		     비밀번호
		     ================================================== -->

		<div class="form-group">

			<label class="col-sm-offset-3 col-sm-3 control-label">

				<?php echo $MSG_PASSWORD?>

			</label>


			<div class="col-sm-3">

				<input
					name="passwd"
					class="form-control"
					placeholder="<?php echo $MSG_PASSWORD."*"?>"
					type="password"
					autocomplete="off"
					required
				>

			</div>

		</div>



		<!-- ==================================================
		     저장 / 초기화 버튼
		     ================================================== -->

		<div class="form-group">

			<?php require_once("../include/set_post_key.php");?>


			<div class="col-sm-offset-4 col-sm-2">

				<button
					name="do"
					type="submit"
					value="do"
					class="btn btn-default btn-block"
				>

					<?php echo $MSG_SAVE?>

				</button>

			</div>


			<div class="col-sm-2">

				<button
					name="submit"
					type="reset"
					class="btn btn-default btn-block"
				>

					<?php echo $MSG_RESET?>

				</button>

			</div>

		</div>


	<?php } ?>


</form>

</div>