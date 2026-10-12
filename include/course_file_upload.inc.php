<?php

/**
 * Course 학습 자료 첨부파일 검증 공통 함수
 *
 * PHP 7.4 호환
 * 실제 파일 저장 및 DB 기록은 수행하지 않는다.
 */

function course_file_allowed_extensions()
{
    return array(
        'pdf', 'docx', 'hwpx',
        'pptx', 'xlsx',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'py', 'ipynb', 'c', 'cpp', 'java',
        'txt', 'csv', 'json', 'zip'
    );
}

/**
 * 업로드 파일의 기본 입력값 검증
 *
 * 정상: array 형태의 검증 정보 반환
 * 오류: InvalidArgumentException 발생
 */
function course_file_validate_basic($file)
{
    if (!is_array($file)) {
        throw new InvalidArgumentException(
            '업로드 파일 정보가 올바르지 않습니다.'
        );
    }

    foreach (array(
        'name', 'tmp_name', 'size', 'error'
    ) as $key) {
        if (!array_key_exists($key, $file)) {
            throw new InvalidArgumentException(
                '업로드 파일 정보가 누락되었습니다.'
            );
        }
    }

    if (
        !is_string($file['name']) ||
        !is_string($file['tmp_name']) ||
        !is_int($file['error']) ||
        !is_int($file['size'])
    ) {
        throw new InvalidArgumentException(
            '업로드 파일 정보의 형식이 올바르지 않습니다.'
        );
    }

    $error = $file['error'];

    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(
            '파일 업로드에 실패했습니다. 오류 코드: ' .
            $error
        );
    }

    $name = $file['name'];
    $tmp_name = $file['tmp_name'];
    $size = $file['size'];

    if (
        $name === '' ||
        strlen($name) > 255 ||
        !preg_match('//u', $name) ||
        preg_match('/[\x00-\x1F\x7F]/', $name) ||
        strpos($name, '/') !== false ||
        strpos($name, '\\') !== false ||
        $name === '.' ||
        $name === '..'
    ) {
        throw new InvalidArgumentException(
            '파일 이름이 올바르지 않습니다.'
        );
    }

    $extension = strtolower(
        (string)pathinfo(
            $name,
            PATHINFO_EXTENSION
        )
    );

    if (!in_array(
        $extension,
        course_file_allowed_extensions(),
        true
    )) {
        throw new InvalidArgumentException(
            '허용되지 않은 파일 확장자입니다.'
        );
    }

    $max_size = 20 * 1024 * 1024;

    if ($size <= 0 || $size > $max_size) {
        throw new InvalidArgumentException(
            '파일 크기는 20MB 이하여야 합니다.'
        );
    }

    if (
        $tmp_name === '' ||
        !is_uploaded_file($tmp_name)
    ) {
        throw new InvalidArgumentException(
            '정상적인 HTTP 업로드 파일이 아닙니다.'
        );
    }

    $actual_size = filesize($tmp_name);

    if (
        $actual_size === false ||
        $actual_size !== $size
    ) {
        throw new InvalidArgumentException(
            '실제 파일 크기가 일치하지 않습니다.'
        );
    }

    return array(
        'original_name' => $name,
        'extension' => $extension,
        'tmp_name' => $tmp_name,
        'file_size' => $size
    );
}


/**
 * PDF 및 이미지 파일 내용 검증
 * 아직 지원하지 않는 파일 형식은 거부한다.
 */
