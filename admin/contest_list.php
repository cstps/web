<?php
require("admin-header.php");
require_once("../include/set_get_key.php");

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

// ——— 정렬 파라미터 & 화이트리스트 ———
$valid_cols = ['contest_id','title','start_time','end_time','private','defunct','codevisible'];
$orderby    = (isset($_GET['orderby']) && in_array($_GET['orderby'], $valid_cols))
             ? $_GET['orderby'] : 'contest_id';
$order      = (isset($_GET['order']) && $_GET['order'] === 'asc') ? 'asc' : 'desc';

// ——— 페이징 계산 ———
$row           = pdo_query("SELECT COUNT(*) AS cnt FROM `contest`")[0];
$total_contests= intval($row['cnt']);
$per_page      = 50;
$page          = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset        = ($page - 1) * $per_page;
$total_pages   = intval(ceil($total_contests / $per_page));

// ——— “내 대회” ID 목록 추출 ———
$my_cids = [];
foreach ($_SESSION as $key => $val) {
    if ($val && preg_match("/^{$OJ_NAME}_m(\d+)$/", $key, $m)) {
        $my_cids[] = intval($m[1]);
    }
}
$in_clause = empty($my_cids) ? '0' : implode(',', $my_cids);

// ——— 검색어 준비 ———
$has_keyword = !empty($_GET['keyword']);
$kw = $has_keyword ? "%{$_GET['keyword']}%" : null;

// ——— SQL 빌드 & 실행 ———
$params = [];
if (!isset($_GET['my'])) {
    // 기본: “내 대회”만
    if ($has_keyword) {
        $sql  = "SELECT contest_id,title,start_time,end_time,private,defunct,codevisible
                 FROM contest
                 WHERE (title LIKE ? OR description LIKE ?)
                   AND contest_id IN ($in_clause)
                 ORDER BY `$orderby` $order
                 LIMIT $offset, $per_page";
        $params = [$kw, $kw];
    } else {
        $sql  = "SELECT contest_id,title,start_time,end_time,private,defunct,codevisible
                 FROM contest
                 WHERE contest_id IN ($in_clause)
                 ORDER BY `$orderby` $order
                 LIMIT $offset, $per_page";
    }
} else {
    // ?my=1 → 전체 대회
    if ($has_keyword) {
        $sql  = "SELECT contest_id,title,start_time,end_time,private,defunct,codevisible
                 FROM contest
                 WHERE title LIKE ? OR description LIKE ?
                 ORDER BY `$orderby` $order
                 LIMIT $offset, $per_page";
        $params = [$kw, $kw];
    } else {
        $sql  = "SELECT contest_id,title,start_time,end_time,private,defunct,codevisible
                 FROM contest
                 ORDER BY `$orderby` $order
                 LIMIT $offset, $per_page";
    }
}

$result = pdo_query($sql, ...$params);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Contest List</title>
  <link rel="stylesheet" href="...bootstrap.css">
</head>
<body>
<hr>
<center><h3><?php echo "{$MSG_CONTEST} - {$MSG_LIST}"; ?></h3></center>

