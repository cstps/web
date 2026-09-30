<?php
require_once(__DIR__ . "/admin-init.php");
require_once(__DIR__ . "/../include/const.inc.php");

if (
    !isset($_SERVER["REQUEST_METHOD"]) ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    header("Allow: POST");
    http_response_code(405);
    exit("POST 요청만 허용됩니다.");
}

if (!isset($_SESSION[$OJ_NAME . "_administrator"])) {
    http_response_code(403);
    exit("재채점은 최고 관리자만 실행할 수 있습니다.");
}

if (
    !isset($_POST["do"]) ||
    !is_string($_POST["do"]) ||
    $_POST["do"] !== "do"
) {
    http_response_code(400);
    exit("재채점 요청이 올바르지 않습니다.");
}

if (
    !isset($_SESSION[$OJ_NAME . "_postkey"]) ||
    !is_string($_SESSION[$OJ_NAME . "_postkey"]) ||
    !isset($_POST["postkey"]) ||
    !is_string($_POST["postkey"]) ||
    !hash_equals(
        $_SESSION[$OJ_NAME . "_postkey"],
        $_POST["postkey"]
    )
) {
    http_response_code(403);
    exit("요청 확인에 실패했습니다. 화면을 새로고침해 주세요.");
}
?>
<?php
$rejudge_request_fields =
    array("rjpid", "rjsid", "result", "rjcid");

$rejudge_selected_fields = array();

foreach ($rejudge_request_fields as $field) {
    if (array_key_exists($field, $_POST)) {
        $rejudge_selected_fields[] = $field;
    }
}

if (count($rejudge_selected_fields) !== 1) {
    http_response_code(400);
    exit("재채점 대상은 한 종류만 지정해야 합니다.");
}

$rejudge_field = $rejudge_selected_fields[0];

$rejudge_parse_integer = function ($value, $allow_zero) {
    if (
        !is_string($value) ||
        !preg_match('/^[0-9]+$/D', $value)
    ) {
        http_response_code(400);
        exit("번호와 상태값은 정수로 입력해 주세요.");
    }

    $digits = ltrim($value, "0");

    if ($digits === "") {
        $digits = "0";
    }

    if (
        strlen($digits) > 10 ||
        (
            strlen($digits) === 10 &&
            strcmp($digits, "2147483647") > 0
        )
    ) {
        http_response_code(400);
        exit("입력값이 허용 범위를 초과했습니다.");
    }

    $number = (int)$digits;

    if (!$allow_zero && $number === 0) {
        http_response_code(400);
        exit("번호는 1 이상이어야 합니다.");
    }

    return $number;
};

$rejudge_value = $rejudge_parse_integer(
    $_POST[$rejudge_field],
    $rejudge_field === "result"
);

$_POST[$rejudge_field] = (string)$rejudge_value;
if (
    $rejudge_field === "result" &&
    !in_array($rejudge_value, range(0, 14), true)
) {
    http_response_code(400);
    exit("채점 상태값은 0부터 14까지 지정할 수 있습니다.");
}


if (array_key_exists("pid", $_POST)) {
    if ($rejudge_field !== "rjcid") {
        http_response_code(400);
        exit("대회 내 문제 순서는 대회 재채점에서만 지정할 수 있습니다.");
    }

    $_POST["pid"] = (string)$rejudge_parse_integer(
        $_POST["pid"],
        true
    );
}

$rejudge_entity_queries = array(
    "rjpid" => "SELECT problem_id FROM problem WHERE problem_id = ? LIMIT 1",
    "rjsid" => "SELECT solution_id FROM solution WHERE solution_id = ? AND problem_id > 0 LIMIT 1",
    "rjcid" => "SELECT contest_id FROM contest WHERE contest_id = ? LIMIT 1"
);

if (isset($rejudge_entity_queries[$rejudge_field])) {
    $entity_rows = pdo_query(
        $rejudge_entity_queries[$rejudge_field],
        $rejudge_value
    );

    if ($entity_rows === false) {
        http_response_code(500);
        exit("재채점 대상 조회에 실패했습니다.");
    }

    if (!isset($entity_rows[0])) {
        http_response_code(404);
        exit("재채점할 문제, 제출 또는 대회를 찾을 수 없습니다.");
    }
}

