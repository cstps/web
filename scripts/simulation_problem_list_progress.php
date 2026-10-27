<?php
require_once(__DIR__ . '/simulation_problem_submission_history.php');

// 현재 목록의 문제에 대해 본인 제출의 최신 채점 결과만 집계한다.
function simulation_problem_list_progress(
    PDO $connection,
    array $problem_ids
) {
    global $OJ_NAME;

    if ($connection->inTransaction()) {
        throw new RuntimeException(
            '풀이 상태는 트랜잭션 밖에서 조회해 주세요.'
        );
    }

    if (count($problem_ids) > 20) {
        throw new InvalidArgumentException(
            '한 번에 조회할 문제 수가 너무 많습니다.'
        );
    }

    $ids = array();

    foreach ($problem_ids as $id) {
        $id = simulation_problem_reader_positive_id($id);
        $ids[$id] = $id;
    }

    $ids = array_values($ids);
    $actor = simulation_problem_current_actor($connection);
    $user = $actor['user_id'] ?? null;

    if (!is_string($user) || trim($user) === '') {
        throw new UnexpectedValueException(
            '로그인 후 풀이 상태를 확인할 수 있습니다.'
        );
    }

    if (!$ids) {
        return array();
    }

    $source_browser = isset($_SESSION[$OJ_NAME . '_source_browser']);
    $cutoff = simulation_performance_history_cutoff($user, $source_browser);
    $context = $cutoff ?? '';

    $arguments = array_merge(array($user), $ids);
    $visibility = 'p.published = 1';

    if (simulation_problem_can_create($actor)) {
        $private =
            "p.published = 0 AND BINARY p.sharing IN ('private', 'shared')";

        if (($actor['administrator'] ?? false) === true) {
            $visibility = 'p.published = 1 OR (' . $private . ')';
        } else {
            $visibility = 'p.published = 1 OR (' . $private .
                " AND (BINARY p.owner_user_id = BINARY ? OR BINARY p.sharing = 'shared'))";
            $arguments[] = $user;
        }
    }

    $scope = '';

    if ($cutoff !== null) {
        $scope =
            ' AND s.performance_context = ? AND s.submitted_at >= ?';
        $arguments[] = $cutoff;
        $arguments[] = $cutoff;
    }

    $sql = str_replace(
        array('{ids}', '{visibility}', '{scope}'),
        array(
            implode(',', array_fill(0, count($ids), '?')),
            $visibility,
            $scope
        ),
        <<<'SQL'
SELECT CAST(problem_id AS CHAR) AS problem_id,
       MAX(CASE WHEN grading_status = 'graded'
                     AND max_score > 0 AND score = max_score
                THEN 1 ELSE 0 END) AS solved,
       MAX(CASE
           WHEN grade_id IS NOT NULL AND (
               grading_status IS NULL OR
               grading_status NOT IN ('pending', 'running', 'graded', 'error')
           ) THEN 1
           WHEN (score IS NOT NULL OR max_score IS NOT NULL) AND (
               grading_status IS NULL OR grading_status <> 'graded' OR
               score IS NULL OR max_score IS NULL OR
               score < 0 OR max_score <= 0 OR score > max_score
           ) THEN 1
           ELSE 0 END) AS invalid
FROM (
    SELECT v.problem_id, g.grade_id, g.grading_status, g.score, g.max_score,
           ROW_NUMBER() OVER (
               PARTITION BY s.submission_id
               ORDER BY g.grading_no DESC, g.grade_id DESC
           ) AS grade_rank
    FROM simulation_problem_submission s
    INNER JOIN simulation_problem_version v ON v.version_id = s.version_id
    INNER JOIN simulation_problem p ON p.problem_id = v.problem_id
    LEFT JOIN simulation_problem_grade g ON g.submission_id = s.submission_id
    WHERE s.user_id = ? AND v.problem_id IN ({ids})
      AND p.status = 1 AND ({visibility}){scope}
) AS ranked_grades
WHERE grade_rank = 1
GROUP BY problem_id
SQL
    );

    $statement = simulation_problem_history_statement(
        $connection,
        $sql,
        $arguments
    );
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    $states = array_fill_keys($ids, 'none');

    foreach ($rows as $row) {
        $id = (string)$row['problem_id'];

        if (
            !array_key_exists($id, $states) ||
            (int)$row['invalid'] !== 0
        ) {
            throw new RuntimeException(
                '저장된 풀이 상태를 확인하지 못했습니다.'
            );
        }

        $states[$id] = (int)$row['solved'] === 1
            ? 'solved'
            : 'submitted';
    }

    // 조회 도중 수행 기준이 달라지면 이전 범위의 상태를 반환하지 않는다.
    simulation_checked_performance_context(
        $user,
        $context,
        $source_browser
    );

    return $states;
}
