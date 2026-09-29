<?php
require_once(__DIR__ . "/admin-init.php");


if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("권한 목록을 볼 권한이 없습니다.");
}

if(isset($OJ_LANG)){
  require_once("../lang/$OJ_LANG.php");
}
?>

<?php
$admin_page_title = "권한 목록";
$admin_active_menu = "privilege_list";
require_once(__DIR__ . "/admin-layout-start.php");
?>

<div class="admin-page">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">권한 목록</h1>
      <div class="admin-page-description">
        사용자에게 부여된 권한을 조회합니다.
      </div>
    </div>
  </div>


<?php
$keyword = isset($_GET["keyword"])
    ? $_GET["keyword"]
    : "";

$category = isset($_GET["category"])
    ? $_GET["category"]
    : "global";

$page_input = isset($_GET["page"])
    ? $_GET["page"]
    : "1";

if (
    !is_string($keyword) ||
    strlen($keyword) > 100
) {
    http_response_code(400);
    exit("검색어가 올바르지 않습니다.");
}

$keyword = trim($keyword);

if (
    !is_string($category) ||
    !in_array(
        $category,
        array("all", "global", "contest_user", "contest_manager", "problem"),
        true
    )
) {
    http_response_code(400);
    exit("권한 분류가 올바르지 않습니다.");
}

if (
    !is_string($page_input) ||
    preg_match('/^[1-9][0-9]{0,7}$/D', $page_input) !== 1
) {
    http_response_code(400);
    exit("페이지 번호가 올바르지 않습니다.");
}

$page = (int)$page_input;
$idsperpage = 50;
$pagesperframe = 5;

$conditions = array();
$params = array();

if ($category === "global") {
    $conditions[] = "p.rightstr NOT REGEXP '^[cmp][0-9]+$'";
} elseif ($category === "contest_user") {
    $conditions[] = "p.rightstr REGEXP '^c[0-9]+$'";
} elseif ($category === "contest_manager") {
    $conditions[] = "p.rightstr REGEXP '^m[0-9]+$'";
} elseif ($category === "problem") {
    $conditions[] = "p.rightstr REGEXP '^p[0-9]+$'";
}

if ($keyword !== "") {
    $escaped_keyword = str_replace("!", "!!", $keyword);
    $escaped_keyword = str_replace("%", "!%", $escaped_keyword);
    $escaped_keyword = str_replace("_", "!_", $escaped_keyword);
    $pattern = "%" . $escaped_keyword . "%";

    $conditions[] =
        "(p.user_id LIKE ? ESCAPE '!' OR p.rightstr LIKE ? ESCAPE '!')";
    $params[] = $pattern;
    $params[] = $pattern;
}

$where_sql = count($conditions) > 0
    ? " WHERE " . implode(" AND ", $conditions)
    : "";

$count_rows = pdo_query(
    "SELECT COUNT(*) AS total_rows FROM privilege p" . $where_sql,
    ...$params
);

if (
    $count_rows === false ||
    !isset($count_rows[0]["total_rows"])
) {
    http_response_code(500);
    exit("권한 건수를 조회할 수 없습니다.");
}

$ids = (int)$count_rows[0]["total_rows"];
$pages = max(1, (int)ceil($ids / $idsperpage));
$page = min($page, $pages);
$sid = ($page - 1) * $idsperpage;

$frame = (int)ceil($page / $pagesperframe);
$spage = ($frame - 1) * $pagesperframe + 1;
$epage = min($spage + $pagesperframe - 1, $pages);

$list_sql =
    "SELECT p.user_id, p.rightstr, p.valuestr, p.defunct, " .
    "u.user_id AS existing_user_id " .
    "FROM privilege p " .
    "LEFT JOIN users u ON u.user_id = p.user_id" .
    $where_sql .
    " ORDER BY p.rightstr, p.user_id, p.valuestr " .
    "LIMIT " . $sid . ", " . $idsperpage;

$result = pdo_query($list_sql, ...$params);

if ($result === false) {
    http_response_code(500);
    exit("권한 목록을 조회할 수 없습니다.");
}
?>

<?php
$escape = function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
};

$category_labels = array(
    "all" => "전체 권한",
    "global" => "특별권한·기타",
    "contest_user" => "대회 참가",
    "contest_manager" => "대회 관리",
    "problem" => "문제 보기"
);

$page_url = function ($target_page) use ($category, $keyword) {
    $query = array(
        "category" => $category,
        "page" => $target_page
    );

    if ($keyword !== "") {
        $query["keyword"] = $keyword;
    }

    return "privilege_list.php?" .
        http_build_query(
            $query,
            "",
            "&",
            PHP_QUERY_RFC3986
        );
};
?>

