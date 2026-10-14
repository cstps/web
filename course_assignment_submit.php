<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/course_file_upload.inc.php');

$view_title = '과제 제출';

function course_assignment_submit_error($message, $status = 400)
{
    global $view_errors, $OJ_TEMPLATE;

    http_response_code($status);

    $view_errors = '<h2>' .
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .
        '</h2>';

    require("template/".$OJ_TEMPLATE."/error.php");
    exit;
}

// ============================================================
// 1. POST / CSRF / 로그인 검사
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    course_assignment_submit_error(
        '잘못된 요청입니다.',
        405
    );
}

require_once('./include/csrf_check.php');

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    course_assignment_submit_error(
        '로그인이 필요합니다.',
        403
    );
}

$user_id = (string)$_SESSION[$OJ_NAME.'_user_id'];

if ($user_id === '' || strlen($user_id) > 48) {
    course_assignment_submit_error(
        '사용자 정보가 올바르지 않습니다.',
        403
    );
}

// ============================================================
// 2. 입력값 검사
// ============================================================

$activity_raw = (
    isset($_POST['activity_id']) &&
    is_string($_POST['activity_id'])
) ? trim($_POST['activity_id']) : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $activity_raw) ||
    strlen($activity_raw) > 10 ||
    (float)$activity_raw > 2147483647
) {
    course_assignment_submit_error(
        '잘못된 과제 번호입니다.'
    );
}

$activity_id = intval($activity_raw);

if (
    !isset($_POST['content_text']) ||
    !is_string($_POST['content_text'])
) {
    course_assignment_submit_error(
        '제출 내용을 입력해 주세요.'
    );
}

$content_text = trim($_POST['content_text']);

if (
    $content_text === '' ||
    strlen($content_text) > 1024 * 1024
) {
    course_assignment_submit_error(
        '제출 내용은 1MB 이하로 입력해 주세요.'
    );
}

// ============================================================
// 2-1. 선택 첨부파일 검증
// ============================================================

$assignment_file = null;
$assignment_file_info = null;
$storage_dir = '/home/judge/course_assignment_files';
$stored_path = null;

if (isset($_FILES['assignment_file'])) {
    $file = $_FILES['assignment_file'];

    if (
        !is_array($file) ||
        !isset($file['error']) ||
        !is_int($file['error'])
    ) {
        course_assignment_submit_error(
            '첨부파일 정보가 올바르지 않습니다.'
        );
    }

    if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $assignment_file = course_file_validate_basic(
                $file
            );

            $assignment_file_info =
                course_file_validate_content(
                    $assignment_file
                );

            if (
                !isset($assignment_file_info['mime_type']) ||
                !is_string($assignment_file_info['mime_type']) ||
                strlen($assignment_file_info['mime_type']) > 150
            ) {
                throw new RuntimeException(
                    '파일 MIME 검사 결과가 올바르지 않습니다.'
                );
            }

            if (
                !is_dir($storage_dir) ||
                is_link($storage_dir) ||
                !is_writable($storage_dir)
            ) {
                throw new RuntimeException(
                    '과제 파일 저장소를 사용할 수 없습니다.'
                );
            }

        } catch (Throwable $e) {
            error_log(
                '[course_assignment_submit] validation: ' .
                $e->getMessage()
            );

            course_assignment_submit_error(
                '첨부파일 검증에 실패했습니다.'
            );
        }
    }
}


// ============================================================
// 3. 과제 소속 Course 확인
// ============================================================

$rows = pdo_query(
    "SELECT
        a.course_id
     FROM course_activity a
     WHERE a.activity_id = ?
       AND a.activity_type = 'assignment'
     LIMIT 1",
    $activity_id
);

if ($rows === false || !isset($rows[0])) {
    course_assignment_submit_error(
        '과제를 찾을 수 없습니다.',
        404
    );
}

$course_id = intval($rows[0]['course_id']);

// ============================================================
// 4. PDO 연결 및 트랜잭션
// ============================================================

$connection_check = pdo_query(
    "SELECT 1 AS connection_ok"
);

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    course_assignment_submit_error(
        '데이터베이스 연결에 실패했습니다.',
        500
    );
}

$transaction_started = false;
$submit_success = false;
$submit_error = '';
$submit_error_status = 400;

