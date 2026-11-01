<?php
// 일반 초안은 보존하고, 수행 초안은 서버가 정한 시작 시각으로 구분한다.
class CodingDraftContextException extends RuntimeException {}

function coding_draft_context($user_id)
{
    $cutoff = course_get_user_performance_cutoff($user_id);
    if ($cutoff === null) return '';
    if (!is_string($cutoff)) {
        throw new RuntimeException('수행모드 기준을 확인하지 못했습니다.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $cutoff);
    if (!$date || $date->format('Y-m-d H:i:s') !== $cutoff || substr($cutoff, 0, 4) === '0000') {
        throw new RuntimeException('수행모드 시작 시각이 올바르지 않습니다.');
    }
    return $cutoff;
}

function coding_draft_save_scoped($user_id, $problem_id, $contest_id, $language, $source, $expected)
{
    $context = coding_draft_context($user_id);
    if (!is_string($expected) || $context !== $expected) {
        throw new CodingDraftContextException(
            '수행모드 적용 상태가 변경되었습니다. 현재 코드를 복사해 보관한 뒤 문제 화면을 다시 열어 주세요.'
        );
    }
    $table = $context === '' ? 'student_code_draft' : 'student_code_draft_performance';
    $column = $context === '' ? '' : ', performance_started_at';
    $placeholder = $context === '' ? '' : ', ?';
    $args = array($user_id, $problem_id, $contest_id, $language, $source);
    if ($context !== '') $args[] = $context;
    $result = pdo_query(
        "INSERT INTO {$table}
         (user_id, problem_id, contest_id, language, source, created_at, updated_at{$column})
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(){$placeholder})
         ON DUPLICATE KEY UPDATE
             language = VALUES(language), source = VALUES(source), updated_at = NOW()",
        ...$args
    );
    if ($result === false) {
        throw new RuntimeException('초안 저장에 실패했습니다.');
    }
}

// 교사용 기존 조회 조건은 유지하고, 현재 모드에 해당하는 초안만 제공한다.
function coding_draft_monitor_source_sql($with_source = false)
{
    $normal_source = $with_source ? 'n.source,' : '';
    $performance_source = $with_source ? 'p.source,' : '';
    return "(
        SELECT n.id, n.user_id, n.problem_id, n.contest_id,
               n.language, {$normal_source} n.created_at, n.updated_at,
               '' AS performance_context
        FROM student_code_draft n
        WHERE NOT EXISTS (
            SELECT 1 FROM course_performance_session cps
            INNER JOIN course_student cs ON cs.course_id = cps.course_id
            WHERE cs.user_id = n.user_id AND cs.status = 1 AND cps.status = 1
        )
        UNION ALL
        SELECT p.id, p.user_id, p.problem_id, p.contest_id,
               p.language, {$performance_source} p.created_at, p.updated_at,
               p.performance_started_at AS performance_context
        FROM student_code_draft_performance p
        WHERE p.performance_started_at = (
            SELECT MIN(cps.started_at) FROM course_performance_session cps
            INNER JOIN course_student cs ON cs.course_id = cps.course_id
            WHERE cs.user_id = p.user_id AND cs.status = 1 AND cps.status = 1
        )
    )";
}
