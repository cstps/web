<?php

header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);

header(
    'Content-Type: text/plain; charset=utf-8'
);


require_once(
    __DIR__ . '/admin-init.php'
);

require_once(
    __DIR__ . '/../include/const.inc.php'
);


$fail = function (
    $status,
    $message
) {
    http_response_code($status);
    echo $message;
    exit;
};


// ============================================================
// POST 요청 확인
// ============================================================

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


// ============================================================
// 대회 번호 확인
// ============================================================

$cid_raw =
    isset($_POST['cid'])
    ? $_POST['cid']
    : null;

if (
    !is_scalar($cid_raw) ||
    preg_match(
        '/^[1-9][0-9]*$/D',
        (string)$cid_raw
    ) !== 1
) {
    $fail(
        422,
        '대회 번호가 올바르지 않습니다.'
    );
}

$cid =
    intval($cid_raw);


// ============================================================
// 대회 존재 여부 확인
// ============================================================

$contest_rows =
    pdo_query(
        "SELECT *
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );

if ($contest_rows === false) {
    $fail(
        500,
        '대회 정보를 확인하지 못했습니다.'
    );
}

if (
    count($contest_rows) === 0 ||
    !isset($contest_rows[0]['contest_id'])
) {
    $fail(
        404,
        '존재하지 않는 대회입니다.'
    );
}

$current_contest =
    $contest_rows[0];


// ============================================================
// 수정 권한 확인
// ============================================================

if (
    !oj_can_manage_contest($cid)
) {
    $fail(
        403,
        '이 대회를 수정할 권한이 없습니다.'
    );
}


// ============================================================
// CSRF 확인
// ============================================================

require_once(
    __DIR__ .
    '/../include/check_post_key.php'
);


$user_id =
    isset($_SESSION[$OJ_NAME . '_user_id'])
    ? (string)$_SESSION[$OJ_NAME . '_user_id']
    : '';

if ($user_id === '') {
    $fail(
        401,
        '로그인이 필요합니다.'
    );
}

$is_admin =
    oj_is_admin();

// ============================================================
// 1. 입력값 사전 검증
//
// 이 구간이 끝나기 전에는 DB를 변경하지 않는다.
// 이후 저장 구간에서는 $_POST를 다시 직접 참조하지 않는다.
// ============================================================


// ========================================================
// 1-1. 공통 입력값 조회 함수
// ========================================================

$get_post_scalar = function (
    $name,
    $label,
    $required = true,
    $default = ''
) use ($fail) {

    if (!array_key_exists($name, $_POST)) {

        if ($required) {
            $fail(
                422,
                $label . ' 항목이 누락되었습니다.'
            );
        }

        return $default;
    }

    if (!is_scalar($_POST[$name])) {
        $fail(
            422,
            $label . ' 값이 올바르지 않습니다.'
        );
    }

    return (string)$_POST[$name];
};


$get_post_binary = function (
    $name,
    $label,
    $default
) use ($fail) {

    if (!array_key_exists($name, $_POST)) {
        return $default;
    }

    if (!is_scalar($_POST[$name])) {
        $fail(
            422,
            $label . ' 값이 올바르지 않습니다.'
        );
    }

    $value =
        trim(
            (string)$_POST[$name]
        );

    if (
        $value !== '0' &&
        $value !== '1'
    ) {
        $fail(
            422,
            $label . ' 값은 0 또는 1이어야 합니다.'
        );
    }

    return intval($value);
};


$get_text_length = function ($value) {

    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
};


// ========================================================
// 1-2. 대회 제목
// ========================================================

$title =
    trim(
        $get_post_scalar(
            'title',
            '대회 제목'
        )
    );

if (
    preg_match('//u', $title) !== 1
) {
    $fail(
        422,
        '대회 제목의 문자 인코딩이 올바르지 않습니다.'
    );
}

if ($title === '') {
    $fail(
        422,
        '대회 제목을 입력하세요.'
    );
}