function course_file_validate_content($validated_file)
{
    if (
        !is_array($validated_file) ||
        !isset(
            $validated_file['extension'],
            $validated_file['tmp_name']
        )
    ) {
        throw new InvalidArgumentException(
            '검증할 파일 정보가 올바르지 않습니다.'
        );
    }

    $extension = $validated_file['extension'];
    $path = $validated_file['tmp_name'];

    if (
        !is_string($extension) ||
        !is_string($path) ||
        !is_file($path)
    ) {
        throw new InvalidArgumentException(
            '파일 형식을 확인할 수 없습니다.'
        );
    }

    if (in_array(
        $extension,
        array('zip', 'docx', 'pptx', 'xlsx', 'hwpx'),
        true
    )) {
        return course_file_validate_zip_content(
            $validated_file
        );
    }

    if (in_array(
        $extension,
        array('py', 'c', 'cpp', 'java', 'txt', 'csv', 'json', 'ipynb'),
        true
    )) {
        return course_file_validate_text_content(
            $validated_file
        );
    }

    $allowed_mimes = array(
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    );

    if (!isset($allowed_mimes[$extension])) {
        throw new InvalidArgumentException(
            '해당 파일 형식은 아직 지원하지 않습니다.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);

    if ($mime !== $allowed_mimes[$extension]) {
        throw new InvalidArgumentException(
            '파일 확장자와 실제 내용이 일치하지 않습니다.'
        );
    }

    if ($extension === 'pdf') {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException(
                'PDF 파일을 읽을 수 없습니다.'
            );
        }

        $signature = fread($handle, 5);
        fclose($handle);

        if ($signature !== '%PDF-') {
            throw new InvalidArgumentException(
                '유효하지 않은 PDF 파일입니다.'
            );
        }

    } else {
        $image_info = @getimagesize($path);

        if (
            $image_info === false ||
            !isset($image_info['mime']) ||
            $image_info['mime'] !== $mime
        ) {
            throw new InvalidArgumentException(
                '유효하지 않은 이미지 파일입니다.'
            );
        }
    }

    return array(
        'mime_type' => $mime
    );
}


/**
 * ZIP 및 ZIP 기반 문서 파일 검사.
 *
 * 실제 압축 해제는 수행하지 않는다.
 * 검증 성공 시 MIME 정보 반환.
 */
function course_file_validate_zip_content($validated_file)
{
    $extension = $validated_file['extension'];
    $path = $validated_file['tmp_name'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);

    $accepted_mimes = array(
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/octet-stream',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    if (!in_array($mime, $accepted_mimes, true)) {
        throw new InvalidArgumentException(
            'ZIP 파일의 실제 형식이 올바르지 않습니다.'
        );
    }

    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        throw new InvalidArgumentException(
            '정상적인 ZIP 파일이 아닙니다.'
        );
    }

    try {
        if ($zip->numFiles < 1 || $zip->numFiles > 200) {
            throw new InvalidArgumentException(
                'ZIP 내부 파일 수가 허용 범위를 초과했습니다.'
            );
        }

        $total_size = 0;
        $names = array();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);

            if (
                $entry === false ||
                !isset(
                    $entry['name'],
                    $entry['size'],
                    $entry['comp_size']
                )
            ) {
                throw new InvalidArgumentException(
                    'ZIP 내부 파일 정보를 확인할 수 없습니다.'
                );
            }

            $name = $entry['name'];
            $size = (int)$entry['size'];
            $compressed = (int)$entry['comp_size'];

            // ZIP 내부 Unix 파일 속성을 검사한다.
            $opsys = 0;
            $attributes = 0;

            if (
                !$zip->getExternalAttributesIndex(
                    $i,
                    $opsys,
                    $attributes
                )
            ) {
                throw new InvalidArgumentException(
                    'ZIP 내부 파일 속성을 확인할 수 없습니다.'
                );
            }

            if ($opsys === ZipArchive::OPSYS_UNIX) {

                // 일반 파일과 디렉터리만 허용한다.
                $file_type = ($attributes >> 16) & 0170000;

                if (
                    $file_type !== 0 &&
                    $file_type !== 0100000 &&
                    $file_type !== 0040000
                ) {
                    throw new InvalidArgumentException(
                        'ZIP 내부에 허용되지 않은 특수 파일이 있습니다.'
                    );
                }

                if (
                    $file_type === 0040000 &&
                    substr($name, -1) !== '/'
                ) {
                    throw new InvalidArgumentException(
                        'ZIP 내부 디렉터리 정보가 올바르지 않습니다.'
                    );
                }

                if (
                    $file_type === 0100000 &&
                    substr($name, -1) === '/'
                ) {
                    throw new InvalidArgumentException(
                        'ZIP 내부 파일 정보가 올바르지 않습니다.'
                    );
                }
            }

            if (
                $name === '' ||
                strlen($name) > 1024 ||
                strpos($name, '\\') !== false ||
                strpos($name, "\0") !== false ||
                substr($name, 0, 1) === '/'
            ) {
                throw new InvalidArgumentException(
                    'ZIP 내부 경로가 올바르지 않습니다.'
                );
            }

            $segments = explode('/', $name);

            if (
                in_array('..', $segments, true) ||
                in_array('.', $segments, true)
            ) {
                throw new InvalidArgumentException(
                    'ZIP 내부에 위험한 경로가 있습니다.'
                );
            }

            if (isset($names[$name])) {
                throw new InvalidArgumentException(
                    'ZIP 내부 파일명이 중복됩니다.'
                );
            }

            $names[$name] = true;

            // 암호화 여부를 확인할 수 없는 환경에서도 차단한다.
            if (
                !array_key_exists('encryption_method', $entry) ||
                (int)$entry['encryption_method'] !== 0
            ) {
                throw new InvalidArgumentException(
                    '암호화되었거나 검사할 수 없는 ZIP 항목입니다.'
                );
            }

            if (
                $size < 0 ||
                $compressed < 0 ||
                $size > 50 * 1024 * 1024
            ) {
                throw new InvalidArgumentException(
                    'ZIP 내부 파일 크기가 허용 범위를 초과했습니다.'
                );
            }

            $total_size += $size;

            if ($total_size > 100 * 1024 * 1024) {
                throw new InvalidArgumentException(
                    'ZIP 압축 해제 예상 크기가 너무 큽니다.'
                );
            }

            if (
                $size > 1024 * 1024 &&
                $size > $compressed * 1000
            ) {
                throw new InvalidArgumentException(
                    'ZIP 압축률이 비정상적으로 높습니다.'
                );
            }
        }

        $required = array(
            'docx' => array(
                '[Content_Types].xml',
                'word/document.xml'
            ),
            'pptx' => array(
                '[Content_Types].xml',
                'ppt/presentation.xml'
            ),
            'xlsx' => array(
                '[Content_Types].xml',
                'xl/workbook.xml'
            ),
            'hwpx' => array(
                'mimetype',
                'Contents/content.hpf'
            )
        );

        if (isset($required[$extension])) {
            foreach ($required[$extension] as $name) {
                if (!isset($names[$name])) {
                    throw new InvalidArgumentException(
                        '문서에 필요한 내부 파일이 없습니다.'
                    );
                }
            }
        }

        // ZIP 기반 문서의 내부 XML 구조를 검사한다.
        $xml_rules = array(
            'docx' => array(
                '[Content_Types].xml' => array(
                    'Types',
                    'http://schemas.openxmlformats.org/package/2006/content-types'
                ),
                'word/document.xml' => array(
                    'document',
                    'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
                )
            ),
            'pptx' => array(
                '[Content_Types].xml' => array(
                    'Types',
                    'http://schemas.openxmlformats.org/package/2006/content-types'
                ),
                'ppt/presentation.xml' => array(
                    'presentation',
                    'http://schemas.openxmlformats.org/presentationml/2006/main'
                )
            ),
            'xlsx' => array(
                '[Content_Types].xml' => array(
                    'Types',
                    'http://schemas.openxmlformats.org/package/2006/content-types'
                ),
                'xl/workbook.xml' => array(
                    'workbook',
                    'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
                )
            ),
            'hwpx' => array(
                'Contents/content.hpf' => array(
                    'package',
                    null
                )
            )
        );

        if (isset($xml_rules[$extension])) {
            foreach ($xml_rules[$extension] as $xml_name => $rule) {
                course_file_validate_document_xml(
                    $zip,
                    $xml_name,
                    $rule[0],
                    $rule[1]
                );
            }
        }

        if ($extension === 'hwpx') {
            $type = $zip->getFromName(
                'mimetype',
                200
            );

            if (
                $type === false ||
                !in_array(
                    trim($type),
                    array(
                        'application/hwp+zip',
                        'application/vnd.hancom.hwpx'
                    ),
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'HWPX 문서 형식이 올바르지 않습니다.'
                );
            }
        }

    } finally {
        $zip->close();
    }

    return array(
        'mime_type' => $mime
    );
}


