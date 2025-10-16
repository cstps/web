<?php
/* ============================================================
 * team_generate.php (frameset-safe, PHP5+ compatible)
 * - 팀 계정 일괄 생성 (관리자/대회 생성자 전용)
 * - 정책:
 *   1) 생성 예정 user_id 중 하나라도 기존과 겹치면 전체 생성 중단
 *   2) 중복/오류 시 빈 화면 대신 폼 위에 안내 박스 출력
 * - 구현:
 *   - 사전 중복검사: SELECT ... IN (?, ?, ...)
 *   - ON DUPLICATE KEY UPDATE 제거
 *   - 트랜잭션으로 원자성 보장
 *   - frameset 환경에서 항상 HTML을 출력해 빈 화면 방지
 * ============================================================ */

require("admin-header.php");
// 필요시 디버깅
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

if (!(isset($_SESSION[$OJ_NAME.'_'.'administrator']) || isset($_SESSION[$OJ_NAME.'_'.'contest_creator']))) {
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Please Login</title></head><body>";
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    echo "</body></html>";
    exit;
}

$dup_notice  = "";
$gen_success = false;

if (isset($_POST['prefix'])) {
    require_once("../include/check_post_key.php");
    require_once("../include/my_func.inc.php");

    $prefix = isset($_POST['prefix']) ? $_POST['prefix'] : '';

    if (!is_valid_user_name($prefix)) {
        $dup_notice = '<div style="margin:12px 0;padding:10px;border:1px solid #f1c0c0;background:#ffecec;color:#b30000;font-weight:600">
            Prefix is not valid.
        </div>';
    } else {
        $teamnumber = intval(isset($_POST['teamnumber']) ? $_POST['teamnumber'] : 0);
        $ulist_raw  = isset($_POST['ulist']) ? trim($_POST['ulist']) : '';
        $pieces     = $ulist_raw === '' ? array() : explode("\n", $ulist_raw);

        if ($teamnumber > 0) {
            // 1) 사전 중복검사
            $candidate_ids = array();
            for ($i = 1; $i <= $teamnumber; $i++) {
                $uid = ($teamnumber == 1) ? $prefix : $prefix . ($i < 10 ? ('0' . $i) : $i);
                $candidate_ids[] = $uid;
            }

            // (?, ?, ...) 플레이스홀더
            $ph = array();
            for ($k = 0; $k < count($candidate_ids); $k++) $ph[] = '?';
            $placeholders = implode(',', $ph);

            $sql_dup = "SELECT `user_id` FROM `users` WHERE `user_id` IN ($placeholders)";

            // ★ pdo_query 반환 타입(PPDOStatement/array) 혼재를 방어
            $args = array_merge(array($sql_dup), $candidate_ids);
            $res  = call_user_func_array('pdo_query', $args);
            $dups = array();
            if ($res instanceof PDOStatement) {
                $dups = $res->fetchAll(PDO::FETCH_COLUMN, 0);
            } elseif (is_array($res)) {
                // 일부 빌드에선 결과 배열을 바로 돌려줌
                foreach ($res as $row) {
                    if (isset($row['user_id'])) {
                        $dups[] = $row['user_id'];
                    } elseif (isset($row[0])) {
                        $dups[] = $row[0];
                    }
                }
            } else {
                $dups = array();
            }

            if ($dups && count($dups) > 0) {
                // ===== 중복 발견: 즉시 완전한 HTML 출력 후 종료 (frameset-safe) =====
                $safe = array();
                foreach ($dups as $u) $safe[] = htmlentities($u, ENT_QUOTES, 'UTF-8');

                if (function_exists('ob_get_level')) {
                    while (ob_get_level() > 0) { @ob_end_clean(); }
                }
                if (!headers_sent()) {
                    header('Content-Type: text/html; charset=utf-8');
                    header('X-Frame-Options: SAMEORIGIN');
                }

                echo '<!doctype html><html><head><meta charset="utf-8"><title>TeamGenerator - Duplicate</title></head><body>';
                echo '<div class="container">';
                echo '<b>TeamGenerator:</b>';
                echo '<div style="margin:12px 0;padding:10px;border:1px solid #f1c0c0;background:#ffecec;color:#b30000;font-weight:600">';
                echo '생성 중단: 다음 아이디가 이미 존재합니다 → ' . implode(', ', $safe) . '<br>';
                echo '동일 아이디가 하나라도 있으면 <u>이번 생성 작업 전체가 중단</u>됩니다. 접두사(prefix) 또는 팀 수를 조정해 다시 시도하세요.';
                echo '</div>';

                // 재시도 폼(현재 입력 유지)
                echo '<form action="team_generate.php" method="post" target="main" style="margin-top:10px">';
                echo 'Prefix: <input type="text" name="prefix" value="'.htmlentities($prefix, ENT_QUOTES, 'UTF-8').'" placeholder="Team"><br>';
                echo 'Generate <input class="input-mini" type="number" name="teamnumber" value="'.intval($teamnumber).'" min="1" style="width:60px;text-align:right;"> Teams.<br>';
                echo 'Users:<br><textarea name="ulist" rows="12" cols="40" placeholder="Preset nicknames of the teams. One name per line.">';
                echo htmlentities($ulist_raw, ENT_QUOTES, 'UTF-8');
                echo '</textarea><br>';
                require("../include/set_post_key.php");
                echo '<br><input type="submit" value="Generate">';
                echo '</form>';

                echo '<span style="color:red;font-weight:bold;display:inline-block;margin-top:8px">
                        안내: 동일한 접두사로 만든 계정 중 하나라도 기존 아이디와 겹치면,
                        <u>이번 생성 작업 전체가 중단</u>되며 아무 계정도 생성되지 않습니다.
                      </span>';

                echo '</div></body></html>';

                if (function_exists('flush')) { @flush(); }
                exit;
            } else {
                // 2) 실제 생성 (트랜잭션)
                pdo_query("BEGIN");
                try {
                    echo '<!doctype html><html><head><meta charset="utf-8"><title>TeamGenerator</title></head><body>';
                    echo '<div class="container">';
                    echo '<b>TeamGenerator:</b>';
                    echo '<table border="1" cellpadding="6" cellspacing="0" style="margin:12px 0">';
                    echo '<tr><td colspan="3"><b>Copy these accounts to distribute</b></td></tr>';
                    echo '<tr><td><b>team_name</b></td><td><b>login_id</b></td><td><b>password</b></td></tr>';

                    $max_length = 20;

                    for ($i = 1; $i <= $teamnumber; $i++) {
                        $user_id = ($teamnumber == 1) ? $prefix : $prefix . ($i < 10 ? ('0' . $i) : $i);
                        $plain_password = strtoupper(substr(md5($user_id . rand(0, 9999999)), 0, 10));

                        // 닉네임 프리셋
                        if (isset($pieces[$i - 1])) {
                            $nick = trim($pieces[$i - 1]);
                            if ($nick === '') $nick = 'your_own_nick';
                        } else {
                            $nick = 'your_own_nick';
                        }

                        // 안내 표 (배포용)
                        echo '<tr>'
                           . '<td>' . htmlentities($nick, ENT_QUOTES, 'UTF-8') . '</td>'
                           . '<td>' . htmlentities($user_id, ENT_QUOTES, 'UTF-8') . '</td>'
                           . '<td>' . htmlentities($plain_password, ENT_QUOTES, 'UTF-8') . '</td>'
                           . '</tr>';

                        // 실제 저장 비밀번호
                        $password = pwGen($plain_password);

                        $email  = 'your_own_email@internet';
                        $school = 'your_own_school';

                        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
                        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                            $REMOTE_ADDR = $_SERVER['HTTP_X_FORWARDED_FOR'];
                            $tmp_ip = explode(',', $REMOTE_ADDR);
                            $ip = htmlentities($tmp_ip[0], ENT_QUOTES, 'UTF-8');
                        }

                        // nick 길이 확장
                        if (function_exists('mb_strlen')) {
                            if (mb_strlen($nick, 'utf-8') > 20) {
                                $new_len = mb_strlen($nick, 'utf-8');
                                if ($new_len > $max_length) {
                                    $max_length = $new_len;
                                    $alter = "ALTER TABLE `users` MODIFY COLUMN `nick` varchar($max_length) NULL DEFAULT '' ";
                                    pdo_query($alter);
                                }
                            }
                        }

                        // 덮어쓰기 금지: ON DUPLICATE 없음
                        $sql_ins = "INSERT INTO `users`
                                    (`user_id`,`email`,`ip`,`accesstime`,`password`,`reg_time`,`nick`,`school`)
                                    VALUES(?,?,?,NOW(),?,NOW(),?,?)";
                        pdo_query($sql_ins, $user_id, $email, $ip, $password, $nick, $school);
                    }

                    echo '</table>';

                    // 하단 재시도 폼
                    echo '<form action="team_generate.php" method="post" target="main" style="margin-top:10px">';
                    echo 'Prefix: <input type="text" name="prefix" value="'.htmlentities($prefix, ENT_QUOTES, 'UTF-8').'" placeholder="Team"><br>';
                    echo 'Generate <input class="input-mini" type="number" name="teamnumber" value="'.intval($teamnumber).'" min="1" style="width:60px;text-align:right;"> Teams.<br>';
                    echo 'Users:<br><textarea name="ulist" rows="12" cols="40" placeholder="Preset nicknames of the teams. One name per line.">';
                    echo htmlentities($ulist_raw, ENT_QUOTES, 'UTF-8');
                    echo '</textarea><br>';
                    require("../include/set_post_key.php");
                    echo '<br><input type="submit" value="Generate">';
                    echo '</form>';

                    echo '<span style="color:red;font-weight:bold;display:inline-block;margin-top:8px">
                        안내: 동일한 접두사로 만든 계정 중 하나라도 기존 아이디와 겹치면,
                        <u>이번 생성 작업 전체가 중단</u>되며 아무 계정도 생성되지 않습니다.
                    </span>';

                    echo '</div></body></html>';

                    pdo_query("COMMIT");
                    $gen_success = true;
                    exit;

                } catch (Exception $e) {
                    pdo_query("ROLLBACK");
                    $dup_notice = '<div style="margin:12px 0;padding:10px;border:1px solid #f1c0c0;background:#ffecec;color:#b30000;font-weight:600">
                        생성 실패: ' . htmlentities($e->getMessage(), ENT_QUOTES, 'UTF-8') . '
                    </div>';
                }
            }
        }
    }
}

