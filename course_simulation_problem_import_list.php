<?php

require_once(__DIR__ . '/scripts/simulation_execution_config.php');
require_once(__DIR__ . '/include/db_info.inc.php');
require_once(__DIR__ . '/include/const.inc.php');
require_once(__DIR__ . '/include/memcache.php');
require_once(__DIR__ . '/include/setlang.php');
require_once(__DIR__ . '/scripts/course_simulation_problem_import_context.php');

$view_title = '가져올 시뮬레이션 문제 선택';
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

function course_import_list_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function course_import_list_error($message, $status)
{
    global $OJ_TEMPLATE, $view_title, $view_errors;
    http_response_code($status);
    $view_errors = '<h2>' . course_import_list_escape($message) . '</h2>';
    require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_import_list_error('로그인이 필요합니다.', 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    course_import_list_error('GET 요청으로 문제 선택 화면을 열어 주세요.', 405);
}

try {
    if (array_diff(array_keys($_GET), array('course_id', 'lesson_id', 'keyword', 'page'))) {
        throw new InvalidArgumentException('지원하지 않는 조회 항목');
    }
    $course_id = course_simulation_problem_import_id($_GET['course_id'] ?? null, true);
    $lesson_id = course_simulation_problem_import_id($_GET['lesson_id'] ?? null, true);
    $keyword = $_GET['keyword'] ?? '';
    $page = $_GET['page'] ?? '1';
    if (!is_string($keyword) || strlen($keyword) > 400 ||
        !is_string($page) || preg_match('/^[1-9][0-9]{0,5}$/D', $page) !== 1) {
        throw new InvalidArgumentException('검색 조건 오류');
    }
    $keyword = trim($keyword);

    if (!function_exists('pdo_query')) {
        require_once(__DIR__ . '/include/pdo.php');
    }
    if (pdo_query('SELECT 1') === false || !isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('DB 연결 실패');
    }

    // 관리자 화면 권한 대신 현재 수업·차시의 교사 권한을 검사한다.
    $context = course_simulation_problem_import_context($dbh, $course_id, $lesson_id);
    $conditions = array('p.status = 1');
    $arguments = array();
    if ($context['actor']['administrator'] !== true) {
        $conditions[] = "(p.owner_user_id = ? OR p.sharing = 'shared')";
        $arguments[] = $context['actor']['user_id'];
    }
    if ($keyword !== '') {
        $conditions[] = 'v.title LIKE ?';
        $arguments[] = '%' . $keyword . '%';
    }
    $from = ' FROM simulation_problem p
        INNER JOIN simulation_problem_version v ON v.version_id = (
            SELECT v2.version_id FROM simulation_problem_version v2
            WHERE v2.problem_id = p.problem_id
            ORDER BY v2.version_no DESC LIMIT 1
        ) WHERE ' . implode(' AND ', $conditions);

    $statement = $dbh->prepare('SELECT COUNT(*)' . $from);
    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('문제 수 조회 실패');
    }
    $total = (int)$statement->fetchColumn();
    $statement->closeCursor();
    $pages = max(1, (int)ceil($total / 20));
    $page = min((int)$page, $pages);
    $offset = ($page - 1) * 20;

    // 정답·시작 코드·PNG·채점 설정은 목록에서 조회하지 않는다.
    $statement = $dbh->prepare(
        'SELECT CAST(p.problem_id AS CHAR) AS problem_id,
                p.owner_user_id, p.sharing, p.status, v.title' . $from .
        ' ORDER BY p.problem_id DESC LIMIT 20 OFFSET ' . $offset
    );
    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('문제 목록 조회 실패');
    }
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    $problems = array();
    foreach ($rows as $row) {
        if (simulation_problem_permissions($context['actor'], $row)['import'] === true) {
            $problems[] = $row;
        }
    }

    $versions = array();
    if ($problems) {
        $problem_ids = array_column($problems, 'problem_id');
        $statement = $dbh->prepare(
            'SELECT CAST(problem_id AS CHAR) AS problem_id,
                    CAST(version_id AS CHAR) AS version_id,
                    version_no, title, simulation_mode,
                    source_language, execution_version
             FROM simulation_problem_version WHERE problem_id IN (' .
            implode(',', array_fill(0, count($problem_ids), '?')) .
            ') ORDER BY problem_id, version_no DESC'
        );
        if (!$statement || !$statement->execute($problem_ids)) {
            throw new RuntimeException('문제 버전 조회 실패');
        }
        while ($version = $statement->fetch(PDO::FETCH_ASSOC)) {
            $versions[$version['problem_id']][] = $version;
        }
        $statement->closeCursor();
    }
} catch (InvalidArgumentException $error) {
    course_import_list_error('수업·차시 번호와 검색 조건을 확인해 주세요.', 400);
} catch (UnexpectedValueException $error) {
    course_import_list_error('활성 수업·차시가 아니거나 가져오기 권한이 없습니다.', 403);
} catch (Throwable $error) {
    error_log('[course_simulation_problem_import_list] ' . $error->getMessage());
    course_import_list_error('가져올 문제 목록을 불러오지 못했습니다.', 500);
}

