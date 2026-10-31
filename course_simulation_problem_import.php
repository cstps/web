<?php

require_once(__DIR__ . '/scripts/simulation_execution_config.php');
require_once(__DIR__ . '/include/db_info.inc.php');
require_once(__DIR__ . '/include/const.inc.php');
require_once(__DIR__ . '/include/memcache.php');
require_once(__DIR__ . '/include/setlang.php');
require_once(__DIR__ . '/scripts/course_simulation_problem_import_context.php');
require_once(__DIR__ . '/scripts/simulation_problem_csrf.php');

$view_title = '문제 가져오기 확인';
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function course_simulation_problem_import_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function course_simulation_problem_import_error($message, $status)
{
    global $OJ_TEMPLATE, $view_errors, $view_title;

    http_response_code($status);
    $view_errors = '<h2>' .
        course_simulation_problem_import_escape($message) . '</h2>';
    require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_simulation_problem_import_error('로그인이 필요합니다.', 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    course_simulation_problem_import_error(
        'GET 요청으로 확인 화면을 열어 주세요.',
        405
    );
}

$required = array('course_id', 'lesson_id', 'version_id');

if (
    count($_GET) !== count($required) ||
    array_diff(array_keys($_GET), $required)
) {
    course_simulation_problem_import_error(
        '수업·차시·문제 버전을 지정해 주세요.',
        400
    );
}

try {
    $course_id = course_simulation_problem_import_id(
        $_GET['course_id'] ?? null, true
    );
    $lesson_id = course_simulation_problem_import_id(
        $_GET['lesson_id'] ?? null, true
    );
    $version_id = course_simulation_problem_import_id(
        $_GET['version_id'] ?? null
    );

    if (!function_exists('pdo_query')) {
        require_once(__DIR__ . '/include/pdo.php');
    }

    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('가져오기 DB 연결에 실패했습니다.');
    }

    $context = course_simulation_problem_import_context(
        $dbh, $course_id, $lesson_id
    );
    $source = course_simulation_problem_import_source(
        $dbh, $context['actor'], $version_id
    );

    $token = simulation_problem_csrf_issue(
        course_simulation_problem_import_token_context(
            $course_id, $lesson_id, $version_id
        )
    );
} catch (InvalidArgumentException $error) {
    course_simulation_problem_import_error(
        '가져오기 대상 번호가 올바르지 않습니다.',
        400
    );
} catch (UnexpectedValueException $error) {
    course_simulation_problem_import_error(
        '문제를 가져올 수 없거나 수업·문제에 대한 권한이 없습니다.',
        403
    );
} catch (Throwable $error) {
    error_log('[course_simulation_problem_import] ' . $error->getMessage());
    course_simulation_problem_import_error(
        '가져오기 정보를 확인하지 못했습니다. 문제와 목표 이미지 상태를 확인해 주세요.',
        500
    );
}

$content = $source['content'];
$drawing = $content['simulation_mode'] === 'drawing';

require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/header.php');
?>
<link rel="stylesheet"
      href="template/<?= course_simulation_problem_import_escape($OJ_TEMPLATE) ?>/css/course.css">

<div class="course-page">
    <div class="course-page-header">
        <a class="ui small basic button"
           href="<?= course_simulation_problem_import_escape(
               'course_simulation_problem_import_list.php?' .
               http_build_query(array('course_id' => $course_id, 'lesson_id' => $lesson_id))
           ) ?>">다른 문제·버전 선택</a>
        <h1 class="ui header">문제 가져오기 확인</h1>
        <p class="course-page-description">
            <?= course_simulation_problem_import_escape($context['course_name']) ?>
        </p>
    </div>

    <div class="ui segment">
        <h2 class="ui dividing header">
            <?= course_simulation_problem_import_escape($content['title']) ?>
        </h2>

        <table class="ui definition stackable table">
            <tbody>
                <tr>
                    <th scope="row">가져올 차시</th>
                    <td>
                        <?= course_simulation_problem_import_escape($context['lesson_no']) ?>차시:
                        <?= course_simulation_problem_import_escape($context['lesson_title']) ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">원본 문제 번호</th>
                    <td><?= course_simulation_problem_import_escape($source['problem_id']) ?></td>
                </tr>
                <tr>
                    <th scope="row">선택 버전</th>
                    <td>
                        <?= course_simulation_problem_import_escape($source['version_no']) ?>번
                        (버전 ID: <?= course_simulation_problem_import_escape($version_id) ?>)
                    </td>
                </tr>
                <tr>
                    <th scope="row">활동 유형</th>
                    <td><?= $drawing ? '그림 문제' : '자유 실습' ?></td>
                </tr>
                <tr>
                    <th scope="row">실행 환경</th>
                    <td>
                        <?= course_simulation_problem_import_escape($content['execution_engine']) ?>
                        / <?= course_simulation_problem_import_escape($content['execution_version']) ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">캔버스</th>
                    <td>
                        <?= course_simulation_problem_import_escape($content['canvas_width']) ?>
                        × <?= course_simulation_problem_import_escape($content['canvas_height']) ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">학생 코드 편집</th>
                    <td><?= (int)$content['allow_edit'] === 1 ? '허용' : '허용하지 않음' ?></td>
                </tr>
                <tr>
                    <th scope="row">학생 제출</th>
                    <td><?= (int)$content['allow_submit'] === 1 ? '허용' : '허용하지 않음' ?></td>
                </tr>
            </tbody>
        </table>

        <div class="ui info message">
            <div class="header">비공개 활동으로 추가됩니다.</div>
            <p>선택한 문제 버전의 내용을 복사합니다.
               가져온 뒤 편집 화면에서 내용을 확인하고 학생 공개 여부를 설정해 주세요.</p>
            <?php if ($drawing): ?>
                <p>원본 목표 그림의 검증이 완료되었습니다.
                   가져온 활동의 목표 그림은 새 정답 번호에 맞춰 별도로 생성해야 합니다.</p>
            <?php endif; ?>
        </div>

        <form class="ui form"
              method="post"
              action="course_simulation_problem_import_save.php">
            <input type="hidden" name="course_id"
                   value="<?= course_simulation_problem_import_escape($course_id) ?>">
            <input type="hidden" name="lesson_id"
                   value="<?= course_simulation_problem_import_escape($lesson_id) ?>">
            <input type="hidden" name="version_id"
                   value="<?= course_simulation_problem_import_escape($version_id) ?>">
            <input type="hidden" name="import_token"
                   value="<?= course_simulation_problem_import_escape($token) ?>">

            <button class="ui primary button" type="submit">
                비공개 활동으로 가져오기
            </button>
            <a class="ui basic button"
               href="<?= course_simulation_problem_import_escape(
                   'course_view.php?' .
                   http_build_query(array('course_id' => $course_id))
               ) ?>">수업으로 돌아가기</a>
        </form>
    </div>
</div>
<?php require(__DIR__ . '/template/' . $OJ_TEMPLATE . '/footer.php'); ?>
