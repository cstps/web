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
            event.application_start_at,
            event.application_end_at,
            school.school_name,
            school.slug AS school_slug

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
    exit('해당 행사의 수업을 등록할 권한이 없습니다.');
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
    exit('취소되거나 보관된 행사에는 수업을 추가할 수 없습니다.');
}

$sort_rows =
    pdo_query(
        "
        SELECT
            COALESCE(
                MAX(sort_order),
                0
            ) + 10 AS next_sort_order

        FROM class_share_class

        WHERE event_id = ?
        ",
        $event_id
    );

if (
    $sort_rows === false ||
    !isset($sort_rows[0])
) {
    http_response_code(500);
    exit('수업 정렬 순서를 확인할 수 없습니다.');
}

$next_sort_order =
    (int)$sort_rows[0]['next_sort_order'];

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
        'subject' => '',
        'title' => '',
        'teacher_name' => '',
        'target' => '',

        'class_start_at' =>
        $to_input_datetime(
            $event['event_start_at']
        ),

        'class_end_at' => '',
        'place' => '',

        'application_deadline' =>
        $to_input_datetime(
            $event['application_end_at']
        ),

        'capacity' => '20',
        'description' => '',

        'sort_order' =>
        (string)$next_sort_order
    );

$form_errors =
    isset(
        $_SESSION['class_share_class_form_errors']
    ) &&
    is_array(
        $_SESSION['class_share_class_form_errors']
    )
    ? $_SESSION['class_share_class_form_errors']
    : array();

$saved_values =
    isset(
        $_SESSION['class_share_class_form_values']
    ) &&
    is_array(
        $_SESSION['class_share_class_form_values']
    )
    ? $_SESSION['class_share_class_form_values']
    : array();

$form_values =
    array_merge(
        $default_values,
        $saved_values
    );

unset(
    $_SESSION['class_share_class_form_errors'],
    $_SESSION['class_share_class_form_values']
);

$page_title =
    $event['title'] .
    ' 수업 추가';

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
                    $format_display_datetime(
                        $event['event_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_display_datetime(
                        $event['event_end_at']
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
                        $event['application_start_at']
                    )
                );
                ?>
                ~
                <?php
                echo class_share_escape(
                    $format_display_datetime(
                        $event['application_end_at']
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
    <form
        method="post"
        action="/class-share/admin/class_save.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

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
                            ?>"
                    placeholder="예: 수학">
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
                            ?>"
                    placeholder="예: 중학교 3학년">
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
                            ?>"
                    placeholder="예: 수학실">
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
                    min="1"
                    max="1000"
                    value="<?php
                            echo class_share_escape(
                                $form_values['capacity']
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
                    숫자가 작은 수업부터 먼저 표시됩니다.
                </small>
            </div>

            <div class="admin-field admin-field-full">
                <label for="description">
                    수업 소개
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="8"
                    maxlength="5000"
                    placeholder="수업 내용과 참관 시 참고할 사항을 입력하세요."><?php
                                                                echo class_share_escape(
                                                                    $form_values['description']
                                                                );
                                                                ?></textarea>
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
                수업 등록
            </button>
        </div>
    </form>
</section>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
