<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$application_id =
    isset($_GET['application_id'])
    ? (int)$_GET['application_id']
    : 0;

if ($application_id <= 0) {
    http_response_code(400);
    exit('신청 번호가 올바르지 않습니다.');
}

$application_rows =
    pdo_query(
        "
        SELECT
            application.id,
            application.event_id,
            application.application_code,
            application.application_scope,
            application.participation_option_id,
            participation.name AS participation_name,
            application.class_id,
            application.applicant_name,
            application.applicant_school,
            application.phone_last4,
            application.status,
            application.privacy_policy_version,
            application.privacy_agreed_at,
            application.privacy_destroyed_at,
            application.cancelled_at,
            application.processed_by,
            application.admin_note,
            application.created_at,
            application.updated_at,

            event.school_id,
            event.title AS event_title,
            event.academic_year,
            event.status AS event_status,

            school.school_name,

            class_item.title AS program_title

        FROM class_share_application AS application

        INNER JOIN class_share_event AS event
            ON event.id =
               application.event_id

        INNER JOIN class_share_school AS school
            ON school.id =
               event.school_id

        LEFT JOIN class_share_class AS class_item
            ON class_item.id =
               application.class_id

        LEFT JOIN class_share_participation_option AS participation
            ON participation.id = application.participation_option_id
           AND participation.event_id = application.event_id

        WHERE application.id = ?

        LIMIT 1
        ",
        $application_id
    );

if ($application_rows === false) {
    http_response_code(500);
    exit('신청 정보를 불러올 수 없습니다.');
}

if (!isset($application_rows[0])) {
    http_response_code(404);
    exit('신청 정보를 찾을 수 없습니다.');
}

$application =
    $application_rows[0];

$privacy_destroyed =
    $application[
        'privacy_destroyed_at'
    ] !== null;

$application_code_display =
    $privacy_destroyed
    ? '—'
    : (string)$application[
        'application_code'
    ];

$applicant_name_display =
    $privacy_destroyed
    ? '개인정보 파기 완료'
    : (string)$application[
        'applicant_name'
    ];

$applicant_school_display =
    $privacy_destroyed
    ? '—'
    : (string)$application[
        'applicant_school'
    ];

$phone_display =
    $privacy_destroyed
    ? '—'
    : '***-****-' .
        (string)$application[
            'phone_last4'
        ];

$event_id =
    (int)$application['event_id'];

$school_id =
    (int)$application['school_id'];

if (
    !class_share_admin_can_view_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 신청 정보를 조회할 권한이 없습니다.');
}

$can_edit =
    !$privacy_destroyed &&
    class_share_admin_can_edit_school(
        $school_id,
        $admin
    );

$participation_options = array();
if ($can_edit && (string)$application['application_scope'] === 'event') {
    require_once(dirname(__DIR__) . '/include/participation_functions.php');
    try {
        $participation_options = class_share_participation_list_options($event_id, false);
    } catch (Throwable $exception) {
        http_response_code(500);
        exit('참여 구분 목록을 불러올 수 없습니다.');
    }
}

$can_view_sensitive =
    !$privacy_destroyed &&
    class_share_admin_can_view_sensitive_school(
        $school_id,
        $admin
    );

require_once(dirname(__DIR__) . '/include/application_form_functions.php');
$form_answers = array();
if ($can_view_sensitive) {
    try {
        $loaded_answers = class_share_form_load_response($event_id, $application_id);
        $form_answers = $loaded_answers === null ? array() : $loaded_answers;
    } catch (Throwable $exception) {
        error_log('[class-share] 추가 답변 조회 실패: ' . $exception->getMessage());
        http_response_code(500);
        exit('추가 답변을 불러올 수 없습니다.');
    }
}
if (!$privacy_destroyed && $applicant_name_display === '') {
    $applicant_name_display = '미입력';
}
if (!$privacy_destroyed && $applicant_school_display === '') {
    $applicant_school_display = '미입력';
}

