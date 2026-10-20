<?php

$simulation_autosave_buffer_level = ob_get_level();
ob_start();

function simulation_autosave_json($data, $status = 200)
{
    global $simulation_autosave_buffer_level;

    while (ob_get_level() > $simulation_autosave_buffer_level) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./scripts/course_simulation_draft_store.php');
// 자동 저장 전용 토큰은 같은 로그인 세션에서 반복 사용할 수 있다.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    simulation_autosave_json(
        array('ok' => false, 'message' => '저장 요청만 허용합니다.'),
        405
    );
}

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    simulation_autosave_json(
        array('ok' => false, 'message' => '로그인이 필요합니다.'),
        403
    );
}

$autosave_token = $_POST['autosave_token'] ?? null;
$autosave_session = $_SESSION[$OJ_NAME . '_simulation_autosave'] ?? null;

if (
    !is_string($autosave_token) ||
    !is_array($autosave_session) ||
    !isset($autosave_session['user_id'], $autosave_session['token']) ||
    !is_string($autosave_session['token']) ||
    (string)$autosave_session['user_id'] !==
        (string)$_SESSION[$OJ_NAME . '_user_id'] ||
    !hash_equals($autosave_session['token'], $autosave_token)
) {
    simulation_autosave_json(
        array(
            'ok' => false,
            'message' => '자동 저장 요청 확인에 실패했습니다. 코드를 보관한 뒤 화면을 새로고침해 주세요.'
        ),
        403
    );
}


$view_title = '시뮬레이션 코드 자동 저장';

// ============================================================
// 1. 로그인 및 요청 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>로그인이 필요합니다.</h2>')),
        400
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>잘못된 요청입니다.</h2>')),
        400
    );
}

$user_id = (string)$_SESSION[$OJ_NAME.'_user_id'];

// ============================================================
// 2. 입력값 확인
// ============================================================

$activity_id_raw = $_POST['activity_id'] ?? '';
$source_code = $_POST['source_code'] ?? null;
$expected_revision = $_POST['expected_revision'] ?? null;

if (
    !is_string($expected_revision) ||
    !preg_match('/^(0|[1-9][0-9]{0,19})$/D', $expected_revision) ||
    (
        strlen($expected_revision) === 20 &&
        strcmp($expected_revision, '18446744073709551615') > 0
    )
) {
    simulation_autosave_json(array(
        'ok' => false,
        'code' => 'revision_required',
        'message' => '초안 버전을 확인하지 못했습니다. 현재 코드를 복사해 보관한 뒤 화면을 새로고침해 주세요.',
    ), 409);
}


if (
    !is_string($activity_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($activity_id_raw) > 10 ||
    (float)$activity_id_raw > 2147483647 ||
    !is_string($source_code) ||
    strlen($source_code) > 200000
) {
    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>저장할 코드 또는 활동 번호가 올바르지 않습니다.</h2>')),
        400
    );
}

$activity_id = intval($activity_id_raw);

// ============================================================
// 3. Activity 및 Course 정보 확인
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        ca.activity_id,
        ca.course_id
     FROM course_activity ca
     WHERE ca.activity_id = ?
       AND ca.activity_type = 'simulation'
     LIMIT 1",
    $activity_id
);

if ($activity_rows === false || !isset($activity_rows[0])) {
    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>시뮬레이션 활동을 찾을 수 없습니다.</h2>')),
        400
    );
}

$course_id = intval($activity_rows[0]['course_id']);

// 학생 본인만 임시 저장할 수 있다.
if (!course_is_active_student($course_id)) {
    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>코드를 저장할 수 있는 수강생이 아닙니다.</h2>')),
        400
    );
}

// ============================================================
// 4. PDO 연결
// ============================================================

$connection_check = pdo_query('SELECT 1 AS connection_ok');

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>데이터베이스 연결에 실패했습니다.</h2>')),
        400
    );
}

// ============================================================
// 5. 트랜잭션 및 최신 상태 재검증
// ============================================================

$transaction_started = false;

try {
    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();
    $transaction_started = true;

    // 수강 상태를 잠그고 재확인한다.
    $student_check = $dbh->prepare(
        "SELECT status
         FROM course_student
         WHERE course_id = ?
           AND user_id = ?
         FOR UPDATE"
    );

    $student_check->execute(array(
        $course_id,
        $user_id
    ));

    $student = $student_check->fetch(PDO::FETCH_ASSOC);
    $student_check->closeCursor();

    if (!$student || intval($student['status']) !== 1) {
        throw new RuntimeException(
            '수강 상태가 변경되었습니다.'
        );
    }

    // 수업·차시·활동 공개 여부 및 편집 허용 상태를 재검사한다.
    $activity_check = $dbh->prepare(
        "SELECT
            c.status AS course_status,
            cl.status AS lesson_status,
            cl.visible AS lesson_visible,
            ca.status AS activity_status,
            ca.visible AS activity_visible,
            s.allow_edit
         FROM course_activity ca
         INNER JOIN course c
           ON c.course_id = ca.course_id
         INNER JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         INNER JOIN course_simulation s
           ON s.activity_id = ca.activity_id
         WHERE ca.activity_id = ?
           AND ca.course_id = ?
           AND ca.activity_type = 'simulation'
         FOR UPDATE"
    );

    $activity_check->execute(array(
        $activity_id,
        $course_id
    ));

    $settings = $activity_check->fetch(PDO::FETCH_ASSOC);
    $activity_check->closeCursor();

    if (
        !$settings ||
        intval($settings['course_status']) !== 1 ||
        intval($settings['lesson_status']) !== 1 ||
        intval($settings['lesson_visible']) !== 1 ||
        intval($settings['activity_status']) !== 1 ||
        intval($settings['activity_visible']) !== 1 ||
        intval($settings['allow_edit']) !== 1
    ) {
        throw new RuntimeException(
            '현재 코드를 저장할 수 없는 활동입니다.'
        );
    }

    // 학생·활동별 최신 작업 코드 하나만 저장한다.
    $saved_revision = simulation_store_versioned_draft(
        $dbh,
        $activity_id,
        $user_id,
        $source_code,
        $expected_revision
    );

    $dbh->commit();
    $transaction_started = false;

} catch (Throwable $e) {

    if (
        $transaction_started &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    if ($e instanceof SimulationDraftConflictException) {
        simulation_autosave_json(array(
            'ok' => false,
            'code' => 'draft_conflict',
            'message' => $e->getMessage(),
        ), 409);
    }

    error_log(
        '[course_simulation_draft_autosave] ' .
        $e->getMessage()
    );

    simulation_autosave_json(
        array('ok' => false, 'message' => strip_tags('<h2>코드를 임시 저장하지 못했습니다.</h2>')),
        400
    );
}

// ============================================================
// 6. 자동 저장 결과 응답
// ============================================================

simulation_autosave_json(array(
    'ok' => true,
    'activity_id' => (string)$activity_id,
    'source_sha256' => hash('sha256', $source_code),
    'revision' => (string)$saved_revision,
    'message' => '코드 자동 저장 완료',
));
