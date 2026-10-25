<?php

require_once(__DIR__ . '/simulation_problem_reader.php');
require_once(__DIR__ . '/course_simulation_performance_policy.php');

class SimulationProblemDraftConflictException extends RuntimeException
{
}

function simulation_problem_draft_revision($value)
{
    if (
        !is_string($value) ||
        preg_match('/^(0|[1-9][0-9]{0,19})$/D', $value) !== 1 ||
        (
            strlen($value) === 20 &&
            strcmp($value, '18446744073709551615') > 0
        )
    ) {
        throw new InvalidArgumentException('초안 버전이 올바르지 않습니다.');
    }

    return $value;
}

function simulation_problem_draft_user($value)
{
    if (
        !is_string($value) ||
        trim($value) !== $value ||
        preg_match('/^[^\x00-\x20\x7F]{1,48}$/uD', $value) !== 1
    ) {
        throw new InvalidArgumentException('학생 계정을 확인하지 못했습니다.');
    }

    return $value;
}

// 요청값이 아닌 서버에서 확인한 수행 기준 시각을 전달한다.
function simulation_problem_draft_scope($current_cutoff)
{
    if ($current_cutoff === null) {
        return '';
    }

    return simulation_assert_performance_context(
        $current_cutoff,
        $current_cutoff
    );
}

function simulation_problem_draft_code($source_code)
{
    if (
        !is_string($source_code) ||
        strlen($source_code) > 200000 ||
        strpos($source_code, "\0") !== false ||
        preg_match('//u', $source_code) !== 1
    ) {
        throw new InvalidArgumentException('저장할 코드는 올바른 UTF-8의 200KB 이하 코드여야 합니다.');
    }

    // 작성 중 전체 코드를 지운 상태도 저장할 수 있다.
    return $source_code;
}

function simulation_problem_draft_statement(
    PDO $connection,
    $sql,
    array $arguments
) {
    $statement = $connection->prepare($sql);
    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('초안 조회 또는 저장에 실패했습니다.');
    }
    return $statement;
}

// 호출자는 로그인·문제 접근 권한을 확인한 뒤 사용한다.
function simulation_problem_load_draft(
    PDO $connection,
    $version_id,
    $user_id,
    $current_cutoff
) {
    $version_id = simulation_problem_reader_positive_id($version_id);
    $user_id = simulation_problem_draft_user($user_id);
    $scope = simulation_problem_draft_scope($current_cutoff);

    $statement = simulation_problem_draft_statement(
        $connection,
        'SELECT source_code, CAST(revision AS CHAR) AS revision, updated_at
         FROM simulation_problem_draft
         WHERE version_id = ? AND user_id = ? AND performance_context = ?',
        array($version_id, $user_id, $scope)
    );
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    if (!$row) {
        return null;
    }

    $row['revision'] = simulation_problem_draft_revision($row['revision']);
    if ($row['revision'] === '0') {
        throw new RuntimeException('저장된 초안 버전이 올바르지 않습니다.');
    }
    simulation_problem_draft_code($row['source_code']);

    return $row;
}

// 호출자가 트랜잭션과 현재 권한·수행 기준 검사를 관리한다.
// 이 함수에서는 문제 버전 행을 잠가 최초 저장도 직렬화한다.
function simulation_problem_store_draft(
    PDO $connection,
    $version_id,
    $user_id,
    $current_cutoff,
    $source_code,
    $expected_revision
) {
    if (!$connection->inTransaction()) {
        throw new RuntimeException('초안 저장 트랜잭션이 필요합니다.');
    }

    $version_id = simulation_problem_reader_positive_id($version_id);
    $user_id = simulation_problem_draft_user($user_id);
    $scope = simulation_problem_draft_scope($current_cutoff);
    $source_code = simulation_problem_draft_code($source_code);
    $expected_revision = simulation_problem_draft_revision($expected_revision);

    $statement = simulation_problem_draft_statement(
        $connection,
        'SELECT version_id FROM simulation_problem_version
         WHERE version_id = ? FOR UPDATE',
        array($version_id)
    );
    $version_exists = $statement->fetchColumn() !== false;
    $statement->closeCursor();

    if (!$version_exists) {
        throw new RuntimeException('저장 대상 문제 버전을 찾지 못했습니다.');
    }

    $statement = simulation_problem_draft_statement(
        $connection,
        'SELECT source_code, CAST(revision AS CHAR) AS revision
         FROM simulation_problem_draft
         WHERE version_id = ? AND user_id = ? AND performance_context = ?
         FOR UPDATE',
        array($version_id, $user_id, $scope)
    );
    $draft = $statement->fetch(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    $current_revision = $draft
        ? simulation_problem_draft_revision($draft['revision'])
        : '0';

    if ($draft && $current_revision === '0') {
        throw new RuntimeException('저장된 초안 버전이 올바르지 않습니다.');
    }

    // 응답 유실 후 같은 코드를 재전송한 경우에도 기존 기록을 유지한다.
    if ($draft && $draft['source_code'] === $source_code) {
        return $current_revision;
    }

    if ($expected_revision !== $current_revision) {
        throw new SimulationProblemDraftConflictException(
            '다른 탭에서 코드가 먼저 저장됐습니다. 현재 코드를 복사해 보관한 뒤 문제 화면을 다시 열어 주세요.'
        );
    }

    if (!$draft) {
        $statement = simulation_problem_draft_statement(
            $connection,
            'INSERT INTO simulation_problem_draft
                (version_id, user_id, performance_context, source_code, revision)
             VALUES (?, ?, ?, ?, 1)',
            array($version_id, $user_id, $scope, $source_code)
        );
        $statement->closeCursor();
        return '1';
    }

    if ($current_revision === '18446744073709551615') {
        throw new RuntimeException('초안 버전이 허용 범위에 도달했습니다.');
    }

    $statement = simulation_problem_draft_statement(
        $connection,
        'UPDATE simulation_problem_draft
         SET source_code = ?, revision = revision + 1,
             updated_at = CURRENT_TIMESTAMP
         WHERE version_id = ? AND user_id = ? AND performance_context = ?
           AND revision = ?',
        array($source_code, $version_id, $user_id, $scope, $expected_revision)
    );
    $changed = $statement->rowCount();
    $statement->closeCursor();

    if ($changed !== 1) {
        throw new SimulationProblemDraftConflictException(
            '저장 중 초안 버전이 변경됐습니다. 현재 코드를 복사해 보관해 주세요.'
        );
    }

    $saved = simulation_problem_load_draft(
        $connection, $version_id, $user_id, $current_cutoff
    );
    if ($saved === null) {
        throw new RuntimeException('저장된 초안을 확인하지 못했습니다.');
    }

    return $saved['revision'];
}
