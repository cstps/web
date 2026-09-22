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
    exit('해당 행사의 수업 양식을 내려받을 권한이 없습니다.');
}

if (!function_exists('iconv')) {
    http_response_code(500);
    exit('Excel용 CSV 인코딩 기능을 사용할 수 없습니다.');
}

$safe_school_slug =
    preg_replace(
        '/[^a-z0-9-]/',
        '',
        strtolower(
            (string)$event['school_slug']
        )
    );

if ($safe_school_slug === '') {
    $safe_school_slug =
        'school';
}

$filename =
    'class-share-' .
    $safe_school_slug .
    '-event-' .
    $event_id .
    '-excel.csv';

$utf8_headers =
    array(
        '교과',
        '수업명',
        '교사명',
        '수업대상',
        '수업시작일시',
        '수업종료일시',
        '장소',
        '신청마감일시',
        '정원',
        '수업소개',
        '정렬순서'
    );

$cp949_headers =
    array();

foreach ($utf8_headers as $header_text) {
    $converted =
        iconv(
            'UTF-8',
            'CP949',
            $header_text
        );

    if ($converted === false) {
        http_response_code(500);
        exit('CSV 한글 제목을 변환할 수 없습니다.');
    }

    $cp949_headers[] =
        $converted;
}

header(
    'Content-Type: text/csv; charset=CP949'
);

header(
    'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Pragma: no-cache'
);

$output =
    fopen(
        'php://output',
        'wb'
    );

if ($output === false) {
    http_response_code(500);
    exit('CSV 파일을 만들 수 없습니다.');
}

/*
 * CP949 파일에는 UTF-8 BOM을 넣지 않습니다.
 */
$write_result =
    fputcsv(
        $output,
        $cp949_headers,
        ',',
        '"',
        ''
    );

if ($write_result === false) {
    fclose($output);
    exit;
}

fclose($output);
exit;