if (
    $rejudge_field === "rjcid" &&
    array_key_exists("pid", $_POST)
) {
    $contest_problem_rows = pdo_query(
        "SELECT problem_id FROM contest_problem WHERE contest_id = ? AND num = ? LIMIT 1",
        $rejudge_value,
        (int)$_POST["pid"]
    );

    if ($contest_problem_rows === false) {
        http_response_code(500);
        exit("대회 문제 조회에 실패했습니다.");
    }

    if (!isset($contest_problem_rows[0])) {
        http_response_code(404);
        exit("해당 순서의 대회 문제를 찾을 수 없습니다.");
    }
}
?>
<?php
// 앞 단계에서 검증한 입력으로만 조건을 구성한다.
$where = "problem_id > 0";
$params = array();
$status_url = "../status.php";

switch ($rejudge_field) {
    case "rjpid":
        $where .= " AND problem_id = ?";
        $params[] = $rejudge_value;
        $status_url .= "?problem_id=" . $rejudge_value;
        break;

    case "rjsid":
        $where .= " AND solution_id = ?";
        $params[] = $rejudge_value;
        $status_url .= "?top=" . ($rejudge_value + 1);
        break;

    case "result":
        $where .= " AND result = ?";
        $params[] = $rejudge_value;
        $status_url .= "?jresult=1";
        break;

    case "rjcid":
        $where .= " AND contest_id = ?";
        $params[] = $rejudge_value;

        if (array_key_exists("pid", $_POST)) {
            $where .= " AND num = ?";
            $params[] = (int)$_POST["pid"];
        }

        $status_url .= "?cid=" . $rejudge_value;
        break;

    default:
        http_response_code(400);
        exit("재채점 대상이 올바르지 않습니다.");
}

// 미리보기 단계에서는 DB 변경과 큐 전달을 하지 않는다.
$rejudge_preview_key = $OJ_NAME . "_rejudge_preview";
$rejudge_pid = array_key_exists("pid", $_POST)
    ? (int)$_POST["pid"]
    : null;

