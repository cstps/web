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
    exit('학교 관리 권한이 없습니다.');
}

$form_errors =
    isset(
        $_SESSION[
            'class_share_school_form_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_school_form_errors'
        ]
    )
    ? $_SESSION[
        'class_share_school_form_errors'
    ]
    : array();

$form_values =
    isset(
        $_SESSION[
            'class_share_school_form_values'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_school_form_values'
        ]
    )
    ? $_SESSION[
        'class_share_school_form_values'
    ]
    : array(
        'school_code' => '',
        'school_name' => '',
        'slug' => '',
        'page_title' => '수업나눔한마당',
        'introduction' => '',
        'status' => 'active'
    );

unset(
    $_SESSION[
        'class_share_school_form_errors'
    ],
    $_SESSION[
        'class_share_school_form_values'
    ]
);

$page_title =
    '학교 추가';

$active_menu =
    'schools';

require_once(
    __DIR__ .
    '/include/admin_layout_start.php'
);

?>
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
        action="/class-share/admin/school_save.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <div class="admin-form-grid">
            <div class="admin-field">
                <label for="school_name">
                    학교명 *
                </label>

                <input
                    type="text"
                    id="school_name"
                    name="school_name"
                    required
                    maxlength="100"
                    value="<?php
                    echo class_share_escape(
                        $form_values['school_name']
                    );
                    ?>">
            </div>

            <div class="admin-field">
                <label for="school_code">
                    학교 코드
                </label>

                <input
                    type="text"
                    id="school_code"
                    name="school_code"
                    maxlength="20"
                    value="<?php
                    echo class_share_escape(
                        $form_values['school_code']
                    );
                    ?>">

                <small>
                    교육기관 고유 코드가 있는 경우 입력합니다.
                </small>
            </div>

            <div class="admin-field">
                <label for="slug">
                    공개 주소 식별자 *
                </label>

                <input
                    type="text"
                    id="slug"
                    name="slug"
                    required
                    minlength="3"
                    maxlength="80"
                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                    placeholder="example-high"
                    value="<?php
                    echo class_share_escape(
                        $form_values['slug']
                    );
                    ?>">

                <small>
                    공개 주소:
                    /class-share/example-high
                </small>
            </div>

            <div class="admin-field">
                <label for="status">
                    운영 상태 *
                </label>

                <select
                    id="status"
                    name="status"
                    required>

                    <option
                        value="active"
                        <?php
                        echo
                            $form_values['status'] === 'active'
                            ? 'selected'
                            : '';
                        ?>>
                        운영 중
                    </option>

                    <option
                        value="inactive"
                        <?php
                        echo
                            $form_values['status'] === 'inactive'
                            ? 'selected'
                            : '';
                        ?>>
                        비활성
                    </option>
                </select>
            </div>
        </div>

        <div class="admin-field">
            <label for="page_title">
                공개 페이지 제목 *
            </label>

            <input
                type="text"
                id="page_title"
                name="page_title"
                required
                maxlength="150"
                value="<?php
                echo class_share_escape(
                    $form_values['page_title']
                );
                ?>">
        </div>

        <div class="admin-field">
            <label for="introduction">
                학교별 안내문
            </label>

            <textarea
                id="introduction"
                name="introduction"
                rows="6"
                maxlength="5000"><?php
                echo class_share_escape(
                    $form_values['introduction']
                );
                ?></textarea>
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-secondary-link"
                href="/class-share/admin/schools.php">
                취소
            </a>

            <button
                type="submit"
                class="admin-submit-button">
                학교 저장
            </button>
        </div>
    </form>
</section>

<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