if (
    $get_text_length($title) > 255
) {
    $fail(
        422,
        '대회 제목은 255자 이하로 입력하세요.'
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


// ========================================================
// 1-3. 대회 설명
// ========================================================

$description =
    $get_post_scalar(
        'description',
        '대회 설명',
        false,
        ''
    );
if (
    preg_match('//u', $description) !== 1
) {
    $fail(
        422,
        '대회 설명의 문자 인코딩이 올바르지 않습니다.'
    );
}


if (
    strpos($description, "\0") !== false
) {
    $fail(
        422,
        '대회 설명에 사용할 수 없는 문자가 포함되어 있습니다.'
    );
}

// contest.description은 TEXT이므로 최대 65,535바이트다.
// HTML 정규화 후에도 저장 직전에 다시 확인한다.
if (
    strlen($description) > 65535
) {
    $fail(
        422,
        '대회 설명이 너무 깁니다.'
    );
}


// ========================================================
// 1-4. 시작·종료 날짜와 시각
// ========================================================

$startdate =
    trim(
        $get_post_scalar(
            'startdate',
            '시작 날짜'
        )
    );

$enddate =
    trim(
        $get_post_scalar(
            'enddate',
            '종료 날짜'
        )
    );

$shour_raw =
    trim(
        $get_post_scalar(
            'shour',
            '시작 시'
        )
    );

$sminute_raw =
    trim(
        $get_post_scalar(
            'sminute',
            '시작 분'
        )
    );

$ehour_raw =
    trim(
        $get_post_scalar(
            'ehour',
            '종료 시'
        )
    );

$eminute_raw =
    trim(
        $get_post_scalar(
            'eminute',
            '종료 분'
        )
    );


if (
    preg_match(
        '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',
        $startdate
    ) !== 1
) {
    $fail(
        422,
        '시작 날짜 형식이 올바르지 않습니다.'
    );
}

if (
    preg_match(
        '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',
        $enddate
    ) !== 1
) {
    $fail(
        422,
        '종료 날짜 형식이 올바르지 않습니다.'
    );
}


$time_values = array(
    array(
        'value' => $shour_raw,
        'label' => '시작 시',
        'max' => 23
    ),
    array(
        'value' => $sminute_raw,
        'label' => '시작 분',
        'max' => 59
    ),
    array(
        'value' => $ehour_raw,
        'label' => '종료 시',
        'max' => 23
    ),
    array(
        'value' => $eminute_raw,
        'label' => '종료 분',
        'max' => 59
    )
);

foreach ($time_values as $time_value) {

    if (
        preg_match(
            '/^[0-9]{1,2}$/D',
            $time_value['value']
        ) !== 1
    ) {
        $fail(
            422,
            $time_value['label'] .
                ' 값이 올바르지 않습니다.'
        );
    }

    $numeric_value =
        intval(
            $time_value['value']
        );

    if (
        $numeric_value < 0 ||
        $numeric_value > $time_value['max']
    ) {
        $fail(
            422,
            $time_value['label'] .
                ' 값이 허용 범위를 벗어났습니다.'
        );
    }
}


$start_input =
    $startdate . ' ' .
    sprintf('%02d', intval($shour_raw)) . ':' .
    sprintf('%02d', intval($sminute_raw));

$end_input =
    $enddate . ' ' .
    sprintf('%02d', intval($ehour_raw)) . ':' .
    sprintf('%02d', intval($eminute_raw));


$start_datetime =
    DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i',
        $start_input
    );

$start_errors =
    DateTimeImmutable::getLastErrors();

if (
    $start_datetime === false ||
    (
        $start_errors !== false &&
        (
            $start_errors['warning_count'] > 0 ||
            $start_errors['error_count'] > 0
        )
    ) ||
    $start_datetime->format('Y-m-d H:i') !== $start_input
) {
    $fail(
        422,
        '시작 날짜와 시각이 올바르지 않습니다.'
    );
}


$end_datetime =
    DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i',
        $end_input
    );

$end_errors =
    DateTimeImmutable::getLastErrors();

