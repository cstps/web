<?php
require_once(__DIR__ . '/course_contest_activity.inc.php');

// 복원과 차시 재배정은 연결·활동·차시 상태를 한 트랜잭션에서 검증한다.
function course_contest_restore_store(PDO $db, $course_id, $contest_id,
    $target_lesson_id = 0, $restore_parent = false, $repair_active = false)
{
    global $OJ_NAME;
    if ($db->inTransaction()) {
        throw new LogicException('중첩 복원 트랜잭션은 허용하지 않습니다.');
    }
    course_contest_activity_require_transactional_tables($db);
    if (!$db->beginTransaction()) {
        throw new RuntimeException('복원 트랜잭션 시작 실패');
    }
    try {
        $statement = course_contest_activity_query($db,
            'SELECT status FROM course WHERE course_id = ? FOR UPDATE', array($course_id));
        $course = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!$course || (int)$course['status'] !== 1) {
            throw new UnexpectedValueException('활성 수업에서만 복원할 수 있습니다.');
        }
        $user = $_SESSION[$OJ_NAME . '_user_id'] ?? null;
        if (!is_string($user) || $user === '') {
            throw new UnexpectedValueException('로그인이 필요합니다.');
        }
        if (!isset($_SESSION[$OJ_NAME . '_administrator'])) {
            $statement = course_contest_activity_query($db,
                'SELECT role, status FROM course_teacher WHERE course_id = ? AND user_id = ? FOR UPDATE',
                array($course_id, $user));
            $teacher = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            if (!$teacher || (int)$teacher['status'] !== 1 ||
                !in_array($teacher['role'], array('owner', 'teacher'), true)) {
                throw new UnexpectedValueException('이 수업의 활동을 복원할 권한이 없습니다.');
            }
        }
        $statement = course_contest_activity_query($db,
            'SELECT id, lesson_id, status, link_type FROM course_contest
             WHERE course_id = ? AND contest_id = ? FOR UPDATE', array($course_id, $contest_id));
        $link = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!$link || !in_array($link['link_type'], array('created', 'linked'), true)) {
            throw new UnexpectedValueException('이 수업의 대회 연결을 확인해 주세요.');
        }
        if ((int)$link['status'] !== 0 && !($repair_active && (int)$link['status'] === 1)) {
            throw new UnexpectedValueException('이미 활성 상태인 연결입니다.');
        }
        $original_lesson_id = (int)$link['lesson_id'];
        $destination = $target_lesson_id > 0 ? $target_lesson_id : $original_lesson_id;
        if ($destination <= 0) {
            throw new UnexpectedValueException('배정할 활성 차시를 선택해 주세요.');
        }
        $statement = course_contest_activity_query($db,
            'SELECT lesson_id, lesson_no, status FROM course_lesson
             WHERE lesson_id = ? AND course_id = ? FOR UPDATE', array($destination, $course_id));
        $lesson = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!$lesson) {
            throw new UnexpectedValueException('현재 수업의 차시를 선택해 주세요.');
        }
        $revive_parent = (int)$lesson['status'] === 0 && $restore_parent &&
            $destination === $original_lesson_id;
        if ((int)$lesson['status'] !== 1 && !$revive_parent) {
            throw new UnexpectedValueException('활성 차시를 선택하거나 원래 차시 함께 복원을 선택해 주세요.');
        }
        $statement = course_contest_activity_query($db,
            'SELECT ca.activity_id, ca.lesson_id, ca.status, ca.activity_type, ca.sort_order
             FROM course_activity_contest cac INNER JOIN course_activity ca ON ca.activity_id = cac.activity_id
             WHERE cac.course_contest_id = ? AND ca.course_id = ? FOR UPDATE', array($link['id'], $course_id));
        $activity = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!$activity || $activity['activity_type'] !== 'contest') {
            throw new UnexpectedValueException('대회와 학습 활동의 매핑을 확인해 주세요.');
        }
        if ((int)$activity['lesson_id'] !== $original_lesson_id && $target_lesson_id <= 0) {
            throw new UnexpectedValueException('차시 연결이 서로 다릅니다. 배정할 차시를 직접 선택해 주세요.');
        }
        // 정상 상태의 요청을 반복해도 공개 상태나 정렬을 바꾸지 않는다.
        if ((int)$link['status'] === 1 && (int)$activity['status'] === 1 &&
            !$revive_parent && $destination === $original_lesson_id &&
            (int)$activity['lesson_id'] === $destination) {
            $db->commit();
            return (int)$activity['activity_id'];
        }
        if ($revive_parent) {
            course_contest_activity_query($db,
                'UPDATE course_lesson SET status = 1, visible = 0 WHERE lesson_id = ? AND course_id = ? AND status = 0',
                array($destination, $course_id))->closeCursor();
        }
        $sort = (int)$activity['sort_order'];
        if ((int)$activity['lesson_id'] !== $destination) {
            $statement = course_contest_activity_query($db,
                'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM course_activity WHERE course_id = ? AND lesson_id = ?',
                array($course_id, $destination));
            $sort = (int)$statement->fetchColumn();
            $statement->closeCursor();
        }
        course_contest_activity_query($db,
            'UPDATE course_contest SET lesson_id = ?, lesson_no = ?, status = 1, visible = 0
             WHERE id = ? AND course_id = ?', array($destination, $lesson['lesson_no'], $link['id'], $course_id))->closeCursor();
        course_contest_activity_query($db,
            'UPDATE course_activity SET lesson_id = ?, sort_order = ?, status = 1, visible = 0
             WHERE activity_id = ? AND course_id = ?',
            array($destination, $sort, $activity['activity_id'], $course_id))->closeCursor();
        if ($link['link_type'] === 'created') {
            $statement = course_contest_activity_query($db,
                'SELECT user_id FROM course_student WHERE course_id = ? AND status = 1 ORDER BY user_id', array($course_id));
            $students = $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement->closeCursor();
            foreach ($students as $student) {
                if (!is_string($student) || trim($student) === '') {
                    continue;
                }
                course_contest_activity_query($db,
                    "INSERT INTO privilege (user_id, rightstr, valuestr, defunct)
                     SELECT ?, ?, 'true', 'N' WHERE NOT EXISTS (
                         SELECT 1 FROM privilege WHERE user_id = ? AND rightstr = ?
                         AND valuestr = 'true' AND defunct = 'N'
                     )", array($student, 'c' . $contest_id, $student, 'c' . $contest_id))->closeCursor();
            }
        }
        if (!$db->commit()) {
            throw new RuntimeException('복원 트랜잭션 확정 실패');
        }
        return (int)$activity['activity_id'];
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}