// 여기까지 왔다는 것은 GET이거나, 중복/오류로 생성 스킵되었거나, prefix invalid
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>TeamGenerator</title>
  <script>
// 단독 열린 경우 프레임셋으로 복귀시키려면 경로 맞춰 주고 해제
// if (window.top === window.self) { window.location.replace('index.php'); }
  </script>
</head>
<body>
<div class="container">
  <b>TeamGenerator:</b>

  <?php
  if (!empty($dup_notice)) {
      echo $dup_notice;
  }
  ?>

  <form action="team_generate.php" method="post" target="main" style="margin-top:10px">
    Prefix:
    <input type="text" name="prefix" value="team" placeholder="Team"><br>

    Generate
    <input class="input-mini" type="number" name="teamnumber" value="5" min="1" style="width:60px;text-align:right;">
    Teams.<br>

    Users:<br>
    <textarea name="ulist" rows="12" cols="40"
      placeholder="Preset nicknames of the teams. One name per line."></textarea><br>

    <?php require_once("../include/set_post_key.php"); ?>

    <br>
    <input type="submit" value="Generate">
  </form>

  <span style="color:red;font-weight:bold;display:inline-block;margin-top:8px">
    정책 안내: 동일한 접두사로 만든 계정 중 하나라도 기존 아이디와 겹치면,
    <u>이번 생성 작업 전체가 중단</u>되며 아무 계정도 생성되지 않습니다.
  </span>
</div>
</body>
</html>
