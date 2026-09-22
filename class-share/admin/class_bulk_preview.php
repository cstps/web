<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    __DIR__ .
    '/include/class_functions.php'
);

require_once(
    __DIR__ .
    '/include/class_bulk_functions.php'
);

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    http_response_code(405);
    exit('POST 요청만 허용됩니다.');
}

$admin =
    class_share_admin_require_login();

class_share_admin_require_post_csrf();

$admin_id =
    isset($admin['id'])
    ? (int)$admin['id']
    : 0;

$event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

if (
    $admin_id <= 0 ||
    $event_id <= 0
) {
    http_response_code(400);
    exit('관리자 또는 행사 정보가 올바르지 않습니다.');
}

$upload_url =
    '/class-share/admin/class_bulk_upload.php' .
    '?event_id=' .
    $event_id;

$list_url =
    '/class-share/admin/classes.php' .
    '?event_id=' .
    $event_id;

$redirect_upload_error =
    function ($errors) use ($upload_url) {
        $_SESSION['class_share_bulk_upload_errors'] =
            is_array($errors)
            ? $errors
            : array(
                'CSV 파일을 확인해 주세요.'
            );

        header(
            'Location: ' . $upload_url,
            true,
            303
        );

        exit;
    };

unset(
    $_SESSION['class_share_bulk_preview']
);

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
    exit('해당 행사의 수업을 일괄 등록할 권한이 없습니다.');
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
    $redirect_upload_error(
        array(
            '취소되거나 보관된 행사에는 수업을 추가할 수 없습니다.'
        )
    );
}

if (
    !isset($_FILES['class_csv']) ||
    !is_array($_FILES['class_csv'])
) {
    $redirect_upload_error(
        array(
            'CSV 파일을 선택해 주세요.'
        )
    );
}

$file =
    $_FILES['class_csv'];

$upload_error =
    isset($file['error'])
    ? (int)$file['error']
    : UPLOAD_ERR_NO_FILE;

$upload_error_messages =
    array(
        UPLOAD_ERR_INI_SIZE =>
        '서버에서 허용하는 파일 크기를 초과했습니다.',

        UPLOAD_ERR_FORM_SIZE =>
        'CSV 파일은 2MB 이하만 업로드할 수 있습니다.',

        UPLOAD_ERR_PARTIAL =>
        'CSV 파일이 일부만 업로드되었습니다.',

        UPLOAD_ERR_NO_FILE =>
        'CSV 파일을 선택해 주세요.',

        UPLOAD_ERR_NO_TMP_DIR =>
        '서버의 임시 저장 경로를 사용할 수 없습니다.',

        UPLOAD_ERR_CANT_WRITE =>
        '서버에 CSV 파일을 기록할 수 없습니다.',

        UPLOAD_ERR_EXTENSION =>
        '서버 확장 기능이 파일 업로드를 중단했습니다.'
    );

if ($upload_error !== UPLOAD_ERR_OK) {
    $message =
        isset(
            $upload_error_messages[$upload_error]
        )
        ? $upload_error_messages[$upload_error]
        : 'CSV 파일 업로드에 실패했습니다.';

    $redirect_upload_error(
        array($message)
    );
}

$original_name =
    isset($file['name'])
    ? basename(
        (string)$file['name']
    )
    : '';

$extension =
    strtolower(
        pathinfo(
            $original_name,
            PATHINFO_EXTENSION
        )
    );

if ($extension !== 'csv') {
    $redirect_upload_error(
        array(
            '확장자가 .csv인 파일만 업로드할 수 있습니다.'
        )
    );
}

$temporary_name =
    isset($file['tmp_name'])
    ? (string)$file['tmp_name']
    : '';

if (
    $temporary_name === '' ||
    !is_uploaded_file($temporary_name)
) {
    $redirect_upload_error(
        array(
            '정상적으로 업로드된 CSV 파일이 아닙니다.'
        )
    );
}

$actual_size =
    filesize($temporary_name);

if (
    $actual_size === false ||
    $actual_size <= 0
) {
    $redirect_upload_error(
        array(
            'CSV 파일이 비어 있거나 크기를 확인할 수 없습니다.'
        )
    );
}

