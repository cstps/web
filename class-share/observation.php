<?php

require_once(
    dirname(__DIR__) . '/include/db_info.inc.php'
);
require_once(
    dirname(__DIR__) . '/include/pdo.php'
);
require_once(
    __DIR__ . '/include/public_functions.php'
);
require_once(
    __DIR__ . '/include/application_functions.php'
);

header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "style-src 'self'; " .
    "img-src 'self' data:; " .
    "script-src 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "object-src 'none'"
);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, max-age=0');

$school_slug = isset($_GET['school'])
    ? trim((string)$_GET['school'])
    : '';

$event_slug = isset($_GET['event'])
    ? trim((string)$_GET['event'])
    : '';

$error = '';
$event = null;
$programs = array();
$accepting = false;

if (
    !class_share_public_valid_slug($school_slug) ||
    !class_share_public_valid_slug($event_slug)
) {
    http_response_code(404);
    $error = '참관록 작성 주소가 올바르지 않습니다.';
} else {
    $rows = pdo_query(
        "
        SELECT event.id, event.title, event.slug,
               event.application_mode, event.status,
               school.school_name, school.slug AS school_slug,
               setting.open_at, setting.close_at,
               setting.privacy_notice,
               setting.retention_until,
               CASE
                   WHEN NOW() >= setting.open_at
                    AND NOW() <= setting.close_at
                   THEN 1 ELSE 0
               END AS accepting
        FROM class_share_event AS event
        INNER JOIN class_share_school AS school
            ON school.id = event.school_id
        INNER JOIN class_share_observation_setting AS setting
            ON setting.event_id = event.id
        WHERE school.slug = ?
          AND school.status = 'active'
          AND event.slug = ?
          AND event.status IN ('published', 'closed')
          AND setting.enabled = 1
          AND setting.retention_until IS NOT NULL
        LIMIT 1
        ",
        $school_slug,
        $event_slug
    );

    if ($rows === false) {
        http_response_code(500);
        $error = '행사 정보를 불러올 수 없습니다.';
    } elseif (!isset($rows[0])) {
        http_response_code(404);
        $error = '참관록을 받는 행사를 찾을 수 없습니다.';
    } else {
        $event = $rows[0];
        $accepting = (int)$event['accepting'] === 1;
    }
}

if (
    $event !== null &&
    $event['application_mode'] === 'program'
) {
    $programs = pdo_query(
        "
        SELECT id, title, teacher_name, class_start_at
        FROM class_share_class
        WHERE event_id = ?
          AND status IN ('published', 'closed')
        ORDER BY sort_order, class_start_at, id
        ",
        (int)$event['id']
    );

    if ($programs === false) {
        http_response_code(500);
        $error = '공개 프로그램을 불러올 수 없습니다.';
        $programs = array();
        $accepting = false;
    }
}

$show_form =
    $error === '' &&
    $accepting &&
    (
        $event['application_mode'] !== 'program' ||
        count($programs) > 0
    );

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$submitted = false;
$success = isset($_SESSION['class_share_observation_success'])
    && is_array($_SESSION['class_share_observation_success'])
    ? $_SESSION['class_share_observation_success']
    : null;

if ($success !== null) {
    unset($_SESSION['class_share_observation_success']);

    $submitted =
        $event !== null &&
        (int)$success['event_id'] === (int)$event['id'] &&
        (int)$success['at'] >= time() - 600;
}

if ($submitted) {
    $show_form = false;
}

$csrf_token = '';
$submission_key = '';

if ($show_form) {
    $csrf_token =
        class_share_application_csrf_token();

    $submission_key =
        bin2hex(random_bytes(32));

    $keys = isset(
        $_SESSION['class_share_observation_keys']
    ) && is_array(
        $_SESSION['class_share_observation_keys']
    )
        ? $_SESSION['class_share_observation_keys']
        : array();

    foreach ($keys as $key => $issued_at) {
        if ((int)$issued_at < time() - 3600) {
            unset($keys[$key]);
        }
    }

    $keys[$submission_key] = time();

    $_SESSION['class_share_observation_keys'] =
        array_slice($keys, -10, null, true);
}

$page_title = $event === null
    ? '참관록 작성'
    : $event['title'] . ' 참관록 작성';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title><?php
        echo class_share_public_escape($page_title);
    ?></title>

    <link
        rel="stylesheet"
        href="/class-share/assets/public.css?v=20260925-1">

    <link
        rel="stylesheet"
        href="/class-share/assets/observation.css">
