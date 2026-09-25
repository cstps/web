<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

$school_id =
    isset($_GET['school_id'])
    ? (int)$_GET['school_id']
    : 0;

if ($school_id <= 0) {
    http_response_code(400);
    exit('학교 번호가 올바르지 않습니다.');
}

if (
    !class_share_admin_can_edit_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('해당 학교의 행사를 등록할 권한이 없습니다.');
}

$school_rows =
    pdo_query(
        "
        SELECT
            id,
            school_name,
            slug,
            status
        FROM class_share_school
        WHERE id = ?
        LIMIT 1
        ",
        $school_id
    );

if ($school_rows === false) {
    http_response_code(500);
    exit('학교 정보를 불러올 수 없습니다.');
}

if (!isset($school_rows[0])) {
    http_response_code(404);
    exit('학교를 찾을 수 없습니다.');
}

$school =
    $school_rows[0];

$form_errors =
    isset(
        $_SESSION[
            'class_share_event_form_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_event_form_errors'
        ]
    )
    ? $_SESSION[
        'class_share_event_form_errors'
    ]
    : array();

$has_saved_values =
    isset(
        $_SESSION[
            'class_share_event_form_values'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_event_form_values'
        ]
    );

if ($has_saved_values) {
    $form_values =
        $_SESSION[
            'class_share_event_form_values'
        ];
} else {
    $form_values =
        array(
            'academic_year' =>
                (string)date('Y'),

            'event_type' =>
                'class_share',

            'application_mode' =>
                'program',

            'application_capacity' =>
                '',

            'slug' => '',
            'title' =>
                date('Y') .
                '학년도 학교 행사',

            'subtitle' => '',
            'event_start_at' => '',
            'event_end_at' => '',
            'application_start_at' => '',
            'application_end_at' => '',

            'privacy_policy_version' =>
                date('Y') . '-1',

            'privacy_notice' =>
                "수집 항목: 성명, 소속학교, 연락처\n" .
                "수집 목적: 행사 신청 확인과 안내\n" .
                "보유 기간: 아래 개인정보 보관 기한까지\n" .
                "동의를 거부할 수 있으나 행사 신청이 제한될 수 있습니다.",

            'retention_until' => ''
        );
}

if (!isset($form_values['event_type'])) {
    $form_values['event_type'] =
        'class_share';
}

if (!isset($form_values['application_mode'])) {
    $form_values['application_mode'] =
        'program';
}

if (!isset($form_values['application_capacity'])) {
    $form_values['application_capacity'] =
        '';
}

unset(
    $_SESSION[
        'class_share_event_form_errors'
    ],
    $_SESSION[
        'class_share_event_form_values'
    ]
);

