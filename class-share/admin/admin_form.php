<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

$admin =
    class_share_admin_require_login();

if (
    !class_share_admin_is_super_admin(
        $admin
    )
) {
    http_response_code(403);
    exit('관리자 계정을 생성할 권한이 없습니다.');
}

$form_errors =
    isset(
        $_SESSION[
            'class_share_admin_form_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_admin_form_errors'
        ]
    )
    ? $_SESSION[
        'class_share_admin_form_errors'
    ]
    : array();

$saved_values =
    isset(
        $_SESSION[
            'class_share_admin_form_values'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_admin_form_values'
        ]
    )
    ? $_SESSION[
        'class_share_admin_form_values'
    ]
    : array();

$form_values =
    array_merge(
        array(
            'login_id' => '',
            'display_name' => '',
            'email' => ''
        ),
        $saved_values
    );

unset(
    $_SESSION[
        'class_share_admin_form_errors'
    ],
    $_SESSION[
        'class_share_admin_form_values'
    ]
);

$page_title =
    '관리자 추가';

$active_menu =
    'admins';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
<div class="admin-toolbar">
    <div>
        <p class="admin-muted">
            일반 관리자 계정을 생성합니다.
            학교와 역할은 계정 생성 후 별도로 배정합니다.
        </p>

        <a
            class="admin-back-link"
            href="/class-share/admin/admins.php">
            ← 관리자 목록
        </a>
    </div>
</div>

<?php if (count($form_errors) > 0) { ?>
    <div class="admin-flash admin-error"
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
        action="/class-share/admin/admin_save.php"
        autocomplete="off">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="login_id">
                    관리자 아이디 *
                </label>

                <input
                    type="text"
                    id="login_id"
                    name="login_id"
                    required
                    minlength="4"
                    maxlength="64"
                    pattern="[a-z0-9._-]{4,64}"
                    autocomplete="username"
                    value="<?php
                    echo class_share_escape(
                        $form_values['login_id']
                    );
                    ?>">

                <small>
                    영문 소문자, 숫자, 점, 밑줄, 하이픈을 사용합니다.
                </small>
            </div>

            <div class="admin-field">
                <label for="display_name">
                    관리자 이름 *
                </label>

                <input
                    type="text"
                    id="display_name"
                    name="display_name"
                    required
                    minlength="2"
                    maxlength="60"
                    autocomplete="name"
                    value="<?php
                    echo class_share_escape(
                        $form_values['display_name']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="email">
                    이메일
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="255"
                    autocomplete="email"
                    value="<?php
                    echo class_share_escape(
                        $form_values['email']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="password">
                    초기 비밀번호 *
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    minlength="12"
                    maxlength="128"
                    autocomplete="new-password">

                <small>
                    12~128자로 입력합니다.
                </small>
            </div>

            <div class="admin-field">
                <label for="password_confirmation">
                    초기 비밀번호 확인 *
                </label>

                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    minlength="12"
                    maxlength="128"
                    autocomplete="new-password">
            </div>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/admins.php">
                취소
            </a>

            <button
                type="submit"
                class="admin-submit-button">
                관리자 계정 생성
            </button>
        </div>
    </form>
</section>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
