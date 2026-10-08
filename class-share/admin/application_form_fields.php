<?php

require_once(__DIR__ . '/include/admin_init.php');
require_once(dirname(__DIR__) . '/include/application_form_functions.php');
$admin = class_share_admin_require_login();
try {
    $event_id = class_share_form_id(isset($_GET['event_id']) ? $_GET['event_id'] : null);
    $field_id = class_share_form_id(isset($_GET['field_id']) ? $_GET['field_id'] : '0', true);
    $field_key = isset($_GET['field_key']) && is_string($_GET['field_key']) ? $_GET['field_key'] : '';
    if ($field_id === 0 && !in_array($field_key, array('', 'name', 'school', 'phone'), true)) {
        throw new DomainException('항목 정보가 올바르지 않습니다.');
    }
} catch (DomainException $exception) {
    http_response_code(400);
    exit($exception->getMessage());
}
$events = pdo_query(
    'SELECT event.id, event.school_id, event.title, school.school_name
     FROM class_share_event AS event INNER JOIN class_share_school AS school ON school.id = event.school_id
     WHERE event.id = ? LIMIT 1', $event_id
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
    exit('신청서를 설정할 권한이 없습니다.');
}
try {
    $form = class_share_form_load($event_id);
} catch (Throwable $exception) {
    error_log('[class-share] 신청서 설정 조회 실패: ' . $exception->getMessage());
    http_response_code(500);
    exit('신청서 설정을 불러올 수 없습니다.');
}
$selected = null;
foreach ($form['fields'] as $field) {
    if (($field_id > 0 && (int)$field['id'] === $field_id)
        || ($field_id === 0 && $field_key !== '' && $field['field_key'] === $field_key)) {
        $selected = $field;
        $field_id = (int)$field['id'];
        $field_key = $field['field_key'];
        break;
    }
}
if (($field_id > 0 || $field_key !== '') && $selected === null) {
    http_response_code(404);
    exit('해당 행사의 항목을 찾을 수 없습니다.');
}
$values = $selected === null
    ? array('label' => '', 'help_text' => '', 'field_type' => 'short_text', 'options_text' => '', 'is_required' => '0', 'is_active' => '1', 'sort_order' => '30')
    : array(
        'label' => $selected['label'], 'help_text' => $selected['help_text'], 'field_type' => $selected['field_type'],
        'options_text' => implode("\n", $selected['options']), 'is_required' => (string)$selected['is_required'],
        'is_active' => (string)$selected['is_active'], 'sort_order' => (string)$selected['sort_order']
    );
$flash = isset($_SESSION['class_share_form_flash']) ? $_SESSION['class_share_form_flash'] : null;
$message = '';
$errors = array();
if (is_array($flash) && isset($flash['event_id']) && (int)$flash['event_id'] === $event_id) {
    unset($_SESSION['class_share_form_flash']);
    $message = isset($flash['message']) ? $flash['message'] : '';
    if (isset($flash['field_id'], $flash['field_key']) && (int)$flash['field_id'] === $field_id && $flash['field_key'] === $field_key) {
        $errors = isset($flash['errors']) ? $flash['errors'] : array();
        if (isset($flash['values']) && is_array($flash['values'])) {
            $values = array_merge($values, $flash['values']);
        }
    }
}
$basic = in_array($field_key, array('name', 'school', 'phone'), true);
$phone = $field_key === 'phone';
// 오류 후에도 기본 항목의 고정 속성은 서버 원본으로 표시합니다.
if ($basic) {
    $values['field_type'] = $selected['field_type'];
    $values['options_text'] = '';
}
if ($phone) {
    $values['label'] = '연락처';
    $values['is_required'] = '1';
    $values['is_active'] = '1';
    $values['sort_order'] = '20';
}
$types = array('short_text' => '짧은 글', 'long_text' => '긴 글', 'single_choice' => '하나 선택', 'multiple_choice' => '여러 개 선택', 'phone' => '전화번호');
$page_title = $event['title'] . ' 신청서 설정';
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
<?php foreach ($errors as $error) { ?><li><?php echo class_share_escape($error); ?></li><?php } ?>
</ul></div>
<?php } ?>
<section class="admin-panel">
    <h2><?php echo class_share_escape($event['title']); ?> — 신청서 항목</h2>
    <p>이름과 소속 항목을 편집하고 필요한 질문을 추가합니다. 연락처와 신청 확인용 비밀번호는 필수로 유지됩니다.</p>
    <?php if ($form['schema_version'] === 0) { ?><p class="admin-muted">아직 수정한 항목이 없어 기본 신청서를 사용합니다.</p><?php } ?>
    <p class="admin-muted">행사 직접 신청과 프로그램 신청에 공통으로 적용됩니다. 기존 신청자의 답변은 변경하지 않습니다.</p>
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>순서</th><th>항목 이름</th><th>입력 방식</th><th>필수 여부</th><th>사용 여부</th><th>관리</th></tr></thead>
        <tbody>
        <?php foreach ($form['fields'] as $field) { ?>
        <tr>
            <td><?php echo (int)$field['sort_order']; ?></td>
            <td><?php echo class_share_escape($field['label']); ?><?php if (in_array($field['field_key'], array('name','school','phone'), true)) { ?> <small class="admin-muted">기본 항목</small><?php } ?></td>
            <td><?php echo class_share_escape(isset($types[$field['field_type']]) ? $types[$field['field_type']] : $field['field_type']); ?></td>
            <td><?php echo (int)$field['is_required'] === 1 ? '필수' : '선택'; ?></td>
            <td><?php echo (int)$field['is_active'] === 1 ? '사용' : '사용 중지'; ?></td>
            <td><a class="admin-table-action" href="/class-share/admin/application_form_fields.php?event_id=<?php echo $event_id; ?>&amp;field_id=<?php echo (int)$field['id']; ?>&amp;field_key=<?php echo rawurlencode($field['field_key']); ?>">수정</a></td>
        </tr>
        <?php } ?>
        </tbody>
    </table></div>
