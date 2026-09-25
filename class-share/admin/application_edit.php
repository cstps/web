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
            application.class_id,
            application.applicant_name,
            application.applicant_school,
            application.phone_last4,
            application.status,
            application.privacy_policy_version,
            application.privacy_agreed_at,
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
    class_share_admin_can_edit_school(
        $school_id,
        $admin
    );

$can_view_sensitive =
    class_share_admin_can_view_sensitive_school(
        $school_id,
        $admin
    );

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

<section class="admin-panel">
    <dl>
        <dt>신청번호</dt>
        <dd>
            <code>
                <?php
                echo class_share_escape(
                    $application[
                        'application_code'
                    ]
                );
                ?>
            </code>
        </dd>

        <dt>성명</dt>
        <dd>
            <?php
            echo class_share_escape(
                $application[
                    'applicant_name'
                ]
            );
            ?>
        </dd>

        <dt>소속</dt>
        <dd>
            <?php
            echo class_share_escape(
                $application[
                    'applicant_school'
                ]
            );
            ?>
        </dd>

        <dt>연락처</dt>
        <dd>
            ***-****-<?php
            echo class_share_escape(
                $application[
                    'phone_last4'
                ]
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
