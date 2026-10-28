<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once(__DIR__ . '/scripts/simulation_execution_config.php');
require_once('./include/csrf_check.php');

$view_title = "시뮬레이션 설정 저장";

// ============================================================
// 1. 로그인 및 POST 요청 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = "<h2>로그인이 필요합니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $view_errors = "<h2>잘못된 요청입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 2. 입력값 확인
// ============================================================

$course_id_raw = $_POST['course_id'] ?? '';
$activity_id_raw = $_POST['activity_id'] ?? '';

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$visible_raw = $_POST['visible'] ?? '';

$simulation_mode = $_POST['simulation_mode'] ?? '';
$starter_code = $_POST['starter_code'] ?? '';

$canvas_width_raw = $_POST['canvas_width'] ?? '';
$canvas_height_raw = $_POST['canvas_height'] ?? '';

$allow_edit_raw = $_POST['allow_edit'] ?? '';
$allow_submit_raw = $_POST['allow_submit'] ?? '';

// 화면을 연 뒤 실행 언어가 변경됐는지 확인하는 기준이다.
// 이 값으로 DB의 실행 언어나 엔진을 변경하지 않는다.
$expected_execution = array(
    'source_language' => $_POST['expected_source_language'] ?? null,
    'execution_engine' => $_POST['expected_execution_engine'] ?? null,
    'execution_version' => $_POST['expected_execution_version'] ?? null,
);

$input_values = array(
    $course_id_raw,
    $activity_id_raw,
    $title,
    $description,
    $visible_raw,
    $simulation_mode,
    $starter_code,
    $canvas_width_raw,
    $canvas_height_raw,
    $allow_edit_raw,
    $allow_submit_raw
);

$input_values = array_merge($input_values, array_values($expected_execution));

foreach ($input_values as $value) {
    if (!is_string($value)) {
        $view_errors = "<h2>잘못된 입력값입니다.</h2>";
        require("template/".$OJ_TEMPLATE."/error.php");
        exit(0);
    }
}

$course_id_raw = trim($course_id_raw);
$activity_id_raw = trim($activity_id_raw);

$title = trim($title);
$description = trim($description);

$simulation_mode = trim($simulation_mode);

$canvas_width_raw = trim($canvas_width_raw);
$canvas_height_raw = trim($canvas_height_raw);

