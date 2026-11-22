<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (
    !isset($_SERVER["REQUEST_METHOD"]) ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    header("Allow: POST");
    http_response_code(405);
    exit("POST 요청만 허용됩니다.");
}

if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("사용자 정보를 수정할 권한이 없습니다.");
}

require_once(
    __DIR__ . "/../include/check_post_key.php"
);

// POST 입력값은 문자열만 허용한다.
foreach (
    array(
        "user_id",
        "nick",
        "school",
        "return_page",
        "return_keyword"
    ) as $field
) {
    if (
        isset($_POST[$field]) &&
        !is_string($_POST[$field])
    ) {
        http_response_code(400);
        exit("요청 값의 형식이 올바르지 않습니다.");
    }
}

$user_id =
    isset($_POST["user_id"])
    ? trim((string)$_POST["user_id"])
    : "";

$nick =
    isset($_POST["nick"])
    ? trim((string)$_POST["nick"])
    : "";

$school =
    isset($_POST["school"])
    ? trim((string)$_POST["school"])
    : "";

$return_page =
    isset($_POST["return_page"])
    ? (int)$_POST["return_page"]
    : 1;

$return_keyword =
    isset($_POST["return_keyword"])
    ? trim((string)$_POST["return_keyword"])
    : "";

if (
    $user_id === "" ||
    strlen($user_id) > 48
) {
    http_response_code(400);
    exit("사용자 ID가 올바르지 않습니다.");
}

if (
    !mb_check_encoding($nick, "UTF-8") ||
    mb_strlen($nick, "UTF-8") > 20
) {
    http_response_code(400);
    exit("별명은 20자 이하로 입력해 주세요.");
}

if (
    !mb_check_encoding($school, "UTF-8") ||
    mb_strlen($school, "UTF-8") > 20
) {
    http_response_code(400);
    exit("학교명은 20자 이하로 입력해 주세요.");
}

if ($return_page < 1) {
    $return_page = 1;
}

if (strlen($return_keyword) > 200) {
    http_response_code(400);
    exit("검색어가 너무 깁니다.");
}

// 사용자 정보와 제출 기록의 별명을 하나의 트랜잭션으로 변경한다.
global $dbh;

try {
    if (!($dbh instanceof PDO)) {
        throw new RuntimeException(
            "데이터베이스 연결을 확인할 수 없습니다."
        );
    }

    if (!$dbh->beginTransaction()) {
        throw new RuntimeException(
            "트랜잭션을 시작할 수 없습니다."
        );
    }

    // 사용자 행을 잠그고 최신 별명을 조회한다.
    $user_rows =
        pdo_query(
            "
            SELECT user_id, nick
            FROM users
            WHERE user_id = ?
            LIMIT 1
            FOR UPDATE
            ",
            $user_id
        );

    if ($user_rows === false) {
        throw new RuntimeException(
            "사용자 정보를 확인할 수 없습니다."
        );
    }

    if (!isset($user_rows[0])) {
        throw new OutOfBoundsException(
            "사용자를 찾을 수 없습니다."
        );
    }

    $previous_nick =
        (string)$user_rows[0]["nick"];

    $update_result =
        pdo_query(
            "
            UPDATE users
            SET nick = ?,
                school = ?
            WHERE user_id = ?
            ",
            $nick,
            $school,
            $user_id
        );

    if ($update_result === false) {
        throw new RuntimeException(
            "사용자 정보를 수정할 수 없습니다."
        );
    }

    if ($previous_nick !== $nick) {
        $solution_update_result =
            pdo_query(
                "
                UPDATE solution
                SET nick = ?
                WHERE user_id = ?
                ",
                $nick,
                $user_id
            );

        if ($solution_update_result === false) {
            throw new RuntimeException(
                "제출 기록의 별명을 동기화할 수 없습니다."
            );
        }
    }

    if (!$dbh->commit()) {
        throw new RuntimeException(
            "사용자 정보 변경을 확정할 수 없습니다."
        );
    }
} catch (Throwable $e) {
    if (
        $dbh instanceof PDO &&
        $dbh->inTransaction()
    ) {
        try {
            $dbh->rollBack();
        } catch (Throwable $rollback_error) {
            error_log(
                "[admin/user_update] rollback failed: " .
                $rollback_error->getMessage()
            );
        }
    }

    error_log(
        "[admin/user_update] " .
        $e->getMessage()
    );

    if ($e instanceof OutOfBoundsException) {
        http_response_code(404);
        exit("사용자를 찾을 수 없습니다.");
    }

    http_response_code(500);
    exit("사용자 정보를 수정할 수 없습니다.");
}

$return_query =
    array(
        "edited" => "1",
        "page" => (string)$return_page
    );

if ($return_keyword !== "") {
    $return_query["keyword"] =
        $return_keyword;
}

header(
    "Location: user_list.php?" .
    http_build_query($return_query),
    true,
    303
);

exit;
