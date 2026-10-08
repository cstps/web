<?php

require_once(
    __DIR__ .
    '/application_functions.php'
);


function class_share_create_event_application(
    $school_id,
    $event_id,
    $data
) {
    global $dbh;

    $school_id =
        (int)$school_id;

    $event_id =
        (int)$event_id;

    if (
        $school_id < 1 ||
        $event_id < 1 ||
        !is_array($data)
    ) {
        throw new InvalidArgumentException(
            '신청 대상 정보가 올바르지 않습니다.'
        );
    }

    $name =
        isset($data['name'])
        ? (string)$data['name']
        : '';

    $school =
        isset($data['school'])
        ? (string)$data['school']
        : '';

    $phone =
        isset($data['phone'])
        ? class_share_normalize_phone(
            $data['phone']
        )
        : '';

    $password =
        isset($data['password'])
        ? (string)$data['password']
        : '';

    $phone_ciphertext =
        class_share_encrypt_phone(
            $phone
        );

    $phone_lookup_hash =
        class_share_phone_lookup_hash(
            $phone
        );

    $phone_last4 =
        class_share_phone_last4(
            $phone
        );

    $password_hash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );

    if ($password_hash === false) {
        throw new RuntimeException(
            '신청 비밀번호를 안전하게 저장할 수 없습니다.'
        );
    }

    $application_code =
        class_share_application_code();

    $ip_address =
        class_share_application_ip_address();

    $connection_result =
        pdo_query(
            'SELECT 1 AS ready'
        );

    if (
        $connection_result === false ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    try {
        $dbh->beginTransaction();

        $event_rows =
            pdo_query(
                "
                SELECT
                    event.id,
                    event.school_id,
                    event.status,
                    event.application_mode,
                    event.application_capacity,
                    event.participation_enabled,
                    event.application_start_at,
                    event.application_end_at,
                    event.privacy_policy_version

                FROM class_share_event AS event

                INNER JOIN class_share_school AS school
                    ON school.id = event.school_id
                   AND school.status = 'active'

                WHERE event.id = ?
                  AND event.school_id = ?

                LIMIT 1

                FOR UPDATE
                ",
                $event_id,
                $school_id
            );

        if (
            $event_rows === false ||
            !isset($event_rows[0])
        ) {
            throw new DomainException(
                '신청할 수 있는 행사를 찾을 수 없습니다.'
            );
        }

        $event =
            $event_rows[0];

        if (
            !class_share_application_event_is_open(
                $event
            )
        ) {
            throw new DomainException(
                '현재 행사 신청 기간이 아닙니다.'
            );
        }

        $duplicate_rows =
            pdo_query(
                "
                SELECT
                    id

                FROM class_share_application

                WHERE event_id = ?
                  AND application_scope = 'event'
                  AND phone_lookup_hash = ?
                  AND status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )

                LIMIT 1
                ",
                $event_id,
                $phone_lookup_hash
            );

        if ($duplicate_rows === false) {
            throw new RuntimeException(
                '중복 신청 정보를 확인할 수 없습니다.'
            );
        }

        if (isset($duplicate_rows[0])) {
            throw new DomainException(
                '같은 연락처로 이미 신청한 행사입니다.'
            );
        }

        $count_rows =
            pdo_query(
                "
                SELECT
                    COUNT(*) AS active_count

                FROM class_share_application

                WHERE event_id = ?
                  AND application_scope = 'event'
                  AND status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )
                ",
                $event_id
            );

        if (
            $count_rows === false ||
            !isset($count_rows[0])
        ) {
            throw new RuntimeException(
                '행사 신청 인원을 확인할 수 없습니다.'
            );
        }

        $active_count =
            (int)$count_rows[0][
                'active_count'
            ];

        $capacity =
            $event[
                'application_capacity'
            ] === null
            ? null
            : (int)$event[
                'application_capacity'
            ];

        if (
            $capacity !== null &&
            $active_count >= $capacity
        ) {
            throw new DomainException(
                '행사 신청 정원이 마감되었습니다.'
            );
        }

        // 행사 잠금 안에서 구분별 남은 인원을 최종 확인합니다.
        $participation_option = class_share_participation_new_choice(
            $event,
            isset($data['participation_option_id']) ? $data['participation_option_id'] : null
        );
        $participation_option_id = $participation_option === null
            ? null : (int)$participation_option['id'];

        $application_id =
            pdo_query(
                "
                INSERT INTO class_share_application
                (
                    event_id,
                    class_id,
                    application_scope,
                    participation_option_id,
                    application_code,
                    applicant_name,
                    applicant_school,
                    phone_ciphertext,
                    phone_lookup_hash,
                    phone_last4,
                    password_hash,
                    status,
                    privacy_policy_version,
                    privacy_agreed_at,
                    cancelled_at,
                    processed_by,
                    admin_note,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    NULL,
                    'event',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'applied',
                    ?,
                    NOW(),
                    NULL,
                    NULL,
                    NULL,
                    NOW(),
                    NOW()
                )
                ",
                $event_id,
                $participation_option_id,
                $application_code,
                $name,
                $school,
                $phone_ciphertext,
                $phone_lookup_hash,
                $phone_last4,
                $password_hash,
                (string)$event[
                    'privacy_policy_version'
                ]
            );

        if ($application_id === false) {
            throw new RuntimeException(
                '행사 신청을 저장할 수 없습니다.'
            );
        }

        $after_json =
            json_encode(
                array(
                    'event_id' =>
                        $event_id,

                    'participation_option_id' => $participation_option_id,

                    'application_scope' =>
                        'event',

                    'application_code' =>
                        $application_code,

                    'status' =>
                        'applied',

                    'privacy_policy_version' =>
                        (string)$event[
                            'privacy_policy_version'
                        ]
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        if ($after_json === false) {
            throw new RuntimeException(
                '신청 감사 자료를 만들 수 없습니다.'
            );
        }

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
                    NULL,
                    'applicant',
                    'application.create',
                    'application',
                    ?,
                    NULL,
                    ?,
                    ?,
                    NOW()
                )
                ",
                $school_id,
                (int)$application_id,
                $after_json,
                $ip_address
            );

        if ($audit_result === false) {
            throw new RuntimeException(
                '신청 감사 기록을 저장할 수 없습니다.'
            );
        }

        $dbh->commit();

        return array(
            'participation_name' => $participation_option === null
                ? '' : (string)$participation_option['name'],

            'application_id' =>
                (int)$application_id,

            'application_code' =>
                $application_code,

            'status' =>
                'applied'
        );
    } catch (Throwable $e) {
        if (
            $dbh instanceof PDO &&
            $dbh->inTransaction()
        ) {
            $dbh->rollBack();
        }

        throw $e;
    }
}


