<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

require_once(
    dirname(__DIR__) .
    '/include/privacy_crypto.php'
);

header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Cache-Control: no-store, max-age=0'
);

header(
    'Pragma: no-cache'
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

$event_id =
    isset($_POST['event_id'])
    ? (int)$_POST['event_id']
    : 0;

$status_filter =
    isset($_POST['status'])
    ? trim((string)$_POST['status'])
    : '';

$status_names =
    array(
        'applied' => '신청 완료',
        'approved' => '승인',
        'waiting' => '대기',
        'rejected' => '거절',
        'attended' => '참석',
        'absent' => '미참석',
        'cancelled' => '취소'
    );

if ($event_id <= 0) {
    http_response_code(400);
    exit('행사 번호가 올바르지 않습니다.');
}

if (
    $status_filter !== '' &&
    !array_key_exists(
        $status_filter,
        $status_names
    )
) {
    http_response_code(400);
    exit('신청 상태가 올바르지 않습니다.');
}

$event_rows =
    pdo_query(
        "
        SELECT
            event.id,
            event.school_id,
            event.title,
            event.academic_year,
            school.school_name

        FROM class_share_event AS event

        INNER JOIN class_share_school AS school
            ON school.id = event.school_id

        WHERE event.id = ?

        LIMIT 1
        ",
        $event_id
    );

if (
    $event_rows === false ||
    !isset($event_rows[0])
) {
    http_response_code(404);
    exit('행사를 찾을 수 없습니다.');
}

$event =
    $event_rows[0];

$school_id =
    (int)$event['school_id'];

if (
    !class_share_admin_can_view_sensitive_school(
        $school_id,
        $admin
    )
) {
    http_response_code(403);
    exit('신청자 연락처를 내려받을 권한이 없습니다.');
}

$status_condition =
    $status_filter === ''
    ? ''
    : ' AND application.status = ?';

$sql =
    "
    SELECT
        application.id,
        application.application_code,
        application.application_scope,
        application.applicant_name,
        application.applicant_school,
        application.phone_ciphertext,
        application.status,
        application.privacy_policy_version,
        application.privacy_agreed_at,
        application.privacy_destroyed_at,
        application.created_at,
        application.cancelled_at,
        application.admin_note,

        class_item.title AS program_title

    FROM class_share_application AS application

    LEFT JOIN class_share_class AS class_item
        ON class_item.id =
           application.class_id

    WHERE application.event_id = ?
    " .
    $status_condition .
    "
    ORDER BY
        application.created_at,
        application.id
    ";

if ($status_filter === '') {
    $applications =
        pdo_query(
            $sql,
            $event_id
        );
} else {
    $applications =
        pdo_query(
            $sql,
            $event_id,
            $status_filter
        );
}

if ($applications === false) {
    http_response_code(500);
    exit('신청자 자료를 불러올 수 없습니다.');
}

$format_phone =
    function ($phone) {
        $phone =
            class_share_normalize_phone(
                $phone
            );

        if (strlen($phone) === 11) {
            return
                substr($phone, 0, 3) .
                '-' .
                substr($phone, 3, 4) .
                '-' .
                substr($phone, 7, 4);
        }

        return
            substr($phone, 0, 3) .
            '-' .
            substr($phone, 3, 3) .
            '-' .
            substr($phone, 6, 4);
    };

$csv_safe =
    function ($value) {
        $value =
            str_replace(
                "\0",
                '',
                (string)$value
            );

        if (
            preg_match(
                '/^[\x00-\x20]*[=+\-@]/',
                $value
            ) ||
            preg_match(
                '/^[\t\r]/',
                $value
            )
        ) {
            return "'" . $value;
        }

        return $value;
    };

$export_rows =
    array();

try {
    foreach ($applications as $application) {
        $privacy_destroyed =
            $application[
                'privacy_destroyed_at'
            ] !== null;

        if ($privacy_destroyed) {
            $application_code = '';
            $applicant_name = '개인정보 파기 완료';
            $applicant_school = '';
            $phone = '';
        } else {
            $application_code =
                (string)$application[
                    'application_code'
                ];

            $applicant_name =
                (string)$application[
                    'applicant_name'
                ];

            $applicant_school =
                (string)$application[
                    'applicant_school'
                ];

            $phone =
                class_share_decrypt_phone(
                    $application[
                        'phone_ciphertext'
                    ]
                );
        }

        $scope =
            (string)$application[
                'application_scope'
            ];

        $scope_name =
            $scope === 'event'
            ? '행사 직접 신청'
            : '프로그램 신청';

        $target_name =
            $scope === 'event'
            ? (string)$event['title']
            : (
                $application[
                    'program_title'
                ] !== null
                ? (string)$application[
                    'program_title'
                ]
                : '-'
            );

        $application_status =
            (string)$application['status'];

        $export_rows[] =
            array(
                $csv_safe($application_code),
                $csv_safe($scope_name),
                $csv_safe($target_name),
                $csv_safe($applicant_name),
                $csv_safe($applicant_school),
                $csv_safe($format_phone($phone)),
                $csv_safe(
                    isset(
                        $status_names[
                            $application_status
                        ]
                    )
                    ? $status_names[
                        $application_status
                    ]
                    : $application_status
                ),
                $csv_safe(
                    $application[
                        'privacy_policy_version'
                    ]
                ),
                $csv_safe(
                    $application[
                        'privacy_agreed_at'
                    ]
                ),
                $csv_safe(
                    $application['created_at']
                ),
                $csv_safe(
                    $application[
                        'cancelled_at'
                    ] !== null
                    ? $application[
                        'cancelled_at'
                    ]
                    : ''
                ),
                $csv_safe(
                    $application[
                        'admin_note'
                    ] !== null
                    ? $application[
                        'admin_note'
                    ]
                    : ''
                )
            );

        $phone = null;
    }
} catch (Throwable $e) {
    error_log(
        '[class-share] 신청자 CSV 연락처 처리 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('신청자 연락처를 안전하게 처리할 수 없습니다.');
}

$csv_headers =
    array(
        '신청번호',
        '신청구분',
        '행사·프로그램',
        '성명',
        '소속',
        '연락처',
        '신청상태',
        '개인정보 안내 버전',
        '개인정보 동의일시',
        '신청일시',
        '취소일시',
        '관리자 메모'
    );

$to_cp949 =
    function ($value) {
        $converted =
            iconv(
                'UTF-8',
                'CP949//TRANSLIT',
                (string)$value
            );

        if ($converted === false) {
            throw new RuntimeException(
                'CSV 문자 인코딩 변환 실패'
            );
        }

        return $converted;
    };

try {
    foreach ($csv_headers as $index => $value) {
        $csv_headers[$index] =
            $to_cp949($value);
    }

    foreach ($export_rows as $row_index => $row) {
        foreach ($row as $column_index => $value) {
            $export_rows[$row_index][$column_index] =
                $to_cp949($value);
        }
    }
} catch (Throwable $e) {
    error_log(
        '[class-share] 신청자 CSV 인코딩 변환 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('CSV 문자 인코딩을 변환할 수 없습니다.');
}

$audit_data =
    array(
        'event_id' =>
            $event_id,

        'school_id' =>
            $school_id,

        'status_filter' =>
            $status_filter,

        'export_count' =>
            count($export_rows)
    );

$after_json =
    json_encode(
        $audit_data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

if ($after_json === false) {
    http_response_code(500);
    exit('내려받기 감사 자료를 만들 수 없습니다.');
}

try {
    if (
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    $dbh->beginTransaction();

    $audit_result =
        pdo_query(
            "
            INSERT INTO class_share_audit_log
            (
                school_id,
                admin_id,
                actor_type,
                action,
                target_type,
                target_id,
                before_data,
                after_data,
                ip_address,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                'admin',
                'application.export',
                'event',
                ?,
                NULL,
                ?,
                ?,
                NOW()
            )
            ",
            $school_id,
            (int)$admin['id'],
            $event_id,
            $after_json,
            class_share_admin_client_ip()
        );

    if ($audit_result === false) {
        throw new RuntimeException(
            '내려받기 감사 기록 저장 실패'
        );
    }

    $dbh->commit();
} catch (Throwable $e) {
    if (
        isset($dbh) &&
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        '[class-share] 신청자 CSV 감사 기록 실패: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('내려받기 기록을 저장할 수 없습니다.');
}

session_write_close();

while (ob_get_level() > 0) {
    if (!ob_end_clean()) {
        break;
    }
}

$filename =
    'class-share-event-' .
    $event_id .
    '-applications-' .
    date('Ymd-His') .
    '.csv';

header(
    'Content-Type: text/csv; charset=CP949'
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

$output =
    fopen(
        'php://output',
        'wb'
    );

if ($output === false) {
    http_response_code(500);
    exit('CSV 출력을 시작할 수 없습니다.');
}

fputcsv(
    $output,
    $csv_headers
);

foreach ($export_rows as $export_row) {
    fputcsv(
        $output,
        $export_row
    );
}

fclose(
    $output
);

exit;
