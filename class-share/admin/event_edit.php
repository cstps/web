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
            event.academic_year,
            event.slug,
            event.title,
            event.subtitle,
            event.event_start_at,
            event.event_end_at,
            event.application_start_at,
            event.application_end_at,
            event.privacy_policy_version,
            event.privacy_notice,
            event.retention_until,
            event.status,

            school.school_name,
            school.status AS school_status,

            (
                SELECT COUNT(*)
                FROM class_share_class AS class_item
                WHERE class_item.event_id = event.id
            ) AS total_class_count,

            (
                SELECT COUNT(*)
                FROM class_share_class AS class_item
                WHERE class_item.event_id = event.id
                  AND class_item.status = 'published'
            ) AS published_class_count,

            (
                SELECT COUNT(*)
                FROM class_share_notice AS notice
                WHERE notice.event_id = event.id
                  AND notice.status = 'published'
            ) AS published_notice_count,

            (
                SELECT COUNT(*)
                FROM class_share_application AS application
                INNER JOIN class_share_class AS class_item
                    ON class_item.id = application.class_id
                WHERE class_item.event_id = event.id
            ) AS application_count

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
    exit('해당 행사를 수정할 권한이 없습니다.');
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

        return $timestamp === false
            ? ''
            : date(
                'Y-m-d\TH:i',
                $timestamp
            );
    };

$to_input_date =
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

        return $timestamp === false
            ? ''
            : date(
                'Y-m-d',
                $timestamp
            );
    };

$default_values =
    array(
        'academic_year' =>
            (string)$event['academic_year'],

        'slug' =>
            (string)$event['slug'],

        'title' =>
            (string)$event['title'],

        'subtitle' =>
            (string)$event['subtitle'],

        'event_start_at' =>
            $to_input_datetime(
                $event['event_start_at']
            ),

        'event_end_at' =>
            $to_input_datetime(
                $event['event_end_at']
            ),

        'application_start_at' =>
            $to_input_datetime(
                $event[
                    'application_start_at'
                ]
            ),

        'application_end_at' =>
            $to_input_datetime(
                $event[
                    'application_end_at'
                ]
            ),

        'privacy_policy_version' =>
            (string)$event[
                'privacy_policy_version'
            ],

        'privacy_notice' =>
            (string)$event['privacy_notice'],

        'retention_until' =>
            $to_input_date(
                $event['retention_until']
            ),

        'status' =>
            (string)$event['status']
    );

$saved_event_id =
    isset(
        $_SESSION[
            'class_share_event_edit_id'
        ]
    )
    ? (int)$_SESSION[
        'class_share_event_edit_id'
    ]
    : 0;

$form_errors =
    $saved_event_id === $event_id &&
    isset(
        $_SESSION[
            'class_share_event_edit_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_event_edit_errors'
        ]
    )
    ? $_SESSION[
        'class_share_event_edit_errors'
    ]
    : array();

$saved_values =
    $saved_event_id === $event_id &&
    isset(
        $_SESSION[
            'class_share_event_edit_values'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_event_edit_values'
        ]
    )
    ? $_SESSION[
        'class_share_event_edit_values'
    ]
    : array();

$form_values =
    array_merge(
        $default_values,
        $saved_values
    );

unset(
    $_SESSION[
        'class_share_event_edit_id'
    ],
    $_SESSION[
        'class_share_event_edit_errors'
    ],
    $_SESSION[
        'class_share_event_edit_values'
    ]
);

$status_names =
    array(
        'draft' => '작성 중',
        'published' => '공개',
        'closed' => '종료',
        'archived' => '보관'
    );

$school_is_active =
    (string)$event['school_status'] ===
    'active';

$published_class_count =
    (int)$event['published_class_count'];

$ready_to_publish =
    $school_is_active &&
    $published_class_count > 0 &&
    trim(
        (string)$event[
            'privacy_policy_version'
        ]
    ) !== '' &&
    trim(
        (string)$event['privacy_notice']
    ) !== '' &&
    $event['retention_until'] !== null;

