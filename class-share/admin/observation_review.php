<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

$admin = class_share_admin_require_login();

$is_post =
    isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST';

if ($is_post) {
    class_share_admin_require_post_csrf();
}

$source = $is_post ? $_POST : $_GET;

$event_id = isset($source['event_id'])
    ? (int)$source['event_id']
    : 0;

$observation_id = isset($source['observation_id'])
    ? (int)$source['observation_id']
    : 0;

if ($event_id <= 0 || $observation_id <= 0) {
    http_response_code(400);
    exit('참관록 주소가 올바르지 않습니다.');
}

$rows = pdo_query(
    "
    SELECT
        observation.id,
        observation.event_id,
        observation.author_name,
        observation.affiliation,
        observation.body,
        observation.archive_body,
        observation.archive_reviewed_at,
        observation.retention_until,
        observation.submitted_at,
        observation.retention_until <
            DATE(UTC_TIMESTAMP() + INTERVAL 9 HOUR)
            AS expired,
        event.school_id,
        event.title AS event_title,
        class_item.title AS class_title

    FROM class_share_observation AS observation

    INNER JOIN class_share_event AS event
        ON event.id = observation.event_id

    LEFT JOIN class_share_class AS class_item
        ON class_item.id = observation.class_id

    WHERE observation.id = ?
      AND observation.event_id = ?

    LIMIT 1
    ",
    $observation_id,
    $event_id
);

if ($rows === false) {
    http_response_code(500);
    exit('참관록을 불러올 수 없습니다.');
}

if (!isset($rows[0])) {
    http_response_code(404);
    exit('참관록을 찾을 수 없습니다.');
}

$observation = $rows[0];
$school_id = (int)$observation['school_id'];

if (
    !class_share_admin_can_view_sensitive_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('참관록을 조회할 권한이 없습니다.');
}

$can_review =
    class_share_admin_can_edit_school(
        $school_id,
        $admin
    );

if ($is_post) {
    if (!$can_review) {
        http_response_code(403);
        exit('보존용 내용을 검토할 권한이 없습니다.');
    }

    if ((int)$observation['expired'] === 1) {
        http_response_code(409);
        exit('보관 기한이 지나 검토할 수 없습니다.');
    }

    $action = isset($_POST['action'])
        ? (string)$_POST['action']
        : '';

    if ($action === 'save') {
        $archive_body = isset($_POST['archive_body'])
            ? trim((string)$_POST['archive_body'])
            : '';

        $length = function_exists('mb_strlen')
            ? mb_strlen($archive_body, 'UTF-8')
            : strlen($archive_body);

        if ($length < 1 || $length > 5000) {
            http_response_code(400);
            exit('보존용 내용은 1~5,000자로 입력해 주세요.');
        }

        $reviewer_id = isset($admin['id'])
            ? (int)$admin['id']
            : null;

        $result = pdo_query(
            "
            UPDATE class_share_observation
            SET archive_body = ?,
                archive_reviewed_at = NOW(),
                archive_reviewed_by = ?
            WHERE id = ?
              AND event_id = ?
              AND retention_until >=
                  DATE(UTC_TIMESTAMP() + INTERVAL 9 HOUR)
            ",
            $archive_body,
            $reviewer_id,
            $observation_id,
            $event_id
        );
    } elseif ($action === 'remove') {
        $result = pdo_query(
            "
            UPDATE class_share_observation
            SET archive_body = NULL,
                archive_reviewed_at = NULL,
                archive_reviewed_by = NULL
            WHERE id = ?
              AND event_id = ?
              AND retention_until >=
                  DATE(UTC_TIMESTAMP() + INTERVAL 9 HOUR)
            ",
            $observation_id,
            $event_id
        );
    } else {
        http_response_code(400);
        exit('검토 작업이 올바르지 않습니다.');
    }

    if ($result === false) {
        http_response_code(500);
        exit('보존용 내용을 저장하지 못했습니다.');
    }

    header(
        'Location: /class-share/admin/observation_review.php' .
        '?event_id=' . $event_id .
        '&observation_id=' . $observation_id .
        '&saved=1',
        true,
        303
    );
    exit;
}

$page_title =
    (string)$observation['event_title'] .
    ' 참관록 검토';

$active_menu = 'schools';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <?php echo class_share_escape(
                $observation['event_title']
            ); ?>
        </p>

        <h1>참관록 보존용 내용 검토</h1>

        <a
            class="admin-back-link"
            href="/class-share/admin/observations.php?event_id=<?php
            echo $event_id;
            ?>">
            ← 참관록 관리
        </a>
    </div>
</div>

<?php if (isset($_GET['saved'])) { ?>
    <section class="admin-panel">
        <p role="status">검토 내용이 저장되었습니다.</p>
    </section>
<?php } ?>

<section class="admin-panel">
    <h2>제출된 원문</h2>

    <p>
        <?php echo class_share_escape(
            $observation['author_name']
        ); ?>
        ·
        <?php echo class_share_escape(
            $observation['affiliation']
        ); ?>
        ·
        <?php echo class_share_escape(
            $observation['class_title'] === null
                ? '행사 전체'
                : $observation['class_title']
        ); ?>
    </p>

    <p class="admin-muted">
        제출:
        <?php echo class_share_escape(
            $observation['submitted_at']
        ); ?>
        · 보관 기한:
        <?php echo class_share_escape(
            $observation['retention_until']
        ); ?>
    </p>

    <div style="white-space: pre-wrap; overflow-wrap: anywhere;">
        <?php echo class_share_escape(
            $observation['body']
        ); ?>
    </div>
</section>

<section class="admin-panel">
    <h2>기한 이후 보존할 내용</h2>

    <p>
        원문에서 이름, 소속, 연락처, 구체적인 개인 사례와
        다른 자료와 결합해 개인을 알아볼 수 있는 표현을
        제거한 뒤 입력해 주세요.
        ‘보존용 내용 저장’을 누르면 검토한 내용이 보존 대상으로
        지정됩니다. 확신할 수 없으면 저장하지 마세요.
    </p>

    <?php if ($observation['archive_reviewed_at'] !== null) { ?>
        <p class="admin-muted">
            마지막 검토:
            <?php echo class_share_escape(
                $observation['archive_reviewed_at']
            ); ?>
        </p>
    <?php } ?>

    <?php if ((int)$observation['expired'] === 1) { ?>
        <p>보관 기한이 지나 검토할 수 없습니다.</p>
    <?php } elseif (!$can_review) { ?>
        <p>검토 권한이 없습니다.</p>
    <?php } else { ?>
        <form
            method="post"
            action="/class-share/admin/observation_review.php">

            <?php echo class_share_admin_csrf_input(); ?>

            <input
                type="hidden"
                name="event_id"
                value="<?php echo $event_id; ?>">

            <input
                type="hidden"
                name="observation_id"
                value="<?php echo $observation_id; ?>">

            <div class="admin-field">
                <label for="archive_body">
                    보존용 참관 내용
                </label>

                <textarea
                    id="archive_body"
                    name="archive_body"
                    rows="12"
                    maxlength="5000"><?php
                    echo class_share_escape(
                        $observation['archive_body'] === null
                            ? ''
                            : $observation['archive_body']
                    );
                    ?></textarea>
            </div>

            <button
                class="admin-submit-button"
                type="submit"
                name="action"
                value="save">
                보존용 내용 저장
            </button>

            <?php if ($observation['archive_body'] !== null) { ?>
                <button
                    class="admin-submit-button"
                    type="submit"
                    name="action"
                    value="remove">
                    보존용 내용 취소
                </button>
            <?php } ?>
        </form>
    <?php } ?>
</section>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
