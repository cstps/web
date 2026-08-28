<?php
header("Cache-control:private");

require_once("../include/db_info.inc.php");
require_once("../lang/$OJ_LANG.php");
require_once("../include/const.inc.php");
require_once("admin-header.php");

if (
    !isset($_SESSION[$OJ_NAME.'_administrator']) &&
    !isset($_SESSION[$OJ_NAME.'_contest_creator'])
) {
    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit(1);
}

include_once("kindeditor.php");

$cid = 0;


// ============================================================
// 1. 저장 처리
// ============================================================

if (isset($_POST['startdate'])) {

    require_once("../include/check_post_key.php");

    $cid =
        isset($_POST['cid'])
            ? intval($_POST['cid'])
            : 0;

    if ($cid <= 0) {
        echo "No such Contest!";
        exit(1);
    }


    // ========================================================
    // 1-1. Contest 존재 / 수정 권한 확인
    //
    // administrator 또는 실제 m{cid} 권한 보유자만 수정 가능
    // ========================================================

    $contest_rows = pdo_query(
        "SELECT
            contest_id,
            user_id
         FROM contest
         WHERE contest_id = ?
         LIMIT 1",
        $cid
    );

    if (
        !$contest_rows ||
        !isset($contest_rows[0]['contest_id'])
    ) {
        echo "No such Contest!";
        exit(1);
    }


    $user_id =
        $_SESSION[$OJ_NAME.'_user_id'];


    $is_admin =
        isset(
            $_SESSION[$OJ_NAME.'_administrator']
        );


    $can_edit =
        $is_admin ||
        isset(
            $_SESSION[$OJ_NAME.'_m'.$cid]
        );


    if (!$can_edit) {

        $privilege_rows = pdo_query(
            "SELECT 1
             FROM privilege
             WHERE user_id = ?
               AND rightstr = ?
               AND defunct = 'N'
             LIMIT 1",
            $user_id,
            "m".$cid
        );

        $can_edit =
            $privilege_rows &&
            isset($privilege_rows[0][0]);
    }


    if (!$can_edit) {

        echo "<h3>이 대회를 수정할 권한이 없습니다.</h3>";
        exit(1);
    }


    // ========================================================
    // 1-2. 기본 입력값
    // ========================================================

    $starttime =
        $_POST['startdate']." ".
        intval($_POST['shour']).":".
        intval($_POST['sminute']).":00";

    $endtime =
        $_POST['enddate']." ".
        intval($_POST['ehour']).":".
        intval($_POST['eminute']).":00";


    $title =
        isset($_POST['title'])
            ? trim($_POST['title'])
            : '';


    $codevisible =
        isset($_POST['codevisible'])
            ? intval($_POST['codevisible'])
            : 0;

    if (!in_array($codevisible, array(0, 1), true)) {
        $codevisible = 0;
    }


    $private =
        isset($_POST['private'])
            ? intval($_POST['private'])
            : 0;

    if (!in_array($private, array(0, 1), true)) {
        $private = 0;
    }


    $password =
        isset($_POST['password'])
            ? trim($_POST['password'])
            : '';

    // 비공개 대회는 참가자 권한(c{cid})만 사용한다.
    if ($private === 1) {
        $password = '';
    }


    $description =
        isset($_POST['description'])
            ? $_POST['description']
            : '';


    $exam_mode =
        isset($_POST['exam_mode'])
            ? intval($_POST['exam_mode'])
            : 0;

    if (!in_array($exam_mode, array(0, 1), true)) {
        $exam_mode = 0;
    }


    $allow_copy =
        isset($_POST['allow_copy'])
            ? intval($_POST['allow_copy'])
            : 1;

    if (!in_array($allow_copy, array(0, 1), true)) {
        $allow_copy = 1;
    }


    // ========================================================
    // 1-3. 제출 가능 언어 검증
    //
    // HUSTOJ langmask
    // bit = 0 : 허용
    // bit = 1 : 금지
    // ========================================================

    $lang =
        isset($_POST['lang']) &&
        is_array($_POST['lang'])
            ? $_POST['lang']
            : array();


    $lang_count =
        count($language_ext);


    $selected_language_ids =
        array();

    $selected_language_map =
        array();


    foreach ($lang as $language_id_raw) {

        if (
            !is_scalar($language_id_raw) ||
            filter_var(
                $language_id_raw,
                FILTER_VALIDATE_INT
            ) === false
        ) {

            echo "제출 언어 정보가 올바르지 않습니다.";
            exit(1);
        }


        $language_id =
            intval($language_id_raw);


        if (
            $language_id < 0 ||
            $language_id >= $lang_count
        ) {

            echo "존재하지 않는 제출 언어입니다.";
            exit(1);
        }


        if (
            isset(
                $selected_language_map[
                    $language_id
                ]
            )
        ) {
            continue;
        }


        $selected_language_map[
            $language_id
        ] = true;

        $selected_language_ids[] =
            $language_id;
    }


    if (empty($selected_language_ids)) {

        echo "제출 가능 언어를 하나 이상 선택하세요.";
        exit(1);
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
    // 1-4. 현재 Contest 문제 목록
    //
    // 이미 포함된 문제는 allow_reuse가 나중에 변경되어도
    // 기존 사용 관계를 유지할 수 있다.
    // ========================================================

    $current_problem_rows =
        pdo_query(
            "SELECT problem_id
             FROM contest_problem
             WHERE contest_id = ?",
            $cid
        );


    $current_problem_map =
        array();


    if (is_array($current_problem_rows)) {

        foreach (
            $current_problem_rows
            as $current_problem
        ) {

            $current_problem_id =
                intval(
                    $current_problem[
                        'problem_id'
                    ]
                );


            if ($current_problem_id > 0) {

                $current_problem_map[
                    $current_problem_id
                ] = true;
            }
        }
    }


    // ========================================================
    // 1-5. POST 문제 ID 정리
    //
    // cproblem 순서를 그대로 A, B, C... 순서로 사용
    // ========================================================

    $plist =
        isset($_POST['cproblem'])
            ? trim($_POST['cproblem'])
            : '';


    $pieces =
        array_filter(
            array_map(
                'trim',
                explode(',', $plist)
            ),
            function ($value) {
                return $value !== '';
            }
        );


    $problem_ids =
        array();

    $problem_id_map =
        array();


    foreach ($pieces as $value) {

        $problem_id =
            intval($value);


        if (
            $problem_id <= 0 ||
            isset(
                $problem_id_map[
                    $problem_id
                ]
            )
        ) {
            continue;
        }


        $problem_id_map[
            $problem_id
        ] = true;

        $problem_ids[] =
            $problem_id;
    }


    // ========================================================
    // 1-6. 문제 존재 / allow_reuse 서버 검증
    //
    // 현재 Contest에 이미 있는 문제
    // → 기존 사용 관계이므로 유지 가능
    //
    // 신규 추가 문제
    // → administrator: 항상 허용
    // → 문제 생성자: 항상 허용
    // → 다른 사용자: 공개 + allow_reuse=1만 허용
    // ========================================================

    foreach ($problem_ids as $problem_id) {

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


        if (
            !$problem_rows ||
            !isset(
                $problem_rows[0]['problem_id']
            )
        ) {

            echo
                "존재하지 않는 문제입니다: ".
                intval($problem_id);

            exit(1);
        }


        // 기존 문제는 재사용 정책을 소급 적용하지 않는다.
        if (
            isset(
                $current_problem_map[
                    $problem_id
                ]
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
            strtoupper(
                trim(
                    $problem['defunct']
                )
            ) === 'N';


        $allow_reuse =
            intval(
                $problem['allow_reuse']
            ) === 1;


        $can_use_problem =
            $is_admin ||
            $is_owner ||
            (
                $is_public &&
                $allow_reuse
            );


        if (!$can_use_problem) {

            if (!$allow_reuse) {

                echo
                    "문제 ".
                    intval($problem_id).
                    "번은 문제 생성자가 다른 대회에서의 ".
                    "사용을 허용하지 않았습니다.";

            } else {

                echo
                    "문제 ".
                    intval($problem_id).
                    "번을 이 대회에 추가할 권한이 없습니다.";
            }

            exit(1);
        }
    }


    // ========================================================
    // 1-7. Contest 기본정보 수정
    //
    // 문제/언어 검증이 모두 끝난 뒤 실제 DB 변경을 시작한다.
    // ========================================================

    $description =
        str_replace(
            "<p>",
            "",
            $description
        );

    $description =
        str_replace(
            "</p>",
            "<br />",
            $description
        );

    $description =
        str_replace(
            ",",
            "&#44;",
            $description
        );


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
        $description,
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


    // ========================================================
    // 1-8. 문제 구성 갱신
    //
    // 기존 solution 자체는 삭제하지 않고 num만 다시 맞춘다.
    // ========================================================

    pdo_query(
        "UPDATE solution
         SET num = -1
         WHERE contest_id = ?",
        $cid
    );


    pdo_query(
        "DELETE FROM contest_problem
         WHERE contest_id = ?",
        $cid
    );


    $cpoints =
        isset($_POST['cpoint']) &&
        is_array($_POST['cpoint'])
            ? $_POST['cpoint']
            : array();


    $num = 0;


    foreach ($problem_ids as $problem_id) {

        $score =
            (
                isset(
                    $cpoints[
                        $problem_id
                    ]
                ) &&
                $cpoints[
                    $problem_id
                ] !== '' &&
                is_numeric(
                    $cpoints[
                        $problem_id
                    ]
                )
            )
                ? intval(
                    $cpoints[
                        $problem_id
                    ]
                )
                : 100;


        if ($score < 0) {
            $score = 0;
        }


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
            $num,
            $score
        );


        pdo_query(
            "UPDATE contest_problem
             SET
                c_accepted = (
                    SELECT COUNT(1)
                    FROM solution
                    WHERE problem_id = ?
                      AND contest_id = ?
                      AND result = 4
                ),
                c_submit = (
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


        pdo_query(
            "UPDATE solution
             SET num = ?
             WHERE contest_id = ?
               AND problem_id = ?",
            $num,
            $cid,
            $problem_id
        );


        $num++;
    }


    // ========================================================
    // 1-9. 참가자 권한 갱신
    // ========================================================

    pdo_query(
        "DELETE FROM privilege
         WHERE rightstr = ?",
        "c".$cid
    );


    $ulist_raw =
        isset($_POST['ulist'])
            ? trim($_POST['ulist'])
            : '';


    if ($ulist_raw !== '') {

        $users =
            preg_split(
                '/\r\n|\r|\n/',
                $ulist_raw
            );


        foreach ($users as $uid) {

            $uid =
                trim($uid);


            if ($uid === '') {
                continue;
            }


            pdo_query(
                "INSERT INTO privilege
                (
                    user_id,
                    rightstr
                )
                VALUES (?, ?)",
                $uid,
                "c".$cid
            );
        }
    }


    header(
        "Location: contest_list.php"
    );

    exit();
}


// ============================================================
// 2. 수정 화면용 Contest 조회
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;


if ($cid <= 0) {
    echo "No such Contest!";
    exit(0);
}


$result =
    pdo_query(
        "SELECT *
         FROM contest
         WHERE contest_id = ?
         LIMIT 1",
        $cid
    );


if (
    !$result ||
    !isset(
        $result[0]['contest_id']
    )
) {
    echo "No such Contest!";
    exit(0);
}


$row =
    $result[0];


$user_id =
    $_SESSION[$OJ_NAME.'_user_id'];


// ============================================================
// 3. GET 단계 수정 권한 확인
// ============================================================

$can_edit =
    isset(
        $_SESSION[
            $OJ_NAME.'_administrator'
        ]
    ) ||
    isset(
        $_SESSION[
            $OJ_NAME.'_m'.$cid
        ]
    );


if (!$can_edit) {

    $privilege_rows =
        pdo_query(
            "SELECT 1
             FROM privilege
             WHERE user_id = ?
               AND rightstr = ?
               AND defunct = 'N'
             LIMIT 1",
            $user_id,
            "m".$cid
        );


    $can_edit =
        $privilege_rows &&
        isset(
            $privilege_rows[0][0]
        );
}


if (!$can_edit) {

    echo "<h3>이 대회를 수정할 권한이 없습니다.</h3>";
    exit(1);
}


// ============================================================
// 4. 기본값
// ============================================================

$starttime =
    $row['start_time'];

$endtime =
    $row['end_time'];

$codevisible =
    intval(
        $row['codevisible']
    );

$private =
    intval(
        $row['private']
    );

$password =
    isset($row['password'])
        ? $row['password']
        : '';

$langmask =
    intval(
        $row['langmask']
    );

$description =
    $row['description'];

$title =
    $row['title'];

$exam_mode =
    intval(
        $row['exam_mode']
    );

$allow_copy =
    isset($row['allow_copy'])
        ? intval(
            $row['allow_copy']
        )
        : 1;


// ============================================================
// 5. 현재 문제 / 점수 / 초기 선택 정보
// ============================================================

$plist =
    '';

$initial_selected_problems =
    array();


$problem_rows =
    pdo_query(
        "SELECT
            cp.problem_id,
            cp.score,
            p.title,
            p.source,
            p.defunct,
            p.allow_reuse

         FROM contest_problem cp

         LEFT JOIN problem p
           ON p.problem_id = cp.problem_id

         WHERE cp.contest_id = ?

         ORDER BY
            cp.num,
            cp.problem_id",
        $cid
    );


if (!is_array($problem_rows)) {
    $problem_rows = array();
}


foreach (
    $problem_rows
    as $problem
) {

    $problem_id =
        intval(
            $problem['problem_id']
        );


    if ($problem_id <= 0) {
        continue;
    }


    if ($plist !== '') {
        $plist .= ',';
    }


    $plist .=
        $problem_id;


    $initial_selected_problems[] =
        array(

            'problem_id' =>
                $problem_id,

            'title' =>
                isset($problem['title'])
                    ? (string)$problem['title']
                    : '(문제 정보 없음)',

            'source' =>
                isset($problem['source'])
                    ? (string)$problem['source']
                    : '',

            'defunct' =>
                isset($problem['defunct'])
                    ? (string)$problem['defunct']
                    : 'Y',

            'allow_reuse' =>
                isset($problem['allow_reuse'])
                    ? intval(
                        $problem['allow_reuse']
                    )
                    : 0,

            'score' =>
                isset($problem['score'])
                    ? intval(
                        $problem['score']
                    )
                    : 100
        );
}


// ============================================================
// 6. 참가자 목록
// ============================================================

$ulist =
    '';


$user_rows =
    pdo_query(
        "SELECT user_id
         FROM privilege
         WHERE rightstr = ?
         ORDER BY user_id",
        "c".$cid
    );


if (!is_array($user_rows)) {
    $user_rows = array();
}


foreach ($user_rows as $user) {

    if ($ulist !== '') {
        $ulist .= "\n";
    }


    $ulist .=
        $user['user_id'];
}


// ============================================================
// 7. 현재 허용 언어 Mask
// ============================================================

$lang_count =
    count($language_ext);


$enabled_language_mask =
    (~intval($langmask)) &
    (
        (1 << $lang_count) - 1
    );

?>
<!DOCTYPE html>
<html>

<head>

    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Content-Language" content="ko">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="stylesheet"
        href="contest-form.css"
    >

    <title>Edit Contest</title>

</head>

<body>

<div class="container contest-add-wrap">


    <div class="contest-edit-header">

        <h3>
            <?php echo $MSG_CONTEST; ?> 수정
        </h3>

        <div>
            Contest ID:
            <strong>
                <?php echo intval($cid); ?>
            </strong>
        </div>

    </div>


    <form
        method="POST"
        id="contest-edit-form"
    >

        <?php
        require_once(
            "../include/set_post_key.php"
        );
        ?>

        <input
            type="hidden"
            name="cid"
            value="<?php echo intval($cid); ?>"
        >


        <!-- ==================================================
             1. 기본 정보
             ================================================== -->

        <div class="contest-add-card">

            <h4>1. 기본 정보</h4>


            <div class="contest-add-field">

                <label>
                    <?php
                    echo
                        $MSG_CONTEST.
                        "-".
                        $MSG_TITLE;
                    ?>
                </label>

                <input
                    type="text"
                    name="title"
                    value="<?php
                        echo htmlspecialchars(
                            $title,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    required
                >

            </div>


            <div
                class="contest-add-grid"
                style="margin-top:14px;"
            >


                <div class="contest-add-field">

                    <label>
                        <?php
                        echo
                            $MSG_CONTEST.
                            $MSG_Start;
                        ?>
                    </label>


                    <div class="contest-add-inline-time">

                        <input
                            type="date"
                            name="startdate"
                            value="<?php
                                echo substr(
                                    $starttime,
                                    0,
                                    10
                                );
                            ?>"
                            required
                        >

                        <input
                            type="number"
                            name="shour"
                            min="0"
                            max="23"
                            value="<?php
                                echo substr(
                                    $starttime,
                                    11,
                                    2
                                );
                            ?>"
                            title="시"
                            required
                        >

                        <input
                            type="number"
                            name="sminute"
                            min="0"
                            max="59"
                            value="<?php
                                echo substr(
                                    $starttime,
                                    14,
                                    2
                                );
                            ?>"
                            title="분"
                            required
                        >

                    </div>

                </div>


                <div class="contest-add-field">

                    <label>
                        <?php
                        echo
                            $MSG_CONTEST.
                            $MSG_End;
                        ?>
                    </label>


                    <div class="contest-add-inline-time">

                        <input
                            type="date"
                            name="enddate"
                            value="<?php
                                echo substr(
                                    $endtime,
                                    0,
                                    10
                                );
                            ?>"
                            required
                        >

                        <input
                            type="number"
                            name="ehour"
                            min="0"
                            max="23"
                            value="<?php
                                echo substr(
                                    $endtime,
                                    11,
                                    2
                                );
                            ?>"
                            title="시"
                            required
                        >

                        <input
                            type="number"
                            name="eminute"
                            min="0"
                            max="59"
                            value="<?php
                                echo substr(
                                    $endtime,
                                    14,
                                    2
                                );
                            ?>"
                            title="분"
                            required
                        >

                    </div>

                </div>

            </div>


            <div
                class="contest-add-field"
                style="margin-top:14px;"
            >

                <label>
                    <?php
                    echo
                        $MSG_CONTEST.
                        "-".
                        $MSG_Description;
                    ?>
                </label>

                <textarea
                    class="kindeditor"
                    rows="13"
                    name="description"
                    cols="80"
                ><?php
                    echo htmlspecialchars(
                        $description,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>

            </div>

        </div>


        <!-- ==================================================
             2. 문제 구성
             ================================================== -->

        <div class="contest-add-card">

            <h4>2. 문제 구성</h4>


            <input
                type="hidden"
                id="plist"
                name="cproblem"
                value="<?php
                    echo htmlspecialchars(
                        $plist,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >


            <div class="contest-problem-selector">


                <div class="contest-problem-search">

                    <input
                        type="text"
                        id="problem-search"
                        placeholder="문제 번호·제목·출처 검색"
                        autocomplete="off"
                    >

                    <button
                        type="button"
                        id="problem-search-button"
                    >
                        검색
                    </button>

                </div>


                <div class="contest-problem-tabs">

                    <button
                        type="button"
                        class="contest-problem-tab active"
                        data-scope="my"
                    >
                        내가 만든 문제
                    </button>

                    <button
                        type="button"
                        class="contest-problem-tab"
                        data-scope="available"
                    >
                        사용 가능한 전체 문제
                    </button>

                </div>


                <div class="contest-add-help">
                    한 번에 최대 50개까지 표시됩니다.
                    검색을 다시 해도 현재 대회에 선택된 문제는 유지됩니다.
                </div>


                <div
                    id="problem-search-results"
                    class="contest-problem-results"
                >
                    문제를 불러오는 중입니다.
                </div>


                <div class="contest-selected-header">

                    <strong>
                        선택한 문제
                    </strong>

                    <span id="selected-problem-count">
                        0개
                    </span>

                </div>


                <div
                    id="selected-problem-list"
                    class="contest-selected-list"
                ></div>

            </div>

        </div>


        <!-- ==================================================
             3. 대회 운영 설정
             ================================================== -->

        <div class="contest-add-card">

            <h4>3. 대회 운영 설정</h4>


            <div class="contest-setting-grid">


                <div class="contest-setting-box">

                    <div class="contest-setting-title">
                        공개 범위
                    </div>

                    <div class="contest-setting-description">
                        사용자가 대회에 접근할 수 있는 범위를 설정합니다.
                    </div>

                    <div class="contest-choice-group">

                        <input
                            type="radio"
                            id="private-public"
                            name="private"
                            value="0"
                            <?php
                            echo
                                $private === 0
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="private-public">
                            공개
                        </label>


                        <input
                            type="radio"
                            id="private-private"
                            name="private"
                            value="1"
                            <?php
                            echo
                                $private === 1
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="private-private">
                            비공개
                        </label>

                    </div>


                    <div class="contest-add-help">
                        비공개 대회는 참가 권한이 있는 사용자만 접근할 수 있습니다.
                    </div>

                </div>


                <div class="contest-setting-box">

                    <div class="contest-setting-title">
                        제출 코드
                    </div>

                    <div class="contest-setting-description">
                        참가자의 제출 코드를 다른 사용자에게 공개할지 설정합니다.
                    </div>

                    <div class="contest-choice-group">

                        <input
                            type="radio"
                            id="codevisible-public"
                            name="codevisible"
                            value="0"
                            <?php
                            echo
                                $codevisible === 0
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="codevisible-public">
                            공개
                        </label>


                        <input
                            type="radio"
                            id="codevisible-private"
                            name="codevisible"
                            value="1"
                            <?php
                            echo
                                $codevisible === 1
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="codevisible-private">
                            비공개
                        </label>

                    </div>

                </div>


                <div class="contest-setting-box">

                    <div class="contest-setting-title">
                        시험 모드
                    </div>

                    <div class="contest-setting-description">
                        평가용 대회에서는 다른 사용자의 제출 상태 노출을 제한합니다.
                    </div>

                    <div class="contest-choice-group">

                        <input
                            type="radio"
                            id="exam-mode-off"
                            name="exam_mode"
                            value="0"
                            <?php
                            echo
                                $exam_mode === 0
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="exam-mode-off">
                            일반
                        </label>


                        <input
                            type="radio"
                            id="exam-mode-on"
                            name="exam_mode"
                            value="1"
                            <?php
                            echo
                                $exam_mode === 1
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="exam-mode-on">
                            시험 모드
                        </label>

                    </div>

                </div>


                <div class="contest-setting-box">

                    <div class="contest-setting-title">
                        대회 복사
                    </div>

                    <div class="contest-setting-description">
                        다른 Contest Creator가 이 대회의 구성을 가져갈 수 있는지 설정합니다.
                    </div>

                    <div class="contest-choice-group">

                        <input
                            type="radio"
                            id="allow-copy-on"
                            name="allow_copy"
                            value="1"
                            <?php
                            echo
                                $allow_copy === 1
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="allow-copy-on">
                            허용
                        </label>


                        <input
                            type="radio"
                            id="allow-copy-off"
                            name="allow_copy"
                            value="0"
                            <?php
                            echo
                                $allow_copy === 0
                                    ? 'checked'
                                    : '';
                            ?>
                        >

                        <label for="allow-copy-off">
                            허용하지 않음
                        </label>

                    </div>

                </div>

            </div>


            <div
                class="contest-setting-password"
                style="margin-top:16px;"
            >

                <label for="contest-password">
                    대회 비밀번호
                </label>

                <input
                    id="contest-password"
                    type="text"
                    name="password"
                    value="<?php
                        echo htmlspecialchars(
                            $password,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    placeholder="필요한 경우에만 입력"
                >

                <div
                    id="contest-password-help"
                    class="contest-add-help"
                >
                    공개 대회에서는 필요한 경우 비밀번호를 사용할 수 있습니다.
                </div>

            </div>

        </div>


        <!-- ==================================================
             4. 제출 가능 언어
             ================================================== -->

        <div class="contest-add-card">

            <div class="contest-language-header">

                <h4>
                    4. 제출 가능 언어
                </h4>

                <span
                    id="selected-language-count"
                    class="contest-language-count"
                >
                    0개 선택
                </span>

            </div>


            <div class="contest-add-help">
                현재 대회에서 참가자가 제출할 수 있는 언어를 선택하세요.
                하나 이상의 언어가 필요합니다.
            </div>


            <div
                class="contest-language-options"
                id="contest-language-options"
            >

                <?php

                for (
                    $i = 0;
                    $i < $lang_count;
                    $i++
                ) {

                    $checked =
                        (
                            $enabled_language_mask &
                            (1 << $i)
                        )
                            ? 'checked'
                            : '';

                    ?>

                    <div class="contest-language-item">

                        <input
                            type="checkbox"
                            id="contest-language-<?php echo $i; ?>"
                            name="lang[]"
                            value="<?php echo $i; ?>"
                            <?php echo $checked; ?>
                        >

                        <label
                            for="contest-language-<?php echo $i; ?>"
                        >
                            <?php
                            echo htmlspecialchars(
                                $language_name[$i],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </label>

                    </div>

                    <?php
                }

                ?>

            </div>


            <div
                id="contest-language-error"
                class="contest-language-error"
                style="display:none;"
            >
                제출 가능 언어를 하나 이상 선택하세요.
            </div>

        </div>


        <!-- ==================================================
             5. 참가자 설정
             ================================================== -->

        <div class="contest-add-card">

            <h4>5. 참가자 설정</h4>


            <div class="contest-add-field">

                <label>
                    <?php
                    echo
                        $MSG_CONTEST.
                        "-".
                        $MSG_USER;
                    ?>
                </label>

                <textarea
                    name="ulist"
                    rows="10"
                    placeholder="user1&#10;user2&#10;user3"
                ><?php
                    echo htmlspecialchars(
                        $ulist,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?></textarea>


                <div class="contest-add-help">
                    비공개 대회의 참가자 아이디를 한 줄에 하나씩 입력하세요.
                    공개 대회에서는 입력하지 않아도 됩니다.
                </div>

            </div>

        </div>


        <div class="contest-edit-actions">

            <input
                type="submit"
                value="<?php echo $MSG_SAVE; ?>"
                name="submit"
            >

            <input
                type="reset"
                value="Reset"
                name="reset"
            >

        </div>

    </form>

</div>


<script>

const INITIAL_SELECTED_PROBLEMS =
    <?php
    echo json_encode(
        $initial_selected_problems,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    );
    ?>;


const selectedProblems =
    new Map();


let currentProblemScope =
    "my";


// ============================================================
// 초기 선택 문제
// ============================================================

INITIAL_SELECTED_PROBLEMS.forEach(
    function (problem) {

        const pid =
            String(
                problem.problem_id
            );


        selectedProblems.set(
            pid,
            {
                problem_id:
                    Number(
                        problem.problem_id
                    ),

                title:
                    String(
                        problem.title || ""
                    ),

                source:
                    String(
                        problem.source || ""
                    ),

                defunct:
                    String(
                        problem.defunct || "N"
                    ),

                allow_reuse:
                    Number(
                        problem.allow_reuse || 0
                    ),

                score:
                    (
                        problem.score !== undefined &&
                        problem.score !== null &&
                        problem.score !== ""
                    )
                        ? Number(
                            problem.score
                        )
                        : 100
            }
        );
    }
);


// ============================================================
// A / B / C / ... / AA 변환
// ============================================================

function problemOrderLabel(index) {

    let number =
        index + 1;

    let result =
        "";


    while (number > 0) {

        number--;


        result =
            String.fromCharCode(
                65 + (number % 26)
            ) +
            result;


        number =
            Math.floor(
                number / 26
            );
    }


    return result;
}


// ============================================================
// hidden cproblem 동기화
// ============================================================

function syncProblemListInput() {

    const input =
        document.getElementById(
            "plist"
        );


    if (!input) {
        return;
    }


    input.value =
        Array.from(
            selectedProblems.keys()
        ).join(",");
}


// ============================================================
// 선택 개수
// ============================================================

function updateSelectedCount() {

    const count =
        document.getElementById(
            "selected-problem-count"
        );


    if (count) {

        count.textContent =
            selectedProblems.size +
            "개";
    }
}


// ============================================================
// 검색 결과 체크상태 동기화
// ============================================================

function synchronizeSearchChecks() {

    document
        .querySelectorAll(
            ".contest-problem-result-check"
        )
        .forEach(
            function (checkbox) {

                checkbox.checked =
                    selectedProblems.has(
                        String(
                            checkbox.value
                        )
                    );
            }
        );
}


// ============================================================
// 선택 문제 목록 렌더링
// ============================================================

function renderSelectedProblems() {

    const container =
        document.getElementById(
            "selected-problem-list"
        );


    if (!container) {
        return;
    }


    container.innerHTML =
        "";


    if (
        selectedProblems.size === 0
    ) {

        const empty =
            document.createElement(
                "div"
            );

        empty.className =
            "contest-problem-empty";

        empty.textContent =
            "선택된 문제가 없습니다.";


        container.appendChild(
            empty
        );


        syncProblemListInput();
        updateSelectedCount();
        synchronizeSearchChecks();

        return;
    }


    let index =
        0;


    selectedProblems.forEach(
        function (
            problem,
            pid
        ) {

            const row =
                document.createElement(
                    "div"
                );

            row.className =
                "contest-selected-row";


            const order =
                document.createElement(
                    "div"
                );

            order.className =
                "contest-selected-order";

            order.textContent =
                problemOrderLabel(
                    index
                );


            row.appendChild(
                order
            );


            const titleBox =
                document.createElement(
                    "div"
                );

            titleBox.className =
                "contest-selected-title";


            const titleLink =
                document.createElement(
                    "a"
                );

            titleLink.href =
                "../problem.php?id=" +
                encodeURIComponent(
                    pid
                );

            titleLink.target =
                "_blank";

            titleLink.textContent =
                pid +
                " · " +
                problem.title;


            titleBox.appendChild(
                titleLink
            );


            if (problem.source) {

                const meta =
                    document.createElement(
                        "div"
                    );

                meta.className =
                    "contest-problem-meta";

                meta.textContent =
                    problem.source;


                titleBox.appendChild(
                    meta
                );
            }


            row.appendChild(
                titleBox
            );


            const scoreBox =
                document.createElement(
                    "div"
                );

            scoreBox.className =
                "contest-selected-score";


            const scoreInput =
                document.createElement(
                    "input"
                );

            scoreInput.type =
                "number";

            scoreInput.name =
                "cpoint[" +
                pid +
                "]";

            scoreInput.min =
                "0";

            scoreInput.step =
                "1";

            scoreInput.value =
                problem.score;


            scoreInput.addEventListener(
                "input",
                function () {

                    problem.score =
                        this.value;
                }
            );


            const scoreLabel =
                document.createElement(
                    "span"
                );

            scoreLabel.textContent =
                "점";


            scoreBox.appendChild(
                scoreInput
            );

            scoreBox.appendChild(
                scoreLabel
            );


            row.appendChild(
                scoreBox
            );


            const removeButton =
                document.createElement(
                    "button"
                );

            removeButton.type =
                "button";

            removeButton.className =
                "contest-selected-remove";

            removeButton.textContent =
                "삭제";


            removeButton.addEventListener(
                "click",
                function () {

                    selectedProblems.delete(
                        pid
                    );

                    renderSelectedProblems();
                }
            );


            row.appendChild(
                removeButton
            );


            container.appendChild(
                row
            );


            index++;
        }
    );


    syncProblemListInput();
    updateSelectedCount();
    synchronizeSearchChecks();
}


// ============================================================
// 검색 결과 렌더링
// ============================================================

function renderProblemSearchResults(
    problems
) {

    const container =
        document.getElementById(
            "problem-search-results"
        );


    if (!container) {
        return;
    }


    container.innerHTML =
        "";


    if (
        !Array.isArray(problems) ||
        problems.length === 0
    ) {

        const empty =
            document.createElement(
                "div"
            );

        empty.className =
            "contest-problem-empty";

        empty.textContent =
            "검색 결과가 없습니다.";


        container.appendChild(
            empty
        );

        return;
    }


    problems.forEach(
        function (problem) {

            const pid =
                String(
                    problem.problem_id
                );


            const row =
                document.createElement(
                    "div"
                );

            row.className =
                "contest-problem-result";


            const checkbox =
                document.createElement(
                    "input"
                );

            checkbox.type =
                "checkbox";

            checkbox.value =
                pid;

            checkbox.className =
                "contest-problem-result-check";

            checkbox.checked =
                selectedProblems.has(
                    pid
                );


            checkbox.addEventListener(
                "change",
                function () {

                    if (this.checked) {

                        if (
                            !selectedProblems.has(
                                pid
                            )
                        ) {

                            selectedProblems.set(
                                pid,
                                {
                                    problem_id:
                                        Number(
                                            problem.problem_id
                                        ),

                                    title:
                                        String(
                                            problem.title || ""
                                        ),

                                    source:
                                        String(
                                            problem.source || ""
                                        ),

                                    defunct:
                                        String(
                                            problem.defunct || "N"
                                        ),

                                    allow_reuse:
                                        Number(
                                            problem.allow_reuse || 0
                                        ),

                                    score:
                                        100
                                }
                            );
                        }

                    } else {

                        selectedProblems.delete(
                            pid
                        );
                    }


                    renderSelectedProblems();
                }
            );


            row.appendChild(
                checkbox
            );


            const titleBox =
                document.createElement(
                    "div"
                );

            titleBox.className =
                "contest-problem-result-title";


            const titleLink =
                document.createElement(
                    "a"
                );

            titleLink.href =
                "../problem.php?id=" +
                encodeURIComponent(
                    pid
                );

            titleLink.target =
                "_blank";

            titleLink.textContent =
                pid +
                " · " +
                String(
                    problem.title || ""
                );


            titleBox.appendChild(
                titleLink
            );


            const meta =
                document.createElement(
                    "div"
                );

            meta.className =
                "contest-problem-meta";


            const metaParts =
                [];


            if (problem.source) {

                metaParts.push(
                    String(
                        problem.source
                    )
                );
            }


            metaParts.push(
                "AC " +
                Number(
                    problem.accepted || 0
                )
            );


            meta.textContent =
                metaParts.join(
                    " · "
                );


            titleBox.appendChild(
                meta
            );


            row.appendChild(
                titleBox
            );


            if (
                Number(
                    problem.allow_reuse
                ) === 0
            ) {

                const badge =
                    document.createElement(
                        "span"
                    );

                badge.className =
                    "contest-problem-reuse";

                badge.textContent =
                    "재사용 제한";


                row.appendChild(
                    badge
                );
            }


            container.appendChild(
                row
            );
        }
    );
}


// ============================================================
// 문제 검색
// ============================================================

async function searchContestProblems() {

    const searchInput =
        document.getElementById(
            "problem-search"
        );

    const resultContainer =
        document.getElementById(
            "problem-search-results"
        );


    if (
        !searchInput ||
        !resultContainer
    ) {
        return;
    }


    resultContainer.textContent =
        "문제를 불러오는 중입니다.";


    try {

        const params =
            new URLSearchParams(
                {
                    scope:
                        currentProblemScope,

                    search:
                        searchInput.value.trim()
                }
            );


        const response =
            await fetch(
                "contest_problem_search.php?" +
                params.toString(),
                {
                    credentials:
                        "same-origin"
                }
            );


        const data =
            await response.json();


        if (
            !response.ok ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                "문제를 불러오지 못했습니다."
            );
        }


        renderProblemSearchResults(
            data.problems || []
        );

    } catch (error) {

        resultContainer.textContent =
            error.message ||
            "문제 검색 중 오류가 발생했습니다.";
    }
}


// ============================================================
// 제출 언어 선택 상태
// ============================================================

function updateLanguageSelection() {

    const checkedLanguages =
        document.querySelectorAll(
            'input[name="lang[]"]:checked'
        );


    const countLabel =
        document.getElementById(
            "selected-language-count"
        );


    const errorBox =
        document.getElementById(
            "contest-language-error"
        );


    if (countLabel) {

        countLabel.textContent =
            checkedLanguages.length +
            "개 선택";
    }


    if (
        errorBox &&
        checkedLanguages.length > 0
    ) {

        errorBox.style.display =
            "none";
    }
}


// ============================================================
// 공개 / 비공개 ↔ 비밀번호
// ============================================================

function synchronizeContestPrivacy() {

    const privateInput =
        document.querySelector(
            'input[name="private"]:checked'
        );

    const passwordInput =
        document.getElementById(
            "contest-password"
        );

    const passwordHelp =
        document.getElementById(
            "contest-password-help"
        );


    if (
        !privateInput ||
        !passwordInput
    ) {
        return;
    }


    const isPrivate =
        String(
            privateInput.value
        ) === "1";


    passwordInput.disabled =
        isPrivate;


    if (isPrivate) {

        passwordInput.value =
            "";

        passwordInput.placeholder =
            "비공개 대회에서는 사용할 수 없습니다.";


        if (passwordHelp) {

            passwordHelp.textContent =
                "비공개 대회는 참가자 목록에 등록되어 참가 권한(c{cid})이 있는 사용자만 접근할 수 있습니다. 비밀번호는 사용하지 않습니다.";
        }

    } else {

        passwordInput.placeholder =
            "필요한 경우에만 입력";


        if (passwordHelp) {

            passwordHelp.textContent =
                "공개 대회에서는 필요한 경우 비밀번호를 사용할 수 있습니다. 비밀번호가 없으면 일반 공개 대회로 운영됩니다.";
        }
    }
}


// ============================================================
// 초기화 / 이벤트
// ============================================================

document.addEventListener(
    "DOMContentLoaded",
    function () {

        renderSelectedProblems();

        searchContestProblems();

        updateLanguageSelection();

        synchronizeContestPrivacy();


        const searchButton =
            document.getElementById(
                "problem-search-button"
            );


        if (searchButton) {

            searchButton.addEventListener(
                "click",
                searchContestProblems
            );
        }


        const searchInput =
            document.getElementById(
                "problem-search"
            );


        if (searchInput) {

            searchInput.addEventListener(
                "keydown",
                function (event) {

                    if (
                        event.key === "Enter"
                    ) {

                        event.preventDefault();

                        searchContestProblems();
                    }
                }
            );
        }


        document
            .querySelectorAll(
                ".contest-problem-tab"
            )
            .forEach(
                function (tab) {

                    tab.addEventListener(
                        "click",
                        function () {

                            document
                                .querySelectorAll(
                                    ".contest-problem-tab"
                                )
                                .forEach(
                                    function (item) {

                                        item.classList.remove(
                                            "active"
                                        );
                                    }
                                );


                            this.classList.add(
                                "active"
                            );


                            currentProblemScope =
                                this.dataset.scope ||
                                "my";


                            searchContestProblems();
                        }
                    );
                }
            );


        document
            .querySelectorAll(
                'input[name="lang[]"]'
            )
            .forEach(
                function (languageInput) {

                    languageInput.addEventListener(
                        "change",
                        updateLanguageSelection
                    );
                }
            );


        document
            .querySelectorAll(
                'input[name="private"]'
            )
            .forEach(
                function (privateInput) {

                    privateInput.addEventListener(
                        "change",
                        synchronizeContestPrivacy
                    );
                }
            );


        const contestForm =
            document.getElementById(
                "contest-edit-form"
            );


        if (contestForm) {

            contestForm.addEventListener(
                "submit",
                function (event) {

                    const checkedLanguages =
                        document.querySelectorAll(
                            'input[name="lang[]"]:checked'
                        );


                    if (
                        checkedLanguages.length === 0
                    ) {

                        event.preventDefault();


                        const errorBox =
                            document.getElementById(
                                "contest-language-error"
                            );


                        if (errorBox) {

                            errorBox.style.display =
                                "block";

                            errorBox.scrollIntoView(
                                {
                                    behavior:
                                        "smooth",

                                    block:
                                        "center"
                                }
                            );
                        }
                    }
                }
            );
        }

    }
);

</script>

</body>

</html>