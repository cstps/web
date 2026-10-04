<?php
require_once(__DIR__ . "/include/db_info.inc.php");

$fail = function ($status, $message) {
    http_response_code($status);
    header("Content-Type: text/plain; charset=UTF-8");
    exit($message);
};

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    $fail(405, "POST 요청만 허용합니다.");
}

if (isset($OJ_BBS) && !$OJ_BBS) {
    $fail(403, "게시판을 사용할 수 없습니다.");
}

$user_id = $_SESSION[$OJ_NAME . "_user_id"] ?? null;
if (!is_string($user_id) || $user_id === "") {
    $fail(403, "로그인이 필요합니다.");
}

$session_key = $_SESSION[$OJ_NAME . "_postkey"] ?? null;
$post_key = $_POST["postkey"] ?? null;

if (
    !is_string($session_key) ||
    $session_key === "" ||
    !is_string($post_key) ||
    !hash_equals($session_key, $post_key)
) {
    $fail(403, "요청 확인에 실패했습니다. 게시물을 새로고침한 뒤 다시 시도해 주세요.");
}

$number = function ($name, $allow_zero = false) use ($fail) {
    $value = $_POST[$name] ?? null;

    if (
        !is_string($value) ||
        !preg_match('/^[0-9]{1,10}$/D', $value) ||
        (float)$value > 2147483647
    ) {
        $fail(400, "잘못된 번호입니다: " . $name);
    }

    $value = (int)$value;
    if (!$allow_zero && $value === 0) {
        $fail(400, "번호는 1 이상이어야 합니다: " . $name);
    }

    return $value;
};

$tid = $number("tid");
$target = $_POST["target"] ?? null;
$action = $_POST["action"] ?? null;
$isadmin = isset($_SESSION[$OJ_NAME . "_administrator"]);

if (
    !is_string($target) ||
    !in_array($target, array("thread", "reply"), true) ||
    !is_string($action)
) {
    $fail(400, "잘못된 관리 요청입니다.");
}

$topics = pdo_query(
    "SELECT tid, author_id, pid, status
       FROM topic
       WHERE tid = ?
       LIMIT 1",
    $tid
);

if ($topics === false) {
    $fail(500, "게시물 조회에 실패했습니다.");
}
if (!isset($topics[0]) || (int)$topics[0]["status"] > 1) {
    $fail(404, "게시물을 찾을 수 없습니다.");
}

$topic = $topics[0];

$is_topic_owner =
    strcasecmp(
        (string)$topic["author_id"],
        $user_id
    ) === 0;

$pid = (int)$topic["pid"];

if ($target === "reply") {
    $states = array("resume" => 0, "disable" => 1, "delete" => 2);
    if (!array_key_exists($action, $states)) {
        $fail(400, "잘못된 답글 관리 요청입니다.");
    }

    $rid = $number("rid");
    $replies = pdo_query(
        "SELECT
             r.rid,
             r.author_id,
             r.status,
             CASE
                 WHEN r.rid = (
                     SELECT MIN(r0.rid)
                     FROM reply r0
                     WHERE r0.topic_id = r.topic_id
                 )
                 THEN 1
                 ELSE 0
             END AS is_topic_body
         FROM reply r
         WHERE r.rid = ?
           AND r.topic_id = ?
         LIMIT 1",
        $rid,
        $tid
    );

    if ($replies === false) {
        $fail(500, "답글 조회에 실패했습니다.");
    }
    if (!isset($replies[0]) || (int)$replies[0]["status"] > 1) {
        $fail(404, "답글을 찾을 수 없습니다.");
    }

    // 게시글 본문은 reply 테이블의 첫 번째 행이지만
    // 일반 답글 관리 기능으로 차단하거나 삭제하지 않는다.
    if ((int)$replies[0]["is_topic_body"] === 1) {
        $fail(
            400,
            "게시글 본문은 답글로 관리할 수 없습니다. " .
            "게시물 관리 기능을 사용해 주세요."
        );
    }

    if (
        !$isadmin &&
        (
            $action !== "delete" ||
            strcasecmp((string)$replies[0]["author_id"], $user_id) !== 0
        )
    ) {
        $fail(403, "이 답글을 관리할 권한이 없습니다.");
    }

    $desired = $states[$action];
    if ($isadmin) {
        $changed = pdo_query(
            "UPDATE reply SET status = ? WHERE rid = ? AND topic_id = ? AND status <= 1",
            $desired,
            $rid,
            $tid
        );
    } else {
        $changed = pdo_query(
            "UPDATE reply SET status = ? WHERE rid = ? AND topic_id = ? AND author_id = ? AND status <= 1",
            $desired,
            $rid,
            $tid,
            $user_id
        );
    }

    if ($changed === false) {
        $fail(500, "답글 상태 변경에 실패했습니다.");
    }

    $check = pdo_query(
        "SELECT status FROM reply WHERE rid = ? AND topic_id = ? LIMIT 1",
        $rid,
        $tid
    );
    $expected = $desired;
    $column = "status";
} else {
    if (
        !$isadmin &&
        (
            !$is_topic_owner ||
            $action !== "delete"
        )
    ) {
        $fail(
            403,
            "이 게시물을 관리할 권한이 없습니다."
        );
    }

    if ($action === "sticky") {
        $level = $number("level", true);
        if ($level > 3) {
            $fail(400, "고정 수준은 0부터 3까지 지정할 수 있습니다.");
        }

        $changed = pdo_query(
            "UPDATE topic SET top_level = ? WHERE tid = ? AND status <= 1",
            $level,
            $tid
        );
        $expected = $level;
        $column = "top_level";
    } else {
        $states = array("resume" => 0, "lock" => 1, "delete" => 2);
        if (!array_key_exists($action, $states)) {
            $fail(400, "잘못된 게시물 관리 요청입니다.");
        }

        $expected = $states[$action];
        $column = "status";
        $changed = pdo_query(
            "UPDATE topic SET status = ? WHERE tid = ? AND status <= 1",
            $expected,
            $tid
        );
    }

    if ($changed === false) {
        $fail(500, "게시물 상태 변경에 실패했습니다.");
    }

    $check = pdo_query(
        "SELECT status, top_level FROM topic WHERE tid = ? LIMIT 1",
        $tid
    );
}

if ($check === false) {
    $fail(500, "변경 결과 조회에 실패했습니다.");
}
if (!isset($check[0]) || (int)$check[0][$column] !== $expected) {
    $fail(409, "변경 결과를 확인할 수 없습니다. 새로고침 후 상태를 확인해 주세요.");
}

$params = array();

if ($target === "thread" && $action === "delete") {
    $destination = "discuss.php";
    if ($pid > 0) {
        $params["pid"] = $pid;
    }
} else {
    $destination = "thread.php";
    $params["tid"] = $tid;
}

if ($params) {
    $destination .= "?" . http_build_query($params);
}

header("Location: " . $destination, true, 303);
exit;
