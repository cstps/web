<?php

require_once(
    __DIR__ . '/include/admin_init.php'
);

$admin = class_share_admin_require_login();

$event_id = isset($_GET['event_id'])
    ? (int)$_GET['event_id']
    : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$event_rows = pdo_query(
    "
    SELECT event.id, event.school_id, event.title,
           event.academic_year, school.school_name
    FROM class_share_event AS event
    INNER JOIN class_share_school AS school
        ON school.id = event.school_id
    WHERE event.id = ?
    LIMIT 1
    ",
    $event_id
);

if ($event_rows === false) {
    http_response_code(500);
    exit('행사 정보를 불러올 수 없습니다.');
}

if (!isset($event_rows[0])) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

$event = $event_rows[0];

if (!class_share_admin_can_view_sensitive_school(
    (int)$event['school_id'],
    $admin
)) {
    http_response_code(403);
    exit('이 행사의 참관록을 조회할 권한이 없습니다.');
}

$programs = pdo_query(
    "
    SELECT id, title, teacher_name
    FROM class_share_class
    WHERE event_id = ?
    ORDER BY class_start_at, id
    ",
    $event_id
);

$count_rows = pdo_query(
    "
    SELECT class_id, COUNT(*) AS total
    FROM (
        SELECT class_id
        FROM class_share_observation
        WHERE event_id = ?

        UNION ALL

        SELECT class_id
        FROM class_share_observation_archive
        WHERE event_id = ?
    ) AS combined
    GROUP BY class_id
    ",
    $event_id,
    $event_id
);

if ($programs === false || $count_rows === false) {
    http_response_code(500);
    exit('참관록 현황을 불러올 수 없습니다.');
}

$program_map = array();

foreach ($programs as $program) {
    $program_map[(int)$program['id']] = $program;
}

$counts = array();

foreach ($count_rows as $row) {
    $key = $row['class_id'] === null
        ? 0
        : (int)$row['class_id'];

    $counts[$key] = (int)$row['total'];
}

$filter = isset($_GET['class_id'])
    ? trim((string)$_GET['class_id'])
    : '';

$params = array($event_id, $event_id);
$filter_sql = '';

if ($filter === 'none') {
    $filter_sql = ' AND observation.class_id IS NULL';
    $total = isset($counts[0]) ? $counts[0] : 0;
} elseif ($filter !== '') {
    if (
        !ctype_digit($filter) ||
        (int)$filter <= 0 ||
        !isset($program_map[(int)$filter])
    ) {
        http_response_code(400);
        exit('프로그램 번호가 올바르지 않습니다.');
    }

    $filter_sql = ' AND observation.class_id = ?';
    $params[] = (int)$filter;
    $total = isset($counts[(int)$filter])
        ? $counts[(int)$filter]
        : 0;
} else {
    $total = array_sum($counts);
}

$page_size = 20;
$last_page = max(
    1,
    (int)ceil($total / $page_size)
);
$page = isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;
$page = min(max(1, $page), $last_page);
$offset = ($page - 1) * $page_size;

$rows = pdo_query(
    "
    SELECT observation.id,
           observation.class_id,
           observation.author_name,
           observation.affiliation,
           observation.body,
           observation.archive_reviewed_at,
           observation.submitted_at,
           observation.is_archive
    FROM (
        SELECT id, class_id, author_name, affiliation,
               body, archive_reviewed_at, submitted_at,
               0 AS is_archive
        FROM class_share_observation
        WHERE event_id = ?

        UNION ALL

        SELECT id, class_id,
               NULL AS author_name,
               NULL AS affiliation,
               body,
               NULL AS archive_reviewed_at,
               NULL AS submitted_at,
               1 AS is_archive
        FROM class_share_observation_archive
        WHERE event_id = ?
    ) AS observation
    WHERE 1 = 1
    " . $filter_sql . "
    ORDER BY observation.is_archive,
             observation.submitted_at DESC,
             observation.id DESC
    LIMIT " . $page_size . "
    OFFSET " . $offset,
    ...$params
);

if ($rows === false) {
    http_response_code(500);
    exit('참관록 목록을 불러올 수 없습니다.');
}

$page_title = $event['title'] . ' 참관록 관리';
$active_menu = 'schools';

require_once(
    __DIR__ . '/include/admin_layout_start.php'
);

?>
<style>
.observation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
}
.observation-tile {
    min-width: 0;
    padding: 18px;
    border: 1px solid #d9e0e8;
    border-radius: 12px;
    background: #fff;
}
.observation-tile h2,
.observation-tile h3 {
    margin: 0 0 10px;
}
.observation-tile p {
    margin: 8px 0;
}
.observation-tile-content {
    max-height: 320px;
    overflow: auto;
    overflow-wrap: anywhere;
    white-space: normal;
}
.observation-tile-current {
    border-color: #2767b2;
    box-shadow: 0 0 0 2px rgba(39, 103, 178, .14);
}
.observation-pages {
    display: flex;
    gap: 16px;
    margin-top: 18px;
}
</style>

