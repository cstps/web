<?php
// 기존 차시에서 시작한 Contest 활동 경로의 공통 처리. PHP 7.4 호환.

function course_contest_activity_error($message, $status = 400)
{
    global $OJ_TEMPLATE, $view_errors;
    http_response_code($status);
    header('Cache-Control: no-store, private');
    $view_errors = '<h2>' . htmlspecialchars(
        $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
    ) . '</h2>';
    require(__DIR__ . '/../template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

function course_contest_activity_id($value)
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) ||
        (strlen($value) === 10 && strcmp($value, '2147483647') > 0)) {
        throw new InvalidArgumentException('수업 또는 차시 번호가 올바르지 않습니다.');
    }
    return (int)$value;
}

function course_contest_activity_db()
{
    global $dbh;
    if (!isset($dbh) || !($dbh instanceof PDO)) {
        pdo_query('SELECT 1');
    }
    if (!isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('수업 DB 연결 실패');
    }
    return $dbh;
}

function course_contest_activity_query(PDO $db, $sql, array $arguments = array())
{
    $statement = $db->prepare($sql);
    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('Contest 활동 DB 처리 실패');
    }
    return $statement;
}

function course_contest_activity_context(PDO $db, $course_id, $lesson_id, $lock = false)
{
    global $OJ_NAME;
    if ($lock && !$db->inTransaction()) {
        throw new LogicException('트랜잭션 없이 차시를 잠글 수 없습니다.');
    }
    $suffix = $lock ? ' FOR UPDATE' : '';
    $statement = course_contest_activity_query($db,
        'SELECT course_id, status FROM course WHERE course_id = ?' . $suffix,
        array($course_id));
    $course = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    if (!$course || (int)$course['status'] !== 1) {
        throw new UnexpectedValueException('활성 수업을 확인해 주세요.');
    }
    $user = $_SESSION[$OJ_NAME . '_user_id'] ?? null;
    if (!is_string($user) || $user === '' || strlen($user) > 192) {
        throw new UnexpectedValueException('로그인이 필요합니다.');
    }
    if (!isset($_SESSION[$OJ_NAME . '_administrator'])) {
        $statement = course_contest_activity_query($db,
            'SELECT role, status FROM course_teacher WHERE course_id = ? AND user_id = ?' . $suffix,
            array($course_id, $user));
        $teacher = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!$teacher || (int)$teacher['status'] !== 1 ||
            !in_array($teacher['role'], array('owner', 'teacher'), true)) {
            throw new UnexpectedValueException('이 수업에 Contest 활동을 추가할 권한이 없습니다.');
        }
    }
    $statement = course_contest_activity_query($db,
        'SELECT lesson_id, course_id, lesson_no, title, status FROM course_lesson
         WHERE lesson_id = ? AND course_id = ?' . $suffix, array($lesson_id, $course_id));
    $lesson = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    if (!$lesson || (int)$lesson['status'] !== 1) {
        throw new UnexpectedValueException('현재 수업의 활성 차시를 선택해 주세요.');
    }
    return $lesson;
}

// lesson_id가 없는 기존 차시 생성 경로는 그대로 둔다.
function course_contest_activity_request(array &$input)
{
    if (!array_key_exists('lesson_id', $input)) {
        return null;
    }
    header('Cache-Control: no-store, private');
    try {
        $course_id = course_contest_activity_id($input['course_id'] ?? null);
        $lesson_id = course_contest_activity_id($input['lesson_id']);
        $lesson = course_contest_activity_context(
            course_contest_activity_db(), $course_id, $lesson_id
        );
        // 차시 번호는 요청값 대신 조회한 값으로 고정한다.
        $input['lesson_no'] = (string)$lesson['lesson_no'];
        $input['link_lesson_no'] = (string)$lesson['lesson_no'];
        return $lesson;
    } catch (InvalidArgumentException $error) {
        course_contest_activity_error($error->getMessage(), 400);
    } catch (UnexpectedValueException $error) {
        course_contest_activity_error($error->getMessage(), 403);
    } catch (Throwable $error) {
        error_log('[course_contest_activity_context] ' . $error->getMessage());
        course_contest_activity_error('차시 정보를 확인하지 못했습니다.', 500);
    }
}

