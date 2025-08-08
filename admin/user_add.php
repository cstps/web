<?php
require_once("admin-header.php");

if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'password_setter']))) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

if (isset($OJ_LANG)) {
    require_once("../lang/$OJ_LANG.php");
}
?>

<title>Add User</title>
<hr>
<center><h3><?php echo $MSG_USER."-".$MSG_ADD?></h3></center>

<div class='container'>

<?php
// [ADD] school_list.json 읽어서 폼의 datalist에 뿌리기
$school_list_file = __DIR__ . "/../school_list.json";
$allowed_schools = [];
if (file_exists($school_list_file)) {
    $json_data = file_get_contents($school_list_file);
    $allowed_schools = json_decode($json_data, true);
    if (!is_array($allowed_schools)) $allowed_schools = [];
}
?>

<?php
if (isset($_POST['do'])) {
    require_once("../include/check_post_key.php");
    require_once("../include/my_func.inc.php");

    // 라인 분해 + 인쇄 불가문자 제거
    $pieces = preg_split("/\r\n|\r|\n/u", trim($_POST['ulist']));  
    $pieces = array_map(function($s){
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $s);  // 제어문자만 제거(탭/개행 제외)
    }, $pieces);

    $ulist = "";
    $error_occurred = false;  // 에러가 발생했는지 확인하는 플래그
    if (count($pieces) > 0 && strlen($pieces[0]) > 0) {
        for ($i = 0; $i < count($pieces); $i++) {
            $id_pw = preg_split('/\s+/', trim($pieces[$i]), 4); 

            // 아이디, 비밀번호, 닉네임이 최소 3개 이상이어야 한다
            if (count($id_pw) < 3) {
                echo "<span style='color: red;'>오류: 형식 오류! 예상 형식: user_id 비밀번호 닉네임 [학교] 순으로 아이디, 비밀번호, 닉네임은 필수입니다.</span><br>";
                $ulist .= implode(" ", $id_pw) . "\n";
                $error_occurred = true;
                continue;
            } else {
                $user = $id_pw[0];  // user_id
                $plain_pw = $id_pw[1];  // 비밀번호
                $nick = $id_pw[2];  // 닉네임
                $school = isset($id_pw[3]) ? trim($id_pw[3]) : '';  // 학교 정보가 생략되었을 경우 빈값 처리

                if (empty($school)) {
                    $school = "";  // 기본학교 설정 (빈 문자열로 설정)
                }

                // 학교 유효성 검사
                if (!empty($school)) {
                    $valid_school = false;
                    foreach ($allowed_schools as $allowed_school) {
                        $allowed_school_no_space = strtolower(preg_replace("/[^a-zA-Z0-9가-힣]/", "", $allowed_school));  
                        $school_no_space = preg_replace("/[^a-zA-Z0-9가-힣]/", "", $school); 

                        if ($allowed_school_no_space == $school_no_space) {
                            $valid_school = true;
                            break;
                        }
                    }

                    if (!$valid_school) {
                        echo "<span style='color: red;'>오류: ' " . htmlspecialchars($user) . "' 사용자의 학교명이 잘못되었습니다. 학교명을 확인해주세요.</span><br>";
                        $ulist .= $user . " " . $plain_pw . "\n";
                        $error_occurred = true;
                        continue;
                    }
                }

                // 기존 사용자 여부 확인
                $sql = "SELECT `user_id` FROM `users` WHERE `users`.`user_id` = ?";
                $result = pdo_query($sql, $user);
                $rows_cnt = count($result);

                if ($rows_cnt == 1) {
                    echo "<span style='color: red;'>오류: 사용자 '" . htmlspecialchars($user) . "'가 이미 존재합니다. 다른 아이디를 선택하세요.</span><br>";
                    $ulist .= $user . " " . $plain_pw . "\n";
                    $error_occurred = true;
                    continue;
                } else {
                    // 사용자 추가
                    $passwd = pwGen($plain_pw);
                    $sql = "INSERT INTO `users` (`user_id`, `password`, `reg_time`, `nick`, `school`) 
                            VALUES (?, ?, NOW(), ?, ?)";
                    pdo_query($sql, $user, $passwd, $nick, $school);
                    echo "<span style='color: green;'>" . htmlspecialchars($user) . " 사용자가 성공적으로 추가되었습니다!</span><br>";

                    $ip = ($_SERVER['REMOTE_ADDR']);
                    $sql = "INSERT INTO `loginlog` VALUES(?,?,?,NOW())";
                    pdo_query($sql, $user, "user added", $ip);
                }
            }
        }

        if (!$error_occurred) {
            echo "<br><span style='color: green;'>모든 사용자가 성공적으로 추가되었습니다!</span><hr>";
        } else {
            echo "<br><span style='color: red;'>일부 라인에 오류가 있습니다!</span><hr>";
        }
    }
}
?>


<form action="user_add.php" method="post" class="form-horizontal">
    <div>
        <label class="col-sm">
            <?php echo $MSG_USER_ID ?> <?php echo $MSG_PASSWORD ?>
            <span style="color:#888;">(선택) 각 줄 뒤에 school을 적을 수 있고, 비우면 아래 공백으로 적용됩니다.</span>
        </label>
    </div>
    <div>
        <?php echo "( 사용자 아이디, 비번, 닉네임, [학교정보 생략가능] 순으로 입력 후 엔터 후 여러명 가능)" ?>
        <br>
        <?php echo "학교 정보를 통해 학교별 순위를 볼 수 있음" ?>
        <br>
        <table width="100%">
            <tr>
                <td height="*">
                    <p align="left">
                        <textarea name='ulist' rows='10' style='width:100%;' placeholder='
userid1 password1 nick1 경남온라인학교
userid2 password2 nick2              ← school 비우면 학교생략
userid3 password3 nick3 경남과학고등학교
<?php echo $MSG_PRIVATE_USERS_ADD ?><?php echo "\n" ?>'><?php if (isset($ulist)) { echo htmlspecialchars($ulist); } ?></textarea>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="form-group">
        <?php require_once("../include/set_post_key.php"); ?>
        <div class="col-sm-offset-4 col-sm-2">
            <button name="do" type="hidden" value="do" class="btn btn-default btn-block"><?php echo $MSG_SAVE ?></button>
        </div>
        <div class="col-sm-2">
            <button name="submit" type="reset" class="btn btn-default btn-block"><?php echo $MSG_RESET ?></button>
        </div>
    </div>
</form>

<
