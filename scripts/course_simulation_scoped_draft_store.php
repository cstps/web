<?php

require_once(__DIR__ . '/course_simulation_draft_store.php');

// 테이블명과 조건은 서버가 정한 두 가지 경우로만 구성한다.
function simulation_draft_storage_scope($performance_started_at)
{
    if ($performance_started_at === null) {
        return array(
            'table' => 'course_simulation_draft',
            'condition' => '',
            'arguments' => array(),
        );
    }

    if (!is_string($performance_started_at)) {
        throw new RuntimeException('수행모드 초안의 기준 시각이 올바르지 않습니다.');
    }

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $performance_started_at
    );

    if (
        !$parsed ||
        $parsed->format('Y-m-d H:i:s') !== $performance_started_at ||
        substr($performance_started_at, 0, 4) === '0000'
    ) {
        throw new RuntimeException('수행모드 초안의 기준 시각이 올바르지 않습니다.');
    }

    return array(
        'table' => 'course_simulation_performance_draft',
        'condition' => ' AND performance_started_at = ?',
        'arguments' => array($performance_started_at),
    );
}

// 호출자가 학생 본인 및 활동 접근 권한을 확인해야 한다.
function simulation_load_scoped_draft(
    PDO $connection,
    $activity_id,
    $user_id,
    $performance_started_at = null
) {
    $scope = simulation_draft_storage_scope($performance_started_at);
    $table = $scope['table'];
    $condition = $scope['condition'];

    $stmt = $connection->prepare(
        "SELECT source_code, CAST(revision AS CHAR) AS revision
         FROM {$table}
         WHERE activity_id = ?
           AND user_id = ?{$condition}
         LIMIT 1"
    );
    $stmt->execute(array_merge(
        array($activity_id, $user_id),
        $scope['arguments']
    ));

    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return $draft ?: null;
}

// 호출자는 트랜잭션을 시작하고 수강생 행의 잠금을 보유해야 한다.
// 수행모드 기준은 클라이언트 값이 아닌 서버에서 확인한 값을 전달한다.
function simulation_store_scoped_draft(
    PDO $connection,
    $activity_id,
    $user_id,
    $source_code,
    $expected_revision,
    $performance_started_at = null
) {
    if ($performance_started_at === null) {
        return simulation_store_versioned_draft(
            $connection,
            $activity_id,
            $user_id,
            $source_code,
            $expected_revision
        );
    }

    if (!$connection->inTransaction()) {
        throw new RuntimeException('초안 저장 트랜잭션이 필요합니다.');
    }

    $scope = simulation_draft_storage_scope($performance_started_at);

    if (
        !is_string($expected_revision) ||
        !preg_match('/^(0|[1-9][0-9]{0,19})$/D', $expected_revision) ||
        (
            strlen($expected_revision) === 20 &&
            strcmp($expected_revision, '18446744073709551615') > 0
        )
    ) {
        throw new RuntimeException('초안 버전을 확인하지 못했습니다.');
    }

    if (!is_string($source_code) || strlen($source_code) > 200000) {
        throw new RuntimeException('저장할 코드는 200KB 이하여야 합니다.');
    }

    $arguments = array(
        $activity_id,
        $user_id,
        $performance_started_at,
    );

    $stmt = $connection->prepare(
        "SELECT source_code, CAST(revision AS CHAR) AS revision
         FROM course_simulation_performance_draft
         WHERE activity_id = ?
           AND user_id = ?
           AND performance_started_at = ?
         FOR UPDATE"
    );
    $stmt->execute($arguments);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $current_revision = $draft ? (string)$draft['revision'] : '0';

    // 동일 코드 재전송은 버전과 변경 시각을 유지한다.
    if ($draft && (string)$draft['source_code'] === $source_code) {
        return $current_revision;
    }

    if ($expected_revision !== $current_revision) {
        throw new SimulationDraftConflictException(
            '다른 탭에서 수행모드 코드가 먼저 저장됐습니다. 현재 코드를 복사해 보관한 뒤 최신 작업을 다시 열어 주세요.'
        );
    }

    if (!$draft) {
        $stmt = $connection->prepare(
            "INSERT INTO course_simulation_performance_draft
                (
                    activity_id, user_id, performance_started_at,
                    source_code, revision
                )
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute(array(
            $activity_id,
            $user_id,
            $performance_started_at,
            $source_code,
        ));
        $stmt->closeCursor();
        return '1';
    }

    if ($current_revision === '18446744073709551615') {
        throw new RuntimeException('초안 버전이 허용 범위에 도달했습니다.');
    }

    $stmt = $connection->prepare(
        "UPDATE course_simulation_performance_draft
         SET source_code = ?,
             revision = revision + 1,
             updated_at = CURRENT_TIMESTAMP
         WHERE activity_id = ?
           AND user_id = ?
           AND performance_started_at = ?
           AND revision = ?"
    );
    $stmt->execute(array(
        $source_code,
        $activity_id,
        $user_id,
        $performance_started_at,
        $expected_revision,
    ));
    $changed = $stmt->rowCount();
    $stmt->closeCursor();

    if ($changed !== 1) {
        throw new SimulationDraftConflictException(
            '저장 중 수행모드 초안 버전이 변경됐습니다. 현재 코드를 복사해 보관해 주세요.'
        );
    }

    $saved = simulation_load_scoped_draft(
        $connection,
        $activity_id,
        $user_id,
        $performance_started_at
    );

    if (
        !$saved ||
        !is_string($saved['revision']) ||
        $saved['revision'] === ''
    ) {
        throw new RuntimeException('저장된 초안 버전을 확인하지 못했습니다.');
    }

    return $saved['revision'];
}
