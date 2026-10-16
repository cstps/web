-- Course p5.js 학생 임시 저장, 제출 이력, 그림 문제 채점
-- 기존 Course/Contest/Assignment 데이터는 변경하지 않는다.

-- 학생별 최신 작업 코드 1개
CREATE TABLE IF NOT EXISTS course_simulation_draft (
    activity_id INT NOT NULL,
    user_id VARCHAR(48) NOT NULL,
    source_code MEDIUMTEXT NOT NULL,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (activity_id, user_id),

    CONSTRAINT fk_simulation_draft_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_simulation (activity_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;


-- 정식 제출 이력: 기존 제출은 수정하지 않는다.
CREATE TABLE IF NOT EXISTS course_simulation_submission (
    submission_id BIGINT NOT NULL AUTO_INCREMENT,
    activity_id INT NOT NULL,
    user_id VARCHAR(48) NOT NULL,
    submission_no INT NOT NULL,
    source_code MEDIUMTEXT NOT NULL,

    submitted_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (submission_id),

    UNIQUE KEY uk_simulation_student_attempt (
        activity_id,
        user_id,
        submission_no
    ),

    KEY idx_simulation_student_latest (
        activity_id,
        user_id,
        submitted_at
    ),

    CONSTRAINT fk_simulation_submission_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_simulation (activity_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;


-- 그림 문제 채점 결과
-- 재채점할 때 기존 결과를 보존할 수 있도록 별도 이력 사용
CREATE TABLE IF NOT EXISTS course_simulation_grade (
    grade_id BIGINT NOT NULL AUTO_INCREMENT,
    submission_id BIGINT NOT NULL,
    grading_no INT NOT NULL,

    grading_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    score DECIMAL(7,2) NULL,
    max_score DECIMAL(7,2) NULL,
    feedback_json JSON NULL,

    graded_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (grade_id),

    UNIQUE KEY uk_simulation_grading_attempt (
        submission_id,
        grading_no
    ),

    KEY idx_simulation_grading_status (
        grading_status,
        created_at
    ),

    CONSTRAINT fk_simulation_grade_submission
        FOREIGN KEY (submission_id)
        REFERENCES course_simulation_submission (submission_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_simulation_grading_status
        CHECK (
            grading_status IN (
                'pending',
                'running',
                'graded',
                'error'
            )
        ),

    CONSTRAINT chk_simulation_grading_score
        CHECK (
            score IS NULL
            OR (
                score >= 0
                AND max_score IS NOT NULL
                AND max_score >= score
            )
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