try {

    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();
    $transaction_started = true;

    // 같은 학생의 동시 제출 요청을 직렬화한다.
    // 수강 상태도 트랜잭션 안에서 재검사한다.
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

    if (
        !$student ||
        intval($student['status']) !== 1
    ) {
        $submit_error = '현재 수강 중인 학생만 제출할 수 있습니다.';
        $submit_error_status = 403;

        throw new DomainException($submit_error);
    }

    // 과제, 차시, Course 상태와 제출 정책을 다시 확인한다.
    $assignment_check = $dbh->prepare(
        "SELECT
            a.activity_id,
            a.course_id,
            a.visible AS activity_visible,
            a.status AS activity_status,
            l.visible AS lesson_visible,
            l.status AS lesson_status,
            c.status AS course_status,
            s.due_at,
            s.allow_late,
            s.allow_resubmit,
            CASE
                WHEN s.due_at IS NOT NULL
                 AND NOW() > s.due_at
                THEN 1
                ELSE 0
            END AS is_late
         FROM course_activity a
         INNER JOIN course_lesson l
           ON l.lesson_id = a.lesson_id
          AND l.course_id = a.course_id
         INNER JOIN course c
           ON c.course_id = a.course_id
         INNER JOIN course_assignment s
           ON s.activity_id = a.activity_id
         WHERE a.activity_id = ?
           AND a.course_id = ?
           AND a.activity_type = 'assignment'
         FOR UPDATE"
    );

    $assignment_check->execute(array(
        $activity_id,
        $course_id
    ));

    $assignment = $assignment_check->fetch(
        PDO::FETCH_ASSOC
    );

    $assignment_check->closeCursor();

    if (!$assignment) {
        $submit_error = '제출할 수 없는 과제입니다.';
        throw new DomainException($submit_error);
    }

    if (
        intval($assignment['course_status']) !== 1 ||
        intval($assignment['lesson_status']) !== 1 ||
        intval($assignment['activity_status']) !== 1 ||
        intval($assignment['lesson_visible']) !== 1 ||
        intval($assignment['activity_visible']) !== 1
    ) {
        $submit_error = '현재 제출할 수 없는 과제입니다.';
        throw new DomainException($submit_error);
    }

    if (
        intval($assignment['is_late']) === 1 &&
        intval($assignment['allow_late']) !== 1
    ) {
        $submit_error = '과제 제출 기한이 지났습니다.';
        throw new DomainException($submit_error);
    }

    // 기존 기록은 수정하지 않고 다음 제출 번호를 계산한다.
    $attempt_check = $dbh->prepare(
        "SELECT
            COALESCE(MAX(submission_no), 0) AS latest_no
         FROM course_assignment_submission
         WHERE activity_id = ?
           AND user_id = ?"
    );

    $attempt_check->execute(array(
        $activity_id,
        $user_id
    ));

    $attempt = $attempt_check->fetch(PDO::FETCH_ASSOC);
    $attempt_check->closeCursor();

    $latest_no = intval($attempt['latest_no'] ?? 0);

    if (
        $latest_no > 0 &&
        intval($assignment['allow_resubmit']) !== 1
    ) {
        $submit_error = '이 과제는 재제출할 수 없습니다.';
        throw new DomainException($submit_error);
    }

    if ($latest_no >= 2147483647) {
        $submit_error = '제출 가능 횟수를 초과했습니다.';
        throw new DomainException($submit_error);
    }

    $next_no = $latest_no + 1;

    $insert = $dbh->prepare(
        "INSERT INTO course_assignment_submission
            (
                activity_id,
                user_id,
                submission_no,
                content_text
            )
         VALUES (?, ?, ?, ?)"
    );

    $insert->execute(array(
        $activity_id,
        $user_id,
        $next_no,
        $content_text
    ));

    $insert->closeCursor();

    // 제출 ID가 확정된 뒤 첨부파일을 연결한다.
    if ($assignment_file !== null) {

        $submission_id = $dbh->lastInsertId();

        if (
            !ctype_digit((string)$submission_id) ||
            (float)$submission_id <= 0
        ) {
            throw new RuntimeException(
                '제출 번호를 확인할 수 없습니다.'
            );
        }

        $stored_name = bin2hex(random_bytes(32));
        $stored_path = $storage_dir . '/' . $stored_name;

        if (
            file_exists($stored_path) ||
            is_link($stored_path)
        ) {
            throw new RuntimeException(
                '저장 파일명 충돌이 발생했습니다.'
            );
        }

        if (!move_uploaded_file(
            $assignment_file['tmp_name'],
            $stored_path
        )) {
            throw new RuntimeException(
                '첨부파일을 저장하지 못했습니다.'
            );
        }

        if (!chmod($stored_path, 0600)) {
            throw new RuntimeException(
                '첨부파일 권한 설정에 실패했습니다.'
            );
        }

        $file_hash = hash_file(
            'sha256',
            $stored_path
        );

        if ($file_hash === false) {
            throw new RuntimeException(
                '첨부파일 해시 계산에 실패했습니다.'
            );
        }

        $file_insert = $dbh->prepare(
            "INSERT INTO course_assignment_submission_file
                (
                    submission_id,
                    original_name,
                    stored_name,
                    file_size,
                    mime_type,
                    sha256
                )
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$file_insert->execute(array(
            $submission_id,
            $assignment_file['original_name'],
            $stored_name,
            $assignment_file['file_size'],
            $assignment_file_info['mime_type'],
            $file_hash
        ))) {
            throw new RuntimeException(
                '첨부파일 DB 기록에 실패했습니다.'
            );
        }

        $file_insert->closeCursor();
    }

    if (!$dbh->commit()) {
        throw new RuntimeException(
            '과제 제출 트랜잭션 완료에 실패했습니다.'
        );
    }
    $transaction_started = false;
    $submit_success = true;

} catch (Throwable $e) {

    if (
        $transaction_started &&
        $dbh->inTransaction()
    ) {
        $dbh->rollBack();
    }

    // 트랜잭션 실패 시 생성한 첨부파일을 삭제한다.
    if (
        $stored_path !== null &&
        is_file($stored_path)
    ) {
        if (!@unlink($stored_path)) {
            error_log(
                '[course_assignment_submit] cleanup failed'
            );
        }
    }

    if (!($e instanceof DomainException)) {
        error_log(
            'Course assignment submission failed: ' .
            $e->getMessage()
        );

        $submit_error = '과제 저장 중 오류가 발생했습니다.';
        $submit_error_status = 500;
    }
}

if (!$submit_success) {
    course_assignment_submit_error(
        $submit_error,
        $submit_error_status
    );
}

// POST 재전송을 방지한다.
header(
    'Location: course_assignment_view.php?activity_id=' .
    $activity_id,
    true,
    303
);
exit;
