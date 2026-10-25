<?php

require_once(__DIR__ . '/simulation_problem_reader.php');
require_once(__DIR__ . '/course_simulation_performance_policy.php');

function simulation_problem_history_statement(
    PDO $connection,
    $sql,
    array $arguments
) {
    $statement = $connection->prepare($sql);

    if (!$statement || !$statement->execute($arguments)) {
        throw new RuntimeException('그림 문제 제출 기록 조회에 실패했습니다.');
    }

    return $statement;
}

// 화면 최초 조회는 expected_context를 생략할 수 있다.
// 자동 갱신 경로는 화면의 수행 기준을 반드시 전달해야 한다.
function simulation_problem_submission_history(
    PDO $connection,
    $problem_id,
    $expected_context = null
) {
    global $OJ_NAME;

    if ($connection->inTransaction()) {
        throw new RuntimeException('제출 기록은 트랜잭션 밖에서 조회해 주세요.');
    }

    $problem_id = simulation_problem_reader_positive_id($problem_id);
    $actor = simulation_problem_current_actor($connection);
    $user_id = $actor['user_id'] ?? null;

    if (!is_string($user_id) || trim($user_id) === '') {
        throw new UnexpectedValueException('로그인 후 제출 기록을 확인할 수 있습니다.');
    }

    // 현재 공개 상태 또는 교사용 열람 권한도 확인한다.
    simulation_problem_reader_latest($connection, $actor, $problem_id);

    $source_browser = isset($_SESSION[$OJ_NAME . '_source_browser']);
    $cutoff = simulation_performance_history_cutoff($user_id, $source_browser);
    $context = $cutoff ?? '';

    if ($expected_context !== null) {
        simulation_assert_performance_context($expected_context, $cutoff);
    }

    $scope_sql = '';
    $arguments = array($problem_id, $user_id);

    if ($cutoff !== null) {
        $scope_sql =
            ' AND s.performance_context = ? AND s.submitted_at >= ?';
        $arguments[] = $cutoff;
        $arguments[] = $cutoff;
    }

    $statement = simulation_problem_history_statement(
        $connection,
        'SELECT
            CAST(s.submission_id AS CHAR) AS submission_id,
            CAST(s.version_id AS CHAR) AS version_id,
            s.submission_no, s.submitted_at,
            v.version_no
         FROM simulation_problem_submission s
         INNER JOIN simulation_problem_version v
           ON v.version_id = s.version_id
         WHERE v.problem_id = ? AND s.user_id = ?' .
         $scope_sql .
        ' ORDER BY s.submission_id DESC
          LIMIT 10',
        $arguments
    );
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();

    $grades = array();

    if ($rows) {
        $ids = array_column($rows, 'submission_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 선택된 본인 제출 10건 안에서 최신 채점 회차만 조회한다.
        $statement = simulation_problem_history_statement(
            $connection,
            'SELECT
                submission_id, grading_status,
                grade_score, grade_max_score,
                grade_region_comparison_json
             FROM (
                 SELECT
                     CAST(submission_id AS CHAR) AS submission_id,
                     grading_status,
                     score AS grade_score,
                     max_score AS grade_max_score,
                     JSON_EXTRACT(
                         feedback_json,
                         "$.result.metrics.region_comparison"
                     ) AS grade_region_comparison_json,
                     ROW_NUMBER() OVER (
                         PARTITION BY submission_id
                         ORDER BY grading_no DESC
                     ) AS grade_rank
                 FROM simulation_problem_grade
                 WHERE submission_id IN (' . $placeholders . ')
             ) AS latest_grades
             WHERE grade_rank = 1',
            $ids
        );

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $grade) {
            $grades[$grade['submission_id']] = $grade;
        }
        $statement->closeCursor();
    }

    foreach ($rows as &$row) {
        $grade = $grades[$row['submission_id']] ?? null;
        $state = $grade ? $grade['grading_status'] : '';

        if (!in_array(
            $state,
            array('', 'pending', 'running', 'graded', 'error'),
            true
        )) {
            throw new RuntimeException('저장된 채점 상태가 올바르지 않습니다.');
        }

        $score = $grade['grade_score'] ?? null;
        $maximum = $grade['grade_max_score'] ?? null;

        if ($score !== null || $maximum !== null) {
            $pattern = '/^(0|[1-9][0-9]{0,4})\.[0-9]{2}$/D';

            if (
                $state !== 'graded' ||
                !is_string($score) ||
                !is_string($maximum) ||
                !preg_match($pattern, $score) ||
                !preg_match($pattern, $maximum) ||
                (int)str_replace('.', '', $maximum) <= 0 ||
                (int)str_replace('.', '', $score) >
                    (int)str_replace('.', '', $maximum)
            ) {
                throw new RuntimeException('저장된 채점 점수가 올바르지 않습니다.');
            }
        }

        $row['grading_status'] = $state;
        $row['grade_score'] = $score;
        $row['grade_max_score'] = $maximum;

        $region_json = $grade['grade_region_comparison_json'] ?? null;

        $row['grade_region_comparison_json'] = (
            $state === 'graded' &&
            is_string($region_json) &&
            $region_json !== 'null' &&
            strlen($region_json) <= 131072
        ) ? $region_json : null;
    }
    unset($row);

    // 조회 도중 수행모드가 시작·종료됐으면 이전 범위의 응답을 반환하지 않는다.
    simulation_checked_performance_context(
        $user_id,
        $context,
        $source_browser
    );

    return array(
        'problem_id' => $problem_id,
        'performance_context' => $context,
        'submissions' => $rows,
    );
}
