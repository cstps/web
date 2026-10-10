<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');
require_once('./include/csrf_check.php');

$view_title = '학습 활동 순서 변경';


// ============================================================
// 1. 오류 화면 출력
// ============================================================

function course_activity_move_error($message)
{
    global $OJ_TEMPLATE, $view_errors;

    $view_errors =
        '<h2>' .
        htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        ) .
        '</h2>';

    require(
        'template/' .
        $OJ_TEMPLATE .
        '/error.php'
    );

    exit(0);
}


// ============================================================
// 2. 로그인 및 POST 확인
// ============================================================

if (!isset($_SESSION[$OJ_NAME . '_user_id'])) {
    course_activity_move_error('로그인이 필요합니다.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    course_activity_move_error('잘못된 요청입니다.');
}


// ============================================================
// 3. 입력값 확인
// ============================================================

$course_id_raw =
    isset($_POST['course_id']) &&
    is_string($_POST['course_id'])
        ? trim($_POST['course_id'])
        : '';

$activity_id_raw =
    isset($_POST['activity_id']) &&
    is_string($_POST['activity_id'])
        ? trim($_POST['activity_id'])
        : '';

$direction =
    isset($_POST['direction']) &&
    is_string($_POST['direction'])
        ? trim($_POST['direction'])
        : '';

if (
    !preg_match('/^[1-9][0-9]*$/D', $course_id_raw) ||
    !preg_match('/^[1-9][0-9]*$/D', $activity_id_raw) ||
    strlen($course_id_raw) > 10 ||
    strlen($activity_id_raw) > 10 ||
    !in_array($direction, array('up', 'down'), true)
) {
    course_activity_move_error(
        '잘못된 학습 활동 이동 요청입니다.'
    );
}

$course_id = intval($course_id_raw);
$activity_id = intval($activity_id_raw);

if (
    $course_id <= 0 ||
    $course_id > 2147483647 ||
    $activity_id <= 0 ||
    $activity_id > 2147483647
) {
    course_activity_move_error('잘못된 번호입니다.');
}


// ============================================================
// 4. Course 관리 권한 확인
// ============================================================

if (
    !course_can_access($course_id) ||
    !course_can_manage_contests($course_id)
) {
    course_activity_move_error(
        '학습 활동 순서를 변경할 권한이 없습니다.'
    );
}


// ============================================================
// 5. PDO 연결 준비
// ============================================================

$connection_check = pdo_query(
    'SELECT 1 AS connection_ok'
);

global $dbh;

if (
    $connection_check === false ||
    !isset($dbh) ||
    !($dbh instanceof PDO)
) {
    course_activity_move_error(
        '데이터베이스 연결에 실패했습니다.'
    );
}

$transaction_started = false;


// ============================================================
// 6. 트랜잭션 시작
// ============================================================

try {

    if ($dbh->inTransaction()) {
        throw new RuntimeException(
            '이미 진행 중인 트랜잭션이 있습니다.'
        );
    }

    $dbh->beginTransaction();
    $transaction_started = true;


    // --------------------------------------------------------
    // 7. Course 행 잠금 및 활성 상태 확인
    // --------------------------------------------------------

    $stmt = $dbh->prepare(
        "SELECT course_id, status
         FROM course
         WHERE course_id = ?
         FOR UPDATE"
    );

    $stmt->execute(array($course_id));
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (
        !$course ||
        intval($course['status']) !== 1
    ) {
        throw new RuntimeException(
            '활성 상태의 수업이 아닙니다.'
        );
    }


    // --------------------------------------------------------
    // 8. 이동 대상 Activity 조회
    // --------------------------------------------------------

    $stmt = $dbh->prepare(
        "SELECT
            activity_id,
            lesson_id,
            activity_type,
            status
         FROM course_activity
         WHERE activity_id = ?
           AND course_id = ?
         FOR UPDATE"
    );

    $stmt->execute(array(
        $activity_id,
        $course_id
    ));

    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (
        !$activity ||
        intval($activity['status']) !== 1 ||
        intval($activity['lesson_id']) <= 0
    ) {
        throw new RuntimeException(
            '이동할 수 없는 학습 활동입니다.'
        );
    }

    $lesson_id = intval($activity['lesson_id']);


    // --------------------------------------------------------
    // 9. 소속 Lesson 확인
    // --------------------------------------------------------

    $stmt = $dbh->prepare(
        "SELECT lesson_id, status
         FROM course_lesson
         WHERE lesson_id = ?
           AND course_id = ?
         FOR UPDATE"
    );

    $stmt->execute(array(
        $lesson_id,
        $course_id
    ));

    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (
        !$lesson ||
        intval($lesson['status']) !== 1
    ) {
        throw new RuntimeException(
            '활성 상태의 차시가 아닙니다.'
        );
    }


    // --------------------------------------------------------
    // 10. 같은 Lesson의 활성 Activity 목록 잠금
    //
    // Contest Activity는 연결된 Contest도 활성인
    // 경우에만 포함한다.
    // --------------------------------------------------------

    $stmt = $dbh->prepare(
        "SELECT
            ca.activity_id,
            ca.sort_order
         FROM course_activity ca
         WHERE ca.course_id = ?
           AND ca.lesson_id = ?
           AND ca.status = 1
           AND (
               ca.activity_type <> 'contest'
               OR EXISTS (
                   SELECT 1
                   FROM course_activity_contest cac
                   INNER JOIN course_contest cc
                     ON cc.id = cac.course_contest_id
                   WHERE cac.activity_id = ca.activity_id
                     AND cc.course_id = ca.course_id
                     AND cc.status = 1
               )
           )
         ORDER BY
            ca.sort_order,
            ca.activity_id
         FOR UPDATE"
    );

    $stmt->execute(array(
        $course_id,
        $lesson_id
    ));

    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();


    // --------------------------------------------------------
    // 11. 이동 대상의 현재 위치 확인
    // --------------------------------------------------------

    $current_index = -1;
    $total = count($activities);

    foreach ($activities as $index => $row) {

        if (
            intval($row['activity_id']) === $activity_id
        ) {
            $current_index = $index;
            break;
        }
    }

    if ($current_index < 0) {
        throw new RuntimeException(
            '활성 학습 활동 목록에서 이동 대상을 찾지 못했습니다.'
        );
    }

    $target_index =
        $direction === 'up'
            ? $current_index - 1
            : $current_index + 1;


    // --------------------------------------------------------
    // 12. 첫 번째 위쪽 / 마지막 아래쪽 이동 방지
    // --------------------------------------------------------

    if (
        $target_index < 0 ||
        $target_index >= $total
    ) {

        $dbh->commit();
        $transaction_started = false;

        header(
            'Location: course_view.php?course_id=' .
            $course_id
        );

        exit(0);
    }


    // --------------------------------------------------------
    // 13. 실제 표시 순서에서 Activity 위치 교환
    // --------------------------------------------------------

    $temp = $activities[$current_index];

    $activities[$current_index] =
        $activities[$target_index];

    $activities[$target_index] = $temp;


    // --------------------------------------------------------
    // 14. 같은 Lesson의 활성 Activity 순서 재정렬
    //
    // course_contest.sort_order는 변경하지 않는다.
    // --------------------------------------------------------

    $update = $dbh->prepare(
        "UPDATE course_activity
         SET sort_order = ?
         WHERE activity_id = ?
           AND course_id = ?
           AND lesson_id = ?
           AND status = 1"
    );

    foreach ($activities as $index => $row) {

        $new_order = ($index + 1) * 10;

        $update->execute(array(
            $new_order,
            intval($row['activity_id']),
            $course_id,
            $lesson_id
        ));
    }

    $update->closeCursor();


    // --------------------------------------------------------
    // 15. 변경 완료
    // --------------------------------------------------------

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
        'course_activity_move: ' .
        $e->getMessage()
    );

    course_activity_move_error(
        '학습 활동 순서를 변경하지 못했습니다. ' .
        '서버 로그를 확인하세요.'
    );
}


// ============================================================
// 16. Course 관리 화면으로 이동
// ============================================================

header(
    'Location: course_view.php?course_id=' .
    $course_id
);

exit(0);
