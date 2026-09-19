<?php

header('Cache-Control: private, no-store');
header('Content-Type: text/plain; charset=utf-8');

require_once(__DIR__ . '/admin-init.php');
require_once(__DIR__ . '/../include/const.inc.php');


$fail = function ($status, $message) {
    http_response_code($status);
    echo $message;
    exit;
};


if (
    !oj_can_manage_admin_contests()
) {
    $fail(
        403,
        '대회를 생성할 권한이 없습니다.'
    );
}


if (
    !isset($_SERVER['REQUEST_METHOD']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Allow: POST');

    $fail(
        405,
        'POST 요청만 허용됩니다.'
    );
}


require_once(
    __DIR__ .
    '/../include/check_post_key.php'
);


// ============================================================
// 입력값 함수
// ============================================================

$post_string = function (
    $name,
    $required = false,
    $trim = true
) use ($fail) {
    if (!isset($_POST[$name])) {
        if ($required) {
            $fail(
                422,
                '필수 입력값이 누락되었습니다: ' .
                    $name
            );
        }

        return '';
    }

    if (!is_scalar($_POST[$name])) {
        $fail(
            422,
            '입력값의 형식이 올바르지 않습니다: ' .
                $name
        );
    }

    $value =
        (string)$_POST[$name];

    if ($trim) {
        $value = trim($value);
    }

    if (
        $required &&
        trim($value) === ''
    ) {
        $fail(
            422,
            '필수 입력값을 입력하세요: ' .
                $name
        );
    }

    return $value;
};


$text_length = function ($value) {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
};


$binary_option = function (
    $name,
    $default
) use ($post_string, $fail) {
    $value =
        $post_string(
            $name,
            false
        );

    if ($value === '') {
        return intval($default);
    }

    if (
        $value !== '0' &&
        $value !== '1'
    ) {
        $fail(
            422,
            '설정값이 올바르지 않습니다: ' .
                $name
        );
    }

    return intval($value);
};


$read_datetime = function (
    $date_name,
    $hour_name,
    $minute_name
) use ($post_string, $fail) {
    $date =
        $post_string(
            $date_name,
            true
        );

    $hour =
        $post_string(
            $hour_name,
            true
        );

    $minute =
        $post_string(
            $minute_name,
            true
        );

    if (
        preg_match(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',
            $date
        ) !== 1 ||
        preg_match(
            '/^[0-9]{1,2}$/D',
            $hour
        ) !== 1 ||
        preg_match(
            '/^[0-9]{1,2}$/D',
            $minute
        ) !== 1
    ) {
        $fail(
            422,
            '대회 날짜 또는 시간이 올바르지 않습니다.'
        );
    }

    $hour_number = intval($hour);
    $minute_number = intval($minute);

    if (
        $hour_number < 0 ||
        $hour_number > 23 ||
        $minute_number < 0 ||
        $minute_number > 59
    ) {
        $fail(
            422,
            '대회 시간이 올바르지 않습니다.'
        );
    }

    $datetime_text =
        $date .
        ' ' .
        sprintf('%02d', $hour_number) .
        ':' .
        sprintf('%02d', $minute_number) .
        ':00';

    $datetime =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $datetime_text
        );

    $datetime_errors =
        DateTimeImmutable::getLastErrors();

    if (
        $datetime === false ||
        (
            $datetime_errors !== false &&
            (
                $datetime_errors['warning_count'] > 0 ||
                $datetime_errors['error_count'] > 0
            )
        ) ||
        $datetime->format('Y-m-d H:i:s') !==
        $datetime_text
    ) {
        $fail(
            422,
            '존재하지 않는 날짜가 입력되었습니다.'
        );
    }

    return $datetime;
};


// ============================================================
// 기본 정보
// ============================================================

$title =
    $post_string(
        'title',
        true
    );

if (
    preg_match(
        '//u',
        $title
    ) !== 1
) {
    $fail(
        422,
        '대회 제목의 문자 인코딩이 올바르지 않습니다.'
    );
}