$status_names =
    array(
        'applied' => '신청 완료',
        'approved' => '승인',
        'waiting' => '대기',
        'rejected' => '거절',
        'attended' => '참석',
        'absent' => '미참석',
        'cancelled' => '취소'
    );

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

$flash_message =
    isset(
        $_SESSION[
            'class_share_admin_flash'
        ]
    )
    ? (string)$_SESSION[
        'class_share_admin_flash'
    ]
    : '';

unset(
    $_SESSION[
        'class_share_admin_flash'
    ]
);

$page_title =
    '신청 상세 관리';

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
                $application['school_name']
            );
            ?>
            ·
            <?php
            echo (int)$application[
                'academic_year'
            ];
            ?>학년도
        </p>

        <h2>
            <?php
            echo class_share_escape(
                $application[
                    'application_scope'
                ] === 'program' &&
                $application[
                    'program_title'
                ] !== null
                ? $application[
                    'program_title'
                ]
                : $application[
                    'event_title'
                ]
            );
            ?>
        </h2>

        <a
            class="admin-back-link"
            href="/class-share/admin/applications.php?event_id=<?php
            echo (int)$event_id;
            ?>">
            ← 신청자 목록
        </a>
    </div>
</div>

<?php if ($flash_message !== '') { ?>
    <div
        class="admin-flash"
        role="status">

        <?php
        echo class_share_escape(
            $flash_message
        );
        ?>
    </div>
<?php } ?>

<?php if ($privacy_destroyed) { ?>
    <div
        class="admin-flash"
        role="status">

        개인정보 보관 기한이 종료되어
        신청자의 개인정보가 파기되었습니다.
        파기 일시:
        <?php
        echo class_share_escape(
            $format_datetime(
                $application[
                    'privacy_destroyed_at'
                ]
            )
        );
        ?>
    </div>
<?php } ?>

<section class="admin-panel">
    <dl>
        <dt>신청번호</dt>
        <dd>
            <code>
                <?php
                echo class_share_escape(
                    $application_code_display
                );
                ?>
            </code>
        </dd>

        <?php if ((string)$application['application_scope'] === 'event') { ?>
        <dt>참여 구분</dt>
        <dd><?php echo class_share_escape($application['participation_name'] === null
            ? '미구분' : $application['participation_name']); ?></dd>
        <?php } ?>
        <dt>성명</dt>
        <dd>
            <?php
            echo class_share_escape(
                $applicant_name_display
            );
            ?>
        </dd>

        <dt>소속</dt>
        <dd>
            <?php
            echo class_share_escape(
                $applicant_school_display
            );
            ?>
        </dd>

        <dt>연락처</dt>
        <dd>
            <?php
            echo class_share_escape(
                $phone_display
            );
            ?>

            <?php if ($can_view_sensitive) { ?>
                <form
                    class="admin-inline-form"
                    method="post"
                    action="/class-share/admin/application_phone_reveal.php">

                    <?php
                    echo class_share_admin_csrf_input();
                    ?>

                    <input
                        type="hidden"
                        name="application_id"
                        value="<?php
                        echo (int)$application_id;
                        ?>">

                    <button
                        type="submit"
                        class="admin-table-action">
                        전체 연락처 확인
                    </button>
                </form>
            <?php } ?>
        </dd>

        <dt>신청일시</dt>
        <dd>
            <?php
            echo class_share_escape(
                $format_datetime(
                    $application[
                        'created_at'
                    ]
                )
            );
            ?>
        </dd>

        <dt>취소일시</dt>
        <dd>
            <?php
            echo class_share_escape(
                $format_datetime(
                    $application[
                        'cancelled_at'
                    ]
                )
            );
            ?>
        </dd>

        <dt>개인정보 동의</dt>
        <dd>
            <?php
            echo class_share_escape(
                $application[
                    'privacy_policy_version'
                ]
            );
            ?>
            ·
            <?php
            echo class_share_escape(
                $format_datetime(
                    $application[
                        'privacy_agreed_at'
                    ]
                )
            );
            ?>
        </dd>
    </dl>
