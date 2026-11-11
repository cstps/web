<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);


$admin = class_share_admin_require_login();
$admin_id = (int)$admin['id'];
$is_super_admin = class_share_admin_is_super_admin($admin);
$page_title = '내 정보 수정';
$admin_page_title = $page_title;
$profile_url = '/class-share/admin/my_profile.php';
$login_url = '/class-share/admin/login.php';

header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if ($method === 'POST') {
    class_share_admin_require_post_csrf();
}

$errors = [];
$display_name = '';
$email = '';

try {
    $query = $dbh->prepare(
        "SELECT display_name, email, password_hash
         FROM class_share_admin
         WHERE id = ? AND status = 'active' AND password_changed_at = ?"
    );
    if (!$query->execute([$admin_id, $admin['password_changed_at']])) {
        throw new RuntimeException('Profile lookup failed');
    }
    $account = $query->fetch(PDO::FETCH_ASSOC);

    if ($account === false) {
        class_share_admin_clear_session();
        header('Location: ' . $login_url, true, 303);
        exit;
    }

    $display_name = (string)$account['display_name'];
    $email = (string)$account['email'];

    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $display_name = is_string($_POST['display_name'] ?? null)
                ? trim($_POST['display_name']) : '';
            $email = is_string($_POST['email'] ?? null)
                ? trim($_POST['email']) : '';
            $name_length = function_exists('mb_strlen')
                ? mb_strlen($display_name, 'UTF-8') : strlen($display_name);

            if ($name_length < 2 || $name_length > 60) {
                $errors[] = '표시 이름을 2~60자로 입력해 주세요.';
            }
            if ($email !== '' && (
                strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            )) {
                $errors[] = '이메일 주소를 확인해 주세요.';
            }

            if (!$errors) {
                $update = $dbh->prepare(
                    "UPDATE class_share_admin
                     SET display_name = ?, email = ?, updated_at = NOW()
                     WHERE id = ? AND status = 'active' AND password_changed_at = ?"
                );
                if (!$update->execute([
                    $display_name, $email === '' ? null : $email,
                    $admin_id, $admin['password_changed_at'],
                ])) {
                    throw new RuntimeException('Profile update failed');
                }

                header('Location: ' . $profile_url . '?saved=1', true, 303);
                exit;
            }
        } elseif ($action === 'password') {
            $current = $_POST['current_password'] ?? null;
            $new = $_POST['new_password'] ?? null;
            $confirm = $_POST['confirm_password'] ?? null;
            $last_failure = (int)($_SESSION['class_share_profile_password_failure'] ?? 0);

            if (time() - $last_failure < 3) {
                $errors[] = '잠시 후 다시 시도해 주세요.';
            } elseif (!is_string($new) || strlen($new) < 8 ||
                strlen($new) > 72 || strpos($new, "\0") !== false) {
                $errors[] = '새 비밀번호는 8~72바이트로 입력해 주세요.';
            } elseif (!is_string($confirm) || $new !== $confirm) {
                $errors[] = '새 비밀번호 확인이 일치하지 않습니다.';
            } elseif (!is_string($current) || strlen($current) > 1024 ||
                !password_verify($current, (string)$account['password_hash'])) {
                $_SESSION['class_share_profile_password_failure'] = time();
                $errors[] = '현재 비밀번호가 올바르지 않습니다.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                if (!is_string($hash)) {
                    throw new RuntimeException('Password hashing failed');
                }

                $update = $dbh->prepare(
                    "UPDATE class_share_admin
                     SET password_hash = ?,
                         password_changed_at = CASE
                             WHEN password_changed_at >= NOW()
                             THEN DATE_ADD(password_changed_at, INTERVAL 1 SECOND)
                             ELSE NOW()
                         END,
                         updated_at = NOW()
                     WHERE id = ? AND status = 'active'
                       AND password_changed_at = ?
                       AND BINARY password_hash = ?"
                );
                if (!$update->execute([
                    $hash, $admin_id, $admin['password_changed_at'],
                    $account['password_hash'],
                ])) {
                    throw new RuntimeException('Password update failed');
                }

                if ($update->rowCount() !== 1) {
                    $errors[] = '계정 정보가 변경되었습니다. 다시 로그인해 주세요.';
                } else {
                    unset($_SESSION['class_share_profile_password_failure']);
                    class_share_admin_clear_session();
                    header('Location: ' . $login_url, true, 303);
                    exit;
                }
            }
        } else {
            http_response_code(400);
            $errors[] = '요청한 작업을 확인할 수 없습니다.';
        }
    }
} catch (Throwable $error) {
    error_log('[class-share] 본인 계정 조회 또는 저장 실패');
    http_response_code(500);
    $errors[] = '계정 정보를 처리할 수 없습니다. 잠시 후 다시 시도해 주세요.';
}