if (
    preg_match(
        '/[\x00-\x1F\x7F]/u',
        $title
    ) === 1
) {
    $fail(
        422,
        '대회 제목에 사용할 수 없는 제어문자가 포함되어 있습니다.'
    );
}

if ($text_length($title) > 255) {
    $fail(
        422,
        '대회 제목은 255자 이하로 입력하세요.'
    );
}

$description =
    $post_string(
        'description',
        false,
        false
    );

if (
    preg_match(
        '//u',
        $description
    ) !== 1
) {
    $fail(
        422,
        '대회 설명의 문자 인코딩이 올바르지 않습니다.'
    );
}


if (
    strpos(
        $description,
        "\0"
    ) !== false
) {
    $fail(
        422,
        '대회 설명에 사용할 수 없는 문자가 포함되어 있습니다.'
    );
}

if (strlen($description) > 65535) {
    $fail(
        422,
        '대회 설명이 너무 깁니다.'
    );
}

$start_datetime =
    $read_datetime(
        'startdate',
        'shour',
        'sminute'
    );

$end_datetime =
    $read_datetime(
        'enddate',
        'ehour',
        'eminute'
    );

if ($end_datetime <= $start_datetime) {
    $fail(
        422,
        '종료 시각은 시작 시각보다 늦어야 합니다.'
    );
}

$starttime =
    $start_datetime->format(
        'Y-m-d H:i:s'
    );

$endtime =
    $end_datetime->format(
        'Y-m-d H:i:s'
    );

$private =
    $binary_option(
        'private',
        0
    );

$codevisible =
    $binary_option(
        'codevisible',
        0
    );

$exam_mode =
    $binary_option(
        'exam_mode',
        0
    );

$allow_copy =
    $binary_option(
        'allow_copy',
        1
    );

$password =
    $post_string(
        'password',
        false
    );

if (
    preg_match(
        '//u',
        $password
    ) !== 1
) {
    $fail(
        422,
        '대회 비밀번호의 문자 인코딩이 올바르지 않습니다.'
    );
}

if ($private === 1) {
    $password = '';
}

if ($text_length($password) > 16) {
    $fail(
        422,
        '대회 비밀번호는 16자 이하로 입력하세요.'
    );
}

$user_id =
    isset(
        $_SESSION[$OJ_NAME . '_user_id']
    )
    ? $_SESSION[$OJ_NAME . '_user_id']
    : '';

$user_id =
    oj_normalize_privilege_user_id(
        $user_id
    );

if ($user_id === false) {
    $fail(
        403,
        '로그인 정보가 올바르지 않습니다.'
    );
}


// ============================================================
// 제출 가능 언어
// ============================================================

$lang_count =
    count($language_ext);

if (
    $lang_count <= 0 ||
    $lang_count > 31
) {
    $fail(
        500,
        '제출 언어 설정을 처리할 수 없습니다.'
    );
}

if (
    !isset($_POST['lang']) ||
    !is_array($_POST['lang'])
) {
    $fail(
        422,
        '제출 가능 언어를 하나 이상 선택하세요.'
    );
}

$selected_language_map =
    array();

foreach (
    $_POST['lang']
    as $language_id_raw
) {
    if (
        !is_scalar($language_id_raw)
    ) {
        $fail(
            422,
            '제출 언어 정보가 올바르지 않습니다.'
        );
    }

    $language_id_raw =
        trim(
            (string)$language_id_raw
        );

    if (
        preg_match(
            '/^[0-9]+$/D',
            $language_id_raw
        ) !== 1
    ) {
        $fail(
            422,
            '제출 언어 정보가 올바르지 않습니다.'
        );
    }

    $language_id =
        intval($language_id_raw);

    if (
        $language_id < 0 ||
        $language_id >= $lang_count
    ) {
        $fail(
            422,
            '존재하지 않는 제출 언어입니다.'
        );
    }

    $selected_language_map[$language_id] = true;
}

if (count($selected_language_map) === 0) {
    $fail(
        422,
        '제출 가능 언어를 하나 이상 선택하세요.'
    );
}

