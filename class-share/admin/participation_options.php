<?php

require_once(__DIR__ . '/include/admin_init.php');

$admin = class_share_admin_require_login();
$raw_event_id = isset($_GET['event_id']) ? $_GET['event_id'] : '';
$raw_option_id = isset($_GET['option_id']) ? $_GET['option_id'] : '0';

if (
    !is_string($raw_event_id) ||
    !preg_match('/^[1-9][0-9]{0,18}$/D', $raw_event_id) ||
    (string)(int)$raw_event_id !== $raw_event_id ||
    !is_string($raw_option_id) ||
    !preg_match('/^(0|[1-9][0-9]{0,18})$/D', $raw_option_id) ||
    (string)(int)$raw_option_id !== $raw_option_id
) {
    http_response_code(400);
    exit('행사 또는 참여 구분 번호가 올바르지 않습니다.');
}

$event_id = (int)$raw_event_id;
$option_id = (int)$raw_option_id;
$events = pdo_query(
    'SELECT event.id, event.school_id, event.title, event.application_mode,
            event.application_capacity, event.participation_enabled,
            school.school_name
     FROM class_share_event AS event
     INNER JOIN class_share_school AS school ON school.id = event.school_id
     WHERE event.id = ? LIMIT 1',
    $event_id
);
if ($events === false) {
    http_response_code(500);
    exit('행사 정보를 불러올 수 없습니다.');
}
if (!isset($events[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}
$event = $events[0];
$school_id = (int)$event['school_id'];
if (!class_share_admin_can_edit_school($school_id, $admin)) {
    http_response_code(403);
    exit('참여 구분을 설정할 권한이 없습니다.');
}

$options = pdo_query(
    "SELECT option_item.id, option_item.name, option_item.capacity,
            option_item.sort_order, option_item.is_active,
            COUNT(application.id) AS total_count,
            COALESCE(SUM(CASE WHEN application.application_scope = 'event'
                AND application.status IN ('applied', 'approved', 'waiting')
                THEN 1 ELSE 0 END), 0) AS active_count
     FROM class_share_participation_option AS option_item
     LEFT JOIN class_share_application AS application
       ON application.participation_option_id = option_item.id
      AND application.event_id = option_item.event_id
     WHERE option_item.event_id = ?
     GROUP BY option_item.id, option_item.name, option_item.capacity,
              option_item.sort_order, option_item.is_active
     ORDER BY option_item.sort_order, option_item.id",
    $event_id
);
if ($options === false) {
    http_response_code(500);
    exit('참여 구분을 불러올 수 없습니다.');
}

$values = array('name' => '', 'capacity' => '', 'sort_order' => '0', 'is_active' => '1');
$selected_option = null;
foreach ($options as $option) {
    if ((int)$option['id'] === $option_id) {
        $selected_option = $option;
        $values = array(
            'name' => (string)$option['name'],
            'capacity' => $option['capacity'] === null ? '' : (string)$option['capacity'],
            'sort_order' => (string)$option['sort_order'],
            'is_active' => (string)$option['is_active']
        );
        break;
    }
}
if ($option_id > 0 && $selected_option === null) {
    http_response_code(404);
    exit('해당 행사의 참여 구분을 찾을 수 없습니다.');
}

$flash = isset($_SESSION['class_share_participation_flash'])
    ? $_SESSION['class_share_participation_flash'] : null;
$message = '';
$errors = array();
if (
    is_array($flash) &&
    isset($flash['event_id']) &&
    (int)$flash['event_id'] === $event_id &&
    (int)$flash['option_id'] === $option_id
) {
    unset($_SESSION['class_share_participation_flash']);
    $message = isset($flash['message']) ? (string)$flash['message'] : '';
    $errors = isset($flash['errors']) && is_array($flash['errors'])
        ? $flash['errors'] : array();
    if (isset($flash['values']) && is_array($flash['values'])) {
        $values = array_merge($values, $flash['values']);
    }
}

$can_configure = (string)$event['application_mode'] === 'event';
$page_title = $event['title'] . ' 참여 구분 설정';
$active_menu = 'schools';
require_once(__DIR__ . '/include/admin_layout_start.php');
?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted"><?php echo class_share_escape($event['school_name']); ?></p>
        <a class="admin-back-link" href="/class-share/admin/event_edit.php?event_id=<?php echo $event_id; ?>">← 행사 수정</a>
    </div>
</div>

<?php if ($message !== '') { ?>
<section class="admin-panel" role="status"><p><?php echo class_share_escape($message); ?></p></section>
<?php } ?>
<?php if (count($errors) > 0) { ?>
<div class="admin-error" role="alert"><ul>
<?php foreach ($errors as $error) { ?>
    <li><?php echo class_share_escape($error); ?></li>
<?php } ?>
</ul></div>
<?php } ?>

<section class="admin-panel">
    <h2><?php echo class_share_escape($event['title']); ?> — 참여 구분</h2>
    <p>온라인·오프라인, 오전·오후 등 참여 구분의 이름과 정원을 설정합니다.</p>
    <p>
        행사 전체 신청 정원:
        <strong><?php echo $event['application_capacity'] === null
            ? '제한 없음'
            : (int)$event['application_capacity'] . '명'; ?></strong>
    </p>
    <p class="admin-muted">
        <?php if ($event['application_capacity'] === null) { ?>
            참여 구분을 사용하면 각 구분에 설정한 정원만 적용됩니다.
        <?php } else { ?>
            참여 구분별 정원과 행사 전체 정원을 함께 적용합니다.
            전체 정원이 차면 구분별 자리가 남아 있어도 신규 신청이 마감됩니다.
            구분별 정원만 적용하려면 행사 수정 화면에서 전체 정원을 비워 두세요.
        <?php } ?>
    </p>
    <p class="admin-muted">
        기존 미구분 신청은 그대로 보존되며 행사 전체 정원에 포함됩니다.
        구분별 정원에 포함하려면 신청자 상세 화면에서 실제 참여 구분을 지정해 주세요.
    </p>
    <p>현재 참여 구분 사용: <strong><?php echo (int)$event['participation_enabled'] === 1 ? '사용' : '사용 안 함'; ?></strong></p>
    <?php if ($can_configure || (int)$event['participation_enabled'] === 1) { ?>
    <form method="post" action="/class-share/admin/participation_mode_save.php">
        <?php echo class_share_admin_csrf_input(); ?>
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <div class="admin-field">
            <label for="participation_enabled">참여 구분 사용 여부</label>
            <select id="participation_enabled" name="participation_enabled">
                <option value="0"<?php echo (int)$event['participation_enabled'] === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                <?php if ($can_configure) { ?>
                <option value="1"<?php echo (int)$event['participation_enabled'] === 1 ? ' selected' : ''; ?>>사용</option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-form-actions">
            <button class="admin-primary-button" type="submit">사용 설정 저장</button>
        </div>
    </form>
    <?php } ?>
    <?php if (!$can_configure) { ?>
    <p>참여 구분 설정은 신청 방식이 ‘행사에 직접 신청’인 행사에서 가능합니다.</p>
    <?php } ?>
</section>

<section class="admin-panel">
    <h2>참여 구분 목록</h2>
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>순서</th><th>구분 이름</th><th>정원</th><th>활성 신청</th><th>모집 여부</th><th>관리</th></tr></thead>
        <tbody>
        <?php if (count($options) === 0) { ?>
        <tr><td colspan="6">등록된 참여 구분이 없습니다.</td></tr>
        <?php } ?>
        <?php foreach ($options as $option) { ?>
        <tr>
            <td><?php echo (int)$option['sort_order']; ?></td>
            <td><?php echo class_share_escape($option['name']); ?></td>
            <td><?php echo $option['capacity'] === null ? '제한 없음' : (int)$option['capacity'] . '명'; ?></td>
            <td><?php echo (int)$option['active_count']; ?>명</td>
            <td><?php echo (int)$option['is_active'] === 1 ? '모집 대상으로 설정' : '모집 중지'; ?></td>
            <td><?php if ($can_configure) { ?>
                <a class="admin-table-action" href="/class-share/admin/participation_options.php?event_id=<?php echo $event_id; ?>&amp;option_id=<?php echo (int)$option['id']; ?>">수정</a>
            <?php } else { echo '—'; } ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table></div>
</section>

<?php if ($can_configure) { ?>
<section class="admin-panel">
    <h2><?php echo $option_id > 0 ? '참여 구분 수정' : '참여 구분 추가'; ?></h2>
    <form method="post" action="/class-share/admin/participation_option_save.php">
        <?php echo class_share_admin_csrf_input(); ?>
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <input type="hidden" name="option_id" value="<?php echo $option_id; ?>">
        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="participation_name">구분 이름 *</label>
                <input id="participation_name" name="name" type="text" required maxlength="60" placeholder="예: 온라인 참여" value="<?php echo class_share_escape($values['name']); ?>">
            </div>
            <div class="admin-field">
                <label for="participation_capacity">모집 정원</label>
                <input id="participation_capacity" name="capacity" type="number" min="1" max="1000000" placeholder="비워 두면 제한 없음" value="<?php echo class_share_escape($values['capacity']); ?>">
            </div>
            <div class="admin-field">
                <label for="participation_sort">표시 순서</label>
                <input id="participation_sort" name="sort_order" type="number" min="0" max="1000000" required value="<?php echo class_share_escape($values['sort_order']); ?>">
            </div>
            <div class="admin-field">
                <label for="participation_active">모집 여부</label>
                <select id="participation_active" name="is_active">
                    <option value="1"<?php echo $values['is_active'] === '1' ? ' selected' : ''; ?>>모집 대상으로 설정</option>
                    <option value="0"<?php echo $values['is_active'] === '0' ? ' selected' : ''; ?>>모집 중지</option>
                </select>
            </div>
        </div>
        <p class="admin-muted">신청 기록을 보존하기 위해 참여 구분은 삭제하지 않고 모집 중지로 관리합니다.</p>
        <div class="admin-form-actions">
            <?php if ($option_id > 0) { ?>
            <a class="admin-secondary-link" href="/class-share/admin/participation_options.php?event_id=<?php echo $event_id; ?>">새 구분 추가</a>
            <?php } ?>
            <button class="admin-primary-button" type="submit"><?php echo $option_id > 0 ? '변경 내용 저장' : '참여 구분 추가'; ?></button>
        </div>
    </form>
</section>
<?php } ?>
<?php require_once(__DIR__ . '/include/admin_layout_end.php'); ?>