</section>

<section class="admin-panel">
    <h2>추가 질문 답변</h2>
    <?php if ($privacy_destroyed) { ?>
    <p class="admin-muted">개인정보 보관 기한이 종료되어 추가 답변이 파기되었습니다.</p>
    <?php } elseif (!$can_view_sensitive) { ?>
    <p class="admin-muted">추가 답변을 조회할 권한이 없습니다.</p>
    <?php } elseif (count($form_answers) === 0) { ?>
    <p class="admin-muted">저장된 추가 답변이 없습니다.</p>
    <?php } else { ?>
    <dl>
    <?php foreach ($form_answers as $answer) { ?>
        <dt><?php echo class_share_escape($answer['label']); ?></dt>
        <dd><?php $answer_text = class_share_form_answer_text($answer);
            echo $answer_text === '' ? '미입력' : nl2br(class_share_escape($answer_text)); ?></dd>
    <?php } ?>
    </dl>
    <?php } ?>
</section>

<?php if ($can_edit) { ?>
    <section class="admin-panel">
        <h2>처리 상태 변경</h2>

        <form
            method="post"
            action="/class-share/admin/application_update.php">

            <?php
            echo class_share_admin_csrf_input();
            ?>

            <input
                type="hidden"
                name="application_id"
                value="<?php
                echo (int)$application_id;
                ?>">

            <input
                type="hidden"
                name="event_id"
                value="<?php
                echo (int)$event_id;
                ?>">

            <div class="admin-field">
                <label for="status">
                    신청 상태
                </label>

                <select
                    id="status"
                    name="status"
                    required>

                    <?php foreach ($status_names as $value => $label) { ?>
                        <option
                            value="<?php
                            echo class_share_escape(
                                $value
                            );
                            ?>"<?php
                            echo $application[
                                'status'
                            ] === $value
                                ? ' selected'
                                : '';
                            ?>>
                            <?php
                            echo class_share_escape(
                                $label
                            );
                            ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <?php if ((string)$application['application_scope'] === 'event') { ?>
            <div class="admin-field">
                <label for="participation_option_id">참여 구분</label>
                <select id="participation_option_id" name="participation_option_id">
                    <option value=""<?php echo $application['participation_option_id'] === null ? ' selected' : ''; ?>>미구분</option>
                    <?php foreach ($participation_options as $option) { ?>
                    <option value="<?php echo (int)$option['id']; ?>"<?php
                        echo (string)$application['participation_option_id'] === (string)$option['id'] ? ' selected' : '';
                    ?>><?php echo class_share_escape($option['name'] . ((int)$option['is_active'] === 1 ? '' : ' · 모집 중지')); ?></option>
                    <?php } ?>
                </select>
                <small class="admin-muted">활성 신청을 다른 구분으로 옮기거나 재활성화할 때는 해당 구분의 정원을 확인합니다.</small>
            </div>
            <?php } ?>

            <div class="admin-field">
                <label for="admin_note">
                    관리자 메모
                </label>

                <textarea
                    id="admin_note"
                    name="admin_note"
                    rows="6"
                    maxlength="5000"><?php
                    echo class_share_escape(
                        $application[
                            'admin_note'
                        ] === null
                        ? ''
                        : $application[
                            'admin_note'
                        ]
                    );
                    ?></textarea>

                <small>
                    신청자에게 공개되지 않는 내부 업무용 메모입니다.
                </small>
            </div>

            <div class="admin-form-actions">
                <a
                    class="admin-secondary-link"
                    href="/class-share/admin/applications.php?event_id=<?php
                    echo (int)$event_id;
                    ?>">
                    취소
                </a>

                <button
                    class="admin-submit-button"
                    type="submit">
                    변경 내용 저장
                </button>
            </div>
        </form>
    </section>
<?php } ?>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
