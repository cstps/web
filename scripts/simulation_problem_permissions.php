<?php

// 호출 전에 로그인·DB 설정을 불러온다.
// 사용자와 교사 권한은 요청값이 아닌 세션과 DB에서 결정한다.
function simulation_problem_current_actor(PDO $connection)
{
    global $OJ_NAME;

    $user_id = $_SESSION[$OJ_NAME.'_user_id'] ?? null;

    if (
        !is_string($user_id) ||
        trim($user_id) === '' ||
        strlen($user_id) > 192
    ) {
        return array(
            'user_id' => '',
            'administrator' => false,
            'teacher' => false,
        );
    }

    $administrator = isset($_SESSION[$OJ_NAME.'_administrator']);
    $teacher = false;

    if (!$administrator) {
        $stmt = $connection->prepare(
            "SELECT 1
             FROM course_teacher
             WHERE user_id = ?
               AND status = 1
               AND role IN ('owner', 'teacher')
             LIMIT 1"
        );

        if (!$stmt || !$stmt->execute(array($user_id))) {
            throw new RuntimeException('문제 관리 권한을 조회하지 못했습니다.');
        }

        $teacher = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();
    }

    return array(
        'user_id' => $user_id,
        'administrator' => $administrator,
        'teacher' => $teacher,
    );
}

function simulation_problem_can_create(array $actor)
{
    return (
        isset($actor['user_id']) &&
        is_string($actor['user_id']) &&
        trim($actor['user_id']) !== '' &&
        (
            ($actor['administrator'] ?? false) === true ||
            ($actor['teacher'] ?? false) === true
        )
    );
}

// 문제 정보는 DB에서 조회한 행을 전달한다.
// 비활성 문제는 소유자·관리자만 조회·관리하며 새로 가져올 수 없다.
function simulation_problem_permissions(array $actor, array $problem)
{
    $denied = array(
        'view' => false,
        'edit' => false,
        'import' => false,
    );

    if (
        !simulation_problem_can_create($actor) ||
        !isset($problem['owner_user_id']) ||
        !is_string($problem['owner_user_id']) ||
        trim($problem['owner_user_id']) === '' ||
        !in_array($problem['sharing'] ?? null, array('private', 'shared'), true) ||
        !in_array($problem['status'] ?? null, array(0, 1, '0', '1'), true)
    ) {
        return $denied;
    }

    $administrator = ($actor['administrator'] ?? false) === true;
    $owner = $actor['user_id'] === $problem['owner_user_id'];
    $active = (string)$problem['status'] === '1';
    $edit = $administrator || $owner;
    $view = $edit || ($active && $problem['sharing'] === 'shared');

    return array(
        'view' => $view,
        'edit' => $edit,
        'import' => $active && $view,
    );
}