function class_share_create_program_application(
    $school_id,
    $event_id,
    $class_id,
    $data
) {
    global $dbh;

    $school_id =
        (int)$school_id;

    $event_id =
        (int)$event_id;

    $class_id =
        (int)$class_id;

    if (
        $school_id < 1 ||
        $event_id < 1 ||
        $class_id < 1 ||
        !is_array($data)
    ) {
        throw new InvalidArgumentException(
            '신청 대상 정보가 올바르지 않습니다.'
        );
    }

    $name =
        isset($data['name'])
        ? (string)$data['name']
        : '';

    $school =
        isset($data['school'])
        ? (string)$data['school']
        : '';

    $phone =
        isset($data['phone'])
        ? class_share_normalize_phone(
            $data['phone']
        )
        : '';

    $password =
        isset($data['password'])
        ? (string)$data['password']
        : '';

    $phone_ciphertext =
        class_share_encrypt_phone(
            $phone
        );

    $phone_lookup_hash =
        class_share_phone_lookup_hash(
            $phone
        );

    $phone_last4 =
        class_share_phone_last4(
            $phone
        );

    $password_hash =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );

    if ($password_hash === false) {
        throw new RuntimeException(
            '신청 비밀번호를 안전하게 저장할 수 없습니다.'
        );
    }

    $application_code =
        class_share_application_code();

    $ip_address =
        class_share_application_ip_address();

    $connection_result =
        pdo_query(
            'SELECT 1 AS ready'
        );

    if (
        $connection_result === false ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    try {
        $dbh->beginTransaction();

        $event_rows =
            pdo_query(
                "
                SELECT
                    event.id,
                    event.school_id,
                    event.status,
                    event.application_mode,
                    event.application_start_at,
                    event.application_end_at,
                    event.privacy_policy_version

                FROM class_share_event AS event

                INNER JOIN class_share_school AS school
                    ON school.id = event.school_id
                   AND school.status = 'active'

                WHERE event.id = ?
                  AND event.school_id = ?

                LIMIT 1

                FOR UPDATE
                ",
                $event_id,
                $school_id
            );

        if (
            $event_rows === false ||
            !isset($event_rows[0])
        ) {
            throw new DomainException(
                '신청할 수 있는 행사를 찾을 수 없습니다.'
            );
        }

        $event =
            $event_rows[0];

        $class_rows =
            pdo_query(
                "
                SELECT
                    id,
                    event_id,
                    title,
                    application_deadline,
                    capacity,
                    status

                FROM class_share_class

                WHERE id = ?
                  AND event_id = ?

                LIMIT 1

                FOR UPDATE
                ",
                $class_id,
                $event_id
            );

        if (
            $class_rows === false ||
            !isset($class_rows[0])
        ) {
            throw new DomainException(
                '신청할 수 있는 프로그램을 찾을 수 없습니다.'
            );
        }

        $class_item =
            $class_rows[0];

        if (
            !class_share_application_program_is_open(
                $event,
                $class_item
            )
        ) {
            throw new DomainException(
                '현재 프로그램 신청 기간이 아닙니다.'
            );
        }

        $duplicate_rows =
            pdo_query(
                "
                SELECT
                    id

                FROM class_share_application

                WHERE event_id = ?
                  AND class_id = ?
                  AND application_scope = 'program'
                  AND phone_lookup_hash = ?
                  AND status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )

                LIMIT 1
                ",
                $event_id,
                $class_id,
                $phone_lookup_hash
            );

        if ($duplicate_rows === false) {
            throw new RuntimeException(
                '중복 신청 정보를 확인할 수 없습니다.'
            );
        }

        if (isset($duplicate_rows[0])) {
            throw new DomainException(
                '같은 연락처로 이미 신청한 프로그램입니다.'
            );
        }

        $count_rows =
            pdo_query(
                "
                SELECT
                    COUNT(*) AS active_count

                FROM class_share_application

                WHERE event_id = ?
                  AND class_id = ?
                  AND application_scope = 'program'
                  AND status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )
                ",
                $event_id,
                $class_id
            );

        if (
            $count_rows === false ||
            !isset($count_rows[0])
        ) {
            throw new RuntimeException(
                '프로그램 신청 인원을 확인할 수 없습니다.'
            );
        }

        $active_count =
            (int)$count_rows[0][
                'active_count'
            ];

        $capacity =
            (int)$class_item['capacity'];

        if ($active_count >= $capacity) {
            throw new DomainException(
                '프로그램 신청 정원이 마감되었습니다.'
            );
        }

        $application_id =
            pdo_query(
                "
                INSERT INTO class_share_application
                (
                    event_id,
                    class_id,
                    application_scope,
                    application_code,
                    applicant_name,
                    applicant_school,
                    phone_ciphertext,
                    phone_lookup_hash,
                    phone_last4,
                    password_hash,
                    status,
                    privacy_policy_version,
                    privacy_agreed_at,
                    cancelled_at,
                    processed_by,
                    admin_note,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,
                    'program',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'applied',
                    ?,
                    NOW(),
                    NULL,
                    NULL,
                    NULL,
                    NOW(),
                    NOW()
                )
                ",
                $event_id,
                $class_id,
                $application_code,
                $name,
                $school,
                $phone_ciphertext,
                $phone_lookup_hash,
                $phone_last4,
                $password_hash,
                (string)$event[
                    'privacy_policy_version'
                ]
            );

        if ($application_id === false) {
            throw new RuntimeException(
                '프로그램 신청을 저장할 수 없습니다.'
            );
        }

        $after_json =
            json_encode(
                array(
                    'event_id' =>
                        $event_id,

                    'class_id' =>
                        $class_id,

                    'application_scope' =>
                        'program',

                    'application_code' =>
                        $application_code,

                    'status' =>
                        'applied',

                    'privacy_policy_version' =>
                        (string)$event[
                            'privacy_policy_version'
                        ]
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        if ($after_json === false) {
            throw new RuntimeException(
                '신청 감사 자료를 만들 수 없습니다.'
            );
        }

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
                    NULL,
                    'applicant',
                    'application.create',
                    'application',
                    ?,
                    NULL,
                    ?,
                    ?,
                    NOW()
                )
                ",
                $school_id,
                (int)$application_id,
                $after_json,
                $ip_address
            );

        if ($audit_result === false) {
            throw new RuntimeException(
                '신청 감사 기록을 저장할 수 없습니다.'
            );
        }

        $dbh->commit();

        return array(
            'application_id' =>
                (int)$application_id,

            'application_code' =>
                $application_code,

            'class_id' =>
                $class_id,

            'status' =>
                'applied'
        );
    } catch (Throwable $e) {
        if (
            $dbh instanceof PDO &&
            $dbh->inTransaction()
        ) {
            $dbh->rollBack();
        }

        throw $e;
    }
}


