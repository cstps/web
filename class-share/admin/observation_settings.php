<?php

require_once(__DIR__ . '/include/admin_init.php');

$admin = class_share_admin_require_login();

$event_id = isset($_GET['event_id'])
    ? (int)$_GET['event_id']
    : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$rows = pdo_query(
    "
    SELECT event.id, event.school_id, event.title,
           event.academic_year, event.event_start_at,
           school.school_name,
           setting.enabled, setting.open_at,
           setting.close_at, setting.privacy_notice,
           setting.retention_until
    FROM class_share_event AS event
    INNER JOIN class_share_school AS school
        ON school.id = event.school_id
    LEFT JOIN class_share_observation_setting AS setting
        ON setting.event_id = event.id
    WHERE event.id = ?
    LIMIT 1
    ",
    $event_id
);

if ($rows === false) {
    http_response_code(500);
    exit('참관록 설정을 불러올 수 없습니다.');
}

if (!isset($rows[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

$event = $rows[0];

if (!class_share_admin_can_edit_school(
    (int)$event['school_id'],
    $admin
)) {
    http_response_code(403);
    exit('참관록 접수를 설정할 권한이 없습니다.');
}

$initial_open_at = $event['open_at'] !== null
    ? $event['open_at']
    : $event['event_start_at'];

$open_value = $initial_open_at === null
    ? ''
    : str_replace(
        ' ',
        'T',
        substr($initial_open_at, 0, 16)
    );

$close_value = $event['close_at'] === null
    ? ''
    : str_replace(
        ' ',
        'T',
        substr($event['close_at'], 0, 16)
    );

$default_privacy_notice =
    "수집 항목: 성명, 소속, 참관 내용\n" .
    "수집 목적: 행사 참관록 접수·확인 및 운영\n" .
    "보유 기간: 아래 참관록 보관 기한까지\n" .
    "동의를 거부할 수 있으나, 동의하지 않으면 참관록을 제출할 수 없습니다.";

$privacy_notice_value =
    trim((string)$event['privacy_notice']) !== ''
    ? (string)$event['privacy_notice']
    : $default_privacy_notice;

$page_title = $event['title'] . ' 참관록 접수 설정';
$active_menu = 'schools';

require_once(
    __DIR__ . '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <?php echo class_share_escape($event['school_name']); ?>
            · <?php echo (int)$event['academic_year']; ?>학년도
        </p>

        <h1><?php echo class_share_escape($page_title); ?></h1>

        <a
            class="admin-back-link"
            href="/class-share/admin/observations.php?event_id=<?php
            echo (int)$event_id;
            ?>">
            ← 참관록 관리
        </a>
    </div>
</div>

<?php if (
    isset($_GET['saved']) &&
    $_GET['saved'] === '1'
) { ?>
    <div class="admin-panel" role="status">
        참관록 접수 설정을 저장했습니다.
    </div>
<?php } ?>

<section class="admin-panel">
    <form
        method="post"
        action="/class-share/admin/observation_settings_save.php">

        <?php echo class_share_admin_csrf_input(); ?>

        <input
            type="hidden"
            name="event_id"
            value="<?php echo (int)$event_id; ?>">

        <fieldset class="admin-field">
            <legend><strong>참관록 접수 상태</strong></legend>

            <p role="status">
                현재 저장된 설정:
                <strong><?php
                echo (int)$event['enabled'] === 1
                    ? '접수 켜짐'
                    : '접수 중지';
                ?></strong>
            </p>

            <label style="display:flex; align-items:center;
                gap:12px; padding:16px; margin:10px 0;
                border:2px solid #cbd5e1; border-radius:10px;
                cursor:pointer;">
                <input
                    type="radio"
                    name="enabled"
                    value="1"
                    required
                    style="width:24px; height:24px; flex:none;"
                    <?php
                    echo (int)$event['enabled'] === 1
                        ? 'checked'
                        : '';
                    ?>>
                <span>
                    <strong>접수 켜기</strong><br>
                    설정한 기간에 공개 행사 페이지에서
                    참관록 작성 버튼을 표시합니다.
                </span>
            </label>

            <label style="display:flex; align-items:center;
                gap:12px; padding:16px; margin:10px 0;
                border:2px solid #cbd5e1; border-radius:10px;
                cursor:pointer;">
                <input
                    type="radio"
                    name="enabled"
                    value="0"
                    required
                    style="width:24px; height:24px; flex:none;"
                    <?php
                    echo (int)$event['enabled'] !== 1
                        ? 'checked'
                        : '';
                    ?>>
                <span>
                    <strong>접수 중지</strong><br>
                    공개 행사 페이지에서 참관록 작성을 받지 않습니다.
                </span>
            </label>
        </fieldset>

        <div class="admin-field">
            <label for="open_at">접수 시작</label>
            <p class="admin-muted">
                처음에는 행사 시작일시를 표시합니다.
                필요하면 수정할 수 있습니다.
            </p>
            <input
                id="open_at"
                type="datetime-local"
                name="open_at"
                value="<?php
                echo class_share_escape($open_value);
                ?>">
        </div>

        <div class="admin-field">
            <label for="close_at">접수 마감</label>
            <input
                id="close_at"
                type="datetime-local"
                name="close_at"
                value="<?php
                echo class_share_escape($close_value);
                ?>">
        </div>

        <div class="admin-field">
            <label for="privacy_notice">
                참관록 개인정보 수집·이용 안내
            </label>
            <textarea
                id="privacy_notice"
                name="privacy_notice"
                rows="7"
                placeholder="수집 항목, 이용 목적, 보유 기간, 동의 거부 시 제한을 입력해 주세요."><?php
                echo class_share_escape(
                    $privacy_notice_value
                );
            ?></textarea>
            <p class="admin-muted">
                행사 신청 안내와 별도로,
                참관록 제출자에게 표시됩니다.
            </p>
        </div>

        <div class="admin-field">
            <label for="retention_until">
                참관록 보관 기한
            </label>
            <input
                id="retention_until"
                type="date"
                name="retention_until"
                value="<?php
                echo class_share_escape(
                    $event['retention_until'] === null
                        ? ''
                        : $event['retention_until']
                );
                ?>">
        </div>

        <button
            type="submit"
            class="admin-submit-button">
            설정 저장
        </button>
    </form>
</section>

<?php

require_once(
    __DIR__ . '/include/admin_layout_end.php'
);
