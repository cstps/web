<?php
        require_once("discuss_func.inc.php");
        require_once("include/db_info.inc.php");
        require_once("include/setlang.php");
        if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
                $view_errors = $MSG_Login;
                require("template/".$OJ_TEMPLATE."/error.php");
                exit(0);
        }

        if (
                isset($_POST['content']) &&
                strlen((string)$_POST['content']) > 5000
        ) {
                $view_errors =
                        "내용은 5000자를 초과할 수 없습니다.";

                require(
                        "template/" .
                        $OJ_TEMPLATE .
                        "/error.php"
                );

                exit(0);
        }

        if (
                isset($_POST['title']) &&
                mb_strlen(
                        (string)$_POST['title'],
                        'UTF-8'
                ) > 60
        ) {
                $view_errors =
                        "제목은 60자를 초과할 수 없습니다.";

                require(
                        "template/" .
                        $OJ_TEMPLATE .
                        "/error.php"
                );

                exit(0);
        }

        $tid = null;

        $action =
                isset($_GET['action'])
                        ? (string)$_GET['action']
                        : '';

        if ($action === 'new') {

                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                        http_response_code(405);
                        header('Allow: POST');
                        exit('POST 요청만 허용합니다.');
                }

                $session_key =
                        $_SESSION[$OJ_NAME . '_postkey']
                        ?? null;

                $post_key =
                        $_POST['postkey']
                        ?? null;

                if (
                        !is_string($session_key) ||
                        $session_key === '' ||
                        !is_string($post_key) ||
                        !hash_equals(
                                $session_key,
                                $post_key
                        )
                ) {
                        http_response_code(403);
                        exit(
                                '요청 확인에 실패했습니다. ' .
                                '새 글 작성 화면을 새로고침한 뒤 다시 시도해 주세요.'
                        );
                }
                $title =
                        isset($_POST['title'])
                                ? trim((string)$_POST['title'])
                                : '';

                $content =
                        isset($_POST['content'])
                                ? trim((string)$_POST['content'])
                                : '';

                if ($title === '' || $content === '') {
                        exit('제목과 내용을 모두 입력해 주세요.');
                }

                // 현재 게시판은 전체 게시판 또는 일반 문제 게시판만 사용한다.
                // Contest 전용 게시판은 사용하지 않으므로 cid는 항상 0으로 저장한다.
                $cid = 0;

                $problem_input =
                        isset($_POST['pid'])
                                ? trim((string)$_POST['pid'])
                                : '';

                $pid = 0;

                // 문제번호를 입력한 경우 실제 problem_id인지 확인한다.
                if ($problem_input !== '') {
                        if (!ctype_digit($problem_input)) {
                                http_response_code(400);
                                exit(
                                        '문제번호는 숫자로 입력해 주세요.'
                                );
                        }

                        $requested_pid =
                                intval($problem_input);

                        if ($requested_pid <= 0) {
                                http_response_code(400);
                                exit(
                                        '올바른 문제번호를 입력해 주세요.'
                                );
                        }

                        $problem_rows =
                                pdo_query(
                                        "SELECT problem_id
                                           FROM problem
                                          WHERE problem_id = ?
                                          LIMIT 1",
                                        $requested_pid
                                );

                        if (!isset($problem_rows[0])) {
                                http_response_code(400);
                                exit(
                                        '존재하지 않는 문제입니다.'
                                );
                        }

                        $pid = $requested_pid;
                }

                $sql =
                        "INSERT INTO topic
                        (
                                title,
                                author_id,
                                cid,
                                pid
                        )
                        VALUES
                        (
                                ?,
                                ?,
                                ?,
                                ?
                        )";

                $rows =
                        pdo_query(
                                $sql,
                                $title,
                                $_SESSION[$OJ_NAME . '_user_id'],
                                $cid,
                                $pid
                        );

                if (!$rows) {
                        exit('게시글을 등록하지 못했습니다.');
                }

                $tid = $rows;
        }


        if ($action === 'reply' || !is_null($tid)) {

                if (
                        $action === 'reply' &&
                        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
                ) {
                        http_response_code(405);
                        header('Allow: POST');
                        exit('POST 요청만 허용합니다.');
                }

                // 새 글 등록 직후 자동 답글 생성이 아닌
                // 일반 답글 등록 요청에만 postkey를 검사한다.
                if ($action === 'reply') {

                        $session_key =
                                $_SESSION[$OJ_NAME . '_postkey']
                                ?? null;

                        $post_key =
                                $_POST['postkey']
                                ?? null;

                        if (
                                !is_string($session_key) ||
                                $session_key === '' ||
                                !is_string($post_key) ||
                                !hash_equals(
                                        $session_key,
                                        $post_key
                                )
                        ) {
                                http_response_code(403);
                                exit(
                                        '요청 확인에 실패했습니다. ' .
                                        '게시물을 새로고침한 뒤 다시 시도해 주세요.'
                                );
                        }
                }

                if (is_null($tid)) {
                        $tid =
                                isset($_POST['tid'])
                                        ? intval($_POST['tid'])
                                        : 0;
                }

                $content =
                        isset($_POST['content'])
                                ? trim((string)$_POST['content'])
                                : '';

                if ($tid <= 0 || $content === '') {
                        echo '답글 내용을 입력해 주세요.';
                }
                else {

                        // 삭제되지 않았고 답글이 잠기지 않은
                        // 정상 게시물에만 답글을 등록한다.
                        $rows =
                                pdo_query(
                                        "SELECT
                                                tid,
                                                status
                                         FROM topic
                                         WHERE tid = ?
                                         LIMIT 1",
                                        $tid
                                );

                        if (!isset($rows[0])) {
                                echo '존재하지 않는 게시물입니다.';
                        }
                        else if (
                                intval($rows[0]['status']) !== 0
                        ) {
                                http_response_code(403);
                                echo '현재 이 게시물에는 답글을 작성할 수 없습니다.';
                        }
                        else {

                                $ip =
                                        $_SERVER['REMOTE_ADDR']
                                        ?? '';

                                if (
                                        !empty(
                                                $_SERVER[
                                                        'HTTP_X_FORWARDED_FOR'
                                                ]
                                        )
                                ) {
                                        $tmp_ip =
                                                explode(
                                                        ',',
                                                        $_SERVER[
                                                                'HTTP_X_FORWARDED_FOR'
                                                        ]
                                                );

                                        $ip =
                                                trim(
                                                        (string)$tmp_ip[0]
                                                );
                                }

                                $sql =
                                        "INSERT INTO reply
                                        (
                                                author_id,
                                                time,
                                                content,
                                                topic_id,
                                                ip
                                        )
                                        VALUES
                                        (
                                                ?,
                                                NOW(),
                                                ?,
                                                ?,
                                                ?
                                        )";

                                if (
                                        pdo_query(
                                                $sql,
                                                $_SESSION[
                                                        $OJ_NAME .
                                                        '_user_id'
                                                ],
                                                $content,
                                                $tid,
                                                $ip
                                        )
                                ) {

                                        $location =
                                                'thread.php?tid=' .
                                                intval($tid);

                                        if (
                                                isset($_REQUEST['cid'])
                                        ) {
                                                $location .=
                                                        '&cid=' .
                                                        intval(
                                                                $_REQUEST[
                                                                        'cid'
                                                                ]
                                                        );
                                        }

                                        header(
                                                'Location: ' .
                                                $location
                                        );

                                        exit(0);
                                }
                                else {
                                        $view_errors =
                                                '답글 등록에 실패했습니다.';

                                        require(
                                                "template/" .
                                                $OJ_TEMPLATE .
                                                "/error.php"
                                        );

                                        exit(0);
                                }
                        }
                }
        }

?>
