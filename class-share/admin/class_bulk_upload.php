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
    http_response_code(409);
    exit('취소되거나 보관된 행사에는 수업을 추가할 수 없습니다.');
}

$upload_errors =
    isset(
        $_SESSION[
            'class_share_bulk_upload_errors'
        ]
    ) &&
    is_array(
        $_SESSION[
            'class_share_bulk_upload_errors'
        ]
    )
    ? $_SESSION[
        'class_share_bulk_upload_errors'
    ]
    : array();

unset(
    $_SESSION[
        'class_share_bulk_upload_errors'
    ]
);

$page_title =
    $event['title'] .
    ' CSV 일괄 등록';

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
            href="/class-share/admin/classes.php?event_id=<?php
            echo (int)$event_id;
            ?>">
            ← 수업 목록
        </a>
    </div>

    <a
        class="admin-secondary-link"
        href="/class-share/admin/class_bulk_template.php?event_id=<?php
        echo (int)$event_id;
        ?>">
        CSV 양식 다운로드
    </a>
</div>

<?php if (count($upload_errors) > 0) { ?>
    <div
        class="admin-error"
        role="alert">

        <strong>CSV 파일을 확인해 주세요.</strong>

        <ul>
            <?php foreach ($upload_errors as $error) { ?>
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
    <h2>CSV 작성 방법</h2>

    <ol>
        <li>
            CSV 양식을 내려받아 Excel에서 엽니다.
        </li>

        <li>
            제목 행은 변경하거나 삭제하지 않습니다.
        </li>

        <li>
            두 번째 행부터 수업 정보를 입력합니다.
        </li>

        <li>
            Excel에서 CSV 형식으로 저장한 뒤 업로드합니다.
        </li>

        <li>
            업로드 후 검증 결과를 확인하고 최종 등록합니다.
        </li>
    </ol>

    <p class="admin-muted">
        UTF-8 CSV와 Windows Excel의 CP949 CSV를 모두 지원할 예정입니다.
        최대 500개 수업, 파일 크기는 2MB까지 허용합니다.
    </p>
</section>

<section class="admin-panel">
    <h2>입력 열</h2>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>열</th>
                    <th>필수</th>
                    <th>입력 방법</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>교과</td>
                    <td>선택</td>
                    <td>50자 이하</td>
                </tr>

                <tr>
                    <td>수업명</td>
                    <td>필수</td>
                    <td>200자 이하</td>
                </tr>

                <tr>
                    <td>교사명</td>
                    <td>필수</td>
                    <td>100자 이하</td>
                </tr>

                <tr>
                    <td>수업대상</td>
                    <td>선택</td>
                    <td>100자 이하</td>
                </tr>

                <tr>
                    <td>수업시작일시</td>
                    <td>필수</td>
                    <td>
                        <code>2026-10-16 14:00</code>
                    </td>
                </tr>

                <tr>
                    <td>수업종료일시</td>
                    <td>선택</td>
                    <td>
                        <code>2026-10-16 14:50</code>
                    </td>
                </tr>

                <tr>
                    <td>장소</td>
                    <td>선택</td>
                    <td>150자 이하</td>
                </tr>

                <tr>
                    <td>신청마감일시</td>
                    <td>필수</td>
                    <td>
                        <code>2026-10-14 17:00</code>
                    </td>
                </tr>

                <tr>
                    <td>정원</td>
                    <td>필수</td>
                    <td>1명 이상 1,000명 이하</td>
                </tr>

                <tr>
                    <td>수업소개</td>
                    <td>선택</td>
                    <td>5,000자 이하</td>
                </tr>

                <tr>
                    <td>정렬순서</td>
                    <td>필수</td>
                    <td>0 이상 999,999 이하</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <h2>CSV 파일 업로드</h2>

    <form
        method="post"
        enctype="multipart/form-data"
        action="/class-share/admin/class_bulk_preview.php">

        <?php
        echo class_share_admin_csrf_input();
        ?>

        <input
            type="hidden"
            name="event_id"
            value="<?php echo (int)$event_id; ?>">

        <input
            type="hidden"
            name="MAX_FILE_SIZE"
            value="2097152">

        <div class="admin-field">
            <label for="class_csv">
                CSV 파일 *
            </label>

            <input
                type="file"
                id="class_csv"
                name="class_csv"
                required
                accept=".csv,text/csv,application/vnd.ms-excel">

            <small class="admin-muted">
                CSV 파일만 가능하며 최대 크기는 2MB입니다.
            </small>
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
                검증 및 미리보기
            </button>
        </div>
    </form>
</section>
<?php

require_once(
    __DIR__ .
    '/include/admin_layout_end.php'
);