function class_share_find_applications(
    $school_id,
    $event_id,
    $phone,
    $password
) {
    $school_id =
        (int)$school_id;

    $event_id =
        (int)$event_id;

    $password =
        (string)$password;

    if (
        $school_id < 1 ||
        $event_id < 1 ||
        $password === ''
    ) {
        throw new InvalidArgumentException(
            '신청 조회 정보가 올바르지 않습니다.'
        );
    }

    $normalized_phone =
        class_share_normalize_phone(
            $phone
        );

    $phone_lookup_hash =
        class_share_phone_lookup_hash(
            $normalized_phone
        );

    $rows =
        pdo_query(
            "
            SELECT
                application.id,
                application.application_code,
                application.application_scope,
                application.participation_option_id,
                participation.name AS participation_name,
                application.class_id,
                application.applicant_name,
                application.applicant_school,
                application.phone_last4,
                application.password_hash,
                application.status,
                application.created_at,
                application.cancelled_at,

                event.title AS event_title,
                class_item.title AS program_title

            FROM class_share_application AS application

            INNER JOIN class_share_event AS event
                ON event.id = application.event_id

            LEFT JOIN class_share_class AS class_item
                ON class_item.id =
                   application.class_id

            LEFT JOIN class_share_participation_option AS participation
                ON participation.id = application.participation_option_id
               AND participation.event_id = application.event_id

            WHERE event.school_id = ?
              AND application.event_id = ?
              AND application.phone_lookup_hash = ?

            ORDER BY
                application.created_at DESC,
                application.id DESC
            ",
            $school_id,
            $event_id,
            $phone_lookup_hash
        );

    if ($rows === false) {
        throw new RuntimeException(
            '신청 내역을 조회할 수 없습니다.'
        );
    }

    if (count($rows) === 0) {
        $dummy_hash =
            password_hash(
                'class-share-invalid-password',
                PASSWORD_DEFAULT
            );

        if ($dummy_hash !== false) {
            password_verify(
                $password,
                $dummy_hash
            );
        }

        return array();
    }

    $applications =
        array();

    foreach ($rows as $row) {
        if (
            !password_verify(
                $password,
                (string)$row['password_hash']
            )
        ) {
            continue;
        }

        $applications[] =
            array(
                'id' =>
                    (int)$row['id'],

                'application_code' =>
                    (string)$row[
                        'application_code'
                    ],

                'application_scope' =>
                    (string)$row[
                        'application_scope'
                    ],

                'participation_name' => $row['participation_name'] === null
                    ? '미구분' : (string)$row['participation_name'],

                'class_id' =>
                    $row['class_id'] === null
                    ? null
                    : (int)$row['class_id'],

                'applicant_name' =>
                    (string)$row[
                        'applicant_name'
                    ],

                'applicant_school' =>
                    (string)$row[
                        'applicant_school'
                    ],

                'phone_last4' =>
                    (string)$row['phone_last4'],

                'status' =>
                    (string)$row['status'],

                'event_title' =>
                    (string)$row['event_title'],

                'program_title' =>
                    $row['program_title'] === null
                    ? ''
                    : (string)$row[
                        'program_title'
                    ],

                'created_at' =>
                    (string)$row['created_at'],

                'cancelled_at' =>
                    $row['cancelled_at'] === null
                    ? null
                    : (string)$row[
                        'cancelled_at'
                    ]
            );
    }

    return $applications;
}


