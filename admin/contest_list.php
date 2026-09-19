<?php

require_once(__DIR__ . "/admin-init.php");
require_once("../include/set_get_key.php");
require_once("../include/set_post_key.php");


if (
    !isset($_SESSION[$OJ_NAME . '_administrator']) &&
    !isset($_SESSION[$OJ_NAME . '_contest_creator'])
) {

    echo "<a href='../loginpage.php'>Please Login First!</a>";
    exit;
}


if (isset($OJ_LANG)) {
    require_once("../lang/$OJ_LANG.php");
}


$current_user_id =
    isset($_SESSION[$OJ_NAME . '_user_id'])
    ? $_SESSION[$OJ_NAME . '_user_id']
    : '';

$is_admin =
    isset($_SESSION[$OJ_NAME . '_administrator']);


// ============================================================
// 1. 보기 모드
//
// 기본: 내가 관리하는 대회
// ?view=all : 전체 대회
// ?view=archived : 보관된 대회
//
// 기존 ?my=1 링크도 전체 대회로 호환
// ============================================================

$requested_view =
    isset($_GET['view']) &&
    is_scalar($_GET['view'])
    ? trim((string)$_GET['view'])
    : '';

if (isset($_GET['my'])) {
    $requested_view = 'all';
}

$view_mode =
    in_array(
        $requested_view,
        array('mine', 'all', 'archived'),
        true
    )
    ? $requested_view
    : 'mine';


// ============================================================
// 2. 정렬
// ============================================================

$valid_cols = array(
    'contest_id',
    'title',
    'start_time',
    'end_time',
    'private',
    'defunct',
    'is_stopped',
    'codevisible',
    'allow_copy'
);

$orderby =
    isset($_GET['orderby']) &&
    in_array(
        $_GET['orderby'],
        $valid_cols,
        true
    )
    ? $_GET['orderby']
    : 'contest_id';

$order =
    isset($_GET['order']) &&
    $_GET['order'] === 'asc'
    ? 'asc'
    : 'desc';


// ============================================================
// 3. 검색
// ============================================================

$keyword =
    isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$has_keyword =
    $keyword !== '';

$keyword_like =
    "%" . $keyword . "%";


// ============================================================
// 4. 내가 관리하는 Contest ID
//
// 세션 m{cid} + DB privilege m{cid}
// ============================================================

$my_cids = array();


foreach ($_SESSION as $key => $val) {

    if (
        $val &&
        preg_match(
            "/^" . preg_quote($OJ_NAME, "/") . "_m(\d+)$/",
            $key,
            $matches
        )
    ) {

        $my_cids[] =
            intval($matches[1]);
    }
}


if ($current_user_id !== '') {

    $managed_rows = pdo_query(
        "SELECT DISTINCT rightstr
     FROM privilege
     WHERE user_id = ?
       AND rightstr LIKE 'm%'
       AND valuestr = 'true'
       AND defunct = 'N'",
        $current_user_id
    );


    if (is_array($managed_rows)) {

        foreach ($managed_rows as $managed) {

            $rightstr =
                isset($managed['rightstr'])
                ? $managed['rightstr']
                : '';

            if (
                preg_match(
                    '/^m(\d+)$/',
                    $rightstr,
                    $matches
                )
            ) {

                $my_cids[] =
                    intval($matches[1]);
            }
        }
    }
}


$my_cids =
    array_values(
        array_unique(
            array_filter(
                $my_cids,
                function ($cid) {
                    return intval($cid) > 0;
                }
            )
        )
    );


$in_clause =
    empty($my_cids)
    ? '0'
    : implode(
        ',',
        array_map(
            'intval',
            $my_cids
        )
    );


// ============================================================
// 5. WHERE 절 구성
// ============================================================

$where_parts =
    array();

$params =
    array();


if (
    $view_mode !== 'all' &&
    !$is_admin
) {

    $where_parts[] =
        "contest_id IN ($in_clause)";
}


$where_parts[] =
    $view_mode === 'archived'
    ? 'is_archived = 1'
    : 'is_archived = 0';


if ($has_keyword) {

    if (ctype_digit($keyword)) {

        $where_parts[] =
            "(
                contest_id = ?
                OR title LIKE ?
                OR description LIKE ?
            )";

        $params[] =
            intval($keyword);

        $params[] =
            $keyword_like;

        $params[] =
            $keyword_like;
    } else {

        $where_parts[] =
            "(
                title LIKE ?
                OR description LIKE ?
            )";

        $params[] =
            $keyword_like;

        $params[] =
            $keyword_like;
    }
}


