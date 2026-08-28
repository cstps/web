<?php
require("admin-header.php");
require_once("../include/set_get_key.php");

if (
  !(
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_problem_editor']) ||
    isset($_SESSION[$OJ_NAME . '_contest_creator'])
  )
) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

if (isset($OJ_LANG)) {
  require_once("../lang/$OJ_LANG.php");
}
?>

<title>문제 관리</title>

<div class="admin-page">

  <div class="admin-page-header">

    <div>
      <h1 class="admin-page-title">
        문제 관리
      </h1>

      <div class="admin-page-description">
        등록된 문제를 검색하고 수정하거나 대회에 사용할 수 있습니다.
      </div>
    </div>

    <?php
    if (
      isset($_SESSION[$OJ_NAME . '_administrator']) ||
      isset($_SESSION[$OJ_NAME . '_problem_editor'])
    ) {
    ?>
      <a
        href="problem_add_page.php"
        class="admin-btn admin-btn-primary">
        + 새 문제 만들기
      </a>
    <?php
    }
    ?>

  </div>

  <?php
  // ============================================================
  // 기본 조회 조건
  // ============================================================

  $user_id = $_SESSION[$OJ_NAME . '_user_id'];

  // 기본값은 "내가 만든 문제"
  // ?scope=all 일 때만 전체 문제 표시
  $show_my = !(
    isset($_GET['scope']) &&
    $_GET['scope'] === 'all'
  );

  $keyword_raw = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

  $where = array();
  $params = array();


  // ============================================================
  // 내가 만든 문제
  // ============================================================

  if ($show_my) {

    $where[] = "
            EXISTS (
                SELECT 1
                FROM privilege pr
                WHERE pr.user_id = ?
                  AND pr.rightstr = CONCAT('p', p.problem_id)
                  AND pr.defunct = 'N'
            )
        ";

    $params[] = $user_id;
  }


  // ============================================================
  // 검색어
  // ============================================================

  if ($keyword_raw !== '') {

    $keyword = '%' . $keyword_raw . '%';

    $where[] = "
            (
                CAST(p.problem_id AS CHAR) LIKE ?
                OR p.title LIKE ?
                OR p.description LIKE ?
                OR p.source LIKE ?
            )
        ";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
  }


  $where_sql = count($where) > 0
    ? ' WHERE ' . implode(' AND ', $where)
    : '';


  // ============================================================
  // 전체 개수
  // ============================================================

  $count_sql = "
        SELECT COUNT(*) AS ids
        FROM problem p
        $where_sql
    ";

  $count_result = pdo_query(
    $count_sql,
    ...$params
  );

  $ids = intval($count_result[0]['ids']);


  // ============================================================
  // 페이지 계산
  // ============================================================

  $idsperpage = 50;

  $pages = max(
    1,
    intval(
      ceil(
        $ids / $idsperpage
      )
    )
  );

  $page = isset($_GET['page'])
    ? max(1, intval($_GET['page']))
    : 1;

  if ($page > $pages) {
    $page = $pages;
  }

  $pagesperframe = 5;

  $frame = intval(
    ceil(
      $page / $pagesperframe
    )
  );

  $spage =
    ($frame - 1)
    * $pagesperframe
    + 1;

  $epage = min(
    $spage + $pagesperframe - 1,
    $pages
  );

  $sid =
    ($page - 1)
    * $idsperpage;


  // ============================================================
  // 문제 목록
  // ============================================================

  $sql = "
        SELECT
            p.problem_id,
            p.title,
            p.accepted,
            p.in_date,
            p.defunct,
            p.allow_reuse
        FROM problem p
        $where_sql
        ORDER BY p.problem_id DESC
        LIMIT $sid, $idsperpage
    ";

  $result = pdo_query(
    $sql,
    ...$params
  );


  // ============================================================
  // 페이지네이션 URL
  // ============================================================

  $pagination_params = array();

  if (!$show_my) {
    $pagination_params['scope'] = 'all';
  }

  if ($keyword_raw !== '') {
    $pagination_params['keyword'] = $keyword_raw;
  }

  $problem_page_url = function ($target_page) use ($pagination_params) {

    $params = $pagination_params;
    $params['page'] = $target_page;

    return
      'problem_list.php?' .
      http_build_query($params);
  };

  $prev_page = ($page > 1)
    ? $page - 1
    : 1;

  $next_page = ($page < $pages)
    ? $page + 1
    : $pages;
  ?>


  <!-- ============================================================
         검색 / 필터
         ============================================================ -->

  <div class="admin-card admin-filter-card">

    <form
      action="problem_list.php"
      method="get"
      class="admin-search-form">

      <?php
      if (!$show_my) {
      ?>
        <input
          type="hidden"
          name="scope"
          value="all">
      <?php
      }
      ?>

      <div class="admin-search-input-wrap">

        <input
          type="text"
          name="keyword"
          class="admin-search-input"
          value="<?php
                  echo htmlspecialchars(
                    $keyword_raw,
                    ENT_QUOTES,
                    'UTF-8'
                  );
                  ?>"
          placeholder="문제 번호, 제목, 설명, 출처 검색">

        <button
          type="submit"
          class="admin-btn admin-btn-primary">
          검색
        </button>

      </div>

    </form>


    <div class="admin-filter-tabs">

      <a
        class="admin-filter-tab <?php
                                echo $show_my
                                  ? 'active'
                                  : '';
                                ?>"
        href="problem_list.php">
        내가 만든 문제
      </a>

      <a
        class="admin-filter-tab <?php
                                echo !$show_my
                                  ? 'active'
                                  : '';
                                ?>"
        href="problem_list.php?scope=all">
        전체 문제
      </a>

    </div>

  </div>


  <!-- ============================================================
         문제 목록
         ============================================================ -->

  <div class="admin-card admin-table-card">

    <form
      method="post"
      action="contest_add.php">

      <input
        type="hidden"
        name="keyword"
        value="<?php
                echo htmlspecialchars(
                  $keyword_raw,
                  ENT_QUOTES,
                  'UTF-8'
                );
                ?>">


      <!-- 선택 문제 작업 -->
      <div class="admin-bulk-actions">

        <span class="admin-bulk-label">
          선택한 문제
        </span>

        <button
          type="submit"
          name="problem2contest"
          class="admin-btn admin-btn-secondary">
          새 대회 만들기
        </button>

      </div>


      <!-- 페이지네이션 : 목록 위 / 가운데 정렬 -->
      <div class="admin-pagination-wrap">

        <div class="admin-pagination-info">
          전체 <?php echo number_format($ids); ?>개
          ·
          <?php echo $page; ?> / <?php echo $pages; ?> 페이지
        </div>

        <?php
        if ($pages > 1) {
        ?>

          <nav
            class="admin-pagination"
            aria-label="문제 목록 페이지">

            <a
              class="admin-page-link <?php
                                      echo $page <= 1
                                        ? 'disabled'
                                        : '';
                                      ?>"
              href="<?php
                    echo htmlspecialchars(
                      $problem_page_url(1),
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    ?>"
              title="첫 페이지">
              «
            </a>


            <a
              class="admin-page-link <?php
                                      echo $page <= 1
                                        ? 'disabled'
                                        : '';
                                      ?>"
              href="<?php
                    echo htmlspecialchars(
                      $problem_page_url($prev_page),
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    ?>"
              title="이전 페이지">
              ‹
            </a>


            <?php
            for ($i = $spage; $i <= $epage; $i++) {
            ?>

              <a
                class="admin-page-link <?php
                                        echo $page === $i
                                          ? 'active'
                                          : '';
                                        ?>"
                href="<?php
                      echo htmlspecialchars(
                        $problem_page_url($i),
                        ENT_QUOTES,
                        'UTF-8'
                      );
                      ?>">
                <?php echo $i; ?>
              </a>

            <?php
            }
            ?>


            <a
              class="admin-page-link <?php
                                      echo $page >= $pages
                                        ? 'disabled'
                                        : '';
                                      ?>"
              href="<?php
                    echo htmlspecialchars(
                      $problem_page_url($next_page),
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    ?>"
              title="다음 페이지">
              ›
            </a>


            <a
              class="admin-page-link <?php
                                      echo $page >= $pages
                                        ? 'disabled'
                                        : '';
                                      ?>"
              href="<?php
                    echo htmlspecialchars(
                      $problem_page_url($pages),
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    ?>"
              title="마지막 페이지">
              »
            </a>

          </nav>

        <?php
        }
        ?>

      </div>


      <div class="admin-table-wrap">

        <table class="admin-table">

          <thead>

            <tr>

              <th class="admin-col-check">

                <input
                  type="checkbox"
                  onchange="
                                        $('input[name=&quot;pid[]&quot;]')
                                            .prop('checked', this.checked);
                                    ">

              </th>

              <th class="admin-col-id">
                문제 번호
              </th>

              <th>
                문제 제목
              </th>

              <th class="admin-col-small">
                AC
              </th>

              <th class="admin-col-date">
                등록일
              </th>

              <th class="admin-col-reuse">
                재사용
              </th>

              <th>
                상태
              </th>

              <th class="admin-col-manage">
                관리
              </th>

            </tr>

          </thead>

          <tbody>

            <?php
            if (count($result) === 0) {
            ?>

              <tr>
                <td
                  colspan="8"
                  style="padding: 32px 16px; color: #7b8797;">
                  표시할 문제가 없습니다.
                </td>
              </tr>

              <?php
            } else {

              foreach ($result as $row) {

                $pid = intval($row['problem_id']);

                $is_admin =
                  isset(
                    $_SESSION[$OJ_NAME . '_administrator']
                  );

                $is_owner =
                  isset(
                    $_SESSION[$OJ_NAME . '_p' . $pid]
                  );
              ?>

                <tr>

                  <td class="admin-col-check">

                    <input
                      type="checkbox"
                      name="pid[]"
                      value="<?php echo $pid; ?>">

                  </td>


                  <td class="admin-col-id">
                    <?php echo $pid; ?>
                  </td>


                  <td class="admin-problem-title">

                    <a
                      href="../problem.php?id=<?php
                                              echo $pid;
                                              ?>">
                      <?php
                      echo htmlspecialchars(
                        $row['title'],
                        ENT_QUOTES,
                        'UTF-8'
                      );
                      ?>
                    </a>

                  </td>


                  <td>
                    <?php
                    echo intval(
                      $row['accepted']
                    );
                    ?>
                  </td>


                  <td>
                    <?php
                    echo htmlspecialchars(
                      $row['in_date'],
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    ?>
                  </td>


                  <td>

                    <?php
                    if (
                      intval(
                        $row['allow_reuse']
                      ) === 1
                    ) {
                    ?>

                      <span
                        class="
                                            admin-badge
                                            admin-badge-success
                                        ">
                        허용
                      </span>

                    <?php
                    } else {
                    ?>

                      <span
                        class="
                                            admin-badge
                                            admin-badge-muted
                                        ">
                        제한
                      </span>

                    <?php
                    }
                    ?>

                  </td>


                  <!-- 상태 -->
                  <td>

                    <?php
                    if (
                      $is_admin ||
                      $is_owner
                    ) {

                      if (
                        $row['defunct']
                        === 'N'
                      ) {
                    ?>

                        <a
                          class="
                                            admin-status
                                            admin-status-public
                                        "
                          href="problem_df_change.php?id=<?php
                                                          echo $pid;
                                                          ?>&getkey=<?php
                                                                    echo urlencode(
                                                                      $_SESSION[$OJ_NAME .
                                                                        '_getkey']
                                                                    );
                                                                    ?>"
                          title="
                                            클릭하면 비공개로
                                            변경됩니다.
                                        ">
                          공개
                        </a>

                      <?php
                      } else {
                      ?>

                        <a
                          class="
                                            admin-status
                                            admin-status-private
                                        "
                          href="problem_df_change.php?id=<?php
                                                          echo $pid;
                                                          ?>&getkey=<?php
                                                                    echo urlencode(
                                                                      $_SESSION[$OJ_NAME .
                                                                        '_getkey']
                                                                    );
                                                                    ?>"
                          title="
                                            클릭하면 공개로
                                            변경됩니다.
                                        ">
                          비공개
                        </a>

                    <?php
                      }
                    } else {
                      echo '--';
                    }
                    ?>

                  </td>


                  <!-- 관리 -->
                  <td class="admin-manage-cell">

                    <?php
                    if (
                      $is_admin ||
                      $is_owner
                    ) {
                    ?>

                      <div class="admin-row-actions">

                        <a
                          class="admin-row-action"
                          href="problem_edit.php?id=<?php
                                                    echo $pid;
                                                    ?>&getkey=<?php
                                            echo urlencode(
                                              $_SESSION[$OJ_NAME .
                                                '_getkey']
                                            );
                                            ?>">
                          수정
                        </a>


                        <a
                          class="admin-row-action"
                          href="javascript:phpfm(<?php
                                                  echo $pid;
                                                  ?>);">
                          테스트 데이터
                        </a>


                        <?php
                        if (
                          $OJ_SAE ||
                          function_exists('system')
                        ) {
                        ?>

                          <a
                            class="
                                          admin-row-action
                                          admin-row-action-danger
                                      "
                            href="#"
                            onclick="
                                          if (
                                              confirm(
                                                  '이 문제를 삭제하시겠습니까?'
                                              )
                                          ) {
                                              location.href =
                                                  'problem_del.php?id=<?php
                                                                      echo $pid;
                                                                      ?>&getkey=<?php
                                                            echo rawurlencode(
                                                              $_SESSION[$OJ_NAME .
                                                                '_getkey']
                                                            );
                                                            ?>';
                                          }

                                          return false;
                                      ">
                            삭제
                          </a>

                        <?php
                        }
                        ?>

                      </div>

                    <?php
                    } else {
                      echo '--';
                    }
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

    </form>

  </div>

</div>


<script src="../template/bs3/jquery.min.js"></script>

<script>
  function phpfm(pid) {

    $.post(
      "phpfm.php", {
        frame: 3,
        pid: pid,
        pass: ""
      },
      function(data, status) {

        if (status === "success") {

          document.location.href =
            "phpfm.php?frame=3&pid=" + pid;
        }
      }
    );
  }
</script>