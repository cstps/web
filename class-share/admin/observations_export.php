<?php

require_once(
    __DIR__ . '/include/admin_init.php'
);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');
    http_response_code(405);
    exit('POST 요청만 허용됩니다.');
}

$admin = class_share_admin_require_login();
class_share_admin_require_post_csrf();

$event_id = isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

$filter = isset($_POST['class_id'])
    ? trim((string)$_POST['class_id'])
    : '';

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

$event_rows = pdo_query(
    "
    SELECT event.id, event.school_id, event.title,
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

$event = $event_rows[0];

if (!class_share_admin_can_view_sensitive_school(
    (int)$event['school_id'],
    $admin
)) {
    http_response_code(403);
    exit('참관록을 내려받을 권한이 없습니다.');
}

$where = '';
$params = array($event_id, $event_id);
$filter_name = '전체';

if ($filter === 'none') {
    $where = ' AND observation.class_id IS NULL';
    $filter_name = '행사 단위';
} elseif ($filter !== '') {
    if (!ctype_digit($filter) || (int)$filter <= 0) {
        http_response_code(400);
        exit('프로그램 번호가 올바르지 않습니다.');
    }

    $program_rows = pdo_query(
        "
        SELECT id, title
        FROM class_share_class
        WHERE id = ? AND event_id = ?
        LIMIT 1
        ",
        (int)$filter,
        $event_id
    );

    if ($program_rows === false) {
        http_response_code(500);
        exit('프로그램 정보를 불러올 수 없습니다.');
    }

    if (!isset($program_rows[0])) {
        http_response_code(400);
        exit('이 행사의 프로그램이 아닙니다.');
    }

    $where = ' AND observation.class_id = ?';
    $params[] = (int)$filter;
    $filter_name = $program_rows[0]['title'];
}

$reports = pdo_query(
    "
    SELECT observation.id,
           observation.class_id,
           observation.author_name,
           observation.affiliation,
           observation.body,
           observation.submitted_at,
           observation.is_archive,
           class.title AS program_title,
           class.class_start_at
    FROM (
        SELECT id, event_id, class_id,
               author_name, affiliation, body,
               submitted_at, 0 AS is_archive
        FROM class_share_observation
        WHERE event_id = ?

        UNION ALL

        SELECT id, event_id, class_id,
               NULL AS author_name,
               NULL AS affiliation,
               body,
               NULL AS submitted_at,
               1 AS is_archive
        FROM class_share_observation_archive
        WHERE event_id = ?
    ) AS observation
    LEFT JOIN class_share_class AS class
        ON class.id = observation.class_id
       AND class.event_id = observation.event_id
    WHERE 1 = 1
    " . $where . "
    ORDER BY
        CASE WHEN observation.class_id IS NULL
            THEN 1 ELSE 0 END,
        class.class_start_at,
        observation.class_id,
        observation.is_archive,
        observation.submitted_at,
        observation.id
    LIMIT 1001
    ",
    ...$params
);

if ($reports === false) {
    http_response_code(500);
    exit('참관록을 불러올 수 없습니다.');
}

if (count($reports) === 0) {
    http_response_code(404);
    exit('내려받을 참관록이 없습니다.');
}

if (count($reports) > 1000) {
    http_response_code(413);
    exit(
        '한 번에 최대 1,000건까지 내려받을 수 있습니다. ' .
        '프로그램을 선택해 주세요.'
    );
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('DOCX 생성 기능을 사용할 수 없습니다.');
}

function class_share_observation_docx_text($value)
{
    $text = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
        '',
        (string)$value
    );

    return htmlspecialchars(
        $text,
        ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function class_share_observation_docx_p($text, $kind = 'body')
{
    $size = $kind === 'title'
        ? 32
        : ($kind === 'heading' ? 26 : 22);

    $bold = $kind === 'body' ? '' : '<w:b/>';

    return
        '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr>' .
        '<w:r><w:rPr>' .
        '<w:rFonts w:eastAsia="Malgun Gothic"/>' .
        $bold .
        '<w:sz w:val="' . $size . '"/>' .
        '</w:rPr><w:t xml:space="preserve">' .
        class_share_observation_docx_text($text) .
        '</w:t></w:r></w:p>';
}

$body = class_share_observation_docx_p(
    $event['title'] . ' · 참관록',
    'title'
);

$body .= class_share_observation_docx_p(
    $event['school_name'] . ' / ' .
    $filter_name . ' / 총 ' .
    count($reports) . '건'
);

$previous_program = null;
$number = 0;

foreach ($reports as $report) {
    if (
        $report['class_id'] !== null &&
        $report['program_title'] === null
    ) {
        http_response_code(500);
        exit('참관록의 프로그램 연결 정보가 올바르지 않습니다.');
    }

    $program_key = $report['class_id'] === null
        ? 'none'
        : (string)$report['class_id'];

    if ($program_key !== $previous_program) {
        $program_name = $program_key === 'none'
            ? '행사 단위'
            : $report['program_title'];

        $body .= class_share_observation_docx_p(
            '프로그램: ' . $program_name,
            'heading'
        );

        $previous_program = $program_key;
    }

    $number++;

    $heading = $number . '. ';

    if ((int)$report['is_archive'] === 1) {
        $heading .= '익명 보존본';
    } else {
        $heading .=
            $report['author_name'] . ' · ' .
            $report['affiliation'] . ' · ' .
            $report['submitted_at'];
    }

    $body .= class_share_observation_docx_p(
        $heading,
        'heading'
    );

    $lines = preg_split(
        '/\r\n|\r|\n/',
        (string)$report['body']
    );

    foreach ($lines as $line) {
        $body .= class_share_observation_docx_p($line);
    }

    $body .= class_share_observation_docx_p('');
}

$document =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<w:document xmlns:w="' .
    'http://schemas.openxmlformats.org/' .
    'wordprocessingml/2006/main">' .
    '<w:body>' .
    $body .
    '<w:sectPr/></w:body></w:document>';

$content_types =
    '<?xml version="1.0" encoding="UTF-8"?>' .
    '<Types xmlns="' .
    'http://schemas.openxmlformats.org/package/2006/' .
    'content-types">' .
    '<Default Extension="rels" ContentType="' .
    'application/vnd.openxmlformats-package.relationships+xml"/>' .
    '<Default Extension="xml" ContentType="application/xml"/>' .
    '<Override PartName="/word/document.xml" ContentType="' .
    'application/vnd.openxmlformats-officedocument.' .
    'wordprocessingml.document.main+xml"/>' .
    '</Types>';

$relationships =
    '<?xml version="1.0" encoding="UTF-8"?>' .
    '<Relationships xmlns="' .
    'http://schemas.openxmlformats.org/package/2006/' .
    'relationships">' .
    '<Relationship Id="rId1" Type="' .
    'http://schemas.openxmlformats.org/' .
    'officeDocument/2006/relationships/officeDocument" ' .
    'Target="word/document.xml"/>' .
    '</Relationships>';

$temp = tempnam(
    sys_get_temp_dir(),
    'class_share_docx_'
);

if ($temp === false) {
    http_response_code(500);
    exit('임시 문서를 만들 수 없습니다.');
}

$zip = new ZipArchive();
$opened = $zip->open(
    $temp,
    ZipArchive::CREATE | ZipArchive::OVERWRITE
);

if ($opened !== true) {
    @unlink($temp);
    http_response_code(500);
    exit('DOCX 압축 파일을 만들 수 없습니다.');
}

$ok =
    $zip->addFromString(
        '[Content_Types].xml',
        $content_types
    ) &&
    $zip->addFromString(
        '_rels/.rels',
        $relationships
    ) &&
    $zip->addFromString(
        'word/document.xml',
        $document
    );

$closed = $zip->close();

if (!$ok || !$closed) {
    @unlink($temp);
    http_response_code(500);
    exit('DOCX 문서 저장에 실패했습니다.');
}

$filename =
    'observations-event-' .
    $event_id .
    '-' .
    date('Ymd') .
    '.docx';

header(
    'Content-Type: ' .
    'application/vnd.openxmlformats-officedocument.' .
    'wordprocessingml.document'
);
header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);
header('Content-Length: ' . filesize($temp));

readfile($temp);
@unlink($temp);
exit;
