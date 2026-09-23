<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$class_id =
    isset($_GET['class_id'])
    ? (int)$_GET['class_id']
    : 0;

if ($class_id <= 0) {
    http_response_code(400);
    exit('수업 번호가 올바르지 않습니다.');
}

$class_rows =
    pdo_query(
        "
        SELECT
            class_item.id,
            class_item.event_id,
            class_item.subject,
            class_item.title,
            class_item.teacher_name,
            class_item.target,
            class_item.class_start_at,
            class_item.class_end_at,
            class_item.place,
            class_item.application_deadline,
            class_item.capacity,
            class_item.description,
            class_item.sort_order,
            class_item.status,

            event.school_id,
            event.title AS event_title,
            event.academic_year,
            event.status AS event_status,
            event.event_start_at,
            event.event_end_at,
            event.application_start_at,
            event.application_end_at,

            school.school_name,

            (
                SELECT COUNT(*)
                FROM class_share_application AS application
                WHERE application.class_id =
                      class_item.id
                  AND application.status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )
            ) AS active_application_count

        FROM class_share_class AS class_item

        INNER JOIN class_share_event AS event
            ON event.id = class_item.event_id

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE class_item.id = ?

        LIMIT 1
        ",
        $class_id
    );

if ($class_rows === false) {
    http_response_code(500);
    exit('수업 정보를 불러올 수 없습니다.');
}

if (!isset($class_rows[0])) {
    http_response_code(404);
    exit('수업을 찾을 수 없습니다.');
}

$class_item =
    $class_rows[0];

$event_id =
    (int)$class_item['event_id'];

$school_id =
    (int)$class_item['school_id'];

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 수업을 수정할 권한이 없습니다.');
}