$allowed_language_mask = 0;

foreach (
    array_keys($selected_language_map)
    as $language_id
) {
    $allowed_language_mask |=
        (1 << $language_id);
}

$all_language_mask =
    (1 << $lang_count) - 1;

$langmask =
    $all_language_mask &
    (~$allowed_language_mask);


// ============================================================
// 문제 번호
// ============================================================

$problem_list_text =
    $post_string(
        'cproblem',
        false
    );

$problem_ids =
    array();

$problem_id_map =
    array();


if ($problem_list_text !== '') {
    $problem_pieces =
        explode(
            ',',
            $problem_list_text
        );


    foreach (
        $problem_pieces
        as $problem_id_raw
    ) {
        $problem_id_raw =
            trim(
                (string)$problem_id_raw
            );


        if (
            preg_match(
                '/^[1-9][0-9]*$/D',
                $problem_id_raw
            ) !== 1
        ) {
            $fail(
                422,
                '문제 번호는 양의 정수로 입력하세요.'
            );
        }


        // intval() 실행 전에 DB INT 범위를 확인한다.
        if (
            strlen($problem_id_raw) > 10 ||
            (
                strlen($problem_id_raw) === 10 &&
                strcmp(
                    $problem_id_raw,
                    '2147483647'
                ) > 0
            )
        ) {
            $fail(
                422,
                '문제 번호가 허용 범위를 벗어났습니다.'
            );
        }


        $problem_id =
            intval(
                $problem_id_raw
            );


        if (
            isset(
                $problem_id_map[$problem_id]
            )
        ) {
            $fail(
                422,
                '중복된 문제 번호가 있습니다: ' .
                    $problem_id
            );
        }


        $problem_id_map[$problem_id] =
            true;

        $problem_ids[] =
            $problem_id;
    }
}


if (
    count($problem_ids) > 128
) {
    $fail(
        422,
        '한 대회에는 문제를 최대 128개까지 등록할 수 있습니다.'
    );
}


// ============================================================
// 문제 점수 사전 검증
// ============================================================

if (
    array_key_exists(
        'cpoint',
        $_POST
    ) &&
    !is_array(
        $_POST['cpoint']
    )
) {
    $fail(
        422,
        '문제 점수 정보가 올바르지 않습니다.'
    );
}


$cpoints =
    isset($_POST['cpoint'])
    ? $_POST['cpoint']
    : array();

$problem_scores =
    array();


foreach (
    $problem_ids
    as $problem_id
) {
    // 점수 항목이 없거나 빈 값이면 기존 정책에 따라
    // 기본 점수 100을 사용한다.
    if (
        !array_key_exists(
            $problem_id,
            $cpoints
        ) ||
        $cpoints[$problem_id] === ''
    ) {
        $problem_scores[$problem_id] =
            100;

        continue;
    }


    if (
        !is_scalar(
            $cpoints[$problem_id]
        )
    ) {
        $fail(
            422,
            '문제 ' .
                $problem_id .
                '번의 점수 정보가 올바르지 않습니다.'
        );
    }


    $score_text =
        trim(
            (string)$cpoints[$problem_id]
        );


    if (
        preg_match(
            '/^(0|[1-9][0-9]*)$/D',
            $score_text
        ) !== 1
    ) {
        $fail(
            422,
            '문제 ' .
                $problem_id .
                '번의 점수는 0 이상의 정수로 입력하세요.'
        );
    }


    // intval() 실행 전에 DB INT 범위를 확인한다.
    if (
        strlen($score_text) > 10 ||
        (
            strlen($score_text) === 10 &&
            strcmp(
                $score_text,
                '2147483647'
            ) > 0
        )
    ) {
        $fail(
            422,
            '문제 ' .
                $problem_id .
                '번의 점수가 허용 범위를 벗어났습니다.'
        );
    }


    $problem_scores[$problem_id] =
        intval(
            $score_text
        );
}

// ============================================================
// 문제 존재 여부·재사용 권한
// ============================================================

