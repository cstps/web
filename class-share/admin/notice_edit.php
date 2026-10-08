<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$notice_id =
    isset($_GET['notice_id'])
    ? (int)$_GET['notice_id']
    : 0;

if ($notice_id <= 0) {
    http_response_code(400);
    exit('공지 번호가 올바르지 않습니다.');
}

$notice_rows =
    pdo_query(
        "
        SELECT
            notice.id,
            notice.event_id,
            notice.title,
            notice.content,
            notice.important,
            notice.status,
            notice.published_at,
            notice.sort_order,

            event.school_id,
            event.title AS event_title,
            event.academic_year,
            event.status AS event_status,
            event.event_start_at,
            event.event_end_at,

            school.school_name

        FROM class_share_notice AS notice

        INNER JOIN class_share_event AS event
            ON event.id = notice.event_id

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE notice.id = ?

        LIMIT 1
        ",
        $notice_id
    );

if ($notice_rows === false) {
    http_response_code(500);
    exit('공지 정보를 불러올 수 없습니다.');
}

if (!isset($notice_rows[0])) {
    http_response_code(404);
    exit('공지를 찾을 수 없습니다.');
}

$notice =
    $notice_rows[0];

$event_id =
    (int)$notice['event_id'];

$school_id =
    (int)$notice['school_id'];

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 공지를 수정할 권한이 없습니다.');
}

if (
    in_array(
        (string)$notice['event_status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    http_response_code(409);
    exit('취소되거나 보관된 행사의 공지는 수정할 수 없습니다.');
}

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
        'title' =>
            (string)$notice['title'],

        'content' =>
            (string)$notice['content'],

        'important' =>
            (int)$notice['important'] === 1
            ? '1'
            : '0',

        'sort_order' =>
            (string)$notice['sort_order'],

        'status' =>
            (string)$notice['status']
    );

$saved_notice_id =
    isset(
        $_SESSION[
            'class_share_notice_edit_id'
        ]
    )
    ? (int)$_SESSION[
        'class_share_notice_edit_id'
    ]
    : 0;

$form_errors =
    $saved_notice_id === $notice_id &&
    isset(
        $_SESSION[
            'class_share_notice_edit_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_notice_edit_errors'
        ]
    )
    ? $_SESSION[
        'class_share_notice_edit_errors'
    ]
    : array();

$saved_values =
    $saved_notice_id === $notice_id &&
    isset(
        $_SESSION[
            'class_share_notice_edit_values'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_notice_edit_values'
        ]
    )
    ? $_SESSION[
        'class_share_notice_edit_values'
    ]
    : array();

$form_values =
    array_merge(
        $default_values,
        $saved_values
    );

unset(
    $_SESSION[
        'class_share_notice_edit_id'
    ],
    $_SESSION[
        'class_share_notice_edit_errors'
    ],
    $_SESSION[
        'class_share_notice_edit_values'
    ]
);

$status_names =
    array(
        'draft' => '작성 중',
        'published' => '공개',
        'archived' => '보관'
    );

$page_title =
    $notice['title'] .
    ' 수정';

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
                $notice['school_name']
            );
            ?>
            ·
            <?php
            echo (int)$notice['academic_year'];
            ?>학년도
            ·
            <?php
            echo class_share_escape(
                $notice['event_title']
            );
            ?>
        </p>

        <p class="admin-muted">
            행사 운영:
            <strong>
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $notice['event_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $notice['event_end_at']
                    )
                );
                ?>
            </strong>
        </p>

        <p class="admin-muted">
            최초 게시일:
            <strong>
                <?php
                echo class_share_escape(
                    $format_datetime(
                        $notice['published_at']
                    )
                );
                ?>
            </strong>
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
        action="/class-share/admin/notice_update.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="notice_id"
            value="<?php echo (int)$notice_id; ?>">

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
                <label for="status">
                    공개 상태 *
                </label>

                <select
                    id="status"
                    name="status"
                    required>

                    <?php
                    foreach (
                        $status_names as
                        $status_value => $status_label
                    ) {
                        ?>
                        <option
                            value="<?php
                            echo class_share_escape(
                                $status_value
                            );
                            ?>"
                            <?php
                            echo (string)$form_values[
                                'status'
                            ] === $status_value
                            ? 'selected'
                            : '';
                            ?>>

                            <?php
                            echo class_share_escape(
                                $status_label
                            );
                            ?>
                        </option>
                    <?php } ?>
                </select>

                <small class="admin-muted">
                    공개로 변경하면 현재 시각이 최초 게시일로 기록됩니다.
                    보관된 공지는 공개 페이지에 표시되지 않습니다.
                </small>
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
                    제목, 강조, 목록, 표, 링크와 이미지를 사용할 수 있습니다. 이미지는 JPG·PNG·GIF·WebP 형식으로 5MB 이하만 업로드할 수 있습니다.
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
                변경 내용 저장
            </button>
        </div>
    </form>
</section>

<script
    src="/tinymce/tinymce.min.js?v=8.9.0"></script>

<script
    src="/class-share/admin/assets/notice-editor.js?v=20261008-5"></script>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
