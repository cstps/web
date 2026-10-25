<?php

require_once(__DIR__ . '/course_simulation_job_lock.php');

function simulation_recover_interrupted_jobs()
{
    global $dbh;

    if (!isset($dbh) || !($dbh instanceof PDO)) {
        throw new RuntimeException('중단 작업 확인용 DB 연결이 없습니다.');
    }

    if ($dbh->inTransaction()) {
        throw new RuntimeException('트랜잭션 진행 중에는 중단 작업을 정리할 수 없습니다.');
    }

    $message = '실행 시작 후 5분이 지났으며 실행 잠금이 해제되어 중단된 작업으로 정리했습니다.';

    foreach (array('grade', 'image', 'bank_image', 'bank_grade') as $kind) {
        if ($kind === 'grade') {
            $select_sql =
                "SELECT grade_id AS job_id
                 FROM course_simulation_grade
                 WHERE grading_status = 'running'
                   AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY started_at, grade_id
                 LIMIT 100";
        } elseif ($kind === 'bank_grade') {
            $select_sql =
                "SELECT grade_id AS job_id
                 FROM simulation_problem_grade
                 WHERE grading_status = 'running'
                   AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY started_at, grade_id
                 LIMIT 100";
        } elseif ($kind === 'bank_image') {
            $select_sql =
                "SELECT version_id AS job_id
                 FROM simulation_problem_reference_image
                 WHERE image_status = 'running'
                   AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY started_at, version_id
                 LIMIT 100";
        } else {
            $select_sql =
                "SELECT reference_id AS job_id
                 FROM course_simulation_reference_image
                 WHERE image_status = 'running'
                   AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY started_at, reference_id
                 LIMIT 100";
        }

        $stmt = $dbh->prepare($select_sql);
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($jobs as $job) {
            $id = (string)$job['job_id'];
            $job_lock = simulation_job_acquire_lock($kind, $id);

            if ($job_lock === null) {
                continue;
            }

            try {
                if ($kind === 'grade') {
                    $update_sql =
                        "UPDATE course_simulation_grade
                         SET grading_status = 'error',
                             score = NULL,
                             max_score = NULL,
                             feedback_json = JSON_SET(
                                 COALESCE(feedback_json, JSON_OBJECT()),
                                 '$.error',
                                 JSON_OBJECT(
                                     'message', ?,
                                     'code', 'interrupted_job'
                                 )
                             ),
                             graded_at = NOW()
                         WHERE grade_id = ?
                           AND grading_status = 'running'
                           AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                } elseif ($kind === 'bank_grade') {
                    $update_sql =
                        "UPDATE simulation_problem_grade
                         SET grading_status = 'error',
                             score = NULL,
                             max_score = NULL,
                             claim_token = NULL,
                             error_message = ?,
                             feedback_json = JSON_SET(
                                 COALESCE(feedback_json, JSON_OBJECT()),
                                 '$.error',
                                 JSON_OBJECT(
                                     'code', 'interrupted_job',
                                     'message',
                                     '채점 작업이 중단되었습니다. 다시 채점을 요청해 주세요.'
                                 )
                             ),
                             graded_at = NOW()
                         WHERE grade_id = ?
                           AND grading_status = 'running'
                           AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                } elseif ($kind === 'bank_image') {
                    $update_sql =
                        "UPDATE simulation_problem_reference_image
                         SET image_status = 'error',
                             error_message = ?,
                             claim_token = NULL
                         WHERE version_id = ?
                           AND image_status = 'running'
                           AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                } else {
                    $update_sql =
                        "UPDATE course_simulation_reference_image
                         SET image_status = 'error',
                             error_message = ?
                         WHERE reference_id = ?
                           AND image_status = 'running'
                           AND started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                }

                $stmt = $dbh->prepare($update_sql);
                $stmt->execute(array($message, $id));
                $changed = $stmt->rowCount();
                $stmt->closeCursor();

                if ($changed === 1) {
                    echo '중단 작업 정리: ' .
                        ($kind === 'grade' ? '채점 ID ' :
                            ($kind === 'bank_grade' ? '문제은행 채점 ID ' :
                                ($kind === 'bank_image' ? '문제은행 버전 ID ' : '정답 ID '))) .
                        $id . " / 오류 상태로 전환\n";
                }
            } finally {
                $job_lock = null;
            }
        }
    }
}
