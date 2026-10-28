<?php

require_once(__DIR__ . '/simulation_execution_config.php');

// 호출 전에 DB 설정과 세션이 초기화되어 있어야 한다.
// 정답 코드와 학생 코드는 조회하지 않는다.

function course_simulation_execution_activity_id($value)
{
    if (
        !is_string($value) ||
        preg_match('/^[1-9][0-9]{0,9}$/D', $value) !== 1 ||
        (
            strlen($value) === 10 &&
            strcmp($value, '2147483647') > 0
        )
    ) {
        throw new InvalidArgumentException(
            '시뮬레이션 활동 번호가 올바르지 않습니다.'
        );
    }

    return (int)$value;
}

function course_simulation_execution_context(
    PDO $connection,
    $activity_id
) {
    global $OJ_NAME;

    $activity_id = course_simulation_execution_activity_id($activity_id);
    $user_id = $_SESSION[$OJ_NAME . '_user_id'] ?? null;

    if (
        !is_string($user_id) ||
        trim($user_id) === '' ||
        strlen($user_id) > 192
    ) {
        throw new UnexpectedValueException(
            '로그인 후 시뮬레이션을 이용할 수 있습니다.'
        );
    }

    $statement = $connection->prepare(
        "SELECT
            ca.activity_id,
            ca.course_id,
            ca.lesson_id,
            ca.visible AS activity_visible,
            ca.status AS activity_status,
            cl.visible AS lesson_visible,
            cl.status AS lesson_status,
            c.status AS course_status,
            s.simulation_mode,
            s.canvas_width,
            s.canvas_height,
            s.allow_edit,
            s.allow_submit,
            s.source_language,
            s.execution_engine,
            s.execution_version,
            ct.role AS teacher_role,
            cs.status AS student_status
         FROM course_activity ca
         INNER JOIN course c
           ON c.course_id = ca.course_id
         INNER JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         INNER JOIN course_simulation s
           ON s.activity_id = ca.activity_id
         LEFT JOIN course_teacher ct
           ON ct.course_id = ca.course_id
          AND ct.user_id = ?
          AND ct.status = 1
         LEFT JOIN course_student cs
           ON cs.course_id = ca.course_id
          AND cs.user_id = ?
         WHERE ca.activity_id = ?
           AND ca.activity_type = 'simulation'
         LIMIT 1"
    );

    if (
        !$statement ||
        !$statement->execute(array($user_id, $user_id, $activity_id))
    ) {
        throw new RuntimeException(
            '시뮬레이션 실행 정보를 조회하지 못했습니다.'
        );
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$row) {
        throw new UnexpectedValueException(
            '이용할 수 있는 시뮬레이션 활동이 없습니다.'
        );
    }

    foreach (array(
        'activity_visible',
        'activity_status',
        'lesson_visible',
        'lesson_status',
        'course_status',
        'allow_edit',
        'allow_submit'
    ) as $key) {
        if (!in_array($row[$key] ?? null, array(0, 1, '0', '1'), true)) {
            throw new RuntimeException(
                '시뮬레이션 활동 설정이 올바르지 않습니다.'
            );
        }
    }

    if (
        (string)$row['activity_status'] !== '1' ||
        (string)$row['lesson_status'] !== '1'
    ) {
        throw new UnexpectedValueException(
            '이용할 수 있는 시뮬레이션 활동이 없습니다.'
        );
    }

    $is_manager = (
        isset($_SESSION[$OJ_NAME . '_administrator']) ||
        in_array(
            $row['teacher_role'],
            array('owner', 'teacher'),
            true
        )
    );

    $is_student = in_array(
        $row['student_status'],
        array(1, '1'),
        true
    );

    if (!$is_manager && !$is_student) {
        throw new UnexpectedValueException(
            '이 시뮬레이션을 이용할 권한이 없습니다.'
        );
    }

    if (
        !$is_manager &&
        (
            (string)$row['lesson_visible'] !== '1' ||
            (string)$row['activity_visible'] !== '1'
        )
    ) {
        throw new UnexpectedValueException(
            '공개되지 않은 시뮬레이션 활동입니다.'
        );
    }

    $execution = simulation_validate_execution_metadata($row, true);

    foreach (array('canvas_width', 'canvas_height') as $key) {
        $dimension = filter_var(
            $row[$key],
            FILTER_VALIDATE_INT,
            array('options' => array(
                'min_range' => 100,
                'max_range' => 2000,
            ))
        );

        if ($dimension === false) {
            throw new RuntimeException(
                '캔버스 크기 설정이 올바르지 않습니다.'
            );
        }

        $row[$key] = $dimension;
    }

    if (
        !in_array(
            $row['simulation_mode'],
            array('free', 'drawing'),
            true
        )
    ) {
        throw new RuntimeException(
            '지원하지 않는 시뮬레이션 유형입니다.'
        );
    }

    if (
        $execution['source_language'] === 'python' &&
        (
            $row['canvas_width'] !== 400 ||
            $row['canvas_height'] !== 400
        )
    ) {
        throw new RuntimeException(
            'Python 그림은 400×400 캔버스만 지원합니다.'
        );
    }

    // 종료된 수업의 열람은 기존 화면과 동일하게 유지한다.
    // 저장·제출 경로에서는 course_status를 별도로 검사해야 한다.
    unset($row['teacher_role'], $row['student_status']);

    $row['is_manager'] = $is_manager;
    $row['is_student'] = $is_student;
    $row['execution'] = $execution;

    return $row;
}
