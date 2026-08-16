<?php

require_once('./include/db_info.inc.php');

// 로그인 확인
if (
    !isset($_SESSION[$OJ_NAME.'_user_id'])
) {
    http_response_code(403);
    exit;
}

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;

if ($cid <= 0) {
    http_response_code(400);
    exit;
}


// 관리자 또는 해당 대회 관리자만 허용
$is_admin =
    isset($_SESSION[$OJ_NAME.'_administrator']);

$is_contest_manager =
    isset($_SESSION[$OJ_NAME.'_m'.$cid]);

if (
    !$is_admin &&
    !$is_contest_manager
) {
    http_response_code(403);
    exit;
}


// 중요:
// SSE 연결이 세션을 계속 잠그지 않도록 한다.
session_write_close();


header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// SSE 응답이 PHP 출력 버퍼에 머물지 않도록 처리
while (ob_get_level() > 0) {
    @ob_end_flush();
}

// 브라우저가 연결 종료를 확인할 수 있도록 함
ignore_user_abort(false);

// EventSource 재연결 간격: 3초
echo "retry: 3000\n\n";

@flush();


// 현재 update_count
$result =
    pdo_query("SELECT
            update_count,
            judge_count
         FROM ranking_cache
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );

$last_count = 0;
$last_judge_count = 0;

if (
    $result &&
    count($result) > 0
) {

    $last_count =
        intval($result[0]['update_count']);

    $last_judge_count =
        intval($result[0]['judge_count']);
}


// 최초 연결 확인 메시지
echo "event: connected\n";
echo "data: ".json_encode(
    array(
        'cid' => $cid,
        'version' => $last_count
    )
)."\n\n";

@flush();


$start_time = time();

$last_heartbeat = time();


while (
    !connection_aborted() &&
    time() - $start_time < 55
) {

    sleep(3);


    // ========================================================
    // Heartbeat
    // - nginx / 브라우저 연결 유지
    // - 끊어진 클라이언트 감지에 도움
    // ========================================================

    if (time() - $last_heartbeat >= 10) {

        echo ": heartbeat\n\n";

        @flush();

        $last_heartbeat = time();


        // heartbeat 출력 후 연결 종료 여부 다시 확인
        if (connection_aborted()) {
            break;
        }
    }


    $result =
        pdo_query(
            "SELECT
                update_count,
                judge_count
            FROM ranking_cache
            WHERE contest_id=?
            LIMIT 1",
            $cid
        );

    $current_count = 0;
    $current_judge_count = 0;
    if (
        $result &&
        count($result) > 0
    ) {

        $current_count =
            intval($result[0]['update_count']);

        $current_judge_count =
            intval($result[0]['judge_count']);
    }


    if ($current_count != $last_count) {

        $last_count = $current_count;

        echo "event: solution_update\n";

        echo "data: ".json_encode(
            array(
                'cid' => $cid,
                'version' => $current_count
            )
        )."\n\n";

        @flush();
    }
    // ============================================================
    // 최종 채점 결과 변화
    // 랭킹에서만 사용
    // ============================================================

    if ($current_judge_count != $last_judge_count) {

        $last_judge_count = $current_judge_count;

        echo "event: ranking_update\n";

        echo "data: ".json_encode(
            array(
                'cid' => $cid,
                'version' => $current_judge_count
            )
        )."\n\n";

        @flush();
    }
}