if ($actual_size > 2097152) {
    $redirect_upload_error(
        array(
            'CSV 파일은 2MB 이하만 업로드할 수 있습니다.'
        )
    );
}

$raw_content =
    file_get_contents(
        $temporary_name
    );

if ($raw_content === false) {
    $redirect_upload_error(
        array(
            '업로드한 CSV 파일을 읽을 수 없습니다.'
        )
    );
}

$parse_result =
    class_share_bulk_parse_csv_content(
        $raw_content,
        500
    );

$existing_rows =
    pdo_query(
        "
        SELECT
            title,
            teacher_name,
            class_start_at,
            place

        FROM class_share_class

        WHERE event_id = ?
          AND status NOT IN (
              'cancelled',
              'archived'
          )
        ",
        $event_id
    );

if ($existing_rows === false) {
    http_response_code(500);
    exit('기존 수업 정보를 확인할 수 없습니다.');
}

$existing_keys =
    array();

foreach ($existing_rows as $existing_row) {
    $existing_key =
        class_share_bulk_duplicate_key(
            $existing_row
        );

    $existing_keys[$existing_key] =
        true;
}

$preview_rows =
    array();

$normalized_rows =
    array();

$file_keys =
    array();

$has_errors =
    count(
        $parse_result['errors']
    ) > 0;

$valid_count =
    0;

$invalid_count =
    0;

foreach (
    $parse_result['rows'] as
    $parsed_row
) {
    $row_number =
        (int)$parsed_row['row_number'];

    $input =
        class_share_bulk_map_row_to_input(
            $parsed_row['cells']
        );

    $validation =
        class_share_class_validate_input(
            $input,
            $event,
            'plain'
        );

    $row_errors =
        $validation['errors'];

    $data =
        $validation['data'];

    if (count($row_errors) === 0) {
        $duplicate_key =
            class_share_bulk_duplicate_key(
                $data
            );

        if (
            isset(
                $file_keys[$duplicate_key]
            )
        ) {
            $row_errors[] =
                '이 파일의 ' .
                $file_keys[$duplicate_key] .
                '행과 같은 수업입니다.';
        } else {
            $file_keys[$duplicate_key] =
                $row_number;
        }

        if (
            isset(
                $existing_keys[$duplicate_key]
            )
        ) {
            $row_errors[] =
                '이미 등록된 수업과 수업명, 교사명, 시작일시, 장소가 같습니다.';
        }
    }

    if (count($row_errors) > 0) {
        $has_errors =
            true;

        $invalid_count++;
    } else {
        $valid_count++;

        $normalized_rows[] =
            array(
                'row_number' =>
                $row_number,

                'data' =>
                $data
            );
    }

    $preview_rows[] =
        array(
            'row_number' =>
            $row_number,

            'data' =>
            $data,

            'errors' =>
            $row_errors
        );
}

$bulk_token =
    '';

if (
    !$has_errors &&
    count($normalized_rows) > 0
) {
    try {
        $bulk_token =
            bin2hex(
                random_bytes(32)
            );
    } catch (Throwable $exception) {
        error_log(
            '[class-share] CSV 미리보기 토큰 생성 실패: ' .
                $exception->getMessage()
        );

        http_response_code(500);
        exit('CSV 미리보기 정보를 만들 수 없습니다.');
    }

    $_SESSION['class_share_bulk_preview'] =
        array(
            'token_hash' =>
            hash(
                'sha256',
                $bulk_token
            ),

            'event_id' =>
            $event_id,

            'school_id' =>
            $school_id,

            'admin_id' =>
            $admin_id,

            'created_at' =>
            time(),

            'expires_at' =>
            time() + 1200,

            'rows' =>
            $normalized_rows
        );
}

$page_title =
    $event['title'] .
    ' CSV 미리보기';

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

        <a
            class="admin-back-link"
            href="<?php
                    echo class_share_escape(
                        $upload_url
                    );
                    ?>">
            ← CSV 다시 선택
        </a>
    </div>
</div>

