<?php
chdir(dirname(__DIR__));

$OJ_CACHE_SHARE = false;
$cache_time = 0;

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');

$view_title = '워드클라우드';
$show_title = $view_title;

function wordcloud_fail($message, $status = 400)
{
    global $view_errors, $OJ_TEMPLATE;

    http_response_code($status);

    $view_errors = '<h2>' .
        htmlspecialchars(
            $message,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) .
        '</h2><p><a href="/tools/wordcloud.php">워드클라우드로 이동</a></p>';

    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

function wordcloud_normalize_code($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = strtolower(trim($value));

    return preg_match('/^[a-z0-9]{4,32}$/D', $value) ? $value : '';
}

function wordcloud_generate_code(PDO $db)
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = substr(bin2hex(random_bytes(8)), 0, 8);

        $stmt = $db->prepare(
            'SELECT room_id
             FROM wordcloud_room
             WHERE access_code = ?
             LIMIT 1'
        );
        $stmt->execute(array($code));
        $exists = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($exists === false) {
            return $code;
        }
    }

    throw new RuntimeException('공유 코드를 생성하지 못했습니다.');
}

function wordcloud_text_length($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function wordcloud_today_create_key()
{
    return date('Ymd');
}

function wordcloud_can_manage_room(array $room, $password)
{
    global $OJ_NAME;

    if (isset($_SESSION[$OJ_NAME . '_administrator'])) {
        return true;
    }

    if (
        isset($_SESSION[$OJ_NAME . '_user_id']) &&
        isset($room['owner_user_id']) &&
        (string)$room['owner_user_id'] ===
        (string)$_SESSION[$OJ_NAME . '_user_id']
    ) {
        return true;
    }

    if (
        isset($room['manage_password_hash']) &&
        is_string($room['manage_password_hash']) &&
        $room['manage_password_hash'] !== '' &&
        is_string($password) &&
        $password !== ''
    ) {
        return password_verify($password, $room['manage_password_hash']);
    }

    return false;
}

function wordcloud_trim_text($value)
{
    $value = trim((string)$value);
    $value = preg_replace('/[[:cntrl:]]/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim((string)$value);
}

try {
    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once('./include/csrf_check.php');

        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $is_logged_in = isset($_SESSION[$OJ_NAME . '_user_id']);
            $create_key = trim((string)($_POST['create_key'] ?? ''));

            if (
                !$is_logged_in &&
                !hash_equals(wordcloud_today_create_key(), $create_key)
            ) {
                $view_create_error =
                    '일일 보안키가 일치하지 않거나 로그인 정보가 없습니다.';

                $view_code = wordcloud_normalize_code($_GET['code'] ?? '');
                $view_room = null;
                $view_entries = array();
                $view_my_rooms = array();
                $view_manage_mode = false;
                $view_manage_authorized = false;

                require('template/' . $OJ_TEMPLATE . '/tools/wordcloud.php');

                if (file_exists('./include/cache_end.php')) {
                    require_once('./include/cache_end.php');
                }

                exit;
            }

            $title = wordcloud_trim_text($_POST['title'] ?? '');
            $password = (string)($_POST['manage_password'] ?? '');
            $allow_anonymous = (string)($_POST['allow_anonymous'] ?? '1') === '1'
                ? 1 : 0;

            if (
                $title === '' ||
                wordcloud_text_length($title) > 120
            ) {
                wordcloud_fail('제목은 1~120자 이하여야 합니다.');
            }

            if (
                !$is_logged_in &&
                $password === ''
            ) {
                wordcloud_fail('비로그인으로 방을 만들 때는 관리 비밀번호가 필요합니다.');
            }

            if (
                $password !== '' &&
                (
                    strlen($password) < 4 ||
                    strlen($password) > 72
                )
            ) {
                wordcloud_fail('관리 비밀번호는 4~72자로 입력해 주세요.');
            }

            $code = wordcloud_generate_code($dbh);
            $password_hash = $password === ''
                ? null
                : password_hash($password, PASSWORD_DEFAULT);

            $stmt = $dbh->prepare(
                'INSERT INTO wordcloud_room
                    (
                        owner_user_id, title, access_code,
                        manage_password_hash, allow_anonymous
                    )
                 VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute(array(
                $is_logged_in
                    ? (string)$_SESSION[$OJ_NAME . '_user_id']
                    : null,
                $title,
                $code,
                $password_hash,
                $allow_anonymous,
            ));
            $stmt->closeCursor();

            header('Location: /tools/wordcloud.php?code=' . rawurlencode($code));
            exit;
        }

        if ($action === 'manage_login') {
            $code = wordcloud_normalize_code($_POST['code'] ?? '');
            $manage_password = (string)($_POST['manage_password'] ?? '');

            if ($code === '') {
                wordcloud_fail('공유 코드가 올바르지 않습니다.');
            }

            $stmt = $dbh->prepare(
                'SELECT room_id, manage_password_hash
                 FROM wordcloud_room
                 WHERE access_code = ?
                 LIMIT 1'
            );
            $stmt->execute(array($code));
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (
                !$room ||
                !is_string($room['manage_password_hash']) ||
                $room['manage_password_hash'] === '' ||
                !password_verify($manage_password, $room['manage_password_hash'])
            ) {
                wordcloud_fail('관리 비밀번호가 올바르지 않습니다.', 403);
            }

            if (!isset($_SESSION[$OJ_NAME . '_wordcloud_manage_rooms'])) {
                $_SESSION[$OJ_NAME . '_wordcloud_manage_rooms'] = array();
            }

            $_SESSION[$OJ_NAME . '_wordcloud_manage_rooms'][
                (string)$room['room_id']
            ] = true;

            header(
                'Location: /tools/wordcloud.php?code=' .
                rawurlencode($code) .
                '&manage=1'
            );
            exit;
        }

        if ($action === 'submit') {
            $code = wordcloud_normalize_code($_POST['code'] ?? '');
            $display_name = wordcloud_trim_text($_POST['display_name'] ?? '');
            $text_content = wordcloud_trim_text($_POST['text_content'] ?? '');

            if ($code === '') {
                wordcloud_fail('공유 코드가 올바르지 않습니다.');
            }

            if (
                $display_name !== '' &&
                wordcloud_text_length($display_name) > 80
            ) {
                wordcloud_fail('이름은 80자 이하여야 합니다.');
            }

            if (
                $text_content === '' ||
                wordcloud_text_length($text_content) > 1000
            ) {
                wordcloud_fail('입력 내용은 1~1000자여야 합니다.');
            }

            $stmt = $dbh->prepare(
                'SELECT room_id, status, allow_anonymous
                 FROM wordcloud_room
                 WHERE access_code = ?
                 LIMIT 1'
            );
            $stmt->execute(array($code));
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$room || intval($room['status']) !== 1) {
                wordcloud_fail('참여 가능한 워드클라우드 방을 찾지 못했습니다.', 404);
            }

            if (
                intval($room['allow_anonymous']) !== 1 &&
                !isset($_SESSION[$OJ_NAME . '_user_id'])
            ) {
                wordcloud_fail('로그인 사용자만 참여할 수 있는 방입니다.', 403);
            }

            $user_id = isset($_SESSION[$OJ_NAME . '_user_id'])
                ? (string)$_SESSION[$OJ_NAME . '_user_id']
                : null;

            $ip_source = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $code;
            $ip_hash = hash('sha256', $ip_source);

            $stmt = $dbh->prepare(
                'INSERT INTO wordcloud_entry
                    (room_id, user_id, display_name, text_content, ip_hash)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute(array(
                $room['room_id'],
                $user_id,
                $display_name === '' ? null : $display_name,
                $text_content,
                $ip_hash,
            ));
            $stmt->closeCursor();

            header('Location: /tools/wordcloud.php?code=' . rawurlencode($code) . '&saved=1');
            exit;
        }

        if (in_array($action, array('toggle', 'delete'), true)) {
            $room_id = isset($_POST['room_id'])
                ? (int)$_POST['room_id']
                : 0;

            $manage_password =
                (string)($_POST['manage_password'] ?? '');

            $manage_password =
                (string)($_POST['manage_password'] ?? '');

            if ($room_id <= 0) {
                wordcloud_fail('방 번호가 올바르지 않습니다.');
            }

            $stmt = $dbh->prepare(
                'SELECT room_id, owner_user_id, status, manage_password_hash
                 FROM wordcloud_room
                 WHERE room_id = ?
                 LIMIT 1'
            );
            $stmt->execute(array($room_id));
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (
                !$room ||
                !wordcloud_can_manage_room($room, $manage_password)
            ) {
                wordcloud_fail('이 워드클라우드를 관리할 권한이 없습니다.', 403);
            }

            if ($action === 'toggle') {
                $new_status = intval($room['status']) === 1 ? 0 : 1;
                $stmt = $dbh->prepare(
                    'UPDATE wordcloud_room
                     SET status = ?
                     WHERE room_id = ?'
                );
                $stmt->execute(array($new_status, $room_id));
                $stmt->closeCursor();
            } else {
                $dbh->beginTransaction();

                $stmt = $dbh->prepare(
                    'DELETE FROM wordcloud_entry
                     WHERE room_id = ?'
                );
                $stmt->execute(array($room_id));
                $stmt->closeCursor();

                $stmt = $dbh->prepare(
                    'DELETE FROM wordcloud_room
                     WHERE room_id = ?'
                );
                $stmt->execute(array($room_id));
                $stmt->closeCursor();

                $dbh->commit();
            }

            header('Location: /tools/wordcloud.php');
            exit;
        }

        wordcloud_fail('지원하지 않는 요청입니다.');
    }

    $view_code = wordcloud_normalize_code($_GET['code'] ?? '');
        $view_manage_mode = (($_GET['manage'] ?? '') === '1');
    $view_manage_authorized = false;
    $view_ajax_entries = (($_GET['ajax'] ?? '') === 'entries');
$view_room = null;
    $view_entries = array();
    $view_my_rooms = array();

    if ($view_code !== '') {
        $stmt = $dbh->prepare(
            'SELECT room_id, owner_user_id, title, access_code,
                    status, allow_anonymous, created_at
             FROM wordcloud_room
             WHERE access_code = ?
             LIMIT 1'
        );
        $stmt->execute(array($view_code));
        $view_room = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$view_room) {
            wordcloud_fail('워드클라우드 방을 찾지 못했습니다.', 404);
        }

        $room_manage_sessions =
            $_SESSION[$OJ_NAME . '_wordcloud_manage_rooms'] ?? array();

        $view_manage_authorized = (
            isset($_SESSION[$OJ_NAME . '_administrator']) ||
            (
                isset($_SESSION[$OJ_NAME . '_user_id']) &&
                isset($view_room['owner_user_id']) &&
                (string)$view_room['owner_user_id'] ===
                    (string)$_SESSION[$OJ_NAME . '_user_id']
            ) ||
            (
                is_array($room_manage_sessions) &&
                isset($room_manage_sessions[(string)$view_room['room_id']])
            )
        );

        $stmt = $dbh->prepare(
            'SELECT entry_id, user_id, display_name, text_content, created_at
             FROM wordcloud_entry
             WHERE room_id = ?
             ORDER BY entry_id DESC
             LIMIT 300'
        );
        $stmt->execute(array($view_room['room_id']));
        $view_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($view_ajax_entries) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');

            $entry_texts = array();
            $entry_rows = array();

            foreach ($view_entries as $entry) {
                $entry_texts[] = (string)$entry['text_content'];

                $entry_rows[] = array(
                    'display_name' => (string)(
                        $entry['display_name'] ?:
                        ($entry['user_id'] ?: '익명')
                    ),
                    'text_content' => (string)$entry['text_content'],
                    'created_at' => (string)$entry['created_at'],
                );
            }

            echo json_encode(
                array(
                    'ok' => true,
                    'code' => (string)$view_room['access_code'],
                    'entry_count' => count($view_entries),
                    'texts' => $entry_texts,
                    'entries' => $entry_rows,
                    'can_view_entries' => $view_manage_authorized,
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        }
    }

    if (isset($_SESSION[$OJ_NAME . '_user_id'])) {
        $stmt = $dbh->prepare(
            'SELECT r.room_id, r.title, r.access_code, r.status,
                    r.allow_anonymous, r.created_at,
                    COUNT(e.entry_id) AS entry_count
             FROM wordcloud_room r
             LEFT JOIN wordcloud_entry e
               ON e.room_id = r.room_id
             WHERE r.owner_user_id = ?
             GROUP BY r.room_id
             ORDER BY r.room_id DESC
             LIMIT 50'
        );
        $stmt->execute(array((string)$_SESSION[$OJ_NAME . '_user_id']));
        $view_my_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    }

} catch (Throwable $e) {
    if (isset($dbh) && $dbh instanceof PDO && $dbh->inTransaction()) {
        $dbh->rollBack();
    }

    error_log('[wordcloud] ' . $e->getMessage());
    wordcloud_fail('워드클라우드 처리 중 오류가 발생했습니다.', 500);
}

require('template/' . $OJ_TEMPLATE . '/tools/wordcloud.php');

if (file_exists('./include/cache_end.php')) {
    require_once('./include/cache_end.php');
}
