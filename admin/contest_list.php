<?php

require("admin-header.php");
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
//
// 기존 ?my=1 링크도 전체 대회로 호환
// ============================================================

$view_mode =
  (
    isset($_GET['view']) &&
    $_GET['view'] === 'all'
  ) ||
  isset($_GET['my'])
  ? 'all'
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
  $view_mode === 'mine' &&
  !$is_admin
) {

  $where_parts[] =
    "contest_id IN ($in_clause)";
}


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

?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1">
  <title>Contest List</title>

  <style>
    .contest-list-wrap {
      max-width: 1500px;
      margin: 0 auto;
      padding: 12px 8px 28px;
      box-sizing: border-box;
    }

    .contest-list-header {
      text-align: center;
      margin: 8px 0 20px;
    }

    .contest-list-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: end;
      justify-content: space-between;
      padding: 14px;
      margin-bottom: 16px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
    }

    .contest-list-search {
      display: flex;
      gap: 8px;
      flex: 1 1 420px;
    }

    .contest-list-search input {
      flex: 1;
      min-width: 180px;
      box-sizing: border-box;
    }

    .contest-list-tabs {
      display: flex;
      gap: 6px;
    }

    .contest-list-tab {
      display: inline-block;
      padding: 7px 12px;
      border: 1px solid #bbb;
      border-radius: 5px;
      text-decoration: none;
    }

    .contest-list-tab.active {
      font-weight: bold;
      border-color: #555;
      background: #eee;
    }

    .contest-table-scroll {
      overflow-x: auto;
    }

    .contest-list-table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
    }

    .contest-list-table th,
    .contest-list-table td {
      border: 1px solid #ddd;
      padding: 7px 8px;
      vertical-align: middle;
      white-space: nowrap;
    }

    .contest-list-table th {
      background: #f5f5f5;
      text-align: center;
    }

    .contest-list-table .contest-title {
      min-width: 220px;
      white-space: normal;
    }

    .contest-list-table .center {
      text-align: center;
    }

    .contest-list-badge {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 12px;
      font-size: 0.88em;
      border: 1px solid #bbb;
    }

    .contest-list-badge.ok {
      color: #216e39;
      border-color: #8dc89e;
      background: #f2fbf5;
    }

    .contest-list-badge.no {
      color: #9f2d2d;
      border-color: #d9a2a2;
      background: #fff6f6;
    }

    .contest-list-actions a,
    .contest-list-actions span {
      margin-right: 5px;
    }

    .contest-list-disabled {
      color: #999;
      font-size: 0.9em;
    }

    .contest-list-pagination {
      margin-top: 18px;
      text-align: center;
    }

    .contest-list-pagination a,
    .contest-list-pagination strong {
      display: inline-block;
      min-width: 28px;
      padding: 5px 7px;
      margin: 2px;
      border: 1px solid #ccc;
      border-radius: 4px;
      text-decoration: none;
    }

    .contest-list-pagination strong {
      background: #eee;
    }

    @media (max-width: 800px) {
      .contest-list-toolbar {
        align-items: stretch;
      }

      .contest-list-search,
      .contest-list-tabs {
        width: 100%;
      }

      .contest-list-search {
        flex-direction: column;
      }
    }
  </style>
</head>

<body>

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
              '상태',
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
                colspan="10"
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
                  if ($is_mine) {
                  ?>

                    <a
                      href="contest_pr_change.php?cid=<?php
                                                      echo $cid;
                                                      ?>&getkey=<?php
                                                                echo urlencode(
                                                                  $_SESSION[$OJ_NAME . '_getkey']
                                                                );
                                                                ?>">

                    <?php
                  }
                    ?>

                    <span class="contest-list-badge <?php
                                                    echo intval($r['private']) === 0
                                                      ? 'ok'
                                                      : 'no';
                                                    ?>">
                      <?php
                      echo intval($r['private']) === 0
                        ? '공개'
                        : '비공개';
                      ?>
                    </span>

                    <?php
                    if ($is_mine) {
                    ?>

                    </a>

                  <?php
                    }
                  ?>

                </td>


                <td class="center">

                  <?php
                  if ($is_mine) {
                  ?>

                    <a
                      href="contest_cv_change.php?cid=<?php
                                                      echo $cid;
                                                      ?>&getkey=<?php
                                                                echo urlencode(
                                                                  $_SESSION[$OJ_NAME . '_getkey']
                                                                );
                                                                ?>">

                    <?php
                  }
                    ?>

                    <span class="contest-list-badge <?php
                                                    echo intval($r['codevisible']) === 0
                                                      ? 'ok'
                                                      : 'no';
                                                    ?>">
                      <?php
                      echo intval($r['codevisible']) === 0
                        ? '공개'
                        : '비공개';
                      ?>
                    </span>

                    <?php
                    if ($is_mine) {
                    ?>

                    </a>

                  <?php
                    }
                  ?>

                </td>


                <td class="center">

                  <?php if ($is_mine) { ?>

                    <form
                      method="post"
                      action="contest_copy_change.php"
                      style="display:inline;"
                      onsubmit="return confirm(
                '이 대회의 복사 허용 설정을 변경하시겠습니까?'
            );">

                      <input
                        type="hidden"
                        name="cid"
                        value="<?php echo intval($cid); ?>">

                      <input
                        type="hidden"
                        name="postkey"
                        value="<?php
                                echo htmlentities(
                                  $_SESSION[$OJ_NAME . '_postkey'],
                                  ENT_QUOTES,
                                  'UTF-8'
                                );
                                ?>">

                      <button
                        type="submit"
                        class="contest-list-badge <?php
                                                  echo intval($r['allow_copy']) === 1
                                                    ? 'ok'
                                                    : 'no';
                                                  ?>"
                        
                        title="클릭하여 대회 복사 정책 변경">

                        <?php
                        echo intval($r['allow_copy']) === 1
                          ? '허용'
                          : '금지';
                        ?>

                      </button>

                    </form>

                  <?php } else { ?>

                    <span class="contest-list-badge <?php
                                                    echo intval($r['allow_copy']) === 1
                                                      ? 'ok'
                                                      : 'no';
                                                    ?>"
                      style="
                    border:0;
                    font-family:inherit;
                ">

                      <?php
                      echo intval($r['allow_copy']) === 1
                        ? '허용'
                        : '금지';
                      ?>

                    </span>

                  <?php } ?>

                </td>


                <td class="center">

                  <?php
                  if ($is_mine) {
                  ?>

                    <a
                      href="contest_df_change.php?cid=<?php
                                                      echo $cid;
                                                      ?>&getkey=<?php
                                                                echo urlencode(
                                                                  $_SESSION[$OJ_NAME . '_getkey']
                                                                );
                                                                ?>">

                    <?php
                  }
                    ?>

                    <span class="contest-list-badge <?php
                                                    echo $r['defunct'] === 'N'
                                                      ? 'ok'
                                                      : 'no';
                                                    ?>">
                      <?php
                      echo $r['defunct'] === 'N'
                        ? '사용'
                        : '예약/중지';
                      ?>
                    </span>

                    <?php
                    if ($is_mine) {
                    ?>

                    </a>

                  <?php
                    }
                  ?>

                </td>


                <td class="center contest-list-actions">

                  <?php
                  if ($is_mine) {
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

</body>

</html>