function class_share_cancel_application(
    $school_id,
    $event_id,
    $application_code,
    $phone,
    $password
) {
    global $dbh;

    $school_id =
        (int)$school_id;

    $event_id =
        (int)$event_id;

    $application_code =
        strtolower(
            trim(
                (string)$application_code
            )
        );

    $password =
        (string)$password;

    if (
        $school_id < 1 ||
        $event_id < 1 ||
        !preg_match(
            '/^[a-f0-9]{32}$/D',
            $application_code
        ) ||
        $password === ''
    ) {
        throw new InvalidArgumentException(
            '신청 취소 정보가 올바르지 않습니다.'
        );
    }

    $normalized_phone =
        class_share_normalize_phone(
            $phone
        );

    $phone_lookup_hash =
        class_share_phone_lookup_hash(
            $normalized_phone
        );

    $ip_address =
        class_share_application_ip_address();

    $connection_result =
        pdo_query(
            'SELECT 1 AS ready'
        );

    if (
        $connection_result === false ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException(
            'DB 연결이 준비되지 않았습니다.'
        );
    }

    try {
        $dbh->beginTransaction();

        $rows =
            pdo_query(
                "
                SELECT
                    application.id,
                    application.event_id,
                    application.application_scope,
                    application.phone_lookup_hash,
                    application.password_hash,
                    application.status

                FROM class_share_application AS application

                INNER JOIN class_share_event AS event
                    ON event.id =
                       application.event_id

                WHERE event.school_id = ?
                  AND application.event_id = ?
                  AND application.application_code = ?

                LIMIT 1

                FOR UPDATE
                ",
                $school_id,
                $event_id,
                $application_code
            );

        if ($rows === false) {
            throw new RuntimeException(
                '신청 정보를 확인할 수 없습니다.'
            );
        }

        if (!isset($rows[0])) {
            $dummy_hash =
                password_hash(
                    'class-share-invalid-password',
                    PASSWORD_DEFAULT
                );

            if ($dummy_hash !== false) {
                password_verify(
                    $password,
                    $dummy_hash
                );
            }

            throw new DomainException(
                '신청 정보를 확인할 수 없습니다.'
            );
        }

        $application =
            $rows[0];

        $phone_matches =
            hash_equals(
                (string)$application[
                    'phone_lookup_hash'
                ],
                $phone_lookup_hash
            );

        $password_matches =
            password_verify(
                $password,
                (string)$application[
                    'password_hash'
                ]
            );

        if (
            !$phone_matches ||
            !$password_matches
        ) {
            throw new DomainException(
                '신청 정보를 확인할 수 없습니다.'
            );
        }

        $current_status =
            (string)$application['status'];

        if ($current_status === 'cancelled') {
            throw new DomainException(
                '이미 취소된 신청입니다.'
            );
        }

        if (
            !in_array(
                $current_status,
                class_share_application_active_statuses(),
                true
            )
        ) {
            throw new DomainException(
                '현재 신청 상태에서는 취소할 수 없습니다.'
            );
        }

        $update_result =
            pdo_query(
                "
                UPDATE class_share_application

                SET
                    status = 'cancelled',
                    cancelled_at = NOW(),
                    updated_at = NOW()

                WHERE id = ?
                  AND status IN (
                      'applied',
                      'approved',
                      'waiting'
                  )
                ",
                (int)$application['id']
            );

        if (
            $update_result === false ||
            (int)$update_result !== 1
        ) {
            throw new RuntimeException(
                '신청을 취소할 수 없습니다.'
            );
        }

        $before_json =
            json_encode(
                array(
                    'event_id' =>
                        $event_id,

                    'application_scope' =>
                        (string)$application[
                            'application_scope'
                        ],

                    'status' =>
                        $current_status
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        $after_json =
            json_encode(
                array(
                    'event_id' =>
                        $event_id,

                    'application_scope' =>
                        (string)$application[
                            'application_scope'
                        ],

                    'status' =>
                        'cancelled'
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        if (
            $before_json === false ||
            $after_json === false
        ) {
            throw new RuntimeException(
                '취소 감사 자료를 만들 수 없습니다.'
            );
        }

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
                    NULL,
                    'applicant',
                    'application.cancel',
                    'application',
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
                ",
                $school_id,
                (int)$application['id'],
                $before_json,
                $after_json,
                $ip_address
            );

        if ($audit_result === false) {
            throw new RuntimeException(
                '취소 감사 기록을 저장할 수 없습니다.'
            );
        }

        $dbh->commit();

        return array(
            'application_id' =>
                (int)$application['id'],

            'application_code' =>
                $application_code,

            'status' =>
                'cancelled'
        );
    } catch (Throwable $e) {
        if (
            $dbh instanceof PDO &&
            $dbh->inTransaction()
        ) {
            $dbh->rollBack();
        }

        throw $e;
    }
}