<div class="container">

  <!-- 검색폼 -->
  <form action="contest_list.php" class="form-inline text-center" method="get">
    <?php if (isset($_GET['my'])): ?>
      <input type="hidden" name="my" value="1">
    <?php endif; ?>
    <input type="text" name="keyword" class="form-control"
           placeholder="<?php echo "{$MSG_CONTEST_NAME}, {$MSG_EXPLANATION}";?>"
           value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>">
    <button type="submit" class="btn btn-primary">검색</button>
  </form>

  <!-- 보기 토글 -->
  <div class="text-center my-2">
    <a href="contest_list.php<?php echo isset($_GET['my']) ? '' : '?my=1'; ?>"
       class="btn btn-<?php echo isset($_GET['my']) ? 'secondary' : 'primary'; ?>">
      내가 만든 대회
    </a>
    <a href="contest_list.php?my=1"
       class="btn btn-<?php echo isset($_GET['my']) ? 'primary' : 'secondary'; ?>">
      전체 대회
    </a>
  </div>

  <?php
    // 정렬 링크용 기본 URL 구성
    $base = 'contest_list.php?page='.$page
          . ($has_keyword ? '&keyword='.urlencode($_GET['keyword']) : '')
          . (isset($_GET['my']) ? '&my=1' : '');
    function sort_th($col, $label, $cur_col, $cur_order, $base) {
      if ($col === $cur_col) {
        $next = $cur_order==='asc' ? 'desc' : 'asc';
        $arrow= $cur_order==='asc' ? ' ▲' : ' ▼';
      } else {
        $next = 'desc'; $arrow = '';
      }
      return "<th><a href=\"{$base}&orderby={$col}&order={$next}\">{$label}{$arrow}</a></th>";
    }
  ?>

  <!-- 테이블 -->
  <table class="table table-bordered text-center">
    <thead>
      <tr>
        <?php
          echo sort_th('contest_id','ID',        $orderby,$order,$base);
          echo sort_th('title','TITLE',          $orderby,$order,$base);
          echo sort_th('codevisible','CODEVIS',  $orderby,$order,$base);
          echo sort_th('private','OPEN',         $orderby,$order,$base);
          echo sort_th('defunct','NOW',          $orderby,$order,$base);
        ?>
        <th>EDIT</th><th>COPY</th>
        <?php if (isset($_SESSION[$OJ_NAME.'_administrator'])||isset($_SESSION[$OJ_NAME.'_contest_creator'])): ?>
          <th>EXPORT</th><th>LOGS</th>
        <?php else: ?>
          <th colspan="2"></th>
        <?php endif; ?>
        <th>SUSPECT</th>
        <?php
          echo sort_th('start_time','START',$orderby,$order,$base);
          echo sort_th('end_time','END',    $orderby,$order,$base);
        ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($result)): ?>
        <tr><td colspan="13">대회가 없습니다.</td></tr>
      <?php else: foreach ($result as $r): ?>
        <tr>
          <td><?= $r['contest_id'] ?></td>
          <td align="left">
            <a href="../contest.php?cid=<?= $r['contest_id'] ?>">
              <?= htmlspecialchars($r['title']) ?>
            </a>
          </td>
          <?php
            $cid = $r['contest_id'];
            $isMine = isset($_SESSION[$OJ_NAME.'_m'.$cid]) || isset($_SESSION[$OJ_NAME.'_administrator']);
            if ($isMine):
          ?>
            <td>
              <a href="contest_cv_change.php?cid=<?=$cid?>&getkey=<?=$_SESSION[$OJ_NAME.'_getkey']?>">
                <?= $r['codevisible']=='0'?'<span class="green">Visible</span>':'<span class="red">NotVisible</span>' ?>
              </a>
            </td>
            <td>
              <a href="contest_pr_change.php?cid=<?=$cid?>&getkey=<?=$_SESSION[$OJ_NAME.'_getkey']?>">
                <?= $r['private']=='0'?'<span class="green">Public</span>':'<span class="red">Private</span>' ?>
              </a>
            </td>
            <td>
              <a href="contest_df_change.php?cid=<?=$cid?>&getkey=<?=$_SESSION[$OJ_NAME.'_getkey']?>">
                <?= $r['defunct']=='N'?'<span class="green">Available</span>':'<span class="red">Reserved</span>' ?>
              </a>
            </td>
            <td><a href="contest_edit.php?cid=<?=$cid?>">Edit</a></td>
            <td><a href="contest_add.php?cid=<?=$cid?>">Copy</a></td>
            <?php if (isset($_SESSION[$OJ_NAME.'_administrator'])||isset($_SESSION[$OJ_NAME.'_contest_creator'])): ?>
              <td><a href="problem_export_xml.php?cid=<?=$cid?>&getkey=<?=$_SESSION[$OJ_NAME.'_getkey']?>">Export</a></td>
              <td><a href="../export_contest_code.php?cid=<?=$cid?>&getkey=<?=$_SESSION[$OJ_NAME.'_getkey']?>">Logs</a></td>
            <?php endif; ?>
          <?php else: ?>
            <td colspan="5"><a href="contest_add.php?cid=<?=$cid?>">Copy</a></td>
          <?php endif; ?>
          <td><a href="suspect_list.php?cid=<?=$cid?>">Suspect</a></td>
          <td><?= $r['start_time'] ?></td>
          <td><?= $r['end_time'] ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- 페이징 -->
  <nav class="text-center">
    <ul class="pagination pagination-sm">
      <li class="page-item"><a href="<?php echo $base ?>&page=1">&laquo;</a></li>
      <li class="page-item"><a href="<?php echo $base ?>&page=<?php echo max(1,$page-1)?>">&lsaquo;</a></li>
      <?php for($i=1;$i<=$total_pages;$i++): ?>
        <li class="page-item <?php echo $i==$page?'active':''?>">
          <a href="<?php echo $base ?>&page=<?php echo $i?>"><?php echo $i?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item"><a href="<?php echo $base ?>&page=<?php echo min($total_pages,$page+1)?>">&rsaquo;</a></li>
      <li class="page-item"><a href="<?php echo $base ?>&page=<?php echo $total_pages?>">&raquo;</a></li>
    </ul>
  </nav>

</div>
</body>
</html>