$page_title =
    $school['school_name'] .
    ' 행사 추가';

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
            행사는 작성 중 상태로 생성됩니다.
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
    <form
        method="post"
        action="/class-share/admin/event_save.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

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
                    minlength="3"
                    maxlength="80"
                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                    placeholder="2026-class-open"
                    value="<?php
                    echo class_share_escape(
                        $form_values['slug']
                    );
                    ?>">

                <small>
                    학교 주소 뒤에 추가되는 영문 주소입니다.
                </small>
            </div>
        </div>

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="event_type">
                    행사 유형 *
                </label>

                <select
                    id="event_type"
                    name="event_type"
                    required>

                    <option
                        value="class_share"<?php
                        echo
                        $form_values['event_type'] ===
                        'class_share'
                        ? ' selected'
                        : '';
                        ?>>
                        수업나눔
                    </option>

                    <option
                        value="school_event"<?php
                        echo
                        $form_values['event_type'] ===
                        'school_event'
                        ? ' selected'
                        : '';
                        ?>>
                        학교행사
                    </option>

                    <option
                        value="briefing"<?php
                        echo
                        $form_values['event_type'] ===
                        'briefing'
                        ? ' selected'
                        : '';
                        ?>>
                        설명회
                    </option>

                    <option
                        value="experience"<?php
                        echo
                        $form_values['event_type'] ===
                        'experience'
                        ? ' selected'
                        : '';
                        ?>>
                        체험행사
                    </option>

                    <option
                        value="training"<?php
                        echo
                        $form_values['event_type'] ===
                        'training'
                        ? ' selected'
                        : '';
                        ?>>
                        연수
                    </option>

                    <option
                        value="other"<?php
                        echo
                        $form_values['event_type'] ===
                        'other'
                        ? ' selected'
                        : '';
                        ?>>
                        기타
                    </option>
                </select>
            </div>

            <div class="admin-field">
                <label for="application_mode">
                    신청 방식 *
                </label>

                <select
                    id="application_mode"
                    name="application_mode"
                    required>

                    <option
                        value="none"<?php
                        echo
                        $form_values['application_mode'] ===
                        'none'
                        ? ' selected'
                        : '';
                        ?>>
                        안내만 제공
                    </option>

                    <option
                        value="event"<?php
                        echo
                        $form_values['application_mode'] ===
                        'event'
                        ? ' selected'
                        : '';
                        ?>>
                        행사에 직접 신청
                    </option>

                    <option
                        value="program"<?php
                        echo
                        $form_values['application_mode'] ===
                        'program'
                        ? ' selected'
                        : '';
                        ?>>
                        세부 프로그램 선택
                    </option>
                </select>

                <small>
                    세부 프로그램은 수업, 강좌, 체험 부스 등을 의미합니다.
                </small>
            </div>

            <div class="admin-field">
                <label for="application_capacity">
                    행사 직접 신청 정원
                </label>

                <input
                    type="number"
                    id="application_capacity"
                    name="application_capacity"
                    min="1"
                    max="1000000"
                    placeholder="비워 두면 제한 없음"
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'application_capacity'
                        ]
                    );
                    ?>">

                <small>
                    행사에 직접 신청 방식을 선택한 경우에만 사용합니다.
                </small>
            </div>
        </div>

        <div class="admin-field">
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

        <div class="admin-field">
            <label for="subtitle">
                행사 부제
            </label>

            <input
                type="text"
                id="subtitle"
                name="subtitle"
                maxlength="255"
                value="<?php
                echo class_share_escape(
                    $form_values['subtitle']
                );
                ?>">
        </div>

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="event_start_at">
                    행사 시작일시
                </label>

                <input
                    type="datetime-local"
                    id="event_start_at"
                    name="event_start_at"
                    value="<?php
                    echo class_share_escape(
                        $form_values['event_start_at']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="event_end_at">
                    행사 종료일시
                </label>

                <input
                    type="datetime-local"
                    id="event_end_at"
                    name="event_end_at"
                    value="<?php
                    echo class_share_escape(
                        $form_values['event_end_at']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="application_start_at">
                    전체 신청 시작일시
                </label>

                <input
                    type="datetime-local"
                    id="application_start_at"
                    name="application_start_at"
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
                    전체 신청 종료일시
                </label>

                <input
                    type="datetime-local"
                    id="application_end_at"
                    name="application_end_at"
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'application_end_at'
                        ]
                    );
                    ?>">
            </div>
        </div>

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="privacy_policy_version">
                    개인정보 처리 문구 버전 *
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
                    개인정보 보관 기한
                </label>

                <input
                    type="date"
                    id="retention_until"
                    name="retention_until"
                    value="<?php
                    echo class_share_escape(
                        $form_values[
                            'retention_until'
                        ]
                    );
                    ?>">
            </div>
        </div>

        <div class="admin-field">
            <label for="privacy_notice">
                개인정보 수집·이용 안내 *
            </label>

            <textarea
                id="privacy_notice"
                name="privacy_notice"
                required
                rows="8"
                maxlength="5000"><?php
                echo class_share_escape(
                    $form_values[
                        'privacy_notice'
                    ]
                );
                ?></textarea>
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
                type="submit"
                class="admin-submit-button">
                작성 중으로 저장
            </button>
        </div>
    </form>
</section>

<script
    src="/class-share/admin/assets/event-form.js?v=20260923"
    defer></script>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
