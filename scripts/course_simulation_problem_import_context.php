<?php

require_once(__DIR__ . '/simulation_problem_permissions.php');
require_once(__DIR__ . '/simulation_problem_image_view.php');
require_once(__DIR__ . '/simulation_problem_input.php');

// 큰 버전 번호는 정수로 변환하지 않고 문자열로 유지한다.
function course_simulation_problem_import_id($value, $course_number = false)
{
    $maximum = $course_number ? '2147483647' : '9223372036854775807';

    if (
        !is_bool($course_number) ||
        !is_string($value) ||
        preg_match('/^[1-9][0-9]*$/D', $value) !== 1 ||
        strlen($value) > strlen($maximum) ||
        (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)
    ) {
        throw new InvalidArgumentException(
            '가져오기 대상 번호가 올바르지 않습니다.'
        );
    }

    return $value;
}

function course_simulation_problem_import_require_lock(PDO $connection, $lock)
{
    if (!is_bool($lock) || ($lock && !$connection->inTransaction())) {
        throw new RuntimeException(
            '잠금 조회는 저장 트랜잭션 안에서 사용해 주세요.'
        );
    }
}

// 로그인 사용자와 해당 수업의 교사 등록에서만 권한을 결정한다.
function course_simulation_problem_import_context(
    PDO $connection,
    $course_id,
    $lesson_id,
    $lock = false
) {
    global $OJ_NAME;

    course_simulation_problem_import_require_lock($connection, $lock);
    $course_id = course_simulation_problem_import_id($course_id, true);
    $lesson_id = course_simulation_problem_import_id($lesson_id, true);

    if (!is_string($OJ_NAME) || $OJ_NAME === '') {
        throw new RuntimeException('사이트 설정을 확인하지 못했습니다.');
    }

    $user = $_SESSION[$OJ_NAME . '_user_id'] ?? null;

    if (
        !is_string($user) ||
        trim($user) === '' ||
        strlen($user) > 192 ||
        preg_match('/^.{1,48}$/usD', $user) !== 1 ||
        preg_match('/[\x00-\x1f\x7f]/', $user) === 1
    ) {
        throw new UnexpectedValueException(
            '로그인 사용자를 확인하지 못했습니다.'
        );
    }

    $suffix = $lock ? ' FOR UPDATE' : '';

    $statement = $connection->prepare(
        'SELECT course_id, course_name, status
         FROM course WHERE course_id = ?' . $suffix
    );

    if (!$statement || !$statement->execute(array($course_id))) {
        throw new RuntimeException('수업 조회에 실패했습니다.');
    }

    $course = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    $statement = $connection->prepare(
        'SELECT lesson_id, course_id, lesson_no, title, status
         FROM course_lesson
         WHERE lesson_id = ? AND course_id = ?' . $suffix
    );

    if (!$statement || !$statement->execute(array($lesson_id, $course_id))) {
        throw new RuntimeException('차시 조회에 실패했습니다.');
    }

    $lesson = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    $statement = $connection->prepare(
        "SELECT role FROM course_teacher
         WHERE course_id = ? AND user_id = ? AND status = 1
           AND role IN ('owner', 'teacher')" . $suffix
    );

    if (!$statement || !$statement->execute(array($course_id, $user))) {
        throw new RuntimeException('수업 교사 권한 조회에 실패했습니다.');
    }

    $role = $statement->fetchColumn();
    $statement->closeCursor();

    $administrator = isset($_SESSION[$OJ_NAME . '_administrator']);
    $teacher = in_array($role, array('owner', 'teacher'), true);

    if (
        !$course ||
        !$lesson ||
        !in_array($course['status'], array(1, '1'), true) ||
        !in_array($lesson['status'], array(1, '1'), true) ||
        (!$administrator && !$teacher)
    ) {
        throw new UnexpectedValueException(
            '문제를 가져올 수 있는 활성 수업·차시가 아니거나 권한이 없습니다.'
        );
    }

    return array(
        'course_id' => (int)$course_id,
        'lesson_id' => (int)$lesson_id,
        'course_name' => $course['course_name'],
        'lesson_no' => (int)$lesson['lesson_no'],
        'lesson_title' => $lesson['title'],
        'actor' => array(
            'user_id' => $user,
            'administrator' => $administrator,
            'teacher' => $teacher,
        ),
    );
}