if (
    in_array(
        (string)$class_item['event_status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    http_response_code(409);
    exit('취소되거나 보관된 행사의 수업은 수정할 수 없습니다.');
}

if (
    in_array(
        (string)$class_item['status'],
        array(
            'cancelled',
            'archived'
        ),
        true
    )
) {
    http_response_code(409);
    exit('취소되거나 보관된 수업은 수정할 수 없습니다.');
}

$to_input_datetime =
    function ($value) {
        if (
            $value === null ||
            $value === ''
        ) {
            return '';
        }

        $timestamp =
            strtotime(
                (string)$value
            );

        if ($timestamp === false) {
            return '';
        }

        return date(
            'Y-m-d\TH:i',
            $timestamp
        );
    };

$format_display_datetime =
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
        'subject' =>
        (string)$class_item['subject'],

        'title' =>
        (string)$class_item['title'],

        'teacher_name' =>
        (string)$class_item['teacher_name'],

        'target' =>
        (string)$class_item['target'],

        'class_start_at' =>
        $to_input_datetime(
            $class_item['class_start_at']
        ),

        'class_end_at' =>
        $to_input_datetime(
            $class_item['class_end_at']
        ),

        'place' =>
        (string)$class_item['place'],

        'application_deadline' =>
        $to_input_datetime(
            $class_item['application_deadline']
        ),

        'capacity' =>
        (string)$class_item['capacity'],

        'description' =>
        (string)$class_item['description'],

        'sort_order' =>
        (string)$class_item['sort_order'],

        'status' =>
        (string)$class_item['status']
    );

$saved_class_id =
    isset(
        $_SESSION['class_share_class_edit_id']
    )
    ? (int)$_SESSION['class_share_class_edit_id']
    : 0;

$form_errors =
    $saved_class_id === $class_id &&
    isset(
        $_SESSION['class_share_class_edit_errors']
    ) &&
    is_array(
        $_SESSION['class_share_class_edit_errors']
    )
    ? $_SESSION['class_share_class_edit_errors']
    : array();

$saved_values =
    $saved_class_id === $class_id &&
    isset(
        $_SESSION['class_share_class_edit_values']
    ) &&
    is_array(
        $_SESSION['class_share_class_edit_values']
    )
    ? $_SESSION['class_share_class_edit_values']
    : array();

$form_values =
    array_merge(
        $default_values,
        $saved_values
    );

unset(
    $_SESSION['class_share_class_edit_id'],
    $_SESSION['class_share_class_edit_errors'],
    $_SESSION['class_share_class_edit_values']
);

$status_names =
    array(
        'draft' => '작성 중',
        'published' => '공개',
        'closed' => '신청 마감',
        'cancelled' => '취소',
        'archived' => '보관'
    );

$editable_status_names =
    array(
        'draft' => '작성 중',
        'published' => '공개',
        'closed' => '신청 마감'
    );

$status =
    (string)$class_item['status'];

$status_name =
    isset($status_names[$status])
    ? $status_names[$status]
    : $status;

$active_application_count =
    (int)$class_item['active_application_count'];

$minimum_capacity =
    max(
        1,
        $active_application_count
    );

$page_title =
    $class_item['title'] .
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
                $class_item['school_name']
            );
            ?>
            ·
            <?php
            echo (int)$class_item['academic_year'];
            ?>학년도
            ·
            <?php
            echo class_share_escape(
                $class_item['event_title']
            );
            ?>
        </p>

        <p class="admin-muted">
            행사 운영:
            <strong>
                <?php
                echo class_share_escape(
                    $format_display_datetime(
                        $class_item['event_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_display_datetime(
                        $class_item['event_end_at']
                    )
                );
                ?>
            </strong>
        </p>

        <p class="admin-muted">
            전체 신청기간:
            <strong>
                <?php
                echo class_share_escape(
                    $format_display_datetime(
                        $class_item['application_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_display_datetime(
                        $class_item['application_end_at']
                    )
                );
                ?>
            </strong>
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/classes.php?event_id=<?php
                                                            echo (int)$event_id;
                                                            ?>">
            ← 수업 목록
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
    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
                <tr>
                    <th>현재 상태</th>
                    <td>
                        <?php
                        echo class_share_escape(
                            $status_name
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>현재 유효 신청</th>
                    <td>
                        <?php
                        echo $active_application_count;
                        ?>명
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <form
        method="post"
        action="/class-share/admin/class_update.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="class_id"
            value="<?php echo (int)$class_id; ?>">

        <input
            type="hidden"
            name="event_id"
            value="<?php echo (int)$event_id; ?>">

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="subject">
                    교과
                </label>

                <input
                    type="text"
                    id="subject"
                    name="subject"
                    maxlength="50"
                    value="<?php
                            echo class_share_escape(
                                $form_values['subject']
                            );
                            ?>">
            </div>

            <div class="admin-field">
                <label for="teacher_name">
                    교사명 *
                </label>

                <input
                    type="text"
                    id="teacher_name"
                    name="teacher_name"
                    required
                    maxlength="100"
                    value="<?php
                            echo class_share_escape(
                                $form_values['teacher_name']
                            );
                            ?>">
            </div>

            <div class="admin-field admin-field-full">
                <label for="title">
                    수업명 *
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
                <label for="target">
                    수업 대상
                </label>

                <input
                    type="text"
                    id="target"
                    name="target"
                    maxlength="100"
                    value="<?php
                            echo class_share_escape(
                                $form_values['target']
                            );
                            ?>">
            </div>

            <div class="admin-field">
                <label for="place">
                    장소
                </label>

                <input
                    type="text"
                    id="place"
                    name="place"
                    maxlength="150"
                    value="<?php
                            echo class_share_escape(
                                $form_values['place']
                            );
                            ?>">
            </div>

            <div class="admin-field">
                <label for="class_start_at">
                    수업 시작일시 *
                </label>

                <input
                    type="datetime-local"
                    id="class_start_at"
                    name="class_start_at"
                    required
                    value="<?php
                            echo class_share_escape(
                                $form_values['class_start_at']
                            );
                            ?>">
            </div>

            <div class="admin-field">
                <label for="class_end_at">
                    수업 종료일시
                </label>

                <input
                    type="datetime-local"
                    id="class_end_at"
                    name="class_end_at"
                    value="<?php
                            echo class_share_escape(
                                $form_values['class_end_at']
                            );
                            ?>">
            </div>

            <div class="admin-field">
                <label for="application_deadline">
                    신청 마감일시 *
                </label>

                <input
                    type="datetime-local"
                    id="application_deadline"
                    name="application_deadline"
                    required
                    value="<?php
                            echo class_share_escape(
                                $form_values['application_deadline']
                            );
                            ?>">
            </div>

            <div class="admin-field">
                <label for="capacity">
                    정원 *
                </label>

                <input
                    type="number"
                    id="capacity"
                    name="capacity"
                    required
                    min="<?php
                            echo (int)$minimum_capacity;
                            ?>"
                    max="1000"
                    value="<?php
                            echo class_share_escape(
                                $form_values['capacity']
                            );
                            ?>">

                <small class="admin-muted">
                    현재 유효 신청
                    <?php
                    echo $active_application_count;
                    ?>명보다 작게 설정할 수 없습니다.
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
                <label for="status">
                    공개 상태 *
                </label>

                <select
                    id="status"
                    name="status"
                    required>

                    <?php
                    foreach (
                        $editable_status_names as
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
                            echo (string)$form_values['status'] === $status_value
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
                    공개 상태에서만 공개 행사 페이지에 표시됩니다.
                    신청 마감 상태에서는 새로운 신청을 받지 않습니다.
                </small>
            </div>

            <div class="admin-field admin-field-full">
                <label for="description">
                    수업 소개
                </label>

                <textarea
                    class="class-share-rich-editor"
                    id="description"
                    name="description"
                    rows="12"><?php
                                echo class_share_escape(
                                    $form_values['description']
                                );
                                ?></textarea>
                <small class="admin-muted">
                    제목, 강조, 목록, 표와 링크를 사용할 수 있습니다.
                    실제 글 내용은 5,000자 이하로 입력해 주세요.
                </small>
            </div>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/classes.php?event_id=<?php
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
    src="/class-share/admin/assets/class-editor.js?v=20260922"></script>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
