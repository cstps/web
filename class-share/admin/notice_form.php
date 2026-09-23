<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$event_id =
    isset($_GET['event_id'])
    ? (int)$_GET['event_id']
    : 0;

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$event_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id,
            event.title,
            event.academic_year,
            event.status,
            event.event_start_at,
            event.event_end_at,
            school.school_name

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

$event =
    $event_rows[0];

$school_id =
    (int)$event['school_id'];

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 행사의 공지를 작성할 권한이 없습니다.');
}

if (
    in_array(
        (string)$event['status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    http_response_code(409);
    exit('취소되거나 보관된 행사에는 공지를 추가할 수 없습니다.');
}

$sort_rows =
    pdo_query(
        "
        SELECT
            COALESCE(
                MAX(sort_order),
                0
            ) + 10 AS next_sort_order

        FROM class_share_notice

        WHERE event_id = ?
        ",
        $event_id
    );

if (
    $sort_rows === false ||
    !isset($sort_rows[0])
) {
    http_response_code(500);
    exit('공지 정렬 순서를 확인할 수 없습니다.');
}

$next_sort_order =
    (int)$sort_rows[0][
        'next_sort_order'
    ];

$format_datetime =
    function ($value) {
        if (
            $value === null ||
            $value === ''
        ) {
            return '-';
        }

        $timestamp =
            strtotime(
                (string)$value
            );

        if ($timestamp === false) {
            return (string)$value;
        }

        return date(
            'Y-m-d H:i',
            $timestamp
        );
    };

$default_values =
    array(
        'title' => '',
        'content' => '',
        'important' => '0',
        'sort_order' =>
            (string)$next_sort_order
    );

$form_errors =
    isset(
        $_SESSION[
            'class_share_notice_form_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_notice_form_errors'
        ]
    )
    ? $_SESSION[
        'class_share_notice_form_errors'
    ]
    : array();

$saved_values =
    isset(
        $_SESSION[
            'class_share_notice_form_values'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_notice_form_values'
        ]
    )
    ? $_SESSION[
        'class_share_notice_form_values'
    ]
    : array();

$form_values =
    array_merge(
        $default_values,
        $saved_values
    );

unset(
    $_SESSION[
        'class_share_notice_form_errors'
    ],
    $_SESSION[
        'class_share_notice_form_values'
    ]
);

$page_title =
    $event['title'] .
    ' 공지 추가';

$active_menu =
    'schools';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            <?php
            echo class_share_escape(
                $event['school_name']
            );
            ?>
            ·
            <?php
            echo (int)$event['academic_year'];
            ?>학년도
        </p>

        <p class="admin-muted">
            행사 운영:
            <strong>
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $event['event_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $event['event_end_at']
                    )
                );
                ?>
            </strong>
        </p>

        <p class="admin-muted">
            공지는 작성 중 상태로 생성됩니다.
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/notices.php?event_id=<?php
            echo (int)$event_id;
            ?>">
            ← 공지 목록
        </a>
    </div>
</div>

<?php if (count($form_errors) > 0) { ?>
    <div
        class="admin-error"
        role="alert">

        <strong>입력 내용을 확인해 주세요.</strong>

        <ul>
            <?php foreach ($form_errors as $error) { ?>
                <li>
                    <?php
                    echo class_share_escape(
                        $error
                    );
                    ?>
                </li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>

<section class="admin-panel">
    <form
        method="post"
        action="/class-share/admin/notice_save.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="event_id"
            value="<?php echo (int)$event_id; ?>">

        <div class="admin-form-grid">
            <div class="admin-field admin-field-full">
                <label for="title">
                    공지 제목 *
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    required
                    maxlength="200"
                    value="<?php
                    echo class_share_escape(
                        $form_values['title']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="sort_order">
                    정렬 순서 *
                </label>

                <input
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    required
                    min="0"
                    max="999999"
                    value="<?php
                    echo class_share_escape(
                        $form_values['sort_order']
                    );
                    ?>">

                <small class="admin-muted">
                    숫자가 작은 공지부터 먼저 표시됩니다.
                    중요 공지는 일반 공지보다 먼저 표시됩니다.
                </small>
            </div>

            <div class="admin-field">
                <label for="important">
                    중요 공지
                </label>

                <label>
                    <input
                        type="checkbox"
                        id="important"
                        name="important"
                        value="1"
                        <?php
                        echo (string)$form_values[
                            'important'
                        ] === '1'
                        ? 'checked'
                        : '';
                        ?>>
                    중요 공지로 표시
                </label>

                <small class="admin-muted">
                    중요한 안내에만 사용해 주세요.
                </small>
            </div>

            <div class="admin-field admin-field-full">
                <label for="content">
                    공지 내용 *
                </label>

                <textarea
                    class="class-share-rich-editor"
                    id="content"
                    name="content"
                    rows="12"><?php
                    echo class_share_escape(
                        $form_values['content']
                    );
                    ?></textarea>

                <small class="admin-muted">
                    제목, 강조, 목록, 표와 링크를 사용할 수 있습니다.
                    실제 글 내용은 10,000자 이하로 입력해 주세요.
                </small>
            </div>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/notices.php?event_id=<?php
                echo (int)$event_id;
                ?>">
                취소
            </a>

            <button
                class="admin-primary-button"
                type="submit">
                공지 저장
            </button>
        </div>
    </form>
</section>

<script
    src="/tinymce/tinymce.min.js?v=8.9.0"></script>

<script
    src="/class-share/admin/assets/class-editor.js?v=20260922"></script>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
