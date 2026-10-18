<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = '시뮬레이션 코드 제출';

// ============================================================
// 1. 로그인 및 POST 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    $view_errors = '<h2>로그인이 필요합니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $view_errors = '<h2>잘못된 요청입니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$user_id = (string)$_SESSION[$OJ_NAME.'_user_id'];

// ============================================================
// 2. 입력값 검증
// ============================================================

$activity_id_raw = $_POST['activity_id'] ?? '';
$source_code = $_POST['source_code'] ?? null;

if (
    !is_string($activity_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($activity_id_raw) > 10 ||
    (float)$activity_id_raw > 2147483647 ||
    !is_string($source_code) ||
    trim($source_code) === '' ||
    strlen($source_code) > 200000
) {
    $view_errors =
        '<h2>제출할 코드 또는 활동 번호가 올바르지 않습니다.</h2>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$activity_id = intval($activity_id_raw);

// ============================================================
// 3. Activity 소속 확인
// ============================================================

$activity_rows = pdo_query(
    "SELECT course_id
     FROM course_activity
     WHERE activity_id = ?
       AND activity_type = 'simulation'
     LIMIT 1",
    $activity_id
);

if ($activity_rows === false || !isset($activity_rows[0])) {
    $view_errors = '<h2>시뮬레이션 활동을 찾을 수 없습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

$course_id = intval($activity_rows[0]['course_id']);

if (!course_is_active_student($course_id)) {
    $view_errors = '<h2>코드를 제출할 수 있는 수강생이 아닙니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 4. PDO 연결 준비
// ============================================================

$connection_check = pdo_query('SELECT 1 AS connection_ok');

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    $view_errors = '<h2>데이터베이스 연결에 실패했습니다.</h2>';
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 5. 트랜잭션 및 제출 정책 검사
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

    // 동일 학생의 동시 제출을 직렬화한다.
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
        throw new RuntimeException('수강 상태가 변경되었습니다.');
    }

    // 실행 설정 및 공개 상태 재검사
    $activity_check = $dbh->prepare(
        "SELECT
            c.status AS course_status,
            cl.status AS lesson_status,
            cl.visible AS lesson_visible,
            ca.status AS activity_status,
            ca.visible AS activity_visible,
            s.allow_edit,
            s.allow_submit,
            s.simulation_mode,
            s.starter_code
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
        intval($settings['allow_submit']) !== 1
    ) {
        throw new RuntimeException(
            '현재 제출할 수 없는 시뮬레이션입니다.'
        );
    }

    // 편집 금지 활동에서는 기본 코드만 제출 가능
    if (
        intval($settings['allow_edit']) !== 1 &&
        $source_code !== (string)$settings['starter_code']
    ) {
        throw new RuntimeException(
            '코드 수정이 허용되지 않은 활동입니다.'
        );
    }

    // 다음 제출 번호 계산
    $attempt_check = $dbh->prepare(
        "SELECT COALESCE(MAX(submission_no), 0) AS latest_no
         FROM course_simulation_submission
         WHERE activity_id = ?
           AND user_id = ?"
    );

    $attempt_check->execute(array(
        $activity_id,
        $user_id
    ));

    $attempt = $attempt_check->fetch(PDO::FETCH_ASSOC);
    $attempt_check->closeCursor();

    $submission_no =
        intval($attempt['latest_no'] ?? 0) + 1;

    if ($submission_no <= 0 || $submission_no > 2147483647) {
        throw new RuntimeException(
            '제출 횟수가 허용 범위를 초과했습니다.'
        );
    }

    // 기존 제출은 변경하지 않고 신규 기록으로 저장
    $save = $dbh->prepare(
        "INSERT INTO course_simulation_submission
            (activity_id, user_id, submission_no, source_code)
         VALUES (?, ?, ?, ?)"
    );

    $save->execute(array(
        $activity_id,
        $user_id,
        $submission_no,
        $source_code
    ));

    $save->closeCursor();

    $saved_submission_id = (string)$dbh->lastInsertId();
    $simulation_grade_queued = false;

    // 정답이 등록된 그림 그리기 활동만 자동 채점 대기 등록.
    // 제출과 채점 요청을 같은 트랜잭션으로 저장한다.
    if ((string)$settings['simulation_mode'] === 'drawing') {
        $reference_check = $dbh->prepare(
            "SELECT
                reference_id,
                reference_no,
                source_code,
                canvas_width,
                canvas_height,
                p5_version,
                render_config,
                grading_config,
                max_score
             FROM course_simulation_reference
             WHERE activity_id = ?
             ORDER BY reference_no DESC
             LIMIT 1
             FOR UPDATE"
        );

        $reference_check->execute(array($activity_id));
        $reference = $reference_check->fetch(PDO::FETCH_ASSOC);
        $reference_check->closeCursor();

        if ($reference) {
            // CLI 대기 등록과 같은 메타데이터 형식을 사용한다.
            $grade_feedback = array(
                'schema_version' => 1,
                'request' => array(
                    'activity_id' => (int)$activity_id,
                    'reference_id' => (int)$reference['reference_id'],
                    'reference_no' => (int)$reference['reference_no'],
                    'canvas_width' => (int)$reference['canvas_width'],
                    'canvas_height' => (int)$reference['canvas_height'],
                    'p5_version' => (string)$reference['p5_version'],
                    'reference_max_score' => (string)$reference['max_score'],
                    'reference_source_sha256' => hash(
                        'sha256',
                        (string)$reference['source_code']
                    ),
                    'student_source_sha256' => hash(
                        'sha256',
                        (string)$source_code
                    ),
                    'render_config_sha256' => hash(
                        'sha256',
                        (string)$reference['render_config']
                    ),
                    'grading_config_sha256' => hash(
                        'sha256',
                        (string)$reference['grading_config']
                    ),
                ),
            );

            $grade_feedback_json = json_encode(
                $grade_feedback,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($grade_feedback_json === false) {
                throw new RuntimeException(
                    '자동 채점 요청 JSON 생성에 실패했습니다.'
                );
            }

            $grade_save = $dbh->prepare(
                "INSERT INTO course_simulation_grade
                    (submission_id, grading_no, grading_status,
                     score, max_score, feedback_json, graded_at)
                 VALUES (?, 1, 'pending', NULL, NULL, ?, NULL)"
            );

            $grade_save->execute(array(
                $saved_submission_id,
                $grade_feedback_json,
            ));
            $grade_save->closeCursor();
            $simulation_grade_queued = true;
        }
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
        '[course_simulation_submit] ' .
        $e->getMessage()
    );

    $view_errors =
        '<h2>시뮬레이션 코드 제출에 실패했습니다.</h2>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

// ============================================================
// 6. 저장 완료 후 채점 처리기에 알림
// ============================================================

// DB 커밋이 완료된 뒤에만 알린다.
// 알림 실패는 이미 저장된 제출의 성공 여부에 영향을 주지 않는다.
if (!empty($simulation_grade_queued)) {
    try {
        $notify_errno = 0;
        $notify_error = '';

        $notify_socket = @stream_socket_client(
            'unix:///run/1024kr-simulation-grade/notify.sock',
            $notify_errno,
            $notify_error,
            0.05,
            STREAM_CLIENT_CONNECT
        );

        if ($notify_socket !== false) {
            // 처리기는 연결 수락 자체를 알림으로 사용한다.
            // 정답 코드나 제출 코드를 소켓으로 보내지 않는다.
            fclose($notify_socket);
        } else {
            error_log(
                '[course_simulation_submit] ' .
                '채점 알림 연결 실패. 제출과 대기 기록은 보존됨. 오류 번호: ' .
                $notify_errno
            );
        }
    } catch (Throwable $notify_exception) {
        error_log(
            '[course_simulation_submit] ' .
            '채점 알림 처리 실패. 제출과 대기 기록은 보존됨.'
        );
    }
}

// ============================================================
// 7. 시뮬레이션 화면으로 이동
// ============================================================

header(
    "Location: course_simulation_view.php?activity_id=" .
    $activity_id .
    "&submitted=" .
    rawurlencode($saved_submission_id),
    true,
    303
);

exit(0);