if (count($problem_ids) > 0) {
    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($problem_ids),
                '?'
            )
        );

    $problem_parameters =
        array_merge(
            array($user_id),
            $problem_ids
        );

    $problem_rows =
        pdo_query(
            "SELECT
                p.problem_id,
                p.defunct,
                p.allow_reuse,
                EXISTS (
                    SELECT 1
                    FROM privilege AS pr
                    WHERE pr.user_id=?
                      AND pr.rightstr=
                          CONCAT('p', p.problem_id)
                      AND pr.defunct='N'
                ) AS is_owner
             FROM problem AS p
             WHERE p.problem_id IN (
                $placeholders
             )",
            ...$problem_parameters
        );

    if ($problem_rows === false) {
        $fail(
            500,
            '문제 정보를 확인하지 못했습니다.'
        );
    }

    $problem_row_map =
        array();

    foreach ($problem_rows as $problem) {
        $problem_row_map[intval($problem['problem_id'])] = $problem;
    }

    $is_admin =
        oj_is_admin();

    foreach ($problem_ids as $problem_id) {
        if (
            !isset(
                $problem_row_map[$problem_id]
            )
        ) {
            $fail(
                422,
                '존재하지 않는 문제입니다: ' .
                    $problem_id
            );
        }

        $problem =
            $problem_row_map[$problem_id];

        $is_owner =
            intval($problem['is_owner']) === 1;

        $is_public =
            strtoupper(
                trim(
                    (string)$problem['defunct']
                )
            ) === 'N';

        $can_reuse =
            intval(
                $problem['allow_reuse']
            ) === 1;

        if (
            !$is_admin &&
            !$is_owner &&
            !(
                $is_public &&
                $can_reuse
            )
        ) {
            $fail(
                403,
                '문제 ' .
                    $problem_id .
                    '번을 대회에 사용할 권한이 없습니다.'
            );
        }
    }
}
// ============================================================
// 참가자 목록 사전 검증
//
// 이 단계에서는 권한을 변경하지 않는다.
// 정규화·중복 제거·사용자 존재 확인만 수행한다.
// ============================================================

if (
    array_key_exists(
        'ulist',
        $_POST
    )
) {
    if (
        !is_scalar(
            $_POST['ulist']
        )
    ) {
        $fail(
            422,
            '참가자 목록 형식이 올바르지 않습니다.'
        );
    }

    $participant_text =
        (string)$_POST['ulist'];
} else {
    $participant_text = '';
}


// 비정상적으로 큰 입력이 문자열 분리와 DB 조회에
// 과도한 자원을 사용하지 않도록 제한한다.
if (
    strlen($participant_text) > 200000
) {
    $fail(
        422,
        '참가자 목록이 너무 큽니다.'
    );
}


$participant_ids =
    array();

$participant_id_map =
    array();


if (
    trim($participant_text) !== ''
) {
    $participant_lines =
        preg_split(
            '/\r\n|\r|\n/',
            $participant_text
        );

    if ($participant_lines === false) {
        $fail(
            500,
            '참가자 목록을 처리하지 못했습니다.'
        );
    }


    foreach (
        $participant_lines
        as $line_index => $participant_id_raw
    ) {
        $participant_id_raw =
            trim(
                (string)$participant_id_raw
            );


        // 참가자 목록 중간의 빈 줄은 무시한다.
        if ($participant_id_raw === '') {
            continue;
        }


        if (
            preg_match(
                '//u',
                $participant_id_raw
            ) !== 1
        ) {
            $fail(
                422,
                '참가자 목록의 ' .
                    ($line_index + 1) .
                    '번째 줄 문자 인코딩이 올바르지 않습니다.'
            );
        }


        if (
            preg_match(
                '/[\x00-\x1F\x7F]/u',
                $participant_id_raw
            ) === 1
        ) {
            $fail(
                422,
                '참가자 목록의 ' .
                    ($line_index + 1) .
                    '번째 줄에 사용할 수 없는 문자가 있습니다.'
            );
        }


        $participant_id =
            oj_normalize_privilege_user_id(
                $participant_id_raw
            );


        if ($participant_id === false) {
            $fail(
                422,
                '참가자 목록의 ' .
                    ($line_index + 1) .
                    '번째 사용자 ID가 올바르지 않습니다.'
            );
        }


        $participant_lookup_key =
            function_exists('mb_strtolower')
            ? mb_strtolower(
                $participant_id,
                'UTF-8'
            )
            : strtolower(
                $participant_id
            );

        $participant_lookup_key =
            'user:' .
            $participant_lookup_key;


        if (
            isset(
                $participant_id_map[$participant_lookup_key]
            )
        ) {
            continue;
        }


        $participant_id_map[$participant_lookup_key] = true;

        $participant_ids[] =
            $participant_id;


        if (
            count($participant_ids) > 2000
        ) {
            $fail(
                422,
                '참가자는 최대 2,000명까지 등록할 수 있습니다.'
            );
        }
    }
}