require __DIR__ . '/include/admin_layout_start.php';
?>
<link rel="stylesheet" href="/class-share/admin/my_profile.css">

<div class="cs-profile">
    <header class="cs-profile__header">
        <div>
            <a class="cs-profile__back" href="/class-share/admin/dashboard.php">← 대시보드</a>
            <h2>내 정보 수정</h2>
        </div>
    </header>

    <?php if ($method === 'GET' && ($_GET['saved'] ?? '') === '1'): ?>
        <p class="cs-profile__notice cs-profile__notice--success" role="status">
            계정 정보를 저장했습니다.
        </p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="cs-profile__notice cs-profile__notice--error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="cs-profile__grid">
        <section class="cs-profile__card" aria-labelledby="my-account-heading">
            <h3 id="my-account-heading">계정 정보</h3>
            <form method="post" action="<?= $profile_url ?>">
                <?= class_share_admin_csrf_input() ?>
                <input type="hidden" name="action" value="profile">

                <dl class="cs-profile__identity">
                    <dt>로그인 아이디</dt>
                    <dd><?= htmlspecialchars((string)$admin['login_id'], ENT_QUOTES, 'UTF-8') ?></dd>
                </dl>

                <div class="cs-profile__field">
                    <label for="my-display-name">표시 이름</label>
                    <input id="my-display-name" name="display_name" required minlength="2"
                        maxlength="60" autocomplete="nickname"
                        value="<?= htmlspecialchars($display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                </div>

                <div class="cs-profile__field">
                    <label for="my-email">이메일 <span>선택</span></label>
                    <input id="my-email" name="email" type="email" maxlength="255"
                        autocomplete="email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                </div>

                <div class="cs-profile__actions">
                    <button type="submit" class="cs-profile__button">계정 정보 저장</button>
                </div>
            </form>
        </section>

        <section class="cs-profile__card" aria-labelledby="my-password-heading">
            <h3 id="my-password-heading">비밀번호 변경</h3>
            <form method="post" action="<?= $profile_url ?>">
                <?= class_share_admin_csrf_input() ?>
                <input type="hidden" name="action" value="password">

                <div class="cs-profile__field">
                    <label for="my-current-password">현재 비밀번호</label>
                    <input id="my-current-password" name="current_password" type="password"
                        autocomplete="current-password" required>
                </div>

                <div class="cs-profile__field">
                    <label for="my-new-password">새 비밀번호</label>
                    <input id="my-new-password" name="new_password" type="password"
                        autocomplete="new-password" aria-describedby="my-password-length" required>
                    <small id="my-password-length">8~72바이트. 영문·숫자는 1자당 1바이트입니다.</small>
                </div>

                <div class="cs-profile__field">
                    <label for="my-confirm-password">새 비밀번호 확인</label>
                    <input id="my-confirm-password" name="confirm_password" type="password"
                        autocomplete="new-password" required>
                </div>

                <div class="cs-profile__actions">
                    <small>변경 후 새 비밀번호로 다시 로그인합니다.</small>
                    <button type="submit" class="cs-profile__button">비밀번호 변경</button>
                </div>
            </form>
        </section>
    </div>
</div>
<?php require __DIR__ . '/include/admin_layout_end.php'; ?>
