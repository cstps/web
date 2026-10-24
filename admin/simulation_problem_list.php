<?php

require_once(__DIR__ . '/simulation-problem-init.php');

$admin_page_title = '그림 문제 목록';
$admin_active_menu = 'simulation_problem_list';

function simulation_admin_list_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$simulation_list_error = '';
$simulation_list_rows = array();
$simulation_list_total = 0;
$simulation_list_pages = 1;
$simulation_list_page = 1;
$simulation_list_keyword = '';

try {
    $keyword = $_GET['keyword'] ?? '';
    $page = $_GET['page'] ?? '1';

    if (
        !is_string($keyword) ||
        strlen($keyword) > 400 ||
        !is_string($page) ||
        !preg_match('/^[1-9][0-9]{0,5}$/D', $page)
    ) {
        http_response_code(400);
        $simulation_list_error = '검색 조건을 확인해 주세요.';
    } else {
        $simulation_list_keyword = trim($keyword);
        $simulation_list_page = (int)$page;

        $conditions = array('p.status = 1');
        $arguments = array();

        if ($simulation_admin_actor['administrator'] !== true) {
            $conditions[] = 'p.owner_user_id = ?';
            $arguments[] = $simulation_admin_actor['user_id'];
        }

        if ($simulation_list_keyword !== '') {
            $conditions[] = 'v.title LIKE ?';
            $arguments[] = '%' . $simulation_list_keyword . '%';
        }

        // 관리 목록에서도 정답 코드와 채점 설정은 조회하지 않는다.
        $from = "
            FROM simulation_problem p
            INNER JOIN simulation_problem_version v
              ON v.version_id = (
                  SELECT v2.version_id
                  FROM simulation_problem_version v2
                  WHERE v2.problem_id = p.problem_id
                  ORDER BY v2.version_no DESC
                  LIMIT 1
              )
            WHERE " . implode(' AND ', $conditions);

        $stmt = $dbh->prepare('SELECT COUNT(*) ' . $from);
        if (!$stmt || !$stmt->execute($arguments)) {
            throw new RuntimeException('문제 수 조회 실패');
        }
        $simulation_list_total = (int)$stmt->fetchColumn();
        $stmt->closeCursor();

        $simulation_list_pages = max(
            1,
            (int)ceil($simulation_list_total / 20)
        );
        $simulation_list_page = min(
            $simulation_list_page,
            $simulation_list_pages
        );
        $offset = ($simulation_list_page - 1) * 20;

        $stmt = $dbh->prepare(
            "SELECT
                p.problem_id,
                p.owner_user_id,
                p.sharing,
                v.title,
                v.version_no,
                v.simulation_mode,
                v.created_at
             " . $from . "
             ORDER BY p.problem_id DESC
             LIMIT 20 OFFSET " . $offset
        );

        if (!$stmt || !$stmt->execute($arguments)) {
            throw new RuntimeException('문제 목록 조회 실패');
        }

        $simulation_list_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    }
} catch (Throwable $e) {
    error_log('[simulation_problem_admin_list] ' . $e->getMessage());
    http_response_code(500);
    $simulation_list_error = '그림 문제 목록을 불러오지 못했습니다.';
}

require(__DIR__ . '/admin-layout-start.php');
?>
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">그림 문제 목록</h1>
            <p class="admin-page-description">
                <?= $simulation_admin_actor['administrator'] === true
                    ? '전체 그림 문제를 관리합니다.'
                    : '본인이 등록한 그림 문제를 관리합니다.' ?>
            </p>
        </div>
    </div>

    <section class="admin-card">
        <form method="get" action="simulation_problem_list.php">
            <label for="simulation-admin-keyword">문제 제목</label>
            <input
                id="simulation-admin-keyword"
                class="admin-form-input"
                type="search"
                name="keyword"
                maxlength="100"
                value="<?= simulation_admin_list_escape($simulation_list_keyword) ?>"
            >
            <p>
                <button class="admin-btn admin-btn-primary" type="submit">
                    검색
                </button>
                <a class="admin-btn admin-btn-secondary"
                   href="simulation_problem_list.php">초기화</a>
            </p>
        </form>

        <?php if ($simulation_list_error !== ''): ?>
            <p role="alert"><?= simulation_admin_list_escape($simulation_list_error) ?></p>
        <?php else: ?>
            <p>총 <?= number_format($simulation_list_total) ?>개</p>

            <?php if (!$simulation_list_rows): ?>
                <p>표시할 그림 문제가 없습니다.</p>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>번호</th>
                                <th>제목</th>
                                <th>유형</th>
                                <th>소유자</th>
                                <th>교사 공유</th>
                                <th>버전</th>
                                <th>버전 저장 일시</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($simulation_list_rows as $problem): ?>
                                <tr>
                                    <td><?= simulation_admin_list_escape($problem['problem_id']) ?></td>
                                    <td><?= simulation_admin_list_escape($problem['title']) ?></td>
                                    <td><?= $problem['simulation_mode'] === 'drawing'
                                        ? '그림 문제' : '자유 실습' ?></td>
                                    <td><?= simulation_admin_list_escape($problem['owner_user_id']) ?></td>
                                    <td><?= $problem['sharing'] === 'shared'
                                        ? '허용' : '허용 안 함' ?></td>
                                    <td><?= simulation_admin_list_escape($problem['version_no']) ?></td>
                                    <td><?= simulation_admin_list_escape($problem['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($simulation_list_pages > 1): ?>
                <nav aria-label="그림 문제 목록 페이지 이동">
                    <?php foreach (array(
                        -1 => '이전',
                        1 => '다음',
                    ) as $direction => $label): ?>
                        <?php
                        $target_page = $simulation_list_page + $direction;
                        if ($target_page < 1 || $target_page > $simulation_list_pages) {
                            continue;
                        }
                        $target_url = 'simulation_problem_list.php?' .
                            http_build_query(array(
                                'keyword' => $simulation_list_keyword,
                                'page' => $target_page,
                            ));
                        ?>
                        <a class="admin-btn admin-btn-secondary"
                           href="<?= simulation_admin_list_escape($target_url) ?>"
                        ><?= $label ?></a>
                    <?php endforeach; ?>
                    <span>
                        <?= $simulation_list_page ?> /
                        <?= $simulation_list_pages ?>페이지
                    </span>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php require(__DIR__ . '/admin-layout-end.php'); ?>
