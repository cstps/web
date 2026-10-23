<?php

// 현재 학생에게 적용되는 가장 이른 수행모드 시작 시각을 반환한다.
// 조회 실패와 수행모드 미적용을 구분하여 과거 기록 노출을 방지한다.
function simulation_performance_history_cutoff(
    $user_id,
    $has_source_browser = false
) {
    $user_id = trim((string)$user_id);
    if ($user_id === '') {
        throw new RuntimeException('학생 계정을 확인하지 못했습니다.');
    }

    // 기존 일반 코딩 화면의 소스 열람 권한 예외와 맞춘다.
    if ($has_source_browser === true) {
        return null;
    }

    $rows = pdo_query(
        "SELECT cps.started_at
         FROM course_performance_session cps
         INNER JOIN course_student cs
           ON cs.course_id = cps.course_id
          AND cs.user_id = ?
          AND cs.status = 1
         WHERE cps.status = 1
         ORDER BY cps.started_at ASC, cps.id ASC
         LIMIT 1",
        $user_id
    );

    if ($rows === false) {
        throw new RuntimeException('수행모드 적용 상태 조회에 실패했습니다.');
    }

    if (!isset($rows[0])) {
        return null;
    }

    $cutoff = $rows[0]['started_at'] ?? null;
    if (!is_string($cutoff)) {
        throw new RuntimeException('수행모드 시작 시각이 올바르지 않습니다.');
    }

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $cutoff
    );

    if (
        !$parsed ||
        $parsed->format('Y-m-d H:i:s') !== $cutoff ||
        substr($cutoff, 0, 4) === '0000'
    ) {
        throw new RuntimeException('수행모드 시작 시각이 올바르지 않습니다.');
    }

    return $cutoff;
}


class SimulationPerformanceContextException extends RuntimeException
{
}

// 화면을 열었을 때의 기준과 현재 서버 기준을 비교한다.
// 일반 모드는 빈 문자열로 전송하고 서버에서는 null로 관리한다.
function simulation_assert_performance_context(
    $expected_context,
    $current_cutoff
) {
    if ($current_cutoff !== null) {
        if (!is_string($current_cutoff)) {
            throw new RuntimeException('현재 수행모드 기준을 확인하지 못했습니다.');
        }

        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $current_cutoff
        );

        if (
            !$parsed ||
            $parsed->format('Y-m-d H:i:s') !== $current_cutoff ||
            substr($current_cutoff, 0, 4) === '0000'
        ) {
            throw new RuntimeException('현재 수행모드 기준이 올바르지 않습니다.');
        }
    }

    $current_context = $current_cutoff === null ? '' : $current_cutoff;

    if (
        !is_string($expected_context) ||
        $expected_context !== $current_context
    ) {
        throw new SimulationPerformanceContextException(
            '수행모드 적용 상태가 변경되었습니다. 현재 코드를 복사해 보관한 뒤 활동 화면을 다시 열어 주세요.'
        );
    }

    return $current_cutoff;
}

// 클라이언트의 기준값으로 저장 위치를 결정하지 않는다.
// 현재 수행모드를 서버에서 조회한 뒤 요청 기준과 일치하는지 확인한다.
function simulation_checked_performance_context(
    $user_id,
    $expected_context,
    $has_source_browser = false
) {
    $current_cutoff = simulation_performance_history_cutoff(
        $user_id,
        $has_source_browser
    );

    return simulation_assert_performance_context(
        $expected_context,
        $current_cutoff
    );
}