// ============================================================
// 참가자 존재 여부 일괄 확인
// ============================================================

$existing_participant_map =
    array();


if (
    count($participant_ids) > 0
) {
    // 한 번에 지나치게 많은 placeholder를 만들지 않도록
    // 500명 단위로 조회한다.
    $participant_chunks =
        array_chunk(
            $participant_ids,
            500
        );


    foreach (
        $participant_chunks
        as $participant_chunk
    ) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($participant_chunk),
                    '?'
                )
            );


        $participant_query_args =
            array_merge(
                array(
                    "SELECT user_id
                     FROM users
                     WHERE user_id IN (" .
                        $placeholders .
                        ")"
                ),
                $participant_chunk
            );


        $participant_rows =
            call_user_func_array(
                'pdo_query',
                $participant_query_args
            );


        if ($participant_rows === false) {
            $fail(
                500,
                '참가자 계정을 확인하지 못했습니다.'
            );
        }


        foreach (
            $participant_rows
            as $participant_row
        ) {
            if (
                !isset(
                    $participant_row['user_id']
                )
            ) {
                $fail(
                    500,
                    '참가자 계정 조회 결과가 올바르지 않습니다.'
                );
            }


            $existing_user_id =
                (string)$participant_row['user_id'];


            $existing_lookup_key =
                function_exists('mb_strtolower')
                ? mb_strtolower(
                    $existing_user_id,
                    'UTF-8'
                )
                : strtolower(
                    $existing_user_id
                );

            $existing_lookup_key =
                'user:' .
                $existing_lookup_key;


            // DB에 저장된 실제 사용자 ID 표기를 보존한다.
            $existing_participant_map[$existing_lookup_key] =
                $existing_user_id;
        }
    }
}


// ============================================================
// 존재하지 않는 참가자 확인과 저장용 ID 구성
// ============================================================

$missing_participant_ids =
    array();

$validated_participant_ids =
    array();


foreach (
    $participant_ids
    as $participant_id
) {
    $participant_lookup_key =
        function_exists('mb_strtolower')
        ? mb_strtolower(
            $participant_id,
            'UTF-8'
        )
        : strtolower(
            $participant_id
        );

    $participant_lookup_key =
        'user:' .
        $participant_lookup_key;


    if (
        !array_key_exists(
            $participant_lookup_key,
            $existing_participant_map
        )
    ) {
        $missing_participant_ids[] =
            $participant_id;

        continue;
    }


    $validated_participant_ids[] =
        $existing_participant_map[$participant_lookup_key];
}


if (
    count($missing_participant_ids) > 0
) {
    $missing_preview =
        array_slice(
            $missing_participant_ids,
            0,
            10
        );


    $missing_message =
        '존재하지 않는 참가자 ID가 있습니다: ' .
        implode(
            ', ',
            $missing_preview
        );


    if (
        count($missing_participant_ids) > 10
    ) {
        $missing_message .=
            ' 외 ' .
            (
                count($missing_participant_ids) -
                10
            ) .
            '명';
    }


    $fail(
        422,
        $missing_message
    );
}


// 이후 권한 저장에는 DB에서 확인한 실제 사용자 ID를 사용한다.
$participant_ids =
    $validated_participant_ids;