// actor는 위 수업 권한 검사에서 얻은 서버 내부 값을 전달한다.
// 저장 시에는 두 함수를 같은 트랜잭션에서 잠금 조회로 호출한다.
function course_simulation_problem_import_source(
    PDO $connection,
    array $actor,
    $version_id,
    $lock = false,
    $include_png = false
) {
    course_simulation_problem_import_require_lock($connection, $lock);
    $version_id = course_simulation_problem_import_id($version_id);

    if (!is_bool($include_png)) {
        throw new InvalidArgumentException(
            '이미지 조회 설정이 올바르지 않습니다.'
        );
    }

    $statement = $connection->prepare(
        'SELECT CAST(v.version_id AS CHAR) AS version_id,
                CAST(v.problem_id AS CHAR) AS problem_id,
                v.version_no, v.title, v.description, v.starter_code,
                v.simulation_mode, v.canvas_width, v.canvas_height,
                v.allow_edit, v.allow_submit, v.p5_version,
                v.source_language, v.execution_engine, v.execution_version,
                p.owner_user_id, p.sharing, p.status, p.published,
                r.source_code AS reference_source,
                r.render_config, r.grading_config, r.max_score,
                i.image_status, i.source_sha256, i.settings_sha256,
                i.request_metadata, i.render_metadata, i.png_sha256,
                i.generated_at, OCTET_LENGTH(i.png_data) AS png_bytes,
                i.png_data
         FROM simulation_problem_version v
         INNER JOIN simulation_problem p ON p.problem_id = v.problem_id
         LEFT JOIN simulation_problem_reference r ON r.version_id = v.version_id
         LEFT JOIN simulation_problem_reference_image i ON i.version_id = v.version_id
         WHERE v.version_id = ?' . ($lock ? ' FOR UPDATE' : '')
    );

    if (!$statement || !$statement->execute(array($version_id))) {
        throw new RuntimeException('가져올 문제 버전 조회에 실패했습니다.');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (
        !$row ||
        simulation_problem_permissions($actor, $row)['import'] !== true
    ) {
        throw new UnexpectedValueException(
            '가져올 수 있는 문제가 아니거나 가져오기 권한이 없습니다.'
        );
    }

    $execution = simulation_validate_execution_metadata($row, true);

    $content = simulation_validate_problem_input(array(
        'title' => $row['title'],
        'description' => $row['description'],
        'starter_code' => $row['starter_code'],
        'simulation_mode' => $row['simulation_mode'],
        'canvas_width' => (string)$row['canvas_width'],
        'canvas_height' => (string)$row['canvas_height'],
        'allow_edit' => (string)$row['allow_edit'],
        'allow_submit' => (string)$row['allow_submit'],
        'sharing' => $row['sharing'],
        'published' => (string)$row['published'],
        'execution_profile' => $execution['execution_engine'] === 'p5js'
            ? 'p5js-2.3.4' : 'python-draw-poc-v1',
    ));

    $reference = null;
    $target = null;

    if ($content['simulation_mode'] === 'drawing') {
        // 실제 PNG까지 검사한다. 수정 권한 전용 조회 함수는 사용하지 않는다.
        $target = simulation_problem_image_validated_view($row, true);

        if ($target['state'] !== 'ready') {
            throw new RuntimeException(
                '가져올 문제의 목표 그림 생성·검사가 완료되지 않았습니다.'
            );
        }

        $reference = array(
            'source_code' => $row['reference_source'],
            'render_config' => $row['render_config'],
            'grading_config' => $row['grading_config'],
            'max_score' => $row['max_score'],
        );

        if (!$include_png) {
            unset($target['png_data']);
        }
    }

    return array(
        'problem_id' => $row['problem_id'],
        'version_id' => $row['version_id'],
        'version_no' => (int)$row['version_no'],
        'content' => $content,
        'p5_version' => $row['p5_version'],
        'reference' => $reference,
        'target' => $target,
    );
}

function course_simulation_problem_import_token_context(
    $course_id,
    $lesson_id,
    $version_id
) {
    return 'course-import:' .
        course_simulation_problem_import_id($course_id, true) . ':' .
        course_simulation_problem_import_id($lesson_id, true) . ':' .
        course_simulation_problem_import_id($version_id);
}