</section>
<section class="admin-panel">
    <h2><?php echo $selected === null ? '질문 추가' : '항목 수정'; ?></h2>
    <form method="post" action="/class-share/admin/application_form_field_save.php">
        <?php echo class_share_admin_csrf_input(); ?>
        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
        <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
        <input type="hidden" name="field_key" value="<?php echo class_share_escape($field_key); ?>">
        <input type="hidden" name="schema_version" value="<?php echo (int)$form['schema_version']; ?>">
        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="field_label">항목 이름 *</label>
                <input id="field_label" name="label" type="text" required maxlength="100" value="<?php echo class_share_escape($values['label']); ?>"<?php echo $phone ? ' readonly' : ''; ?>>
            </div>
            <div class="admin-field">
                <label for="field_type">입력 방식</label>
                <?php if ($basic) { ?>
                <input id="field_type" type="text" readonly value="<?php echo class_share_escape($types[$values['field_type']]); ?>">
                <input type="hidden" name="field_type" value="<?php echo class_share_escape($values['field_type']); ?>">
                <?php } else { ?>
                <select id="field_type" name="field_type">
                <?php foreach ($types as $type => $label) { if ($type === 'phone') { continue; } ?>
                <option value="<?php echo $type; ?>"<?php echo $values['field_type'] === $type ? ' selected' : ''; ?>><?php echo class_share_escape($label); ?></option>
                <?php } ?>
                </select>
                <?php } ?>
            </div>
            <div class="admin-field admin-field-full">
                <label for="field_help">입력 설명</label>
                <textarea id="field_help" name="help_text" maxlength="500" rows="3"><?php echo class_share_escape($values['help_text']); ?></textarea>
                <?php if ($phone) { ?><small class="admin-muted">010-0000-0000 형식 안내에 덧붙일 설명을 입력할 수 있습니다.</small><?php } ?>
            </div>
            <?php if (!$basic) { ?>
            <div class="admin-field admin-field-full">
                <label for="field_options">선택지</label>
                <textarea id="field_options" name="options_text" maxlength="4000" rows="5" placeholder="교직원&#10;학부모&#10;기타"><?php echo class_share_escape($values['options_text']); ?></textarea>
                <small class="admin-muted">선택형 질문은 한 줄에 하나씩 1~30개 입력합니다. 글 입력 질문은 비워 두세요.</small>
            </div>
            <?php } else { ?><input type="hidden" name="options_text" value=""><?php } ?>
            <?php foreach (array('is_required' => '필수 여부', 'is_active' => '사용 여부') as $flag => $label) { ?>
            <div class="admin-field">
                <label for="field_<?php echo $flag; ?>"><?php echo $label; ?></label>
                <?php if ($phone) { ?>
                <input id="field_<?php echo $flag; ?>" type="text" readonly value="<?php echo $flag === 'is_required' ? '항상 필수' : '항상 사용'; ?>">
                <input type="hidden" name="<?php echo $flag; ?>" value="1">
                <?php } else { ?>
                <select id="field_<?php echo $flag; ?>" name="<?php echo $flag; ?>">
                    <option value="1"<?php echo $values[$flag] === '1' ? ' selected' : ''; ?>><?php echo $flag === 'is_required' ? '필수' : '사용'; ?></option>
                    <option value="0"<?php echo $values[$flag] === '0' ? ' selected' : ''; ?>><?php echo $flag === 'is_required' ? '선택' : '사용 중지'; ?></option>
                </select>
                <?php } ?>
            </div>
            <?php } ?>
            <div class="admin-field">
                <label for="field_order">표시 순서</label>
                <input id="field_order" name="sort_order" type="number" min="0" max="1000000" required value="<?php echo class_share_escape($values['sort_order']); ?>"<?php echo $phone ? ' readonly' : ''; ?>>
            </div>
        </div>
        <p class="admin-muted">신청 기록을 보존하기 위해 삭제 대신 사용 중지로 관리합니다. 답변이 있는 질문의 입력 방식과 선택지는 변경할 수 없습니다.</p>
        <div class="admin-form-actions">
            <?php if ($selected !== null) { ?><a class="admin-secondary-link" href="/class-share/admin/application_form_fields.php?event_id=<?php echo $event_id; ?>">새 질문 추가</a><?php } ?>
            <button class="admin-primary-button" type="submit"><?php echo $selected === null ? '질문 추가' : '변경 내용 저장'; ?></button>
        </div>
    </form>
</section>
<?php require_once(__DIR__ . '/include/admin_layout_end.php'); ?>
