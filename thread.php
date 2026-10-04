<?php
require_once("discuss_func.inc.php");

$tid =
    isset($_GET['tid'])
        ? intval($_GET['tid'])
        : 0;

if ($tid <= 0) {
    http_response_code(400);
    exit("올바르지 않은 게시물 번호입니다.");
}

$sql = "SELECT
                    t.`title`,
                    t.`author_id`,
                    t.`pid`,
                    t.`status`,
                    t.`top_level`
              FROM `topic` t
             WHERE t.`tid` = ? AND t.`status` <= 1";
$result = pdo_query($sql, $tid);

if (count($result) === 0) {
    http_response_code(404);
    exit("게시물을 찾을 수 없습니다.");
}

$row = $result[0];

$topic_title = $row['title'];
$topic_author_id = (string)$row['author_id'];
$topic_pid = intval($row['pid']);
$topic_status = intval($row['status']);
$topic_level = intval($row['top_level']);

$pid = $topic_pid;

$isadmin = isset($_SESSION[$OJ_NAME . '_' . 'administrator']);
$is_logged_in = isset($_SESSION[$OJ_NAME . '_' . 'user_id']);

$is_topic_owner =
    $is_logged_in &&
    strcasecmp(
        $topic_author_id,
        (string)$_SESSION[$OJ_NAME . '_user_id']
    ) === 0;

$discuss_params = array();
if ($topic_pid > 0) {
	$discuss_params['pid'] = $topic_pid;
}
$discuss_url = 'discuss.php';
if (!empty($discuss_params)) {
	$discuss_url .= '?' . http_build_query($discuss_params);
}

$newpost_params = array();
if ($topic_pid > 0) {
	$newpost_params['pid'] = $topic_pid;
}
$newpost_url = 'newpost.php';
if (!empty($newpost_params)) {
	$newpost_url .= '?' . http_build_query($newpost_params);
}

// 게시글 본문과 답글을 조회한다.
// reply 테이블의 첫 번째 행은 게시글 본문이다.
$reply_sql = "SELECT
                    `rid`,
                    `author_id`,
                    `time`,
                    `content`,
                    `status`,
                    CASE
                        WHEN `rid` = (
                            SELECT MIN(r0.`rid`)
                            FROM `reply` r0
                            WHERE r0.`topic_id` = ?
                        )
                        THEN 1
                        ELSE 0
                    END AS `is_topic_body`
                FROM `reply`
               WHERE `topic_id` = ?
                 AND `status` <= 1
            ORDER BY `rid`
               LIMIT 30";

$reply_result = pdo_query(
    $reply_sql,
    $tid,
    $tid
);

$reply_rows_cnt = count($reply_result);

require_once(
    "template/$OJ_TEMPLATE/thread.php"
);
