<?php
//////////////////////////////////////////////////////////////////
// admin/shorturl_list.php - 단축 URL 관리자 페이지
//////////////////////////////////////////////////////////////////
require_once(__DIR__ . "/admin-init.php"); //
require_once("../include/permission_functions.inc.php"); //

// [보안] 관리자 권한 검증
if (!oj_can_manage_admin_system()) {
    http_response_code(403);
    exit('접근 권한이 없습니다.');
}

// 1. 단축 URL 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_id = intval($_POST['id'] ?? 0);
    if ($del_id > 0) {
        pdo_query("DELETE FROM short_url WHERE id = ?", $del_id);
        $msg = "단축 URL(ID: {$del_id})이 성공적으로 삭제되었습니다.";
    }
}

// 2. 검색 및 페이징 로직
$keyword = trim($_GET['keyword'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$page_size = 15;
$offset = ($page - 1) * $page_size;

$where_clause = "";
$params = [];

if ($keyword !== '') {
    $where_clause = "WHERE short_code LIKE ? OR original_url LIKE ? OR created_by LIKE ?";
    $like_kw = "%{$keyword}%";
    $params = [$like_kw, $like_kw, $like_kw];
}

// 총 개수 산출
$count_sql = "SELECT COUNT(*) AS cnt FROM short_url {$where_clause}";
$total_rows = pdo_query($count_sql, ...$params)[0]['cnt'] ?? 0;
$total_pages = max(1, ceil($total_rows / $page_size));

// Data 조회
$list_sql = "SELECT * FROM short_url {$where_clause} ORDER BY id DESC LIMIT {$offset}, {$page_size}";
$url_list = pdo_query($list_sql, ...$params);

$admin_page_title = "단축 URL 관리";
$admin_active_menu = "system";

require(__DIR__ . "/admin-layout-start.php"); //
?>

<div class="container-fluid" style="padding: 1rem;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="m-0"><i class="fa fa-link text-info"></i> 단축 URL 관리 목록</h3>
        <span class="badge badge-secondary" style="font-size: 0.9rem;">총 <?php echo number_format($total_rows); ?>개</span>
    </div>

    <?php if (isset($msg)) { ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php } ?>

    <!-- 검색 바 -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <form method="get" action="shorturl_list.php" class="form-inline">
                <input type="text" name="keyword" class="form-control mr-2" style="width: 280px;"
                    placeholder="코드, 원본 URL, 생성자 검색" value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 검색</button>
                <?php if ($keyword !== '') { ?>
                    <a href="shorturl_list.php" class="btn btn-outline-secondary ml-2">초기화</a>
                <?php } ?>
            </form>
        </div>
    </div>

    <!-- 목록 테이블 -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="width: 130px;">단축 코드</th>
                            <th>원본 URL</th>
                            <th style="width: 110px;">작성자</th>
                            <th style="width: 90px; text-align: center;">클릭수</th>
                            <th style="width: 150px;">생성일시</th>
                            <th style="width: 80px; text-align: center;">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($url_list)) { ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">등록된 단축 URL이 없습니다.</td>
                            </tr>
                            <?php } else {
                            $http_host = $_SERVER['HTTP_HOST'];
                            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                            foreach ($url_list as $row) {
                                $full_short_url = "{$scheme}://{$http_host}/shorturl.php?code=" . $row['short_code'];
                            ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($full_short_url); ?>" target="_blank" class="font-weight-bold text-info">
                                            <?php echo htmlspecialchars($row['short_code']); ?>
                                        </a>
                                    </td>
                                    <td style="max-width: 300px; word-break: break-all;">
                                        <a href="<?php echo htmlspecialchars($row['original_url']); ?>" target="_blank" class="text-secondary">
                                            <?php echo htmlspecialchars($row['original_url']); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-light"><?php echo htmlspecialchars($row['created_by']); ?></span></td>
                                    <td class="text-center"><span class="badge badge-info"><?php echo number_format($row['click_count']); ?></span></td>
                                    <td><small class="text-muted"><?php echo $row['created_at']; ?></small></td>
                                    <td class="text-center">
                                        <form method="post" action="shorturl_list.php" onsubmit="return confirm('정말 삭제하시겠습니까?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">삭제</button>
                                        </form>
                                    </td>
                                </tr>
                        <?php }
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 페이징 -->
    <?php if ($total_pages > 1) { ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                    <li class="page-item <?php if ($i === $page) echo 'active'; ?>">
                        <a class="page-link" href="shorturl_list.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>"><?php echo $i; ?></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    <?php } ?>
</div>

<?php require(__DIR__ . "/admin-layout-end.php"); // 
?>