if (
    $end_datetime === false ||
    (
        $end_errors !== false &&
        (
            $end_errors['warning_count'] > 0 ||
            $end_errors['error_count'] > 0
        )
    ) ||
    $end_datetime->format('Y-m-d H:i') !== $end_input
) {
    $fail(
        422,
        '종료 날짜와 시각이 올바르지 않습니다.'
    );
}


if (
    $end_datetime <= $start_datetime
) {
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


// ========================================================
// 1-5. 대회 운영 설정
// ========================================================

$codevisible =
    $get_post_binary(
        'codevisible',
        '코드 공개 설정',
        0
    );

$private =
    $get_post_binary(
        'private',
        '대회 공개 설정',
        0
    );

$exam_mode =
    $get_post_binary(
        'exam_mode',
        '시험 모드 설정',
        0
    );

$allow_copy =
    $get_post_binary(
        'allow_copy',
        '대회 복사 설정',
        1
    );


// ========================================================
// 1-6. 비밀번호
// ========================================================

$password =
    trim(
        $get_post_scalar(
            'password',
            '대회 비밀번호',
            false,
            ''
        )
    );
if (
    preg_match('//u', $password) !== 1
) {
    $fail(
        422,
        '대회 비밀번호의 문자 인코딩이 올바르지 않습니다.'
    );
}


if (
    $get_text_length($password) > 16
) {
    $fail(
        422,
        '대회 비밀번호는 16자 이하로 입력하세요.'
    );
}

// 비공개 대회는 참가자 권한(c{cid})만 사용한다.
if ($private === 1) {
    $password = '';
}

// ========================================================
// 1-7. 제출 가능 언어 검증
//
// HUSTOJ langmask
// bit = 0 : 허용
// bit = 1 : 금지
// ========================================================

if (
    !isset($_POST['lang']) ||
    !is_array($_POST['lang'])
) {
    $fail(
        422,
        '제출 가능 언어를 하나 이상 선택하세요.'
    );
}

$lang =
    $_POST['lang'];

$lang_count =
    count($language_ext);

// contest.langmask가 signed INT이므로
// 안전하게 31개 이하의 언어 비트만 사용한다.
if (
    $lang_count <= 0 ||
    $lang_count > 31
) {
    $fail(
        500,
        '제출 언어 설정을 처리할 수 없습니다.'
    );
}

$selected_language_ids =
    array();

$selected_language_map =
    array();

foreach ($lang as $language_id_raw) {

    if (
        !is_scalar($language_id_raw)
    ) {
        $fail(
            422,
            '제출 언어 정보가 올바르지 않습니다.'
        );
    }

    $language_id_text =
        trim(
            (string)$language_id_raw
        );

    if (
        preg_match(
            '/^(0|[1-9][0-9]*)$/D',
            $language_id_text
        ) !== 1
    ) {
        $fail(
            422,
            '제출 언어 정보가 올바르지 않습니다.'
        );
    }

    $language_id =
        intval($language_id_text);

    if (
        $language_id < 0 ||
        $language_id >= $lang_count
    ) {
        $fail(
            422,
            '존재하지 않는 제출 언어입니다.'
        );
    }

    if (
        isset(
            $selected_language_map[$language_id]
        )
    ) {
        continue;
    }

    $selected_language_map[$language_id] = true;

    $selected_language_ids[] =
        $language_id;
}

if (
    count($selected_language_ids) === 0
) {
    $fail(
        422,
        '제출 가능 언어를 하나 이상 선택하세요.'
    );
}

$allowed_language_mask = 0;

foreach (
    $selected_language_ids
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

// ========================================================
// 1-8. 현재 Contest 문제 목록
//
// 이미 포함된 문제는 allow_reuse가 나중에 변경되어도
// 기존 사용 관계를 유지할 수 있다.
// ========================================================

$current_problem_rows =
    pdo_query(
        "SELECT
            problem_id,
            num
        FROM contest_problem
        WHERE contest_id = ?
        ORDER BY num ASC, problem_id ASC",
        $cid
    );

if ($current_problem_rows === false) {
    $fail(
        500,
        '현재 대회의 문제 목록을 확인하지 못했습니다.'
    );
}

$current_problem_map =
    array();

$original_problem_num_map =
    array();

foreach (
    $current_problem_rows
    as $current_problem
) {
    if (
        !isset($current_problem['problem_id'])
    ) {
        $fail(
            500,
            '현재 대회의 문제 정보가 올바르지 않습니다.'
        );
    }

    $current_problem_id =
        intval(
            $current_problem['problem_id']
        );

    if ($current_problem_id <= 0) {
        $fail(
            500,
            '현재 대회의 문제 번호가 올바르지 않습니다.'
        );
    }

    if (
        !isset(
            $current_problem['num']
        ) ||
        !is_scalar(
            $current_problem['num']
        )
    ) {
        $fail(
            500,
            '현재 대회의 문제 순서가 올바르지 않습니다.'
        );
    }

    $current_problem_num_text =
        trim(
            (string)$current_problem['num']
        );

    if (
        preg_match(
            '/^(0|[1-9][0-9]*)$/D',
            $current_problem_num_text
        ) !== 1
    ) {
        $fail(
            500,
            '현재 대회의 문제 순서가 올바르지 않습니다.'
        );
    }

    $current_problem_num =
        intval(
            $current_problem_num_text
        );

    if (
        $current_problem_num < 0 ||
        $current_problem_num > 127
    ) {
        $fail(
            500,
            '현재 대회의 문제 순서가 허용 범위를 벗어났습니다.'
        );
    }

    $current_problem_map[$current_problem_id] = true;

    $original_problem_num_map[$current_problem_id] = $current_problem_num;
}


// ========================================================
// 1-9. POST 문제 번호 검증
//
// cproblem에 전달된 순서를 A, B, C... 순서로 사용한다.
// solution.num이 signed TINYINT이므로 0~127만 저장한다.
// ========================================================

if (
    array_key_exists(
        'cproblem',
        $_POST
    )
) {
    if (
        !is_scalar(
            $_POST['cproblem']
        )
    ) {
        $fail(
            422,
            '문제 목록 형식이 올바르지 않습니다.'
        );
    }

    $plist =
        trim(
            (string)$_POST['cproblem']
        );
} else {
    $plist = '';
}

$problem_ids =
    array();

$problem_id_map =
    array();

if ($plist !== '') {

    $pieces =
        explode(
            ',',
            $plist
        );

    foreach ($pieces as $value) {

        $problem_id_text =
            trim(
                (string)$value
            );

        if (
            preg_match(
                '/^[1-9][0-9]*$/D',
                $problem_id_text
            ) !== 1
        ) {
            $fail(
                422,
                '문제 번호는 양의 정수로 입력하세요.'
            );
        }

        if (
            strlen($problem_id_text) > 10 ||
            (
                strlen($problem_id_text) === 10 &&
                strcmp(
                    $problem_id_text,
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
                $problem_id_text
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

        $problem_id_map[$problem_id] = true;

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


// ========================================================
// 1-10. 문제 점수 사전 검증
//
// 이후 DB 저장 구간에서는 $_POST가 아니라
// 정규화된 $problem_scores만 사용한다.
// ========================================================

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
    if (
        !array_key_exists(
            $problem_id,
            $cpoints
        ) ||
        $cpoints[$problem_id] === ''
    ) {
        $problem_scores[$problem_id] = 100;

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


// ========================================================
// 1-11. 문제 존재 여부와 재사용 권한 검증
//
// 현재 대회에 이미 포함된 문제:
// 이후 allow_reuse가 금지되어도 기존 관계 유지 가능
//
// 새로 추가하는 문제:
// 관리자·문제 관리자 또는 공개+재사용 허용 문제만 가능
// ========================================================

foreach (
    $problem_ids
    as $problem_id
) {
    $problem_rows =
        pdo_query(
            "SELECT
                    p.problem_id,
                    p.defunct,
                    p.allow_reuse,
    
                    EXISTS
                    (
                        SELECT 1
                        FROM privilege pr
                        WHERE pr.user_id = ?
                          AND pr.rightstr =
                              CONCAT(
                                  'p',
                                  p.problem_id
                              )
                          AND pr.defunct = 'N'
                    ) AS is_owner
    
                 FROM problem p
                 WHERE p.problem_id = ?
                 LIMIT 1",
            $user_id,
            $problem_id
        );

    if ($problem_rows === false) {
        $fail(
            500,
            '문제 정보를 확인하지 못했습니다: ' .
                $problem_id
        );
    }

    if (
        count($problem_rows) === 0 ||
        !isset(
            $problem_rows[0]['problem_id']
        )
    ) {
        $fail(
            422,
            '존재하지 않는 문제입니다: ' .
                $problem_id
        );
    }

    // 현재 대회에 이미 들어 있는 문제에는
    // 재사용 정책을 소급 적용하지 않는다.
    if (
        isset(
            $current_problem_map[$problem_id]
        )
    ) {
        continue;
    }

    $problem =
        $problem_rows[0];

    $is_owner =
        intval(
            $problem['is_owner']
        ) === 1;

    $is_public =
        isset($problem['defunct']) &&
        strtoupper(
            trim(
                (string)$problem['defunct']
            )
        ) === 'N';

    $problem_allow_reuse =
        isset($problem['allow_reuse']) &&
        intval(
            $problem['allow_reuse']
        ) === 1;

    $can_use_problem =
        $is_admin ||
        $is_owner ||
        (
            $is_public &&
            $problem_allow_reuse
        );

    if (!$can_use_problem) {

        if (!$problem_allow_reuse) {
            $fail(
                422,
                '문제 ' .
                    $problem_id .
                    '번은 문제 생성자가 다른 대회에서의 사용을 허용하지 않았습니다.'
            );
        }

        $fail(
            403,
            '문제 ' .
                $problem_id .
                '번을 이 대회에 추가할 권한이 없습니다.'
        );
    }
}

// ========================================================
// 1-12. 참가자 목록 사전 검증
//
// 이 단계에서는 권한을 변경하지 않는다.
// 정규화·중복 제거·사용자 존재 확인만 수행한다.
// ========================================================

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

    $ulist_raw =
        (string)$_POST['ulist'];
} else {
    $ulist_raw = '';
}

// 비정상적으로 큰 입력이 preg_split과 DB 조회에
// 과도한 자원을 사용하지 않도록 제한한다.
if (
    strlen($ulist_raw) > 200000
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
    trim($ulist_raw) !== ''
) {
    $participant_lines =
        preg_split(
            '/\r\n|\r|\n/',
            $ulist_raw
        );

    if ($participant_lines === false) {
        $fail(
            500,
            '참가자 목록을 처리하지 못했습니다.'
        );
    }

    foreach (
        $participant_lines
        as $line_index => $participant_raw
    ) {
        $participant_raw =
            trim(
                (string)$participant_raw
            );

        // 빈 줄은 참가자로 취급하지 않는다.
        if ($participant_raw === '') {
            continue;
        }

        if (
            preg_match(
                '//u',
                $participant_raw
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
                $participant_raw
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
                $participant_raw
            );

        if ($participant_id === false) {
            $fail(
                422,
                '참가자 목록의 ' .
                    ($line_index + 1) .
                    '번째 사용자 ID가 올바르지 않습니다.'
            );
        }

        // users.user_id가 대소문자를 구분하지 않는
        // utf8mb4_0900_ai_ci이므로 중복 판단도 이에 맞춘다.
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


// ========================================================
// 1-13. 참가자 존재 여부 일괄 확인
// ========================================================

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

            $existing_participant_map['user:' .
                $existing_lookup_key] = true;
        }
    }
}


// ========================================================
// 1-14. 존재하지 않는 참가자 확인
// ========================================================

$missing_participant_ids =
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
        !isset(
            $existing_participant_map[$participant_lookup_key]
        )
    ) {
        $missing_participant_ids[] =
            $participant_id;
    }
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

// ========================================================
// 1-15. 저장용 설명 정규화와 최종 길이 확인
// ========================================================

$description_for_storage =
    str_replace(
        "<p>",
        "",
        $description
    );

$description_for_storage =
    str_replace(
        "</p>",
        "<br />",
        $description_for_storage
    );

$description_for_storage =
    str_replace(
        ",",
        "&#44;",
        $description_for_storage
    );

if (
    strlen(
        $description_for_storage
    ) > 65535
) {
    $fail(
        422,
        '정리된 대회 설명이 너무 깁니다.'
    );
}


// ========================================================
// 1-16. 기존 참가자 권한 조회
//
// 활성·비활성·중복 행을 모두 확인하여
// 최종 참가자 목록과 비교한다.
// ========================================================

$participant_right =
    'c' . $cid;

$current_participant_rows =
    pdo_query(
        "SELECT
            user_id,
            valuestr,
            defunct
         FROM privilege
         WHERE rightstr = ?",
        $participant_right
    );

if (
    $current_participant_rows === false
) {
    $fail(
        500,
        '기존 참가자 권한을 확인하지 못했습니다.'
    );
}

$current_participant_map =
    array();

foreach (
    $current_participant_rows
    as $current_participant_row
) {
    if (
        !isset(
            $current_participant_row['user_id']
        )
    ) {
        $fail(
            500,
            '기존 참가자 권한 정보가 올바르지 않습니다.'
        );
    }

    $current_participant_id =
        oj_normalize_privilege_user_id(
            $current_participant_row['user_id']
        );

    if (
        $current_participant_id === false
    ) {
        $fail(
            500,
            '기존 참가자 권한의 사용자 ID가 올바르지 않습니다.'
        );
    }

    $current_lookup_key =
        function_exists('mb_strtolower')
        ? mb_strtolower(
            $current_participant_id,
            'UTF-8'
        )
        : strtolower(
            $current_participant_id
        );

    $current_lookup_key =
        'user:' .
        $current_lookup_key;

    if (
        !isset(
            $current_participant_map[$current_lookup_key]
        )
    ) {
        $current_participant_map[$current_lookup_key] = array(
            'user_id' =>
            $current_participant_id,

            'active_true' =>
            false
        );
    }

    $is_active_true =
        isset(
            $current_participant_row['valuestr'],
            $current_participant_row['defunct']
        ) &&
        (string)$current_participant_row['valuestr'] === 'true' &&
        strtoupper(
            trim(
                (string)$current_participant_row['defunct']
            )
        ) === 'N';

    if ($is_active_true) {
        $current_participant_map[$current_lookup_key]['active_true'] = true;
    }
}


// ========================================================
// 1-17. 참가자 권한 변경 대상 계산
// ========================================================

$participant_ids_to_grant =
    array();

$participant_ids_to_revoke =
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
        !isset(
            $current_participant_map[$participant_lookup_key]
        ) ||
        !$current_participant_map[$participant_lookup_key]['active_true']
    ) {
        $participant_ids_to_grant[] =
            $participant_id;
    }
}

foreach (
    $current_participant_map
    as $participant_lookup_key =>
    $current_participant
) {
    if (
        !isset(
            $participant_id_map[$participant_lookup_key]
        )
    ) {
        $participant_ids_to_revoke[] =
            $current_participant['user_id'];
    }
}


// ========================================================
// 1-18. PDO 연결 상태 확인
// ========================================================

global $dbh;

if (
    !($dbh instanceof PDO)
) {
    $fail(
        500,
        '데이터베이스 연결을 확인할 수 없습니다.'
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
// 2. 안전한 저장 처리
//
// contest, contest_problem, privilege:
// InnoDB 트랜잭션 적용
//
// solution:
// MyISAM이므로 실패 시 기존 num으로 보상 복구
// ============================================================


// ========================================================
// 2-1. 새 문제 순서 생성
// ========================================================

$new_problem_num_map =
    array();

foreach (
    $problem_ids
    as $problem_num => $problem_id
) {
    $new_problem_num_map[$problem_id] = $problem_num;
}


// ========================================================
// 2-2. solution.num 일괄 동기화 함수
//
// 하나의 UPDATE 문으로 처리하여 대회 수정 중
// 새 제출과 문제 순서가 어긋날 수 있는 시간을 줄인다.
// ========================================================

$sync_solution_nums = function (
    $problem_num_map
) use (
    $cid
) {
    if (
        !is_array(
            $problem_num_map
        )
    ) {
        return false;
    }

    if (
        count($problem_num_map) === 0
    ) {
        $result =
            pdo_query(
                "UPDATE solution
                 SET num = -1
                 WHERE contest_id = ?",
                $cid
            );

        return $result !== false;
    }

    $sql =
        "UPDATE solution
         SET num =
            CASE problem_id ";

    $query_args =
        array();

    foreach (
        $problem_num_map
        as $problem_id => $problem_num
    ) {
        $sql .=
            "WHEN ? THEN ? ";

        $query_args[] =
            intval(
                $problem_id
            );

        $query_args[] =
            intval(
                $problem_num
            );
    }

    $sql .=
        "ELSE -1
         END
         WHERE contest_id = ?";

    $query_args[] =
        $cid;

    array_unshift(
        $query_args,
        $sql
    );

    $result =
        call_user_func_array(
            'pdo_query',
            $query_args
        );

    return $result !== false;
};


// ========================================================
// 2-3. 트랜잭션 및 보상 복구 상태
// ========================================================

$transaction_started =
    false;

$solution_num_touched =
    false;

$rollback_succeeded =
    false;


// ========================================================
// 2-4. 실제 저장
// ========================================================

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


    // ----------------------------------------------------
    // 대회 기본정보 수정
    // ----------------------------------------------------

    $contest_update_result =
        pdo_query(
            "UPDATE contest
             SET
                title = ?,
                description = ?,
                start_time = ?,
                end_time = ?,
                codevisible = ?,
                private = ?,
                langmask = ?,
                password = ?,
                exam_mode = ?,
                allow_copy = ?
             WHERE contest_id = ?",
            $title,
            $description_for_storage,
            $starttime,
            $endtime,
            $codevisible,
            $private,
            $langmask,
            $password,
            $exam_mode,
            $allow_copy,
            $cid
        );

    if (
        $contest_update_result === false
    ) {
        throw new RuntimeException(
            '대회 기본정보 수정에 실패했습니다.'
        );
    }


    // ----------------------------------------------------
    // 기존 문제 구성 삭제
    // ----------------------------------------------------

    $contest_problem_delete_result =
        pdo_query(
            "DELETE FROM contest_problem
             WHERE contest_id = ?",
            $cid
        );

    if (
        $contest_problem_delete_result === false
    ) {
        throw new RuntimeException(
            '기존 문제 구성 삭제에 실패했습니다.'
        );
    }


    // ----------------------------------------------------
    // 새 문제 구성 저장
    // ----------------------------------------------------

    foreach (
        $problem_ids
        as $problem_num => $problem_id
    ) {
        $score =
            $problem_scores[$problem_id];

        $contest_problem_insert_result =
            pdo_query(
                "INSERT INTO contest_problem
                (
                    contest_id,
                    problem_id,
                    num,
                    score
                )
                VALUES (?, ?, ?, ?)",
                $cid,
                $problem_id,
                $problem_num,
                $score
            );

        if (
            $contest_problem_insert_result === false
        ) {
            throw new RuntimeException(
                '문제 구성 저장에 실패했습니다: ' .
                    $problem_id
            );
        }


        // 기존 제출 결과를 이용해 통계를 다시 계산한다.
        $contest_problem_count_result =
            pdo_query(
                "UPDATE contest_problem
                 SET
                    c_accepted =
                    (
                        SELECT COUNT(1)
                        FROM solution
                        WHERE problem_id = ?
                          AND contest_id = ?
                          AND result = 4
                    ),
                    c_submit =
                    (
                        SELECT COUNT(1)
                        FROM solution
                        WHERE problem_id = ?
                          AND contest_id = ?
                    )
                 WHERE problem_id = ?
                   AND contest_id = ?",
                $problem_id,
                $cid,
                $problem_id,
                $cid,
                $problem_id,
                $cid
            );

        if (
            $contest_problem_count_result === false
        ) {
            throw new RuntimeException(
                '문제 제출 통계 갱신에 실패했습니다: ' .
                    $problem_id
            );
        }
    }


    // ----------------------------------------------------
    // 제외된 참가자 권한 회수
    // ----------------------------------------------------

    foreach (
        $participant_ids_to_revoke
        as $participant_id
    ) {
        if (
            !oj_revoke_contest_participant_right(
                $participant_id,
                $cid
            )
        ) {
            throw new RuntimeException(
                '참가자 권한 회수에 실패했습니다: ' .
                    $participant_id
            );
        }
    }


    // ----------------------------------------------------
    // 새 참가자 또는 비활성 참가자 권한 부여
    // ----------------------------------------------------

    foreach (
        $participant_ids_to_grant
        as $participant_id
    ) {
        if (
            !oj_grant_contest_participant_right(
                $participant_id,
                $cid
            )
        ) {
            throw new RuntimeException(
                '참가자 권한 부여에 실패했습니다: ' .
                    $participant_id
            );
        }
    }


    // ----------------------------------------------------
    // MyISAM solution.num을 새 문제 순서로 갱신
    //
    // 실행 직전부터 실패 시 보상 복구 대상으로 표시한다.
    // ----------------------------------------------------

    $solution_num_touched =
        true;

    if (
        !$sync_solution_nums(
            $new_problem_num_map
        )
    ) {
        throw new RuntimeException(
            '제출 문제 순서 갱신에 실패했습니다.'
        );
    }


    // ----------------------------------------------------
    // InnoDB 변경 확정
    // ----------------------------------------------------

    if (
        !$dbh->commit()
    ) {
        throw new RuntimeException(
            '대회 수정 내용을 확정하지 못했습니다.'
        );
    }

    $transaction_started =
        false;
} catch (Throwable $exception) {

    // ----------------------------------------------------
    // InnoDB 변경 롤백
    // ----------------------------------------------------

    if ($transaction_started) {
        try {
            if (
                $dbh->inTransaction() &&
                $dbh->rollBack()
            ) {
                $rollback_succeeded =
                    true;
            } else {
                error_log(
                    '[contest_update] InnoDB 롤백 상태를 확인하지 못함: cid=' .
                        $cid
                );
            }
        } catch (Throwable $rollback_exception) {
            error_log(
                '[contest_update] InnoDB 롤백 실패: cid=' .
                    $cid .
                    ', error=' .
                    $rollback_exception->getMessage()
            );
        }
    }


    // ----------------------------------------------------
    // MyISAM solution.num 보상 복구
    //
    // InnoDB가 기존 상태로 돌아간 것이 확인된 경우에만
    // 기존 문제 순서로 복구한다.
    // ----------------------------------------------------

    if (
        $solution_num_touched &&
        $rollback_succeeded
    ) {
        if (
            !$sync_solution_nums(
                $original_problem_num_map
            )
        ) {
            error_log(
                '[contest_update] solution.num 보상 복구 실패: cid=' .
                    $cid
            );
        }
    }

    if (
        $solution_num_touched &&
        !$rollback_succeeded
    ) {
        error_log(
            '[contest_update] solution.num 복구 보류 - ' .
                'InnoDB 롤백 상태 불명: cid=' .
                $cid
        );
    }

    error_log(
        '[contest_update] 대회 수정 실패: cid=' .
            $cid .
            ', error=' .
            $exception->getMessage()
    );

    $fail(
        500,
        '대회 수정 중 오류가 발생했습니다. 변경 내용이 저장되지 않았는지 관리자 확인이 필요합니다.'
    );
}

header(
    'Location: contest_list.php',
    true,
    303
);

exit;