$where_sql =
    empty($where_parts)
    ? ''
    : " WHERE " . implode(
        " AND ",
        $where_parts
    );


// ============================================================
// 6. 페이징
// ============================================================

$count_rows =
    pdo_query(
        "SELECT COUNT(*) AS cnt
         FROM contest" .
            $where_sql,
        ...$params
    );

$total_contests =
    isset($count_rows[0]['cnt'])
    ? intval($count_rows[0]['cnt'])
    : 0;

$per_page =
    50;

$total_pages =
    max(
        1,
        intval(
            ceil(
                $total_contests /
                    $per_page
            )
        )
    );

$page =
    isset($_GET['page'])
    ? max(
        1,
        intval($_GET['page'])
    )
    : 1;

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset =
    ($page - 1) *
    $per_page;


// ============================================================
// 7. Contest 목록
// ============================================================

$sql =
    "SELECT
        contest_id,
        title,
        start_time,
        end_time,
        private,
        defunct,
        is_stopped,
        is_archived,
        archived_at,
        archived_by,
        codevisible,
        allow_copy,
        user_id
     FROM contest" .
    $where_sql .
    " ORDER BY `" . $orderby . "` " . $order .
    " LIMIT " . $offset . ", " . $per_page;


$result =
    pdo_query(
        $sql,
        ...$params
    );

if (!is_array($result)) {
    $result = array();
}

// ============================================================
// 삭제 Form용 POST Key
// ============================================================

ob_start();
require("../include/set_post_key.php");
$contest_delete_csrf_input = ob_get_clean();


// ============================================================
// 정렬 링크 함수
// ============================================================

function contest_list_sort_th(
    $col,
    $label,
    $cur_col,
    $cur_order,
    $base
) {

    if ($col === $cur_col) {

        $next =
            $cur_order === 'asc'
            ? 'desc'
            : 'asc';

        $arrow =
            $cur_order === 'asc'
            ? ' ▲'
            : ' ▼';
    } else {

        $next =
            'desc';

        $arrow =
            '';
    }


    return
        "<th><a href=\"" .
        htmlspecialchars(
            $base .
                "&orderby=" .
                urlencode($col) .
                "&order=" .
                urlencode($next),
            ENT_QUOTES,
            'UTF-8'
        ) .
        "\">" .
        htmlspecialchars(
            $label . $arrow,
            ENT_QUOTES,
            'UTF-8'
        ) .
        "</a></th>";
}


function contest_list_setting_control(
    $cid,
    $setting,
    $label,
    $badge_class,
    $can_edit,
    $postkey
) {
    $safe_label = htmlspecialchars(
        $label,
        ENT_QUOTES,
        'UTF-8'
    );

    $safe_class =
        $badge_class === 'ok'
        ? 'ok'
        : 'no';

    if (!$can_edit) {
        echo '<span class="contest-list-badge ' .
            $safe_class . '">' .
            $safe_label .
            '</span>';
        return;
    }

    echo '<form method="post" ' .
        'action="contest_setting_change.php" ' .
        'class="contest-setting-form">';

    echo '<input type="hidden" name="cid" value="' .
        intval($cid) . '">';

    echo '<input type="hidden" name="setting" value="' .
        htmlspecialchars(
            $setting,
            ENT_QUOTES,
            'UTF-8'
        ) . '">';

    echo '<input type="hidden" name="postkey" value="' .
        htmlspecialchars(
            $postkey,
            ENT_QUOTES,
            'UTF-8'
        ) . '">';

    echo '<button type="submit" ' .
        'class="contest-list-badge ' .
        $safe_class . '" ' .
        'title="클릭하여 설정 변경">' .
        $safe_label .
        '</button>';

    echo '</form>';
}


// ============================================================
// 기본 URL
// ============================================================

$base_params =
    array(
        'view' =>
        $view_mode
    );


if ($has_keyword) {

    $base_params['keyword'] =
        $keyword;
}


$base =
    'contest_list.php?' .
    http_build_query(
        $base_params
    );

$admin_page_title =
    '대회 관리';

$admin_active_menu =
    'contest_list';

$admin_page_head_file =
    __DIR__ . '/contest-list-head.php';