/**
 * ZIP 내부 문서 XML 검증
 *
 * XML 최대 2MB, DTD 거부, 외부 네트워크 접근 차단.
 */
function course_file_validate_document_xml(
    $zip,
    $entry_name,
    $expected_root,
    $expected_namespace
) {
    $max_xml_size = 2 * 1024 * 1024;

    $entry = $zip->statName($entry_name);

    if (
        $entry === false ||
        !isset($entry['size']) ||
        (int)$entry['size'] <= 0 ||
        (int)$entry['size'] > $max_xml_size
    ) {
        throw new InvalidArgumentException(
            '문서 내부 XML 크기가 올바르지 않습니다.'
        );
    }

    $xml = $zip->getFromName(
        $entry_name,
        $max_xml_size + 1
    );

    if (
        $xml === false ||
        strlen($xml) !== (int)$entry['size']
    ) {
        throw new InvalidArgumentException(
            '문서 내부 XML을 읽을 수 없습니다.'
        );
    }

    $previous_errors = libxml_use_internal_errors(true);

    try {
        libxml_clear_errors();

        $document = new DOMDocument();

        if (
            !$document->loadXML(
                $xml,
                LIBXML_NONET
            ) ||
            $document->doctype !== null ||
            !$document->documentElement
        ) {
            throw new InvalidArgumentException(
                '문서 내부 XML이 올바르지 않습니다.'
            );
        }

        $root = $document->documentElement;

        if ($root->localName !== $expected_root) {
            throw new InvalidArgumentException(
                '문서 내부 XML 루트 요소가 올바르지 않습니다.'
            );
        }

        if (
            $expected_namespace !== null &&
            $root->namespaceURI !== $expected_namespace
        ) {
            throw new InvalidArgumentException(
                '문서 내부 XML 네임스페이스가 올바르지 않습니다.'
            );
        }

    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);
    }
}