if (
    !preg_match('/^[1-9][0-9]*$/D', $course_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($course_id_raw) > 10 ||
    strlen($activity_id_raw) > 10 ||
    (float)$course_id_raw > 2147483647 ||
    (float)$activity_id_raw > 2147483647 ||
    $title === '' ||
    strlen($title) > 255 ||
    strlen($description) > 65535 ||
    !in_array($visible_raw, array('0', '1'), true) ||
    !in_array($simulation_mode, array('free', 'drawing'), true) ||
    trim($starter_code) === '' ||
    strlen($starter_code) > 200000 ||
    strpos($starter_code, "\0") !== false ||
    preg_match('//u', $starter_code) !== 1 ||
    !preg_match('/^[0-9]+$/D', $canvas_width_raw) ||
    !preg_match('/^[0-9]+$/D', $canvas_height_raw) ||
    strlen($canvas_width_raw) > 4 ||
    strlen($canvas_height_raw) > 4 ||
    !in_array($allow_edit_raw, array('0', '1'), true) ||
    !in_array($allow_submit_raw, array('0', '1'), true)
) {
    $view_errors = "<h2>시뮬레이션 입력값이 올바르지 않습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($course_id_raw);
$activity_id = intval($activity_id_raw);

$canvas_width = intval($canvas_width_raw);
$canvas_height = intval($canvas_height_raw);

$visible = intval($visible_raw);
$allow_edit = intval($allow_edit_raw);
$allow_submit = intval($allow_submit_raw);

if (
    $canvas_width < 100 ||
    $canvas_width > 2000 ||
    $canvas_height < 100 ||
    $canvas_height > 2000
) {
    $view_errors = "<h2>캔버스 크기는 100~2000px이어야 합니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 3. Activity 및 Course 상태 확인
// ============================================================

$activity_rows = pdo_query(
    "SELECT
        activity_id,
        course_id,
        lesson_id,
        activity_type,
        status
     FROM course_activity
     WHERE activity_id = ?
       AND course_id = ?
     LIMIT 1",
    $activity_id,
    $course_id
);

if (!$activity_rows || !isset($activity_rows[0])) {
    $view_errors = "<h2>존재하지 않는 학습 활동입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity = $activity_rows[0];

if (
    $activity['activity_type'] !== 'simulation' ||
    intval($activity['status']) !== 1
) {
    $view_errors = "<h2>수정할 수 없는 활동입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_rows = pdo_query(
    "SELECT course_id, status
     FROM course
     WHERE course_id = ?
     LIMIT 1",
    $course_id
);

if (
    !$course_rows ||
    !isset($course_rows[0]) ||
    intval($course_rows[0]['status']) !== 1
) {
    $view_errors = "<h2>활성 상태의 수업이 아닙니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 4. 교사 관리 권한 및 Lesson 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {
    $view_errors = "<h2>시뮬레이션 수정 권한이 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$lesson_id = intval($activity['lesson_id']);

if ($lesson_id <= 0) {
    $view_errors = "<h2>소속 차시가 없습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$lesson = course_get_lesson($lesson_id);

if (
    !$lesson ||
    intval($lesson['course_id']) !== $course_id ||
    intval($lesson['status']) !== 1
) {
    $view_errors = "<h2>유효하지 않은 차시입니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 5. PDO 연결 준비
// ============================================================

$connection_check = pdo_query('SELECT 1 AS connection_ok');

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    $view_errors = "<h2>데이터베이스 연결에 실패했습니다.</h2>";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. Activity 및 시뮬레이션 설정 동시 저장
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

    // 수업 상태 잠금 및 재확인
    $check_course = $dbh->prepare(
        "SELECT status
         FROM course
         WHERE course_id = ?
         FOR UPDATE"
    );
    $check_course->execute(array($course_id));
    $locked_course = $check_course->fetch(PDO::FETCH_ASSOC);
    $check_course->closeCursor();

    if (!$locked_course || intval($locked_course['status']) !== 1) {
        throw new RuntimeException('수업 상태가 변경되었습니다.');
    }

    // 차시 상태 잠금 및 재확인
    $check_lesson = $dbh->prepare(
        "SELECT status
         FROM course_lesson
         WHERE lesson_id = ?
           AND course_id = ?
         FOR UPDATE"
    );
    $check_lesson->execute(array($lesson_id, $course_id));
    $locked_lesson = $check_lesson->fetch(PDO::FETCH_ASSOC);
    $check_lesson->closeCursor();

    if (!$locked_lesson || intval($locked_lesson['status']) !== 1) {
        throw new RuntimeException('차시 상태가 변경되었습니다.');
    }

    // 학습활동 잠금 및 재확인
    $check_activity = $dbh->prepare(
        "SELECT activity_type
         FROM course_activity
         WHERE activity_id = ?
           AND course_id = ?
           AND lesson_id = ?
           AND status = 1
         FOR UPDATE"
    );

    $check_activity->execute(array(
        $activity_id,
        $course_id,
        $lesson_id
    ));

    $locked_activity = $check_activity->fetch(PDO::FETCH_ASSOC);
    $check_activity->closeCursor();

    if (
        !$locked_activity ||
        $locked_activity['activity_type'] !== 'simulation'
    ) {
        throw new RuntimeException(
            '시뮬레이션 활동 상태가 변경되었습니다.'
        );
    }

    // 활동 정보가 변경되기 전에 시뮬레이션 설정을 잠근다.
    $execution_check = $dbh->prepare(
        'SELECT source_language, execution_engine, execution_version
         FROM course_simulation
         WHERE activity_id = ? FOR UPDATE'
    );
    $execution_check->execute(array($activity_id));
    $locked_simulation = $execution_check->fetch(PDO::FETCH_ASSOC);
    $execution_check->closeCursor();

    if (!$locked_simulation) {
        throw new RuntimeException('시뮬레이션 설정이 없습니다.');
    }

    $saved_execution = simulation_validate_execution_metadata(
        $locked_simulation, true
    );

    foreach ($expected_execution as $key => $value) {
        if ($saved_execution[$key] !== $value) {
            throw new UnexpectedValueException(
                '활동의 실행 설정이 변경됐습니다. 편집 화면을 다시 열어 주세요.'
            );
        }
    }

    if (
        ($saved_execution['source_language'] === 'python' ||
         $simulation_mode === 'drawing') &&
        ($canvas_width !== 400 || $canvas_height !== 400)
    ) {
        throw new UnexpectedValueException(
            'Python 활동과 그림 문제의 캔버스는 400×400이어야 합니다.'
        );
    }

    // 기존 Activity 정보 수정
    $update_activity = $dbh->prepare(
        "UPDATE course_activity
         SET title = ?,
             description = ?,
             visible = ?
         WHERE activity_id = ?
           AND course_id = ?
           AND lesson_id = ?
           AND activity_type = 'simulation'
           AND status = 1"
    );

    $update_activity->execute(array(
        $title,
        $description,
        $visible,
        $activity_id,
        $course_id,
        $lesson_id
    ));
    $update_activity->closeCursor();

    // 시뮬레이션 전용 설정 수정
    $update_simulation = $dbh->prepare(
        "UPDATE course_simulation
         SET simulation_mode = ?,
             starter_code = ?,
             canvas_width = ?,
             canvas_height = ?,
             allow_edit = ?,
             allow_submit = ?
         WHERE activity_id = ?"
    );

    $update_simulation->execute(array(
        $simulation_mode,
        $starter_code,
        $canvas_width,
        $canvas_height,
        $allow_edit,
        $allow_submit,
        $activity_id
    ));
    $update_simulation->closeCursor();

    // UPDATE rowCount()는 값이 동일하면 0일 수 있으므로
    // 실제 설정 레코드 존재 여부를 별도로 확인한다.
    $check_simulation = $dbh->prepare(
        "SELECT activity_id
         FROM course_simulation
         WHERE activity_id = ?
         LIMIT 1"
    );
    $check_simulation->execute(array($activity_id));
    $saved_simulation = $check_simulation->fetch(PDO::FETCH_ASSOC);
    $check_simulation->closeCursor();

    if (!$saved_simulation) {
        throw new RuntimeException(
            '시뮬레이션 설정 레코드가 존재하지 않습니다.'
        );
    }

    $dbh->commit();
    $transaction_started = false;

} catch (Throwable $e) {
    if (
        $transaction_started &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    error_log(
        '[course_simulation_update] ' . $e->getMessage()
    );

    $view_errors =
        "<h2>시뮬레이션 설정 저장에 실패했습니다.</h2>";

    if ($e instanceof UnexpectedValueException) {
        http_response_code(409);
        $view_errors .= '<p>' . htmlspecialchars(
            $e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
        ) . '</p>';
    }

    // 저장 실패 시 교사가 작성한 코드를 복사할 수 있게 보존한다.
    $view_errors .=
        '<p>입력한 기본 코드를 복사해 보관한 뒤 편집 화면을 다시 열어 주세요.</p>' .
        '<textarea readonly rows="12" aria-label="저장하지 못한 기본 코드" ' .
        'style="width:100%;font-family:monospace;">' .
        htmlspecialchars($starter_code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '</textarea>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 7. 수업 관리 화면으로 이동
// ============================================================

header(
    "Location: course_view.php?course_id=" .
    $course_id,
    true,
    303
);

exit(0);