</head>
<body>
<main class="observation-main">
    <div class="observation-panel">
        <p class="observation-muted">
            <?php if ($event !== null) {
                echo class_share_public_escape(
                    $event['school_name']
                );
            } ?>
        </p>

        <h1><?php
            echo class_share_public_escape($page_title);
        ?></h1>

        <?php if ($event !== null) { ?>
            <p>
                <a href="/class-share/index.php?school=<?php
                    echo rawurlencode($school_slug);
                ?>&amp;event=<?php
                    echo rawurlencode($event_slug);
                ?>">
                    ← 행사 안내로 돌아가기
                </a>
            </p>
        <?php } ?>

        <?php if ($error !== '') { ?>
            <p role="alert">
                <?php echo class_share_public_escape($error); ?>
            </p>
        <?php } elseif ($submitted) { ?>
            <p role="status">
                참관록이 제출되었습니다. 감사합니다.
            </p>
        <?php } elseif (!$accepting) { ?>
            <p>현재 참관록 접수 기간이 아닙니다.</p>
        <?php } elseif (!$show_form) { ?>
            <p>선택할 수 있는 공개 프로그램이 없습니다.</p>
        <?php } else { ?>
            <p>
                행사 신청 여부와 관계없이 작성할 수 있습니다.
            </p>

            <form
                method="post"
                action="/class-share/observation_submit.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                    echo class_share_public_escape(
                        $csrf_token
                    );
                    ?>">

                <input
                    type="hidden"
                    name="submission_key"
                    value="<?php
                    echo class_share_public_escape(
                        $submission_key
                    );
                    ?>">

                <input
                    type="hidden"
                    name="school"
                    value="<?php
                    echo class_share_public_escape(
                        $school_slug
                    );
                    ?>">

                <input
                    type="hidden"
                    name="event"
                    value="<?php
                    echo class_share_public_escape(
                        $event_slug
                    );
                    ?>">

                <input
                    type="text"
                    name="website"
                    autocomplete="off"
                    tabindex="-1"
                    hidden>

                <?php if (
                    $event['application_mode'] === 'program'
                ) { ?>
                    <div class="observation-field">
                        <label for="class_id">
                            참관한 프로그램 *
                        </label>

                        <select
                            id="class_id"
                            name="class_id"
                            required>
                            <option value="">
                                프로그램 선택
                            </option>

                            <?php foreach (
                                $programs as $program
                            ) { ?>
                                <option value="<?php
                                    echo (int)$program['id'];
                                ?>">
                                    <?php
                                    echo class_share_public_escape(
                                        $program['title'] .
                                        ' · ' .
                                        $program['teacher_name']
                                    );
                                    ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>

                <div class="observation-field">
                    <label for="author_name">성명 *</label>
                    <input
                        id="author_name"
                        name="author_name"
                        type="text"
                        maxlength="100"
                        required>
                </div>

                <div class="observation-field">
                    <label for="affiliation">소속 *</label>
                    <input
                        id="affiliation"
                        name="affiliation"
                        type="text"
                        maxlength="150"
                        required>
                </div>

                <div class="observation-field">
                    <label for="body">참관 내용 *</label>
                    <textarea
                        id="body"
                        name="body"
                        rows="12"
                        maxlength="5000"
                        required></textarea>
                    <p class="observation-muted">
                        최대 5,000자
                    </p>
                </div>

                <div class="observation-privacy">
                    <h2>개인정보 수집·이용 안내</h2>

                    <p><?php
                        echo nl2br(
                            class_share_public_escape(
                                $event['privacy_notice']
                            ),
                            false
                        );
                    ?></p>

                    <p>
                        참관록 보관 기한:
                        <?php
                        echo class_share_public_escape(
                            $event['retention_until']
                        );
                        ?>
                    </p>

                    <label>
                        <input
                            type="checkbox"
                            name="privacy_consent"
                            value="1"
                            required>
                        개인정보 수집·이용에 동의합니다.
                    </label>
                </div>

                <button
                    class="public-button"
                    type="submit">
                    참관록 제출
                </button>
            </form>
        <?php } ?>
    </div>
</main>
</body>
</html>