/**
 * 프로그래밍·텍스트·JSON 파일 형식 검증.
 *
 * 파일을 실행하지 않으며 UTF-8 텍스트 구조만 검사한다.
 */
function course_file_validate_text_content($validated_file)
{
    $extension = $validated_file['extension'];
    $path = $validated_file['tmp_name'];

    $allowed = array(
        'py', 'c', 'cpp', 'java',
        'txt', 'csv', 'json', 'ipynb'
    );

    if (!in_array($extension, $allowed, true)) {
        throw new InvalidArgumentException(
            '지원하지 않는 텍스트 파일입니다.'
        );
    }

    if (!is_file($path)) {
        throw new InvalidArgumentException(
            '파일을 확인할 수 없습니다.'
        );
    }

    $content = file_get_contents($path);

    if ($content === false) {
        throw new InvalidArgumentException(
            '파일을 읽을 수 없습니다.'
        );
    }

    // UTF-8 BOM 제거
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    if (
        !mb_check_encoding($content, 'UTF-8') ||
        strpos($content, "\0") !== false ||
        preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)
    ) {
        throw new InvalidArgumentException(
            'UTF-8 텍스트 형식이 올바르지 않습니다.'
        );
    }

    if ($extension === 'json' || $extension === 'ipynb') {

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                'JSON 문법이 올바르지 않습니다.'
            );
        }

        if ($extension === 'ipynb') {

            if (
                !is_array($decoded) ||
                !isset(
                    $decoded['cells'],
                    $decoded['metadata'],
                    $decoded['nbformat']
                ) ||
                !is_array($decoded['cells']) ||
                !is_array($decoded['metadata']) ||
                !is_int($decoded['nbformat']) ||
                $decoded['nbformat'] !== 4
            ) {
                throw new InvalidArgumentException(
                    'Jupyter Notebook 형식이 올바르지 않습니다.'
                );
            }

            foreach ($decoded['cells'] as $cell) {
                if (
                    !is_array($cell) ||
                    !isset($cell['cell_type']) ||
                    !in_array(
                        $cell['cell_type'],
                        array('code', 'markdown', 'raw'),
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Notebook 셀 구조가 올바르지 않습니다.'
                    );
                }
            }
        }
    }

    return array(
        'mime_type' => (
            $extension === 'json' ||
            $extension === 'ipynb'
                ? 'application/json'
                : 'text/plain'
        )
    );
}
