<?php
require_once(__DIR__ . "/admin-init.php");
require_once(__DIR__ . "/../include/const.inc.php");

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

$fail = function ($status, $message) {
    http_response_code($status);
    echo json_encode(array("error" => $message));
    exit;
};

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    $fail(405, "GET 요청만 허용됩니다.");
}

if (!isset($_SESSION[$OJ_NAME . "_administrator"])) {
    $fail(403, "조회 권한이 없습니다.");
}

$job = isset($_SESSION[$OJ_NAME . "_rejudge_monitor"])
    ? $_SESSION[$OJ_NAME . "_rejudge_monitor"]
    : null;

if (
    !isset($_GET["token"]) ||
    !is_string($_GET["token"]) ||
    !is_array($job) ||
    !isset($job["token"], $job["expires"], $job["ids"]) ||
    !hash_equals($job["token"], $_GET["token"]) ||
    time() > $job["expires"] ||
    empty($job["ids"])
) {
    $fail(400, "조회 요청이 만료되었습니다.");
}

// 조회 중 다른 관리자 요청을 막지 않도록 세션 잠금을 해제한다.
session_write_close();

$counts = array();
$found = 0;
$pending = 0;
$finished = 0;
$manual = 0;

foreach (array_chunk($job["ids"], 500) as $chunk) {
    $placeholders = implode(",", array_fill(0, count($chunk), "?"));

    $rows = call_user_func_array(
        "pdo_query",
        array_merge(
            array(
                "SELECT result, COUNT(*) AS cnt FROM solution " .
                "WHERE solution_id IN (" . $placeholders . ") GROUP BY result"
            ),
            $chunk
        )
    );

    if ($rows === false) {
        $fail(500, "채점 상태 조회에 실패했습니다.");
    }

    foreach ($rows as $row) {
        $code = (int)$row["result"];
        $count = (int)$row["cnt"];
        $found += $count;

        if (!isset($counts[$code])) {
            $counts[$code] = 0;
        }

        $counts[$code] += $count;

        if ($code >= 0 && $code <= 3) {
            $pending += $count;
        } elseif ($code === 14) {
            $manual += $count;
        } elseif ($code >= 4 && $code <= 13) {
            $finished += $count;
        } else {
            $pending += $count;
        }
    }
}

ksort($counts);
$distribution = array();

foreach ($counts as $code => $count) {
    $distribution[] = array(
        "code" => $code,
        "label" => isset($judge_result[$code])
            ? $judge_result[$code]
            : "알 수 없는 상태",
        "count" => $count
    );
}

echo json_encode(array(
    "total" => count($job["ids"]),
    "pending" => $pending,
    "finished" => $finished,
    "manual" => $manual,
    "missing" => count($job["ids"]) - $found,
    "settled" => $pending === 0,
    "distribution" => $distribution
));
