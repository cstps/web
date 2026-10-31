<?php

require_once(__DIR__ . '/scripts/simulation_execution_config.php');
require_once(__DIR__ . '/include/db_info.inc.php');
require_once(__DIR__ . '/include/const.inc.php');
require_once(__DIR__ . '/include/memcache.php');
require_once(__DIR__ . '/include/setlang.php');
require_once(__DIR__ . '/scripts/course_simulation_problem_import_context.php');

$view_title = '문제를 가져올 차시 선택';
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

function course_simulation_problem_import_select_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function course_simulation_problem_import_select_error($message, $status)
{
    global $OJ_TEMPLATE, $view_errors, $view_title;

    http_response_code($status);
    $view_errors = '<h2>' .
        course_simulation_problem_import_select_escape($message) . '</h2>';
    require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_simulation_problem_import_select_error(
        '로그인이 필요합니다.',
        403
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    course_simulation_problem_import_select_error(
        'GET 요청으로 차시 선택 화면을 열어 주세요.',
        405
    );
}

if (count($_GET) !== 1 || !array_key_exists('version_id', $_GET)) {
    course_simulation_problem_import_select_error(
        '가져올 문제 버전을 지정해 주세요.',
        400
    );
}

try {
    $version_id = course_simulation_problem_import_id($_GET['version_id']);

    $user = $_SESSION[$OJ_NAME . '_user_id'];
    if (
        !is_string($user) ||
        trim($user) === '' ||
        strlen($user) > 192 ||
        preg_match('/^.{1,48}$/usD', $user) !== 1 ||
        preg_match('/[\x00-\x1f\x7f]/', $user) === 1
    ) {
        throw new UnexpectedValueException('로그인 사용자 확인 실패');
    }

    if (!function_exists('pdo_query')) {
        require_once(__DIR__ . '/include/pdo.php');
    }

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('가져오기 DB 연결 실패');
    }

    $administrator = isset($_SESSION[$OJ_NAME . '_administrator']);

    $sql = 'SELECT c.course_id, c.course_name,
                   l.lesson_id, l.lesson_no, l.title AS lesson_title
            FROM course c
            INNER JOIN course_lesson l ON l.course_id = c.course_id
            WHERE c.status = 1 AND l.status = 1';

    $arguments = array();

    if (!$administrator) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM course_teacher t
            WHERE t.course_id = c.course_id
              AND t.user_id = ?
              AND t.status = 1
              AND t.role IN ('owner', 'teacher')
        )";
        $arguments[] = $user;
    }

    $sql .= ' ORDER BY c.course_id DESC, l.lesson_no, l.lesson_id';

    $statement = $dbh->prepare($sql);
    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('가져올 수업·차시 조회 실패');
    }

    $lessons = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$lessons) {
        throw new UnexpectedValueException(
            '가져올 수 있는 활성 수업·차시가 없습니다.'
        );
    }

    // 기존 수업 권한 검사에서 얻은 actor만 문제 조회에 사용한다.
    $context = course_simulation_problem_import_context(
        $dbh,
        (string)$lessons[0]['course_id'],
        (string)$lessons[0]['lesson_id']
    );
    $source = course_simulation_problem_import_source(
        $dbh,
        $context['actor'],
        $version_id
    );
} catch (InvalidArgumentException $error) {
    course_simulation_problem_import_select_error(
        '문제 버전 번호가 올바르지 않습니다.',
        400
    );
} catch (UnexpectedValueException $error) {
    course_simulation_problem_import_select_error(
        '가져오기 권한이 없거나 가져올 수 있는 활성 수업·차시가 없습니다.',
        403
    );
} catch (Throwable $error) {
    error_log('[course_simulation_problem_import_select] ' . $error->getMessage());
    course_simulation_problem_import_select_error(
        '가져오기 정보를 확인하지 못했습니다. 문제와 목표 이미지 상태를 확인해 주세요.',
        500
    );
}

require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/header.php');
?>
<link rel="stylesheet"
      href="template/<?= course_simulation_problem_import_select_escape($OJ_TEMPLATE) ?>/css/course.css">
<div class="course-page">
    <div class="course-page-header">
        <h1 class="ui header">문제를 가져올 차시 선택</h1>
        <p>
            <?= course_simulation_problem_import_select_escape($source['content']['title']) ?>
            · <?= course_simulation_problem_import_select_escape($source['version_no']) ?>번 버전
        </p>
    </div>
    <div class="ui segment">
        <p>문제를 추가할 차시를 선택하면 가져오기 확인 화면으로 이동합니다.</p>
        <table class="ui celled stackable table">
            <thead>
                <tr>
                    <th scope="col">수업</th>
                    <th scope="col">차시</th>
                    <th scope="col">선택</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lessons as $lesson): ?>
                <?php
                $url = 'course_simulation_problem_import.php?' .
                    http_build_query(array(
                        'course_id' => (string)$lesson['course_id'],
                        'lesson_id' => (string)$lesson['lesson_id'],
                        'version_id' => $version_id,
                    ));
                ?>
                <tr>
                    <td><?= course_simulation_problem_import_select_escape($lesson['course_name']) ?></td>
                    <td>
                        <?= course_simulation_problem_import_select_escape($lesson['lesson_no']) ?>차시:
                        <?= course_simulation_problem_import_select_escape($lesson['lesson_title']) ?>
                    </td>
                    <td>
                        <a class="ui small primary button"
                           href="<?= course_simulation_problem_import_select_escape($url) ?>">
                            이 차시 선택
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/footer.php'); ?>
