<?php

class SimulationDraftConflictException extends RuntimeException
{
}

// 호출자는 트랜잭션을 시작하고 해당 수강생 행의 잠금을 보유해야 한다.
// 버전은 큰 정수의 정밀도를 보존하도록 문자열로 전달하고 반환한다.
function simulation_store_versioned_draft(
    PDO $connection,
    $activity_id,
    $user_id,
    $source_code,
    $expected_revision
) {
    if (!$connection->inTransaction()) {
        throw new RuntimeException('초안 저장 트랜잭션이 필요합니다.');
    }

    if (
        !is_string($expected_revision) ||
        !preg_match('/^(0|[1-9][0-9]{0,19})$/D', $expected_revision) ||
        (
            strlen($expected_revision) === 20 &&
            strcmp($expected_revision, '18446744073709551615') > 0
        )
    ) {
        throw new RuntimeException(
            '초안 버전을 확인하지 못했습니다. 코드를 보관한 뒤 화면을 새로고침해 주세요.'
        );
    }

    if (!is_string($source_code) || strlen($source_code) > 200000) {
        throw new RuntimeException('저장할 코드는 200KB 이하여야 합니다.');
    }

    $stmt = $connection->prepare(
        "SELECT source_code, CAST(revision AS CHAR) AS revision
         FROM course_simulation_draft
         WHERE activity_id = ?
           AND user_id = ?
         FOR UPDATE"
    );
    $stmt->execute(array($activity_id, $user_id));
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $current_revision = $draft ? (string)$draft['revision'] : '0';

    // 저장은 완료됐으나 응답을 받지 못한 요청의 재전송도 허용한다.
    // 코드가 같으면 버전과 마지막 변경 시각을 불필요하게 늘리지 않는다.
    if ($draft && (string)$draft['source_code'] === $source_code) {
        return $current_revision;
    }

    if ($expected_revision !== $current_revision) {
        throw new SimulationDraftConflictException(
            '다른 탭에서 코드가 먼저 저장됐습니다. 현재 코드를 복사해 보관한 뒤 최신 작업을 다시 열어 주세요.'
        );
    }

    if (!$draft) {
        $stmt = $connection->prepare(
            "INSERT INTO course_simulation_draft
                (activity_id, user_id, source_code, revision)
             VALUES (?, ?, ?, 1)"
        );
        $stmt->execute(array($activity_id, $user_id, $source_code));
        $stmt->closeCursor();
        return '1';
    }

    if ($current_revision === '18446744073709551615') {
        throw new RuntimeException('초안 버전이 허용 범위에 도달했습니다.');
    }

    $stmt = $connection->prepare(
        "UPDATE course_simulation_draft
         SET source_code = ?,
             revision = revision + 1,
             updated_at = CURRENT_TIMESTAMP
         WHERE activity_id = ?
           AND user_id = ?
           AND revision = ?"
    );
    $stmt->execute(array(
        $source_code,
        $activity_id,
        $user_id,
        $expected_revision,
    ));
    $changed = $stmt->rowCount();
    $stmt->closeCursor();

    if ($changed !== 1) {
        throw new SimulationDraftConflictException(
            '저장 중 초안 버전이 변경됐습니다. 현재 코드를 복사해 보관해 주세요.'
        );
    }

    $stmt = $connection->prepare(
        "SELECT CAST(revision AS CHAR)
         FROM course_simulation_draft
         WHERE activity_id = ?
           AND user_id = ?"
    );
    $stmt->execute(array($activity_id, $user_id));
    $saved_revision = $stmt->fetchColumn();
    $stmt->closeCursor();

    if (!is_string($saved_revision) || $saved_revision === '') {
        throw new RuntimeException('저장된 초안 버전을 확인하지 못했습니다.');
    }

    return $saved_revision;
}
