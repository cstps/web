<?php

require_once __DIR__ . '/admin-init.php';


if (!oj_can_view_admin_problems()) {
  http_response_code(403);
  exit('문제 목록을 볼 권한이 없습니다.');
}

$can_create_contest_from_problems =
  oj_can_manage_admin_contests();

$can_create_notice_from_problems =
  oj_can_manage_admin_notice();

$can_select_problems =
  $can_create_contest_from_problems ||
  $can_create_notice_from_problems;

$admin_page_title = '문제 관리';
$admin_active_menu = 'problem_list';

require_once __DIR__ . '/admin-layout-start.php';
?>

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
    if (oj_can_create_admin_problems()) {
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

  $show_archived =
    isset($_GET['archived']) &&
    $_GET['archived'] === '1';

  // 보관 목록에서는 대회·공지에 문제를 선택해 추가하지 않습니다.
  $can_select_problems =
    $can_select_problems && !$show_archived;

  $where = array('p.is_archived = ?');
  $params = array($show_archived ? 1 : 0);


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
            p.allow_reuse,
            p.is_archived
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

  if ($show_archived) {
    $pagination_params['archived'] = '1';
  }

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

      <?php if ($show_archived) { ?>
        <input type="hidden" name="archived" value="1">
      <?php } ?>

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
        class="admin-filter-tab <?php echo $show_my ? 'active' : ''; ?>"
        href="problem_list.php<?php echo $show_archived ? '?archived=1' : ''; ?>">
        내가 만든 문제
      </a>

      <a
        class="admin-filter-tab <?php echo !$show_my ? 'active' : ''; ?>"
        href="problem_list.php?scope=all<?php echo $show_archived ? '&amp;archived=1' : ''; ?>">
        전체 문제
      </a>
    </div>

    <div class="admin-filter-tabs">
      <a
        class="admin-filter-tab <?php echo !$show_archived ? 'active' : ''; ?>"
        href="problem_list.php<?php echo !$show_my ? '?scope=all' : ''; ?>">
        일반 목록
      </a>

      <a
        class="admin-filter-tab <?php echo $show_archived ? 'active' : ''; ?>"
        href="problem_list.php?archived=1<?php echo !$show_my ? '&amp;scope=all' : ''; ?>">
        보관 목록
      </a>
    </div>

  </div>


  <!-- ============================================================
         문제 목록
         ============================================================ -->

  <div class="admin-card admin-table-card">

    <?php
    if ($can_select_problems) {
    ?>

      <form
        id="problem-selection-form"
        method="post"
        action="contest_add.php">

        <div class="admin-csrf-fields">

          <?php
          require(
            __DIR__ .
            "/../include/set_post_key.php"
          );
          ?>

        </div>

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

        <div class="admin-bulk-actions">

          <span class="admin-bulk-label">
            선택한 문제
          </span>

          <?php
          if ($can_create_contest_from_problems) {
          ?>

            <button
              type="submit"
              name="problem2contest"
              class="admin-btn admin-btn-secondary">
              새 대회 만들기
            </button>

          <?php
          }

          if ($can_create_notice_from_problems) {
          ?>

            <button
              type="submit"
              name="problem2notice"
              value="1"
              formaction="news_add_page.php"
              class="admin-btn admin-btn-secondary">
              선택 문제로 공지 작성
            </button>

          <?php
          }
          ?>

        </div>

      </form>

    <?php
    }
    ?>


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

            <?php
            if ($can_select_problems) {
            ?>

              <th class="admin-col-check">

                <input
                  type="checkbox"
                  aria-label="현재 페이지 문제 전체 선택"
                  onchange="
        var checked = this.checked;

        document
          .querySelectorAll(
            'input[name=&quot;pid[]&quot;][form=&quot;problem-selection-form&quot;]'
          )
          .forEach(function (input) {
            input.checked = checked;
          });
      ">

              </th>

            <?php
            }
            ?>

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
                colspan="<?php
                          echo $can_select_problems
                            ? 8
                            : 7;
                          ?>"
                style="padding: 32px 16px; color: #7b8797;">
                표시할 문제가 없습니다.
              </td>
            </tr>

            <?php
          } else {

            foreach ($result as $row) {

              $pid = intval($row['problem_id']);

              $can_manage_problem =
                oj_can_manage_problem($pid);
            ?>

              <tr>

                <?php
                if ($can_select_problems) {
                ?>

                  <td class="admin-col-check">

                    <input
                      type="checkbox"
                      name="pid[]"
                      value="<?php echo $pid; ?>"
                      form="problem-selection-form"
                      aria-label="<?php echo $pid; ?>번 문제 선택">

                  </td>

                <?php
                }
                ?>


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
                  if ($can_manage_problem) {

                    if (
                      $row['defunct']
                      === 'N'
                    ) {
                  ?>

                      <button
                        type="submit"
                        form="problem-visibility-form"
                        name="visibility_change"
                        value="Y:<?php echo $pid; ?>"
                        class="admin-status admin-status-public"
                        title="클릭하면 비공개로 변경됩니다.">
                        공개
                      </button>

                    <?php
                    } else {
                    ?>

                      <button
                        type="submit"
                        form="problem-visibility-form"
                        name="visibility_change"
                        value="N:<?php echo $pid; ?>"
                        class="admin-status admin-status-private"
                        title="클릭하면 공개로 변경됩니다.">
                        비공개
                      </button>

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
                  if ($can_manage_problem) {
                  ?>

                    <div class="admin-row-actions">

                      <a
                        class="admin-row-action"
                        href="problem_edit.php?id=<?php echo $pid; ?>">
                        수정
                      </a>

                      <a
                        class="admin-row-action"
                        href="problem_testdata.php?id=<?php echo $pid; ?>">
                        테스트 데이터
                      </a>
                      <button
                        type="submit"
                        class="admin-row-action"
                        form="problem-archive-form"
                        name="archive_change"
                        value="<?php
                          echo ($show_archived ? 'restore:' : 'archive:') .
                            (int)$pid;
                        ?>">
                        <?php echo $show_archived ? '복원' : '보관'; ?>
                      </button>

                      <a
                        class="admin-row-action"
                        href="problem_delete.php?id=<?php
                          echo (int)$pid;
                        ?><?php
                          echo $show_my ? '' : '&amp;scope=all';
                          echo $show_archived ? '&amp;archived=1' : '';
                        ?>">
                        영구 삭제
                      </a>

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


    <form
      id="problem-archive-form"
      method="post"
      action="problem_archive.php"
      hidden>

      <?php
      require __DIR__ . '/../include/set_post_key.php';
      ?>

      <input
        type="hidden"
        name="return_scope"
        value="<?php echo $show_my ? '' : 'all'; ?>">

      <input
        type="hidden"
        name="return_archived"
        value="<?php echo $show_archived ? '1' : '0'; ?>">
    </form>

    <form
      id="problem-visibility-form"
      method="post"
      action="problem_df_change.php"
      hidden>

      <div class="admin-csrf-fields">

        <?php
        require(
          __DIR__ .
          "/../include/set_post_key.php"
        );
        ?>

      </div>

      <input
        type="hidden"
        name="return_scope"
        value="<?php
                echo $show_my
                  ? ''
                  : 'all';
                ?>">

      <input
        type="hidden"
        name="return_keyword"
        value="<?php
                echo htmlspecialchars(
                  $keyword_raw,
                  ENT_QUOTES,
                  'UTF-8'
                );
                ?>">

      <input
        type="hidden"
        name="return_page"
        value="<?php echo $page; ?>">

    </form>
  </div>

</div>

<?php
require_once __DIR__ . '/admin-layout-end.php';