// ============================================================
// 트랜잭션 준비
// ============================================================

$connection_check =
    pdo_query(
        'SELECT 1 AS connection_ok'
    );

if ($connection_check === false) {
    $fail(
        500,
        '데이터베이스에 연결할 수 없습니다.'
    );
}

if (
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    $fail(
        500,
        '데이터베이스 연결 상태가 올바르지 않습니다.'
    );
}

if (
    $dbh->inTransaction()
) {
    $fail(
        500,
        '이미 진행 중인 데이터베이스 작업이 있습니다.'
    );
}

// ============================================================
// 대회·문제·권한 저장
// ============================================================

$cid = 0;

$transaction_started =
    false;


try {
    if (
        !$dbh->beginTransaction()
    ) {
        throw new RuntimeException(
            '트랜잭션을 시작하지 못했습니다.'
        );
    }

    $transaction_started =
        true;


    $cid_raw =
        pdo_query(
            "INSERT INTO contest (
                title,
                start_time,
                end_time,
                codevisible,
                private,
                langmask,
                description,
                password,
                user_id,
                exam_mode,
                allow_copy
             )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $title,
            $starttime,
            $endtime,
            $codevisible,
            $private,
            $langmask,
            $description,
            $password,
            $user_id,
            $exam_mode,
            $allow_copy
        );

    if (
        $cid_raw === false ||
        intval($cid_raw) <= 0
    ) {
        throw new RuntimeException(
            '대회 정보를 저장하지 못했습니다.'
        );
    }

    $cid =
        intval($cid_raw);

    foreach (
        $problem_ids
        as $problem_number => $problem_id
    ) {
        $inserted =
            pdo_query(
                "INSERT INTO contest_problem (
                    contest_id,
                    problem_id,
                    num,
                    score
                 )
                 VALUES (?, ?, ?, ?)",
                $cid,
                $problem_id,
                $problem_number,
                $problem_scores[$problem_id]
            );

        if ($inserted === false) {
            throw new RuntimeException(
                '대회 문제를 저장하지 못했습니다.'
            );
        }
    }

    if (
        !oj_grant_contest_manager_right(
            $user_id,
            $cid
        )
    ) {
        throw new RuntimeException(
            '대회 관리자 권한을 저장하지 못했습니다.'
        );
    }

    foreach (
        $participant_ids
        as $participant_id
    ) {
        if (
            !oj_grant_contest_participant_right(
                $participant_id,
                $cid
            )
        ) {
            throw new RuntimeException(
                '참가자 권한을 저장하지 못했습니다: ' .
                    $participant_id
            );
        }
    }


    // InnoDB 변경 확정
    if (
        !$dbh->commit()
    ) {
        throw new RuntimeException(
            '대회 생성 내용을 확정하지 못했습니다.'
        );
    }

    $transaction_started =
        false;
} catch (Throwable $exception) {

    // InnoDB 변경 롤백
    if ($transaction_started) {
        try {
            if (
                $dbh->inTransaction()
            ) {
                if (
                    !$dbh->rollBack()
                ) {
                    error_log(
                        '[contest_create] InnoDB 롤백 상태를 확인하지 못함: cid=' .
                            $cid
                    );
                }
            } else {
                error_log(
                    '[contest_create] 롤백할 트랜잭션이 없음: cid=' .
                        $cid
                );
            }
        } catch (Throwable $rollback_exception) {
            error_log(
                '[contest_create] InnoDB 롤백 실패: cid=' .
                    $cid .
                    ', error=' .
                    $rollback_exception->getMessage()
            );
        }
    }


    error_log(
        '[contest_create] cid=' .
            $cid .
            ' error=' .
            $exception->getMessage()
    );


    $fail(
        500,
        '대회를 저장하지 못했습니다.'
    );
}

// 트랜잭션 성공 후 현재 세션에 관리자 권한 반영
$_SESSION[$OJ_NAME . '_m' . $cid] = true;


header(
    'Location: contest_list.php',
    true,
    303
);

exit;