<div class="admin-form-card">
  <div class="admin-form-card-header">
    <div>
      <div class="admin-form-card-title">권한 검색</div>
      <div class="admin-form-card-desc">
        사용자 ID 또는 권한명을 검색합니다.
      </div>
    </div>
  </div>

  <form method="get" action="privilege_list.php" class="admin-privilege-filter">
    <div class="admin-form-grid-2">
      <div class="admin-form-field">
        <label class="admin-form-label" for="privilege-keyword">
          검색어
        </label>
        <input
          type="text"
          id="privilege-keyword"
          name="keyword"
          class="admin-form-input"
          maxlength="100"
          value="<?php echo $escape($keyword); ?>"
          placeholder="사용자 ID 또는 권한명">
      </div>

      <div class="admin-form-field">
        <label class="admin-form-label" for="privilege-category">
          권한 분류
        </label>
        <select
          id="privilege-category"
          name="category"
          class="admin-form-input">
          <?php foreach ($category_labels as $key => $label) { ?>
            <option
              value="<?php echo $escape($key); ?>"
              <?php echo $category === $key ? "selected" : ""; ?>>
              <?php echo $escape($label); ?>
            </option>
          <?php } ?>
        </select>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary">
        검색
      </button>
      <a class="admin-btn admin-btn-secondary" href="privilege_list.php">
        초기화
      </a>
    </div>
  </form>
</div>

<div class="admin-card">
  <p>
    검색 결과 <strong><?php echo number_format($ids); ?>건</strong>
    · <?php echo $page; ?> / <?php echo $pages; ?>페이지
  </p>

  <div style="overflow-x: auto;">
    <table class="admin-table" style="width: 100%;">
      <thead>
        <tr>
          <th scope="col">사용자 ID</th>
          <th scope="col">권한 분류</th>
          <th scope="col">권한 코드</th>
          <th scope="col">대상 번호</th>
          <th scope="col">관리</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($result) === 0) { ?>
          <tr>
            <td colspan="5">조회된 권한이 없습니다.</td>
          </tr>
        <?php } else { ?>
          <?php foreach ($result as $row) {
            $rightstr = (string)$row["rightstr"];
            $kind_label = "특별권한·기타";
            $target_id = "—";

            if (
                preg_match(
                    '/^([cmp])([0-9]+)$/D',
                    $rightstr,
                    $parts
                ) === 1
            ) {
                $target_id = $parts[2];

                if ($parts[1] === "c") {
                    $kind_label = "대회 참가";
                } elseif ($parts[1] === "m") {
                    $kind_label = "대회 관리";
                } else {
                    $kind_label = "문제 보기";
                }
            }
            ?>
            <tr>
              <td><?php echo $escape($row["user_id"]); ?></td>
              <td><?php echo $escape($kind_label); ?></td>
              <td><?php echo $escape($rightstr); ?></td>
              <td><?php echo $escape($target_id); ?></td>
              <td>
                <?php if (isset($row["existing_user_id"])) { ?>
                  <a
                    class="admin-privilege-action admin-privilege-action-link"
                    href="user_privilege_manage.php?uid=<?php
                    echo rawurlencode((string)$row["user_id"]);
                    ?>">
                    권한 관리
                  </a>
                <?php } else { ?>
                  <span class="admin-privilege-action admin-privilege-action-missing">
                    계정 없음
                  </span>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1) { ?>
  <nav class="admin-form-actions" aria-label="권한 목록 페이지 이동">
    <a class="admin-btn"
       href="<?php echo $escape($page_url(1)); ?>">처음</a>

    <a class="admin-btn"
       href="<?php echo $escape($page_url(max(1, $page - 1))); ?>">
      이전
    </a>

    <?php for ($i = $spage; $i <= $epage; $i++) { ?>
      <a
        class="<?php
        echo $i === $page
            ? "admin-btn admin-btn-primary"
            : "admin-btn";
        ?>"
        href="<?php echo $escape($page_url($i)); ?>"
        <?php echo $i === $page ? 'aria-current="page"' : ""; ?>>
        <?php echo $i; ?>
      </a>
    <?php } ?>

    <a class="admin-btn"
       href="<?php echo $escape($page_url(min($pages, $page + 1))); ?>">
      다음
    </a>

    <a class="admin-btn"
       href="<?php echo $escape($page_url($pages)); ?>">마지막</a>
  </nav>
<?php } ?>

</div>

<?php
require_once(__DIR__ . "/admin-layout-end.php");
?>