<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <?php echo class_share_escape($event['school_name']); ?>
            · <?php echo (int)$event['academic_year']; ?>학년도
        </p>
        <h1><?php echo class_share_escape($page_title); ?></h1>
        <p>전체 <?php echo array_sum($counts); ?>건</p>
        <p class="admin-muted">
            각 참관록의 ‘보존용 내용 검토’에서 개인정보를 제거한
            내용을 저장하면, 보관 기한 이후 그 내용과 프로그램
            연결만 별도로 보관됩니다. 검토하지 않은 원문은 삭제됩니다.
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/events.php?school_id=<?php
            echo (int)$event['school_id'];
            ?>">
            ← 행사 목록
        </a>
    </div>

    <?php if (
        class_share_admin_can_edit_school(
            (int)$event['school_id'],
            $admin
        )
    ) { ?>
        <a
            class="admin-submit-button"
            href="/class-share/admin/observation_settings.php?event_id=<?php
            echo (int)$event_id;
            ?>">
            참관록 접수 설정
        </a>
    <?php } ?>

    <?php if ($total > 0) { ?>
        <form
            method="post"
            action="/class-share/admin/observations_export.php"
            class="admin-inline-form">
            <?php echo class_share_admin_csrf_input(); ?>

            <input
                type="hidden"
                name="event_id"
                value="<?php echo (int)$event_id; ?>">

            <input
                type="hidden"
                name="class_id"
                value="<?php echo class_share_escape($filter); ?>">

            <button
                type="submit"
                class="admin-submit-button">
                DOCX 내려받기
            </button>
        </form>
    <?php } ?>
</div>

<section class="admin-panel">
    <h2>프로그램별 참관록</h2>

    <div class="observation-grid">
        <?php
        $groups = array(
            array('', '전체', array_sum($counts))
        );

        foreach ($programs as $program) {
            $id = (int)$program['id'];
            $groups[] = array(
                (string)$id,
                $program['title'],
                isset($counts[$id]) ? $counts[$id] : 0
            );
        }

        if (
            count($programs) === 0 ||
            !empty($counts[0])
        ) {
            $groups[] = array(
                'none',
                '행사 단위',
                isset($counts[0]) ? $counts[0] : 0
            );
        }

        foreach ($groups as $group) {
            $url = '/class-share/admin/observations.php?' .
                http_build_query(array(
                    'event_id' => $event_id,
                    'class_id' => $group[0]
                ));
            ?>
            <a
                class="observation-tile<?php
                echo $filter === $group[0]
                    ? ' observation-tile-current'
                    : '';
                ?>"
                href="<?php echo class_share_escape($url); ?>">
                <strong>
                    <?php echo class_share_escape($group[1]); ?>
                </strong>
                <p><?php echo (int)$group[2]; ?>건</p>
            </a>
        <?php } ?>
    </div>
</section>

<section class="admin-panel">
    <h2>참관록 목록 · <?php echo (int)$total; ?>건</h2>

    <?php if (count($rows) === 0) { ?>
        <div class="admin-empty">
            <strong>제출된 참관록이 없습니다.</strong>
        </div>
    <?php } else { ?>
        <div class="observation-grid">
            <?php foreach ($rows as $row) { ?>
                <article class="observation-tile">
                    <h3>
                        <?php
                        $program_id = $row['class_id'] === null
                            ? 0
                            : (int)$row['class_id'];

                        echo $program_id === 0
                            ? '행사 단위'
                            : class_share_escape(
                                $program_map[$program_id]['title']
                            );
                        ?>
                    </h3>

                    <p>
                        <strong>
                            <?php
                            echo class_share_escape(
                                (int)$row['is_archive'] === 1
                                    ? '익명 보존본'
                                    : $row['author_name']
                            );
                            ?>
                        </strong>
                        ·
                        <?php
                        echo class_share_escape(
                            (int)$row['is_archive'] === 1
                                ? '검토된 참관 내용'
                                : $row['affiliation']
                        );
                        ?>
                    </p>

                    <p class="admin-muted">
                        <?php
                        echo class_share_escape(
                            (int)$row['is_archive'] === 1
                                ? '보관 기한 이후 보존된 내용'
                                : $row['submitted_at']
                        );
                        ?>
                    </p>

                    <div class="observation-tile-content">
                        <?php
                        echo nl2br(
                            class_share_escape($row['body']),
                            false
                        );
                        ?>
                    </div>

                    <p class="admin-muted">
                        <?php
                        echo (int)$row['is_archive'] === 1
                            ? '익명 보존본'
                            : (
                                $row['archive_reviewed_at'] === null
                                    ? '보존용 내용 미검토'
                                    : '보존용 내용 검토 완료'
                            );
                        ?>
                    </p>

                    <?php if ((int)$row['is_archive'] === 0) { ?>
                        <p>
                            <a
                                class="admin-table-action"
                                href="/class-share/admin/observation_review.php?event_id=<?php
                                echo (int)$event_id;
                                ?>&amp;observation_id=<?php
                                echo (int)$row['id'];
                                ?>">
                                보존용 내용 검토
                            </a>
                        </p>
                    <?php } ?>
                </article>
            <?php } ?>
        </div>

        <nav class="observation-pages" aria-label="참관록 페이지">
            <?php if ($page > 1) {
                $url = '/class-share/admin/observations.php?' .
                    http_build_query(array(
                        'event_id' => $event_id,
                        'class_id' => $filter,
                        'page' => $page - 1
                    ));
                ?>
                <a href="<?php echo class_share_escape($url); ?>">
                    이전
                </a>
            <?php } ?>

            <span><?php echo $page; ?> / <?php echo $last_page; ?></span>

            <?php if ($page < $last_page) {
                $url = '/class-share/admin/observations.php?' .
                    http_build_query(array(
                        'event_id' => $event_id,
                        'class_id' => $filter,
                        'page' => $page + 1
                    ));
                ?>
                <a href="<?php echo class_share_escape($url); ?>">
                    다음
                </a>
            <?php } ?>
        </nav>
    <?php } ?>
</section>

<?php

require_once(
    __DIR__ . '/include/admin_layout_end.php'
);
