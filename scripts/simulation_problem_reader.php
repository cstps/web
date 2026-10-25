<?php

require_once(__DIR__ . '/simulation_problem_permissions.php');

class SimulationProblemReaderNotFoundException extends RuntimeException
{
}

// 공개 열람과 교사용 열람을 구분한다.
// 비공개 문제의 소유자라도 현재 교사 권한이 없으면 예외를 허용하지 않는다.
function simulation_problem_reader_can_view(array $actor, array $problem)
{
    if (
        !in_array($problem['status'] ?? null, array(0, 1, '0', '1'), true) ||
        !in_array($problem['published'] ?? null, array(0, 1, '0', '1'), true)
    ) {
        return false;
    }

    if (
        (string)$problem['status'] === '1' &&
        (string)$problem['published'] === '1'
    ) {
        return true;
    }

    $permissions = simulation_problem_permissions($actor, $problem);
    return $permissions['view'] === true;
}

function simulation_problem_reader_positive_id($value)
{
    if (
        !is_string($value) ||
        preg_match('/^[1-9][0-9]{0,18}$/D', $value) !== 1 ||
        (
            strlen($value) === 19 &&
            strcmp($value, '9223372036854775807') > 0
        )
    ) {
        throw new InvalidArgumentException('그림 문제 번호가 올바르지 않습니다.');
    }

    return $value;
}

// 공개 상태는 항상 현재 문제 행에서 확인한다.
// 정답 코드·채점 설정·이미지 내부 검사 기록은 이 조회에 포함하지 않는다.
function simulation_problem_reader_latest(
    PDO $connection,
    array $actor,
    $problem_id
) {
    $problem_id = simulation_problem_reader_positive_id($problem_id);

    $reader_logged_in = (
        is_string($actor['user_id'] ?? null) &&
        trim($actor['user_id']) !== ''
    );

    $statement = $connection->prepare(
        'SELECT
            CAST(p.problem_id AS CHAR) AS problem_id,
            p.owner_user_id, p.published, p.sharing, p.status,
            CAST(v.version_id AS CHAR) AS version_id,
            v.version_no,
            v.title, v.description,
            v.simulation_mode, ' .
            ($reader_logged_in ? 'v.starter_code' : 'NULL AS starter_code') . ',
            v.canvas_width, v.canvas_height,
            v.source_language, v.execution_engine, v.execution_version,
            v.p5_version, v.allow_edit, v.allow_submit,
            v.created_at,
            i.image_status
         FROM simulation_problem p
         INNER JOIN simulation_problem_version v
           ON v.version_id = (
               SELECT v2.version_id
               FROM simulation_problem_version v2
               WHERE v2.problem_id = p.problem_id
               ORDER BY v2.version_no DESC
               LIMIT 1
           )
         LEFT JOIN simulation_problem_reference_image i
           ON i.version_id = v.version_id
         WHERE p.problem_id = ?'
    );

    if (!$statement || !$statement->execute(array($problem_id))) {
        throw new RuntimeException('그림 문제 조회에 실패했습니다.');
    }

    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    // 존재 여부와 비공개 여부를 같은 오류로 처리한다.
    if (!$row || !simulation_problem_reader_can_view($actor, $row)) {
        throw new SimulationProblemReaderNotFoundException(
            '문제를 찾을 수 없거나 공개되지 않은 문제입니다.'
        );
    }

    $permissions = simulation_problem_permissions($actor, $row);
    $row['reader_is_public'] = (
        (string)$row['status'] === '1' &&
        (string)$row['published'] === '1'
    );
    $row['reader_can_edit'] = $permissions['edit'] === true;

    return $row;
}