function course_contest_activity_require_transactional_tables(PDO $db)
{
    // 기존 혼합 엔진 환경에서 부분 저장이 일어나지 않도록 저장 전 검사한다.
    $tables = array('course', 'course_teacher', 'course_lesson', 'course_contest',
        'course_activity', 'course_activity_contest', 'contest', 'contest_problem', 'privilege', 'problem');
    $statement = course_contest_activity_query($db,
        'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' .
         implode(',', array_fill(0, count($tables), '?')) . ')', $tables);
    $engines = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    $statement->closeCursor();
    foreach ($tables as $table) {
        if (!isset($engines[$table]) || strtoupper($engines[$table]) !== 'INNODB') {
            throw new UnexpectedValueException(
                '저장 전 확인이 필요합니다: ' . $table . ' 테이블이 InnoDB인지 확인해 주세요.'
            );
        }
    }
}

function course_contest_activity_store(array $lesson, $mode, array $settings, array $problems)
{
    global $OJ_NAME;
    $db = course_contest_activity_db();
    $user = $_SESSION[$OJ_NAME . '_user_id'];
    $admin = isset($_SESSION[$OJ_NAME . '_administrator']);
    if (!in_array($mode, array('copy', 'direct', 'link'), true) || $db->inTransaction()) {
        throw new LogicException('Contest 활동 저장 요청 오류');
    }
    course_contest_activity_require_transactional_tables($db);
    if (!$db->beginTransaction()) {
        throw new RuntimeException('Contest 활동 트랜잭션 시작 실패');
    }
    try {
        // 수업 → 담당 교사 → 차시 순으로 잠그고 권한·상태·중복을 재검사한다.
        $lesson = course_contest_activity_context($db,
            (int)$lesson['course_id'], (int)$lesson['lesson_id'], true);
        $course_id = (int)$lesson['course_id'];
        $lesson_id = (int)$lesson['lesson_id'];
        $source_id = null;
        if ($mode === 'link' || $mode === 'copy') {
            $source_id = (int)$settings['source_id'];
            $statement = course_contest_activity_query($db,
                'SELECT contest_id, title, user_id, defunct, allow_copy, codevisible, langmask
                 FROM contest WHERE contest_id = ? FOR UPDATE', array($source_id));
            $source = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            if (!$source || (!$admin && trim($source['user_id']) !== $user &&
                ($mode === 'link' || (int)$source['allow_copy'] !== 1)) ||
                ($mode === 'link' && strtoupper(trim($source['defunct'])) !== 'N')) {
                throw new UnexpectedValueException('원본 대회의 사용 권한 또는 상태가 변경되었습니다.');
            }
        }
        if ($mode === 'link') {
            $contest_id = $source_id;
            $title = $source['title'];
            $statement = course_contest_activity_query($db,
                'SELECT id FROM course_contest WHERE contest_id = ? FOR UPDATE', array($contest_id));
            $existing = $statement->fetchColumn();
            $statement->closeCursor();
            if ($existing !== false) {
                throw new UnexpectedValueException('이미 수업에 연결된 대회입니다.');
            }
        } else {
            if (!$problems) {
                throw new UnexpectedValueException('최소 한 개 이상의 문제를 선택해 주세요.');
            }
            // 기존 화면에서 검증한 선택 순서를 유지하고, 잠금 안에서 권한을 재검사한다.
            foreach ($problems as &$problem) {
                $pid = (int)$problem['problem_id'];
                $statement = course_contest_activity_query($db,
                    'SELECT problem_id, defunct, allow_reuse FROM problem WHERE problem_id = ? FOR UPDATE',
                    array($pid));
                $info = $statement->fetch(PDO::FETCH_ASSOC);
                $statement->closeCursor();
                $statement = course_contest_activity_query($db,
                    "SELECT user_id FROM privilege WHERE user_id = ? AND rightstr = ? AND defunct = 'N' FOR UPDATE",
                    array($user, 'p' . $pid));
                $owned = $statement->fetchColumn() !== false;
                $statement->closeCursor();
                if (!$info || (!$admin && !$owned &&
                    (strtoupper(trim($info['defunct'])) !== 'N' || (int)$info['allow_reuse'] !== 1))) {
                    throw new UnexpectedValueException('선택 문제의 재사용 권한이 변경되었습니다.');
                }
                if ($mode === 'copy') {
                    $statement = course_contest_activity_query($db,
                        'SELECT score FROM contest_problem WHERE contest_id = ? AND problem_id = ? FOR UPDATE',
                        array($source_id, $pid));
                    $original = $statement->fetch(PDO::FETCH_ASSOC);
                    $statement->closeCursor();
                    if (!$original) {
                        throw new UnexpectedValueException('원본 대회의 문제 구성이 변경되었습니다.');
                    }
                    $problem['score'] = $original['score'] === null ? 100 : (int)$original['score'];
                }
            }
            unset($problem);
            $title = $settings['title'];
            $statement = course_contest_activity_query($db,
                'INSERT INTO contest (title, start_time, end_time, codevisible, private, langmask,
                 description, password, user_id, exam_mode) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, 0)',
                array($title, $settings['start_time'], $settings['end_time'],
                    $mode === 'copy' ? (int)$source['codevisible'] : 0,
                    $mode === 'copy' ? (int)$source['langmask'] : (int)$settings['langmask'], '', '', $user));
            $contest_id = (int)$db->lastInsertId();
            $statement->closeCursor();
            if ($contest_id <= 0) {
                throw new RuntimeException('새 Contest 번호 확인 실패');
            }
            foreach ($problems as $num => $problem) {
                course_contest_activity_query($db,
                    'INSERT INTO contest_problem (contest_id, problem_id, num, score) VALUES (?, ?, ?, ?)',
                    array($contest_id, (int)$problem['problem_id'], $num, (int)$problem['score']))->closeCursor();
            }
            $statement = course_contest_activity_query($db,
                "SELECT user_id FROM course_teacher WHERE course_id = ? AND status = 1
                 AND role IN ('owner', 'teacher') FOR UPDATE", array($course_id));
            $teachers = $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement->closeCursor();
            $teachers[] = $user;
            foreach (array_unique($teachers) as $teacher) {
                course_contest_activity_query($db,
                    'INSERT INTO privilege (user_id, rightstr) VALUES (?, ?)',
                    array($teacher, 'm' . $contest_id))->closeCursor();
            }
            // 새 Contest는 private=1, 활동과 연결은 visible=0. 학생 권한도 같은 트랜잭션에서 저장.
            $statement = course_contest_activity_query($db,
                'SELECT user_id FROM course_student WHERE course_id = ? AND status = 1', array($course_id));
            $students = $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement->closeCursor();
            foreach ($students as $student) {
                course_contest_activity_query($db,
                    'INSERT INTO privilege (user_id, rightstr) VALUES (?, ?)',
                    array($student, 'c' . $contest_id))->closeCursor();
            }
        }
        // 기존 차시 행은 갱신하지 않는다. 연결·활동·매핑만 함께 추가한다.
        course_contest_activity_query($db,
            'INSERT INTO course_contest (course_id, lesson_id, contest_id, source_contest_id,
             link_type, lesson_no, sort_order, visible, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?)',
            array($course_id, $lesson_id, $contest_id, $mode === 'copy' ? $source_id : null,
                $mode === 'link' ? 'linked' : 'created', (int)$lesson['lesson_no'], 0, $user))->closeCursor();
        $link_id = (int)$db->lastInsertId();
        $statement = course_contest_activity_query($db,
            'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM course_activity WHERE course_id = ? AND lesson_id = ?',
            array($course_id, $lesson_id));
        $sort = (int)$statement->fetchColumn();
        $statement->closeCursor();
        course_contest_activity_query($db,
            "INSERT INTO course_activity (course_id, lesson_id, activity_type, title, description,
             sort_order, visible, status, created_by) VALUES (?, ?, 'contest', ?, NULL, ?, 0, 1, ?)",
            array($course_id, $lesson_id, $title, $sort, $user))->closeCursor();
        $activity_id = (int)$db->lastInsertId();
        course_contest_activity_query($db,
            'INSERT INTO course_activity_contest (activity_id, course_contest_id) VALUES (?, ?)',
            array($activity_id, $link_id))->closeCursor();
        if (!$db->commit()) {
            throw new RuntimeException('Contest 활동 저장 확정 실패');
        }
        // 세션은 DB 확정 후에만 갱신한다. 기존 대회 연결에는 관리자 권한을 새로 부여하지 않는다.
        if ($mode !== 'link') {
            $_SESSION[$OJ_NAME . '_m' . $contest_id] = true;
        }
        return $activity_id;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function course_contest_activity_save_and_return(array $lesson, $mode, array $settings, array $problems = array())
{
    try {
        course_contest_activity_store($lesson, $mode, $settings, $problems);
    } catch (UnexpectedValueException $error) {
        course_contest_activity_error($error->getMessage(), 409);
    } catch (Throwable $error) {
        error_log('[course_contest_activity_store] ' . $error->getMessage());
        course_contest_activity_error('Contest 활동을 저장하지 못했습니다. 다시 확인해 주세요.', 500);
    }
    header('Location: course_view.php?course_id=' . (int)$lesson['course_id'], true, 303);
    exit;
}
