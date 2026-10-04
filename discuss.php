<?php
require_once("discuss_func.inc.php");

$pid = isset($_GET['pid'])
        ? intval($_GET['pid'])
        : 0;

$prob_exist = problem_exist($pid, '');
$isadmin = isset(
        $_SESSION[$OJ_NAME . '_' . 'administrator']
);

echo "<title>1024 Online Judge WebBoard</title>";

$current_problem_label =
        $pid > 0
                ? (string)$pid
                : '';

if ($pid > 0) {
        $board_title = '문제 토론';
        $board_description =
                '풀이 아이디어와 질문을 나누는 문제별 게시판입니다.';
} else {
        $board_title = '전체 게시판';
        $board_description =
                '1024 Online Judge 사용자들과 질문과 정보를 나눠 보세요.';
}

$newpost_params = array();

if ($pid > 0) {
        $newpost_params['pid'] = $pid;
}

$newpost_url = 'newpost.php';

if (!empty($newpost_params)) {
        $newpost_url .=
                '?' .
                http_build_query($newpost_params);
}

$sql = "SELECT `tid`, `title`, `top_level`, `t`.`status`, `pid`,
                   CONVERT(
                       (
                           SELECT MIN(rp.time)
                           FROM reply rp
                           WHERE rp.topic_id = t.tid
                       ),
                       DATE
                   ) AS posttime,
                   MAX(r.time) AS lastupdate, `t`.`author_id`, SUM(
                       CASE
                           WHEN r.rid IS NOT NULL
                            AND r.rid <> (
                                SELECT MIN(r0.rid)
                                FROM reply r0
                                WHERE r0.topic_id = t.tid
                            )
                           THEN 1
                           ELSE 0
                       END
                   ) AS reply_count
              FROM `topic` t
         LEFT JOIN `reply` r
                    ON t.tid = r.topic_id
                   AND r.status <= 1
             WHERE `t`.`status` != 2";

if (array_key_exists('pid', $_REQUEST) && $_REQUEST['pid'] !== '') {
	$sql .= " AND (`pid` = '" . intval($_REQUEST['pid']) . "' OR `top_level` >= 2)";
	$level = '';
} else {
	$level = " - (`top_level` = 1)";
}

$sql .= " GROUP BY t.tid";
$sql .= " ORDER BY t.top_level DESC, MAX(r.time) DESC";
$sql .= " LIMIT 30";

$result = pdo_query($sql);
$rows_cnt = count($result);

require_once(
    "template/$OJ_TEMPLATE/discuss.php"
);
