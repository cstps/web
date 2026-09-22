<?php

function class_share_bulk_expected_headers()
{
    return array(
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
}

function class_share_bulk_convert_csv_to_utf8(
    $raw_content
) {
    if (!is_string($raw_content)) {
        return array(
            'ok' => false,
            'content' => '',
            'encoding' => '',
            'error' =>
                'CSV 파일 내용을 읽을 수 없습니다.'
        );
    }

    if (
        strpos(
            $raw_content,
            "\0"
        ) !== false
    ) {
        return array(
            'ok' => false,
            'content' => '',
            'encoding' => '',
            'error' =>
                'UTF-16 형식은 지원하지 않습니다. Excel에서 CSV 형식으로 다시 저장해 주세요.'
        );
    }

    if (
        substr(
            $raw_content,
            0,
            3
        ) === "\xEF\xBB\xBF"
    ) {
        $raw_content =
            substr(
                $raw_content,
                3
            );

        $encoding =
            'UTF-8 BOM';
    } else {
        $encoding =
            'UTF-8';
    }

    if (
        preg_match(
            '//u',
            $raw_content
        ) === 1
    ) {
        return array(
            'ok' => true,
            'content' => $raw_content,
            'encoding' => $encoding,
            'error' => ''
        );
    }

    if (!function_exists('iconv')) {
        return array(
            'ok' => false,
            'content' => '',
            'encoding' => '',
            'error' =>
                'CP949 CSV를 변환하는 기능을 사용할 수 없습니다.'
        );
    }

    $converted =
        iconv(
            'CP949',
            'UTF-8',
            $raw_content
        );

    if (
        $converted === false ||
        preg_match(
            '//u',
            $converted
        ) !== 1
    ) {
        return array(
            'ok' => false,
            'content' => '',
            'encoding' => '',
            'error' =>
                'CSV 문자 인코딩을 확인할 수 없습니다. UTF-8 또는 CP949 CSV로 저장해 주세요.'
        );
    }

    return array(
        'ok' => true,
        'content' => $converted,
        'encoding' => 'CP949',
        'error' => ''
    );
}

function class_share_bulk_row_is_empty($row)
{
    if (!is_array($row)) {
        return true;
    }

    foreach ($row as $value) {
        if (
            trim(
                (string)$value
            ) !== ''
        ) {
            return false;
        }
    }

    return true;
}

function class_share_bulk_normalize_header($value)
{
    $value =
        trim(
            (string)$value
        );

    /*
     * Excel 또는 편집 과정에서 제목에 들어간
     * UTF-8 BOM과 줄바꿈을 제거합니다.
     */
    if (
        substr(
            $value,
            0,
            3
        ) === "\xEF\xBB\xBF"
    ) {
        $value =
            substr(
                $value,
                3
            );
    }

    return trim(
        str_replace(
            array(
                "\r",
                "\n"
            ),
            '',
            $value
        )
    );
}

/**
 * 반환값:
 *
 * array(
 *     'errors' => array(),
 *     'encoding' => 'UTF-8|UTF-8 BOM|CP949',
 *     'rows' => array(
 *         array(
 *             'row_number' => 2,
 *             'cells' => array(...)
 *         )
 *     )
 * )
 */
function class_share_bulk_parse_csv_content(
    $raw_content,
    $maximum_rows
) {
    $maximum_rows =
        (int)$maximum_rows;

    if ($maximum_rows < 1) {
        $maximum_rows =
            500;
    }

    $result =
        array(
            'errors' => array(),
            'encoding' => '',
            'rows' => array()
        );

    $conversion =
        class_share_bulk_convert_csv_to_utf8(
            $raw_content
        );

    if (!$conversion['ok']) {
        $result['errors'][] =
            $conversion['error'];

        return $result;
    }

    $result['encoding'] =
        $conversion['encoding'];

    $stream =
        fopen(
            'php://temp',
            'w+b'
        );

    if ($stream === false) {
        $result['errors'][] =
            'CSV 분석용 임시 공간을 만들 수 없습니다.';

        return $result;
    }

    $written =
        fwrite(
            $stream,
            $conversion['content']
        );

    if ($written === false) {
        fclose($stream);

        $result['errors'][] =
            'CSV 내용을 임시 공간에 기록할 수 없습니다.';

        return $result;
    }

    rewind($stream);

    $header_row =
        fgetcsv(
            $stream,
            0,
            ',',
            '"',
            ''
        );

    if ($header_row === false) {
        fclose($stream);

        $result['errors'][] =
            'CSV 제목 행을 찾을 수 없습니다.';

        return $result;
    }

    $normalized_headers =
        array();

    foreach ($header_row as $header_value) {
        $normalized_headers[] =
            class_share_bulk_normalize_header(
                $header_value
            );
    }

    $expected_headers =
        class_share_bulk_expected_headers();

    if (
        count($normalized_headers) !==
            count($expected_headers) ||
        $normalized_headers !==
            $expected_headers
    ) {
        fclose($stream);

        $result['errors'][] =
            'CSV 제목 행이 현재 양식과 일치하지 않습니다. 새 양식을 내려받아 제목을 변경하지 않고 사용해 주세요.';

        return $result;
    }

    $row_number =
        1;

    while (
        (
            $row =
                fgetcsv(
                    $stream,
                    0,
                    ',',
                    '"',
                    ''
                )
        ) !== false
    ) {
        $row_number++;

        if (
            class_share_bulk_row_is_empty(
                $row
            )
        ) {
            continue;
        }

        if (
            count($result['rows']) >=
            $maximum_rows
        ) {
            $result['errors'][] =
                '한 번에 등록할 수 있는 수업은 최대 ' .
                $maximum_rows .
                '개입니다.';

            break;
        }

        if (
            count($row) !==
            count($expected_headers)
        ) {
            $result['errors'][] =
                $row_number .
                '행의 열 개수가 올바르지 않습니다. ' .
                count($expected_headers) .
                '개 열을 모두 유지해 주세요.';

            if (
                count($row) <
                count($expected_headers)
            ) {
                $row =
                    array_pad(
                        $row,
                        count($expected_headers),
                        ''
                    );
            } else {
                $row =
                    array_slice(
                        $row,
                        0,
                        count($expected_headers)
                    );
            }
        }

        $result['rows'][] =
            array(
                'row_number' =>
                    $row_number,

                'cells' =>
                    $row
            );
    }

    fclose($stream);

    if (count($result['rows']) === 0) {
        $result['errors'][] =
            '등록할 수업 정보가 없습니다. 제목 행 아래에 수업을 입력해 주세요.';
    }

    return $result;
}

function class_share_bulk_map_row_to_input($cells)
{
    $cells =
        is_array($cells)
        ? array_values($cells)
        : array();

    $cells =
        array_pad(
            $cells,
            11,
            ''
        );

    return array(
        'subject' =>
            $cells[0],

        'title' =>
            $cells[1],

        'teacher_name' =>
            $cells[2],

        'target' =>
            $cells[3],

        'class_start_at' =>
            $cells[4],

        'class_end_at' =>
            $cells[5],

        'place' =>
            $cells[6],

        'application_deadline' =>
            $cells[7],

        'capacity' =>
            $cells[8],

        'description' =>
            $cells[9],

        'sort_order' =>
            $cells[10]
    );
}

function class_share_bulk_duplicate_key($data)
{
    if (!is_array($data)) {
        $data =
            array();
    }

    $values =
        array(
            isset($data['title'])
            ? (string)$data['title']
            : '',

            isset($data['teacher_name'])
            ? (string)$data['teacher_name']
            : '',

            isset($data['class_start_at'])
            ? (string)$data['class_start_at']
            : '',

            isset($data['place'])
            ? (string)$data['place']
            : ''
        );

    $encoded =
        json_encode(
            $values,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    if ($encoded === false) {
        $encoded =
            implode(
                "\x1F",
                $values
            );
    }

    return hash(
        'sha256',
        $encoded
    );
}
