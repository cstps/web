<?php
require_once("./include/db_info.inc.php");
require_once("./include/cookie_helper.php"); // 안전 쿠키 헬퍼
require_once("./include/setlang.php");

$use_cookie = false;
$login = false;

// ------------------------------
// 1) 쿠키 로그인 검증
// ------------------------------
if ($OJ_COOKIE_LOGIN === true
    && isset($_COOKIE[$OJ_NAME . "_user"])
    && isset($_COOKIE[$OJ_NAME . "_check"])) {

    $C_check = (string)$_COOKIE[$OJ_NAME . "_check"];
    $C_user  = (string)$_COOKIE[$OJ_NAME . "_user"];
    $use_cookie = true;

    // 빈 쿠키 가드
    $chk_len = strlen($C_check);
    if ($chk_len < 1) {
        safe_expire_cookie($OJ_NAME . "_check", "/");
        safe_expire_cookie($OJ_NAME . "_user",  "/");
        echo "<script>alert('Cookie비활성화 또는 오류!(-1a)');history.go(-1);</script>";
        exit(0);
    }

    // 마지막 자리 숫자 검증
    $C_num = ($chk_len - 1);
    $C_num = ($C_num * $C_num) % 7;
    if ($C_check[$chk_len - 1] != $C_num) {
        safe_expire_cookie($OJ_NAME . "_check", "/");
        safe_expire_cookie($OJ_NAME . "_user",  "/");
        echo "<script>alert('Cookie비활성화 또는 오류!(-1)');history.go(-1);</script>";
        exit(0);
    }

    // 사용자 정보 조회
    $rows = pdo_query(
        "SELECT `password`,`accesstime` FROM `users` WHERE `user_id`=? AND defunct='N'",
        $C_user
    );
    if (!$rows || count($rows) === 0) {
        safe_expire_cookie($OJ_NAME . "_check", "/");
        safe_expire_cookie($OJ_NAME . "_user",  "/");
        echo "<script>alert('Cookie비활성화 또는 오류!(-1b)');history.go(-1);</script>";
        exit(0);
    }
    $C_info = $rows[0];
    $pwd    = isset($C_info['password'])   ? $C_info['password']   : $C_info[0];
    $atime  = isset($C_info['accesstime']) ? $C_info['accesstime'] : $C_info[1];

    $C_len = strlen($atime);
    if ($C_len <= 0) {
        safe_expire_cookie($OJ_NAME . "_check", "/");
        safe_expire_cookie($OJ_NAME . "_user",  "/");
        echo "<script>alert('Cookie비활성화 또는 오류!(-1c)');history.go(-1);</script>";
        exit(0);
    }

    // C_res 생성
    $C_res = "";
    $pwd_len = strlen($pwd);
    for ($i = 0; $i < $pwd_len; $i++) {
        $tp    = ord($pwd[$i]);
        $C_res.= chr(39 + ($tp * $tp + ord($atime[$i % $C_len]) * $tp) % 88);
    }

    if (substr($C_check, 0, -1) === sha1($C_res)) {
        $login = $C_user;
    } else {
        safe_expire_cookie($OJ_NAME . "_check", "/");
        safe_expire_cookie($OJ_NAME . "_user",  "/");
        echo "<script>alert('Cookie비활성화 또는 오류!(-2)');history.go(-1);</script>";
        exit(0);
    }
}

// ------------------------------
// 2) 폼 로그인 (쿠키로그인 미사용 시)
// ------------------------------
$vcode = "";
if (!$use_cookie) {
    if (isset($_POST['vcode'])) $vcode = trim($_POST['vcode']);
    if ($OJ_VCODE && ($vcode != $_SESSION[$OJ_NAME . '_' . "vcode"] || $vcode === "" || $vcode === null)) {
        echo "<script>alert('Verify Code Wrong!');history.go(-1);</script>";
        exit(0);
    }

    $view_errors = "";
    require_once("./include/login-" . $OJ_LOGIN_MOD . ".php");

    $user_id  = isset($_POST['user_id'])  ? $_POST['user_id']  : "";
    $password = isset($_POST['password']) ? $_POST['password'] : "";

    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        $user_id  = stripslashes($user_id);
        $password = stripslashes($password);
    }

    $login = check_login($user_id, $password); // 성공 시 user_id, 실패 시 false
}

// ------------------------------
// 3) 로그인 성공 처리
// ------------------------------
if ($login) {
    // 세션 고정 공격 방지: 최초 1회 재생성
    if (empty($_SESSION[$OJ_NAME . '__regenerated'])) {
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        $_SESSION[$OJ_NAME . '__regenerated'] = 1;
    }

    $_SESSION[$OJ_NAME . '_' . 'user_id'] = $login;

    // 권한 로딩
    $sql = "SELECT * FROM `privilege` WHERE `user_id`=?";
    $result = pdo_query($sql, $login);
    foreach ($result as $row) {
        if (isset($row['valuestr']))
            $_SESSION[$OJ_NAME . '_' . $row['rightstr']] = $row['valuestr'];
        else
            $_SESSION[$OJ_NAME . '_' . $row['rightstr']] = true;
    }

    // VIP → VIP 대회 자동 접근
    if (isset($_SESSION[$OJ_NAME . '_vip'])) {
        $sql = "SELECT contest_id FROM contest WHERE title LIKE '%[VIP]%'";
        $vrows = pdo_query($sql);
        foreach ($vrows as $r) {
            $_SESSION[$OJ_NAME . '_c' . $r['contest_id']] = true;
        }
    }

    // 접속시간 갱신
    pdo_query("UPDATE `users` SET `accesstime` = NOW() WHERE `user_id` = ?", $login);

    // 장기 로그인 쿠키
    if ($OJ_LONG_LOGIN) {
        $rows = pdo_query(
            "SELECT `password`, `accesstime` FROM `users` WHERE `user_id`=? AND defunct='N'",
            $login
        );
        if ($rows && count($rows) > 0) {
            $row   = $rows[0];
            $pwd   = isset($row['password'])   ? $row['password']   : $row[0];
            $atime = isset($row['accesstime']) ? $row['accesstime'] : $row[1];

            $C_len = strlen($atime);
            if ($C_len > 0) {
                $C_res = "";
                $pwd_len = strlen($pwd);
                for ($i = 0; $i < $pwd_len; $i++) {
                    $tp    = ord($pwd[$i]);
                    $C_res.= chr(39 + ($tp * $tp + ord($atime[$i % $C_len]) * $tp) % 88);
                }
                $C_res  = sha1($C_res);
                $expire = time() + 86400 * (int)$OJ_KEEP_TIME;

                // 안전 쿠키 세팅 (중복 세팅 방지 + 보안옵션)
                safe_setcookie($OJ_NAME . "_user",  $login, $expire, "/");
                safe_setcookie($OJ_NAME . "_check", $C_res . (strlen($C_res) * strlen($C_res)) % 7, $expire, "/");
            }
        }
    }

    // 리다이렉트
    echo "<script>";
    if ($OJ_NEED_LOGIN) {
        echo "window.location.href='index.php';";
    } else {
        echo "setTimeout('history.go(-2)',500);";
    }
    echo "</script>";
    exit(0);
}

// ------------------------------
// 4) 로그인 실패 처리
// ------------------------------
if (!empty($view_errors)) {
    require("template/" . $OJ_TEMPLATE . "/error.php");
} else {
    echo "<script>alert('UserName or Password Wrong!');history.go(-1);</script>";
}
?>