$page_title =
    $event['title'] .
    ' 행사 수정';

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
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/events.php?school_id=<?php
            echo (int)$school_id;
            ?>">
            ← 행사 목록
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
    <h2>공개 준비 상태</h2>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
                <tr>
                    <th>학교 상태</th>
                    <td>
                        <?php
                        echo $school_is_active
                        ? '활성'
                        : '비활성 또는 보관';
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>전체 수업</th>
                    <td>
                        <?php
                        echo (int)$event[
                            'total_class_count'
                        ];
                        ?>개
                    </td>
                </tr>

                <tr>
                    <th>공개 수업</th>
                    <td>
                        <?php
                        echo $published_class_count;
                        ?>개
                    </td>
                </tr>

                <tr>
                    <th>공개 공지</th>
                    <td>
                        <?php
                        echo (int)$event[
                            'published_notice_count'
                        ];
                        ?>개
                    </td>
                </tr>

                <tr>
                    <th>전체 신청 기록</th>
                    <td>
                        <?php
                        echo (int)$event[
                            'application_count'
                        ];
                        ?>건
                    </td>
                </tr>

                <tr>
                    <th>현재 정보 기준</th>
                    <td>
                        <strong>
                            <?php
                            echo $ready_to_publish
                            ? '공개 가능'
                            : '공개 조건 확인 필요';
                            ?>
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if (!$school_is_active) { ?>
        <div class="admin-error">
            행사를 공개하려면 먼저 학교 상태를 활성으로 변경해야 합니다.
        </div>
    <?php } ?>

    <?php if ($published_class_count < 1) { ?>
        <div class="admin-error">
            행사를 공개하려면 공개 상태의 수업이 최소 1개 필요합니다.
        </div>
    <?php } ?>
</section>

<section class="admin-panel">
    <form
        method="post"
        action="/class-share/admin/event_update.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="event_id"
            value="<?php echo (int)$event_id; ?>">

        <input
            type="hidden"
            name="school_id"
            value="<?php echo (int)$school_id; ?>">

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="academic_year">
                    학년도 *
                </label>

                <input
                    type="number"
                    id="academic_year"
                    name="academic_year"
                    required
                    min="2020"
                    max="2100"
                    value="<?php
                    echo class_share_escape(
                        $form_values['academic_year']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="slug">
                    행사 주소 식별자 *
                </label>

                <input
                    type="text"
                    id="slug"
                    name="slug"
                    required
                    minlength="2"
                    maxlength="100"
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                    value="<?php
                    echo class_share_escape(
                        $form_values['slug']
                    );
                    ?>">

                <small class="admin-muted">
                    영문 소문자, 숫자와 가운데 하이픈만 사용합니다.
                </small>
            </div>

            <div class="admin-field admin-field-full">
                <label for="title">
                    행사명 *
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

            <div class="admin-field admin-field-full">
                <label for="subtitle">
                    부제목
                </label>

                <input
                    type="text"
                    id="subtitle"
                    name="subtitle"
                    maxlength="300"
                    value="<?php
                    echo class_share_escape(
                        $form_values['subtitle']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="event_start_at">
                    행사 시작일시 *
                </label>

                <input
                    type="datetime-local"
                    id="event_start_at"
                    name="event_start_at"
                    required
                    value="<?php
                    echo class_share_escape(
                        $form_values['event_start_at']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="event_end_at">
                    행사 종료일시 *
                </label>

                <input
                    type="datetime-local"
                    id="event_end_at"
                    name="event_end_at"
                    required
                    value="<?php
                    echo class_share_escape(
                        $form_values['event_end_at']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="application_start_at">
                    전체 신청 시작일시 *
                </label>

                <input
                    type="datetime-local"
                    id="application_start_at"
                    name="application_start_at"
                    required
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'application_start_at'
                        ]
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="application_end_at">
                    전체 신청 종료일시 *
                </label>

                <input
                    type="datetime-local"
                    id="application_end_at"
                    name="application_end_at"
                    required
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'application_end_at'
                        ]
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="privacy_policy_version">
                    개인정보 처리 안내 버전 *
                </label>

                <input
                    type="text"
                    id="privacy_policy_version"
                    name="privacy_policy_version"
                    required
                    maxlength="50"
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'privacy_policy_version'
                        ]
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="retention_until">
                    개인정보 보관 기한 *
                </label>

                <input
                    type="date"
                    id="retention_until"
                    name="retention_until"
                    required
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'retention_until'
                        ]
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="status">
                    행사 상태 *
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
                    공개 상태에서만 학교별 공개 주소로 행사에 접근할 수 있습니다.
                </small>
            </div>

            <div class="admin-field admin-field-full">
                <label for="privacy_notice">
                    개인정보 수집·이용 안내 *
                </label>

                <textarea
                    id="privacy_notice"
                    name="privacy_notice"
                    rows="8"
                    required
                    maxlength="10000"><?php
                    echo class_share_escape(
                        $form_values[
                            'privacy_notice'
                        ]
                    );
                    ?></textarea>
            </div>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/events.php?school_id=<?php
                echo (int)$school_id;
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
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