require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/header.php');
?>
<link rel="stylesheet" href="template/<?= course_import_list_escape($OJ_TEMPLATE) ?>/css/course.css">
<div class="course-page">
    <div class="course-page-header">
        <a class="ui small basic button" href="course_activity_add.php?lesson_id=<?= course_import_list_escape($lesson_id) ?>">활동 유형 다시 선택</a>
        <h1 class="ui header">가져올 시뮬레이션 문제 선택</h1>
        <p><?= course_import_list_escape($context['course_name']) ?> ·
            <?= course_import_list_escape($context['lesson_no']) ?>차시:
            <?= course_import_list_escape($context['lesson_title']) ?></p>
    </div>
    <div class="ui segment">
        <p>본인 문제와 교사 공유 문제에서 가져올 버전을 선택하세요.</p>
        <form class="ui form" method="get" action="course_simulation_problem_import_list.php">
            <input type="hidden" name="course_id" value="<?= course_import_list_escape($course_id) ?>">
            <input type="hidden" name="lesson_id" value="<?= course_import_list_escape($lesson_id) ?>">
            <div class="field">
                <label for="course-import-keyword">문제 제목 검색</label>
                <input id="course-import-keyword" name="keyword" type="search" maxlength="100"
                       value="<?= course_import_list_escape($keyword) ?>">
            </div>
            <button class="ui primary button" type="submit">검색</button>
        </form>
        <p>총 <?= number_format($total) ?>개</p>
        <?php if (!$problems): ?>
            <p>가져올 수 있는 문제가 없습니다. 본인 문제나 교사 공유 문제를 등록해 주세요.</p>
        <?php else: ?>
            <table class="ui celled stackable table">
                <thead><tr><th scope="col">문제</th><th scope="col">소유자</th><th scope="col">가져올 버전</th></tr></thead>
                <tbody>
                <?php foreach ($problems as $problem): ?>
                    <tr>
                        <td><?= course_import_list_escape($problem['title']) ?></td>
                        <td><?= course_import_list_escape($problem['owner_user_id']) ?></td>
                        <td>
                            <form class="ui form" method="get" action="course_simulation_problem_import.php">
                                <input type="hidden" name="course_id" value="<?= course_import_list_escape($course_id) ?>">
                                <input type="hidden" name="lesson_id" value="<?= course_import_list_escape($lesson_id) ?>">
                                <div class="field">
                                    <label for="course-import-version-<?= course_import_list_escape($problem['problem_id']) ?>">문제 버전</label>
                                    <select id="course-import-version-<?= course_import_list_escape($problem['problem_id']) ?>" name="version_id" required>
                                    <?php foreach ($versions[$problem['problem_id']] ?? array() as $version): ?>
                                        <option value="<?= course_import_list_escape($version['version_id']) ?>">
                                            <?= course_import_list_escape($version['version_no']) ?>번 ·
                                            <?= course_import_list_escape($version['title']) ?> ·
                                            <?= $version['simulation_mode'] === 'drawing' ? '그림 문제' : '자유 실습' ?> ·
                                            <?= course_import_list_escape($version['source_language']) ?>
                                            <?= course_import_list_escape($version['execution_version']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="ui small primary button" type="submit">이 버전 가져오기 확인</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if ($pages > 1): ?>
            <nav aria-label="문제 목록 페이지 이동">
                <?php foreach (array(-1 => '이전', 1 => '다음') as $direction => $label): ?>
                    <?php
                    $target = $page + $direction;
                    if ($target < 1 || $target > $pages) { continue; }
                    $url = 'course_simulation_problem_import_list.php?' . http_build_query(array(
                        'course_id' => $course_id, 'lesson_id' => $lesson_id,
                        'keyword' => $keyword, 'page' => $target,
                    ));
                    ?>
                    <a class="ui small basic button" href="<?= course_import_list_escape($url) ?>"><?= course_import_list_escape($label) ?></a>
                <?php endforeach; ?>
                <span><?= $page ?> / <?= $pages ?>페이지</span>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/footer.php'); ?>