if (!array_key_exists("confirm_token", $_POST)) {
    $preview_rows = call_user_func_array(
        "pdo_query",
        array_merge(
            array(
                "SELECT solution_id, problem_id, user_id, result FROM solution WHERE " .
                $where . " ORDER BY solution_id"
            ),
            $params
        )
    );

    if ($preview_rows === false) {
        http_response_code(500);
        exit("재채점 대상 조회에 실패했습니다.");
    }

    $preview_ids = array();
    $preview_result_counts = array();

    foreach ($preview_rows as $row) {
        $preview_ids[] = (int)$row["solution_id"];
        $previous_result = (int)$row["result"];

        if (!isset($preview_result_counts[$previous_result])) {
            $preview_result_counts[$previous_result] = 0;
        }

        $preview_result_counts[$previous_result]++;
    }

    ksort($preview_result_counts);

    if (count($preview_ids) === 0) {
        http_response_code(400);
        exit("조건에 해당하는 재채점 대상 제출이 없습니다.");
    }

    $confirm_token = bin2hex(random_bytes(32));

    $_SESSION[$rejudge_preview_key] = array(
        "token" => $confirm_token,
        "created_at" => time(),
        "field" => $rejudge_field,
        "value" => $rejudge_value,
        "pid" => $rejudge_pid,
        "ids" => $preview_ids
    );

    $scope_labels = array(
        "rjpid" => "문제별",
        "rjsid" => "제출별",
        "result" => "채점 상태별",
        "rjcid" => "대회별"
    );

    $scope = $scope_labels[$rejudge_field];
    $target_label = (string)$rejudge_value;

    if ($rejudge_field === "result") {
        $target_label .= " · " . $judge_result[$rejudge_value];
    }

    if ($rejudge_pid !== null) {
        $scope = "대회 내 문제별";
        $target_label .= " · 문제 순서 " . $rejudge_pid . " (0부터 시작)";
    }

    $escape = function ($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    };

    $admin_page_title = "재채점 대상 확인";
    $admin_active_menu = "rejudge";
    $admin_page_head_file = __DIR__ . "/rejudge-head.php";
require(__DIR__ . "/admin-layout-start.php");
?>
<div class="admin-page rejudge-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">재채점 대상 확인</h1>
            <div class="admin-page-description">
                아래 대상을 확인한 뒤 재채점 요청을 실행하세요.
            </div>
        </div>
    </div>

    <table>
        <tbody>
            <tr>
                <th scope="row">재채점 범위</th>
                <td><?php echo $escape($scope); ?></td>
            </tr>
            <tr>
                <th scope="row">지정한 대상</th>
                <td><?php echo $escape($target_label); ?></td>
            </tr>
            <?php if (count($preview_rows) === 1) {
                $previous = $preview_rows[0];
                $previous_code = (int)$previous["result"];
                $previous_label = isset($judge_result[$previous_code])
                    ? $judge_result[$previous_code]
                    : "알 수 없는 상태";
            ?>
                <tr>
                    <th scope="row">문제 번호</th>
                    <td><?php echo (int)$previous["problem_id"]; ?></td>
                </tr>
                <tr>
                    <th scope="row">제출자</th>
                    <td><?php echo $escape($previous["user_id"]); ?></td>
                </tr>
                <tr>
                    <th scope="row">기존 채점 결과</th>
                    <td>
                        <strong><?php echo $escape($previous_label); ?></strong>
                        (<?php echo $previous_code; ?>)
                    </td>
                </tr>
            <?php } else { ?>
                <tr>
                    <th scope="row">기존 결과별 건수</th>
                    <td>
                        <?php foreach ($preview_result_counts as $code => $count) {
                            $label = isset($judge_result[$code])
                                ? $judge_result[$code]
                                : "알 수 없는 상태";
                        ?>
                            <div>
                                <?php echo $escape($label); ?>
                                (<?php echo (int)$code; ?>):
                                <strong><?php echo (int)$count; ?>건</strong>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>

            <tr>
                <th scope="row">대상 제출 수</th>
                <td><strong><?php echo count($preview_ids); ?>건</strong></td>
            </tr>
            <tr>
                <th scope="row">제출 번호 범위</th>
                <td>
                    <?php echo $preview_ids[0]; ?>
                    ~
                    <?php echo $preview_ids[count($preview_ids) - 1]; ?>
                    (범위 내에서 조건에 맞는 제출만 포함)
                </td>
            </tr>
        </tbody>
    </table>

    <p>
        확인한 제출만 재채점합니다.
        확인 이후 추가된 제출은 포함하지 않습니다.
        확인 요청은 15분 동안 유효하며, 새 미리보기를 열면 이전 요청은 만료됩니다.
    </p>

    <form action="rejudge_update.php" method="post">
        <input type="hidden" name="do" value="do">
        <input type="hidden" name="postkey"
               value="<?php echo $escape($_SESSION[$OJ_NAME . "_postkey"]); ?>">
        <input type="hidden" name="confirm_token"
               value="<?php echo $escape($confirm_token); ?>">
        <input type="hidden"
               name="<?php echo $escape($rejudge_field); ?>"
               value="<?php echo $rejudge_value; ?>">

        <?php if ($rejudge_pid !== null) { ?>
            <input type="hidden" name="pid"
                   value="<?php echo $rejudge_pid; ?>">
        <?php } ?>

        <button type="submit">확인한 대상 재채점 요청</button>
        <a href="rejudge.php">취소하고 돌아가기</a>
    </form>
</div>
<?php
    require(__DIR__ . "/admin-layout-end.php");
    exit;
}

$confirmed = isset($_SESSION[$rejudge_preview_key])
    ? $_SESSION[$rejudge_preview_key]
    : null;

if (
    !is_string($_POST["confirm_token"]) ||
    !is_array($confirmed) ||
    !isset($confirmed["token"], $confirmed["created_at"]) ||
    !hash_equals($confirmed["token"], $_POST["confirm_token"]) ||
    time() - $confirmed["created_at"] > 900 ||
    $confirmed["field"] !== $rejudge_field ||
    $confirmed["value"] !== $rejudge_value ||
    $confirmed["pid"] !== $rejudge_pid ||
    empty($confirmed["ids"])
) {
    http_response_code(400);
    exit("확인 요청이 만료되었거나 대상이 변경되었습니다. 재채점 화면에서 다시 확인해 주세요.");
}

$confirmed_ids = $confirmed["ids"];

// 실행 시도마다 토큰을 소비하여 새로고침·중복 요청을 차단한다.
unset($_SESSION[$rejudge_preview_key]);

$redis = null;
$transaction_started = false;
$target_ids = array();
$warnings = array();
$queued_count = 0;

// Redis 연결 실패는 DB 변경 전에 확인한다.
try {
    if (!empty($OJ_REDIS)) {
        if (!class_exists("Redis")) {
            throw new RuntimeException("Redis extension unavailable");
        }

        $redis = new Redis();

        if (!$redis->connect($OJ_REDISSERVER, $OJ_REDISPORT, 3.0)) {
            throw new RuntimeException("Redis connection failed");
        }

        $redis->setOption(Redis::OPT_READ_TIMEOUT, 3.0);

        if (
            isset($OJ_REDISAUTH) &&
            $OJ_REDISAUTH !== "" &&
            $OJ_REDISAUTH !== null &&
            $OJ_REDISAUTH !== false &&
            !$redis->auth($OJ_REDISAUTH)
        ) {
            throw new RuntimeException("Redis authentication failed");
        }

        if ($redis->ping() === false) {
            throw new RuntimeException("Redis ping failed");
        }
    }

    // DB 연결을 확보하고, 조회 실패와 대상 없음은 구분한다.
    $query_args = array_merge(
        array(
            "SELECT solution_id FROM solution WHERE " .
            $where . " LIMIT 1"
        ),
        $params
    );

    $probe = call_user_func_array("pdo_query", $query_args);

    if ($probe === false) {
        throw new RuntimeException("Target lookup failed");
    }

    if (!isset($probe[0])) {
        if ($redis !== null) {
            $redis->close();
        }

        http_response_code(400);
        exit("조건에 해당하는 재채점 대상 제출이 없습니다.");
    }

    // solution은 확인된 InnoDB 테이블이다.
    if (!$dbh->beginTransaction()) {
        throw new RuntimeException("Transaction start failed");
    }

    $transaction_started = true;

    // 미리보기에서 보관한 번호만 잠그고 다시 확인한다.
    // 대상의 삭제 또는 조건 변경이 있으면 전체 실행을 중단한다.
    foreach (array_chunk($confirmed_ids, 500) as $chunk) {
        $placeholders = implode(",", array_fill(0, count($chunk), "?"));

        $target_rows = call_user_func_array(
            "pdo_query",
            array_merge(
                array(
                    "SELECT solution_id FROM solution WHERE " .
                    $where . " AND solution_id IN (" .
                    $placeholders . ") ORDER BY solution_id FOR UPDATE"
                ),
                $params,
                $chunk
            )
        );

        if ($target_rows === false) {
            throw new RuntimeException("Confirmed target lookup failed");
        }

        if (count($target_rows) !== count($chunk)) {
            throw new RuntimeException("Confirmed targets changed; preview again");
        }

        foreach ($target_rows as $row) {
            $target_ids[] = (int)$row["solution_id"];
        }
    }

    if (count($target_ids) === 0) {
        throw new RuntimeException("Targets changed before update");
    }

    // 확정한 제출 번호만 변경한다.
    foreach (array_chunk($target_ids, 500) as $chunk) {
        $placeholders = implode(",", array_fill(0, count($chunk), "?"));

        $updated = call_user_func_array(
            "pdo_query",
            array_merge(
                array(
                    "UPDATE solution SET result = 1 " .
                    "WHERE problem_id > 0 AND solution_id IN (" .
                    $placeholders . ")"
                ),
                $chunk
            )
        );

        if ($updated === false) {
            throw new RuntimeException("Solution update failed");
        }
    }

    if (!$dbh->commit()) {
        throw new RuntimeException("Transaction commit failed");
    }

    $transaction_started = false;
} catch (Throwable $e) {
    if ($transaction_started && $dbh->inTransaction()) {
        try {
            $dbh->rollBack();
        } catch (Throwable $rollback_error) {
            error_log("[rejudge rollback] " . $rollback_error->getMessage());
        }
    }

    if ($redis !== null) {
        try {
            $redis->close();
        } catch (Throwable $close_error) {
            error_log("[rejudge redis close] " . $close_error->getMessage());
        }
    }

    error_log("[rejudge prepare/update] " . $e->getMessage());
    http_response_code(500);
    exit("재채점 대상 확정 또는 DB 변경에 실패했습니다. 서버 로그를 확인해 주세요.");
}

// sim은 저장 엔진이 아직 확인되지 않았으므로,
// solution 트랜잭션과 함께 롤백된다고 가정하지 않는다.
foreach (array_chunk($target_ids, 500) as $chunk) {
    $placeholders = implode(",", array_fill(0, count($chunk), "?"));

    $deleted = call_user_func_array(
        "pdo_query",
        array_merge(
            array(
                "DELETE FROM sim WHERE s_id IN (" .
                $placeholders . ")"
            ),
            $chunk
        )
    );

    if ($deleted === false) {
        $warnings[] =
            "일부 유사도 기록 정리에 실패했습니다. 서버 로그를 확인해 주세요.";
        break;
    }
}

// 전체 대기 제출을 다시 조회하지 않는다.
if ($redis !== null) {
    try {
        foreach ($target_ids as $solution_id) {
            if ($redis->lPush($OJ_REDISQNAME, $solution_id) === false) {
                throw new RuntimeException("Redis queue write failed");
            }

            $queued_count++;
        }
    } catch (Throwable $e) {
        error_log("[rejudge redis queue] " . $e->getMessage());
        $warnings[] =
            "Redis 큐 전달을 완료하지 못했습니다. DB는 재채점 대기 상태이며, " .
            "큐 전달 성공 응답은 " . $queued_count . "건입니다. " .
            "전송 중 연결이 끊긴 항목은 실제 큐 반영 여부를 별도로 확인해야 합니다.";
    } finally {
        try {
            $redis->close();
        } catch (Throwable $e) {
            error_log("[rejudge redis close] " . $e->getMessage());
        }
    }
}

// 재채점 전용 UDP 알림: 실패 시 반복하지 않고 예외로 종료한다.
$rejudge_notify_udp = function ($solution_id) {
    global $OJ_UDPSERVER, $OJ_UDPPORT, $OJ_JUDGE_HUB_PATH;

    if (!function_exists("socket_create")) {
        throw new RuntimeException("Sockets extension unavailable");
    }

    $servers = explode(",", (string)$OJ_UDPSERVER);
    $server = trim($servers[$solution_id % count($servers)]);
    $host = $server;
    $port = (int)$OJ_UDPPORT;

    // 기존 설정의 host:port 형식을 유지한다.
    if (strpos($server, ":") !== false) {
        $parts = explode(":", $server);

        if (count($parts) !== 2) {
            throw new RuntimeException("Invalid UDP server format");
        }

        $host = trim($parts[0]);
        $port = (int)$parts[1];
    }

    if ($host === "" || $port < 1 || $port > 65535) {
        throw new RuntimeException("Invalid UDP destination");
    }

    $message = isset($OJ_JUDGE_HUB_PATH)
        ? (string)$OJ_JUDGE_HUB_PATH
        : (string)$solution_id;

    if ($message === "") {
        throw new RuntimeException("Empty UDP message");
    }

    $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

    if ($socket === false) {
        throw new RuntimeException("UDP socket creation failed");
    }

    try {
        if (!@socket_set_option(
            $socket,
            SOL_SOCKET,
            SO_SNDTIMEO,
            array("sec" => 2, "usec" => 0)
        )) {
            throw new RuntimeException("UDP timeout configuration failed");
        }

        // UDP 메시지는 한 개의 데이터그램으로 전송한다.
        $sent = @socket_sendto(
            $socket,
            $message,
            strlen($message),
            0,
            $host,
            $port
        );

        if ($sent === false || $sent !== strlen($message)) {
            throw new RuntimeException("UDP send failed");
        }
    } finally {
        socket_close($socket);
    }
};

if (!empty($OJ_UDP)) {
    try {
        foreach ($target_ids as $solution_id) {
            $rejudge_notify_udp($solution_id);
        }
    } catch (Throwable $e) {
        error_log("[rejudge udp] " . $e->getMessage());
        $warnings[] =
            "채점기 알림 호출 중 오류가 발생했습니다. DB의 대기 상태와 채점기 로그를 확인해 주세요.";
    }
}

// 큐 처리까지 마친 뒤 결과를 표시한다.
// 이것은 채점 완료가 아니라 재채점 대기 상태 등록 결과다.
$monitor_token = bin2hex(random_bytes(32));
$_SESSION[$OJ_NAME . "_rejudge_monitor"] = array(
    "token" => $monitor_token,
    "expires" => time() + 1800,
    "ids" => $target_ids
);

$admin_page_title = "재채점 요청 결과";
$admin_active_menu = "rejudge";
$admin_page_head_file = __DIR__ . "/rejudge-head.php";
require(__DIR__ . "/admin-layout-start.php");
?>
<div class="admin-page rejudge-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">재채점 요청 결과</h1>
        </div>
    </div>

    <p>
        재채점 대기 상태로 등록한 제출:
        <strong><?php echo count($target_ids); ?>건</strong>
    </p>

    <div
        id="rejudge-live-status"
        data-token="<?php
            echo htmlspecialchars($monitor_token, ENT_QUOTES, "UTF-8");
        ?>"
        role="status"
        aria-live="polite"
        style="margin:20px 0">
        <p data-status-text style="white-space:pre-line">채점 상태를 조회하고 있습니다.</p>
        <button type="button">상태 다시 조회</button>
    </div>

    <?php foreach ($warnings as $warning) { ?>
        <p role="alert">
            <?php echo htmlspecialchars($warning, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php } ?>

    <p>
        <a href="<?php
            echo htmlspecialchars($status_url, ENT_QUOTES, "UTF-8");
        ?>">채점기록 확인</a>
        ·
        <a href="rejudge.php">재채점 화면으로 돌아가기</a>
    </p>
</div>
<?php
require(__DIR__ . "/admin-layout-end.php");