<section class="admin-panel">
    <h2>검증 결과</h2>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <tbody>
                <tr>
                    <th>파일명</th>
                    <td>
                        <?php
                        echo class_share_escape(
                            $original_name
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>문자 인코딩</th>
                    <td>
                        <?php
                        echo class_share_escape(
                            $parse_result['encoding']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th>전체 수업</th>
                    <td>
                        <?php
                        echo count(
                            $preview_rows
                        );
                        ?>개
                    </td>
                </tr>

                <tr>
                    <th>정상</th>
                    <td>
                        <?php
                        echo (int)$valid_count;
                        ?>개
                    </td>
                </tr>

                <tr>
                    <th>오류</th>
                    <td>
                        <?php
                        echo (int)$invalid_count;
                        ?>개
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php
if (
    count(
        $parse_result['errors']
    ) > 0
) {
?>
    <div
        class="admin-error"
        role="alert">

        <strong>CSV 파일 구조를 확인해 주세요.</strong>

        <ul>
            <?php
            foreach (
                $parse_result['errors'] as
                $parse_error
            ) {
            ?>
                <li>
                    <?php
                    echo class_share_escape(
                        $parse_error
                    );
                    ?>
                </li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>

<section class="admin-panel">
    <h2>수업별 검증 내용</h2>

    <?php if (count($preview_rows) === 0) { ?>
        <div class="admin-empty">
            <strong>
                미리보기할 수업이 없습니다.
            </strong>
        </div>
    <?php } else { ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>CSV 행</th>
                        <th>교과</th>
                        <th>수업명</th>
                        <th>교사명</th>
                        <th>대상</th>
                        <th>시작일시</th>
                        <th>장소</th>
                        <th>신청 마감</th>
                        <th>정원</th>
                        <th>순서</th>
                        <th>검증</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($preview_rows as $row) { ?>
                        <tr>
                            <td>
                                <?php
                                echo (int)$row['row_number'];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['subject']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['title']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['teacher_name']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['target']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['class_start_at']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['place']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo class_share_escape(
                                    $row['data']['application_deadline']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$row['data']['capacity'];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo (int)$row['data']['sort_order'];
                                ?>
                            </td>

                            <td>
                                <?php
                                if (
                                    count(
                                        $row['errors']
                                    ) === 0
                                ) {
                                ?>
                                    <strong>정상</strong>
                                <?php } else { ?>
                                    <ul>
                                        <?php
                                        foreach (
                                            $row['errors'] as
                                            $row_error
                                        ) {
                                        ?>
                                            <li>
                                                <?php
                                                echo class_share_escape(
                                                    $row_error
                                                );
                                                ?>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<section class="admin-panel">
    <?php if ($has_errors) { ?>
        <div class="admin-error">
            오류를 모두 수정한 후 CSV 파일을 다시 업로드해 주세요.
            오류가 있는 상태에서는 어떤 수업도 등록되지 않습니다.
        </div>

        <div class="admin-form-actions">
            <a
                class="admin-primary-link"
                href="<?php
                        echo class_share_escape(
                            $upload_url
                        );
                        ?>">
                CSV 다시 선택
            </a>
        </div>
    <?php } else { ?>
        <div class="admin-flash">
            모든 수업이 정상적으로 검증되었습니다.
        </div>

        <form
            method="post"
            action="/class-share/admin/class_bulk_commit.php">

            <?php
            echo class_share_admin_csrf_input();
            ?>

            <input
                type="hidden"
                name="event_id"
                value="<?php echo (int)$event_id; ?>">

            <input
                type="hidden"
                name="bulk_token"
                value="<?php
                        echo class_share_escape(
                            $bulk_token
                        );
                        ?>">

            <div class="admin-form-actions">
                <a
                    class="admin-secondary-link"
                    href="<?php
                            echo class_share_escape(
                                $list_url
                            );
                            ?>">
                    취소
                </a>

                <button
                    class="admin-primary-button"
                    type="submit">
                    최종 일괄 등록
                </button>
            </div>

            <p class="admin-muted">
                최종 등록을 누르면 표시된 모든 수업이
                작성 중 상태로 한 번에 등록됩니다.
            </p>
        </form>
    <?php } ?>
</section>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
