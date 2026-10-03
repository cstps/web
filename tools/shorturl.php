<?php

// 기존 HUSTOJ 상대경로 호환을 위해 웹 루트를 작업 디렉터리로 사용
chdir(dirname(__DIR__));
$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once(__DIR__ . '/../include/db_info.inc.php');
require_once(__DIR__ . '/../include/const.inc.php');
require_once(__DIR__ . '/../include/memcache.php');
require_once(__DIR__ . '/../include/setlang.php');

// 매일 변경되는 일일 보안키 (예: 오늘 날짜 MMDD -> 0920)
$DAILY_SECRET_KEY = date('md');

/**
 * 한글 및 특수문자가 포함된 URL을 표준 Percent-Encoding / Punycode 주소로 자동 변환하는 함수
 */
function encode_korean_url($url)
{
    if (!preg_match('/[\x{80}-\x{10FFFF}]/u', $url)) {
        return $url;
    }

    $parsed_url = parse_url($url);
    if (!$parsed_url) return $url;

    $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
    $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

    // 한글 도메인일 경우 Punycode(퓨니코드)로 변환 (예: 쌤.닷컴 -> xn--9r2b15m.com)
    if (function_exists('idn_to_ascii') && preg_match('/[\x{80}-\x{10FFFF}]/u', $host)) {
        $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
    }

    // 경로(Path) 및 쿼리스트링(Query) 인코딩
    if ($path) {
        $path = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($match) {
            return rawurlencode($match[0]);
        }, $path);
    }

    if ($query) {
        $query = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($match) {
            return rawurlencode($match[0]);
        }, $query);
    }

    return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
}

// 1. 단축 URL 접속 시 리다이렉트
if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    $sql = "SELECT original_url FROM short_url WHERE short_code = ?";
    $result = pdo_query($sql, $code);

    if (!empty($result)) {
        $url = $result[0]['original_url'];
        pdo_query("UPDATE short_url SET click_count = click_count + 1 WHERE short_code = ?", $code);
        header("Location: " . $url);
        exit();
    } else {
        $error_msg = "존재하지 않거나 만료된 단축 URL입니다.";
    }
}

$view_title = "단축 URL 생성기";
$generated_url = "";
$is_guest_generated = false;

// 2. POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // 삭제 처리
    if ($action === 'delete') {
        if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
            $error_msg = "삭제 권한이 없습니다.";
        } else {
            $user_id = $_SESSION[$OJ_NAME . '_user_id'];
            $del_id = intval($_POST['id'] ?? 0);
            if ($del_id > 0) {
                if (isset($_SESSION[$OJ_NAME . '_administrator'])) {
                    pdo_query("DELETE FROM short_url WHERE id = ?", $del_id);
                    $success_msg = "단축 URL이 성공적으로 삭제되었습니다.";
                } else {
                    pdo_query("DELETE FROM short_url WHERE id = ? AND created_by = ?", $del_id, $user_id);
                    $success_msg = "단축 URL이 성공적으로 삭제되었습니다.";
                }
            }
        }
    }
    // 생성 처리
    else if ($action === 'create') {
        $raw_input_url = trim($_POST['url'] ?? '');
        $input_passkey = trim($_POST['passkey'] ?? '');
        $user_id = isset($_SESSION[$OJ_NAME . '_user_id']) ? $_SESSION[$OJ_NAME . '_user_id'] : '';

        // [핵심 1] 사용자가 http:// 또는 https:// 를 안 붙였을 경우 자동으로 https:// 붙이기
        if ($raw_input_url !== '' && !preg_match('/^https?:\/\//i', $raw_input_url)) {
            $raw_input_url = 'https://' . $raw_input_url;
        }

        // [핵심 2] 한글 원본 주소 인코딩 변환
        $original_url = encode_korean_url($raw_input_url);

        // 생성 권한 검증
        $is_authorized = false;
        if (!empty($user_id)) {
            $is_authorized = true;
        } else if ($input_passkey === $DAILY_SECRET_KEY) {
            $is_authorized = true;
            $user_id = 'guest_' . $_SERVER['REMOTE_ADDR'];
            $is_guest_generated = true;
        }

        if (!$is_authorized) {
            $error_msg = "일일 보안키가 일치하지 않거나 로그인 정보가 없습니다.";
        } else if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
            $error_msg = "올바른 웹 주소를 입력해주세요.";
        } else {
            // 도배 방지: 동일 사용자 5초 제한
            $check_recent = pdo_query("SELECT created_at FROM short_url WHERE created_by = ? ORDER BY id DESC LIMIT 1", $user_id);
            if (!empty($check_recent) && (time() - strtotime($check_recent[0]['created_at'])) < 5) {
                $error_msg = "너무 빠르게 연속으로 생성할 수 없습니다. 잠시 후 다시 시도해주세요.";
            } else {
                // 5자리 무작위 코드 생성
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                do {
                    $short_code = '';
                    for ($i = 0; $i < 5; $i++) {
                        $short_code .= $chars[rand(0, strlen($chars) - 1)];
                    }
                    $exists = pdo_query("SELECT id FROM short_url WHERE short_code = ?", $short_code);
                } while (!empty($exists));

                $sql = "INSERT INTO short_url (short_code, original_url, created_by) VALUES (?, ?, ?)";
                pdo_query($sql, $short_code, $original_url, $user_id);

                $http_host = $_SERVER['HTTP_HOST'];
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $generated_url = "{$scheme}://{$http_host}/shorturl.php?code={$short_code}";
            }
        }
    }
}

// 3. 내 생성 목록
$my_short_urls = [];
if (isset($_SESSION[$OJ_NAME . '_user_id'])) {
    $user_id = $_SESSION[$OJ_NAME . '_user_id'];
    if (isset($_SESSION[$OJ_NAME . '_administrator'])) {
        $my_short_urls = pdo_query("SELECT * FROM short_url ORDER BY id DESC LIMIT 50");
    } else {
        $my_short_urls = pdo_query("SELECT * FROM short_url WHERE created_by = ? ORDER BY id DESC LIMIT 50", $user_id);
    }
}

require(__DIR__ . "/../template/" . $OJ_TEMPLATE . "/tools/shorturl.php");

if (file_exists(__DIR__ . '/../include/cache_end.php')) {
    require_once(__DIR__ . '/../include/cache_end.php');
}