require(
    __DIR__ . '/admin-layout-start.php'
);
?>

    <div class="contest-list-wrap">

        <div class="contest-list-header">
            <h3>
                <?php echo $MSG_CONTEST; ?> - <?php echo $MSG_LIST; ?>
            </h3>
        </div>


        <!-- ========================================================
       검색 / 보기
       ======================================================== -->

        <div class="contest-list-toolbar">

            <form
                action="contest_list.php"
                method="get"
                class="contest-list-search">

                <input
                    type="hidden"
                    name="view"
                    value="<?php
                            echo htmlspecialchars(
                                $view_mode,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>">

                <input
                    type="text"
                    name="keyword"
                    placeholder="대회 번호, 제목 또는 설명 검색"
                    value="<?php
                            echo htmlspecialchars(
                                $keyword,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>">

                <button type="submit">
                    검색
                </button>

            </form>


            <div class="contest-list-tabs">

                <a
                    class="contest-list-tab <?php
                                            echo $view_mode === 'mine'
                                                ? 'active'
                                                : '';
                                            ?>"
                    href="contest_list.php?view=mine">
                    내가 관리하는 대회
                </a>


                <a
                    class="contest-list-tab <?php
                                            echo $view_mode === 'all'
                                                ? 'active'
                                                : '';
                                            ?>"
                    href="contest_list.php?view=all">
                    전체 대회
                </a>


                <a
                    class="contest-list-tab <?php
                                            echo $view_mode === 'archived'
                                                ? 'active'
                                                : '';
                                            ?>"
                    href="contest_list.php?view=archived">
                    보관함
                </a>

            </div>

        </div>


        <!-- ========================================================
       목록
       ======================================================== -->

        <div class="contest-table-scroll">

            <table class="contest-list-table">

                <thead>

                    <tr>

                        <?php
                        echo contest_list_sort_th(
                            'contest_id',
                            'ID',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'title',
                            '제목',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'private',
                            '공개',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'codevisible',
                            '코드',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'allow_copy',
                            '복사',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'defunct',
                            '목록',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'is_stopped',
                            '운영',
                            $orderby,
                            $order,
                            $base
                        );
                        ?>

                        <th>관리</th>

                        <th>부가기능</th>

                        <?php
                        echo contest_list_sort_th(
                            'start_time',
                            '시작',
                            $orderby,
                            $order,
                            $base
                        );

                        echo contest_list_sort_th(
                            'end_time',
                            '종료',
                            $orderby,
                            $order,
                            $base
                        );
                        ?>

                    </tr>

                </thead>


                <tbody>

                    <?php
                    if (empty($result)) {
                    ?>

                        <tr>
                            <td
                                colspan="11"
                                class="center">
                                대회가 없습니다.
                            </td>
                        </tr>

                        <?php
                    } else {

                        foreach ($result as $r) {

                            $cid =
                                intval($r['contest_id']);

                            $is_mine =
                                $is_admin ||
                                isset(
                                    $_SESSION[$OJ_NAME . '_m' . $cid]
                                ) ||
                                in_array(
                                    $cid,
                                    $my_cids,
                                    true
                                );

                            $is_owner =
                                isset($r['user_id']) &&
                                trim($r['user_id']) ===
                                $current_user_id;

                            $row_is_archived =
                                intval($r['is_archived']) === 1;

                            $can_change_settings =
                                $is_mine &&
                                !$row_is_archived;

                            $can_copy =
                                $is_admin ||
                                $is_owner ||
                                intval($r['allow_copy']) === 1;
                        ?>

                            <tr>

                                <td class="center">
                                    <?php echo $cid; ?>
                                </td>


                                <td class="contest-title">

                                    <a
                                        href="../contest.php?cid=<?php
                                                                    echo $cid;
                                                                    ?>">
                                        <?php
                                        echo htmlspecialchars(
                                            $r['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </a>

                                </td>


                                <td class="center">
                                    <?php
                                    contest_list_setting_control(
                                        $cid,
                                        'private',
                                        intval($r['private']) === 0
                                            ? '공개'
                                            : '비공개',
                                        intval($r['private']) === 0
                                            ? 'ok'
                                            : 'no',
                                        $can_change_settings,
                                        $_SESSION[$OJ_NAME . '_postkey']
                                    );
                                    ?>
                                </td>


                                <td class="center">
                                    <?php
                                    contest_list_setting_control(
                                        $cid,
                                        'codevisible',
                                        intval($r['codevisible']) === 0
                                            ? '공개'
                                            : '비공개',
                                        intval($r['codevisible']) === 0
                                            ? 'ok'
                                            : 'no',
                                        $can_change_settings,
                                        $_SESSION[$OJ_NAME . '_postkey']
                                    );
                                    ?>
                                </td>


                                <td class="center">
                                    <?php
                                    contest_list_setting_control(
                                        $cid,
                                        'allow_copy',
                                        intval($r['allow_copy']) === 1
                                            ? '허용'
                                            : '금지',
                                        intval($r['allow_copy']) === 1
                                            ? 'ok'
                                            : 'no',
                                        $can_change_settings,
                                        $_SESSION[$OJ_NAME . '_postkey']
                                    );
                                    ?>
                                </td>


                                <td class="center">
                                    <?php
                                    contest_list_setting_control(
                                        $cid,
                                        'defunct',
                                        $r['defunct'] === 'N'
                                            ? '표시'
                                            : '숨김',
                                        $r['defunct'] === 'N'
                                            ? 'ok'
                                            : 'no',
                                        $can_change_settings,
                                        $_SESSION[$OJ_NAME . '_postkey']
                                    );
                                    ?>
                                </td>


                                <td class="center">
                                    <?php
                                    $is_stopped =
                                        intval($r['is_stopped']) !== 0;

                                    contest_list_setting_control(
                                        $cid,
                                        'is_stopped',
                                        $is_stopped
                                            ? '중지됨'
                                            : '운영 중',
                                        $is_stopped
                                            ? 'no'
                                            : 'ok',
                                        $can_change_settings,
                                        $_SESSION[$OJ_NAME . '_postkey']
                                    );
                                    ?>
                                </td>


                                <td class="center contest-list-actions">

                                    <?php
                                    if (
                                        $is_mine &&
                                        !$row_is_archived
                                    ) {
                                    ?>

                                        <a
                                            href="contest_edit.php?cid=<?php
                                                                        echo $cid;
                                                                        ?>">
                                            수정
                                        </a>

                                    <?php
                                    }

                                    if (
                                        $is_admin ||
                                        $is_owner
                                    ) {
                                    ?>

                                        <form
                                            method="post"
                                            action="contest_delete.php"
                                            style="display:inline;"
                                            onsubmit="return confirm(
                  '이 대회를 완전히 삭제하시겠습니까?\n\n'.
                  '제출 기록이 있거나 Course 차시와 연결된 대회는 삭제할 수 없습니다.\n'.
                  '삭제된 대회는 복구할 수 없습니다.'
                );">

                                            <?php echo $contest_delete_csrf_input; ?>

                                            <input
                                                type="hidden"
                                                name="cid"
                                                value="<?php echo $cid; ?>">

                                            <button
                                                type="submit"
                                                style="
                    border:0;
                    background:none;
                    padding:0;
                    margin-right:5px;
                    color:#b03030;
                    cursor:pointer;
                  ">
                                                삭제
                                            </button>

                                        </form>

                                    <?php
                                    }
                                    ?>

                                    <?php
                                    if (
                                        $is_admin ||
                                        $is_owner
                                    ) {
                                        if ($row_is_archived) {
                                    ?>

                                            <form
                                                method="post"
                                                action="contest_archive.php"
                                                style="display:inline;"
                                                onsubmit="return window.confirm('이 대회를 복원하시겠습니까?\n복원 후에도 중지 상태가 유지됩니다.');">

                                                <input
                                                    type="hidden"
                                                    name="postkey"
                                                    value="<?php
                                                            echo htmlspecialchars(
                                                                $_SESSION[$OJ_NAME . '_postkey'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            );
                                                            ?>">

                                                <input
                                                    type="hidden"
                                                    name="cid"
                                                    value="<?php echo $cid; ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="restore">

                                                <button
                                                    type="submit"
                                                    style="
                            border:0;
                            background:none;
                            padding:0;
                            margin-right:5px;
                            color:#216e39;
                            cursor:pointer;
                          ">
                                                    복원
                                                </button>

                                            </form>

                                        <?php
                                        } elseif ($is_stopped) {
                                        ?>

                                            <form
                                                method="post"
                                                action="contest_archive.php"
                                                style="display:inline;"
                                                onsubmit="return window.confirm('이 대회를 보관하시겠습니까?\n보관된 대회는 학생이 접근하거나 제출할 수 없습니다.');">

                                                <input
                                                    type="hidden"
                                                    name="postkey"
                                                    value="<?php
                                                            echo htmlspecialchars(
                                                                $_SESSION[$OJ_NAME . '_postkey'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            );
                                                            ?>">

                                                <input
                                                    type="hidden"
                                                    name="cid"
                                                    value="<?php echo $cid; ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="archive">

                                                <button
                                                    type="submit"
                                                    style="
                            border:0;
                            background:none;
                            padding:0;
                            margin-right:5px;
                            color:#8a5a00;
                            cursor:pointer;
                          ">
                                                    보관
                                                </button>

                                            </form>

                                        <?php
                                        } else {
                                        ?>

                                            <button
                                                type="button"
                                                disabled
                                                title="대회를 먼저 중지해야 보관할 수 있습니다."
                                                style="
                          border:0;
                          background:none;
                          padding:0;
                          margin-right:5px;
                          color:#999;
                          cursor:not-allowed;
                        ">
                                                보관
                                            </button>

                                    <?php
                                        }
                                    }
                                    ?>

                                    <?php

                                    if ($can_copy) {
                                    ?>

                                        <a
                                            href="contest_add.php?cid=<?php
                                                                        echo $cid;
                                                                        ?>">
                                            복사
                                        </a>

                                    <?php
                                    } else {
                                    ?>

                                        <span class="contest-list-disabled">
                                            복사 금지
                                        </span>

                                    <?php
                                    }
                                    ?>

                                </td>


                                <td class="center contest-list-actions">

                                    <?php
                                    if ($is_mine) {
                                    ?>

                                        <a
                                            href="problem_export_xml.php?cid=<?php
                                                                                echo $cid;
                                                                                ?>&getkey=<?php
                                                                    echo urlencode(
                                                                        $_SESSION[$OJ_NAME . '_getkey']
                                                                    );
                                                                    ?>">
                                            Export
                                        </a>

                                        <a
                                            href="../export_contest_code.php?cid=<?php
                                                                                    echo $cid;
                                                                                    ?>&getkey=<?php
                                                                        echo urlencode(
                                                                            $_SESSION[$OJ_NAME . '_getkey']
                                                                        );
                                                                        ?>">
                                            Logs
                                        </a>

                                        <a
                                            href="suspect_list.php?cid=<?php
                                                                        echo $cid;
                                                                        ?>">
                                            Suspect
                                        </a>

                                    <?php
                                    } else {
                                    ?>

                                        -

                                    <?php
                                    }
                                    ?>

                                </td>


                                <td class="center">
                                    <?php
                                    echo htmlspecialchars(
                                        $r['start_time'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>


                                <td class="center">
                                    <?php
                                    echo htmlspecialchars(
                                        $r['end_time'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>

                            </tr>

                    <?php
                        }
                    }
                    ?>

                </tbody>

            </table>

        </div>


        <!-- ========================================================
       페이징
       ======================================================== -->

        <div class="contest-list-pagination">

            <?php

            $page_base_params =
                $base_params;

            $page_base_params['orderby'] =
                $orderby;

            $page_base_params['order'] =
                $order;


            function page_link(
                $label,
                $page_no,
                $params
            ) {

                $params['page'] =
                    $page_no;

                echo
                '<a href="contest_list.php?' .
                    htmlspecialchars(
                        http_build_query($params),
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    '">' .
                    $label .
                    '</a>';
            }


            page_link(
                '&laquo;',
                1,
                $page_base_params
            );

            page_link(
                '&lsaquo;',
                max(1, $page - 1),
                $page_base_params
            );


            $page_start =
                max(
                    1,
                    $page - 5
                );

            $page_end =
                min(
                    $total_pages,
                    $page + 5
                );


            for (
                $i = $page_start;
                $i <= $page_end;
                $i++
            ) {

                if ($i === $page) {

                    echo "<strong>" .
                        intval($i) .
                        "</strong>";
                } else {

                    page_link(
                        intval($i),
                        $i,
                        $page_base_params
                    );
                }
            }


            page_link(
                '&rsaquo;',
                min(
                    $total_pages,
                    $page + 1
                ),
                $page_base_params
            );

            page_link(
                '&raquo;',
                $total_pages,
                $page_base_params
            );

            ?>

        </div>

    </div>

    <script>
        document
            .querySelectorAll(
                ".contest-setting-form"
            )
            .forEach(function(form) {
                form.addEventListener(
                    "submit",
                    function(event) {
                        event.preventDefault();

                        const button =
                            form.querySelector(
                                'button[type="submit"]'
                            );

                        if (button) {
                            button.disabled = true;
                        }

                        fetch(
                                form.action, {
                                    method: "POST",
                                    body: new FormData(form),
                                    credentials: "same-origin",
                                    headers: {
                                        "X-Requested-With": "XMLHttpRequest"
                                    }
                                }
                            )
                            .then(function(response) {
                                return response
                                    .json()
                                    .catch(function() {
                                        return {};
                                    })
                                    .then(function(data) {
                                        if (!response.ok) {
                                            throw new Error(
                                                data.message ||
                                                "대회 설정을 변경하지 못했습니다."
                                            );
                                        }

                                        return data;
                                    });
                            })
                            .then(function() {
                                window.location.reload();
                            })
                            .catch(function(error) {
                                window.alert(error.message);

                                if (button) {
                                    button.disabled = false;
                                }
                            });
                    }
                );
            });
    </script>

<?php
require(
    __DIR__ . '/admin-layout-end.php'
);
?>