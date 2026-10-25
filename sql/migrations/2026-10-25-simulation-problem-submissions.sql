-- 문제은행 전용 정식 제출 및 채점 이력
-- 제출 번호는 문제 버전·사용자별로 증가한다.
-- request_key는 서버가 발급한 제출 요청 토큰의 SHA-256 값이다.

CREATE TABLE simulation_problem_submission (
    submission_id BIGINT NOT NULL AUTO_INCREMENT,
    version_id BIGINT NOT NULL,
    user_id VARCHAR(48) NOT NULL,
    submission_no INT NOT NULL,
    performance_context VARCHAR(19)
        CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT '',
    request_key CHAR(64)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_code MEDIUMTEXT NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (submission_id),

    UNIQUE KEY uq_sim_problem_submission_attempt (
        version_id, user_id, submission_no
    ),
    UNIQUE KEY uq_sim_problem_submission_request (
        user_id, request_key
    ),
    KEY idx_sim_problem_submission_history (
        version_id, user_id, submitted_at
    ),
    KEY idx_sim_problem_submission_user (
        user_id, submitted_at
    ),

    CONSTRAINT fk_sim_problem_submission_version
        FOREIGN KEY (version_id)
        REFERENCES simulation_problem_version (version_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_sim_problem_submission_no
        CHECK (submission_no > 0),
    CONSTRAINT chk_sim_problem_submission_code
        CHECK (OCTET_LENGTH(source_code) BETWEEN 1 AND 200000),
    CONSTRAINT chk_sim_problem_submission_request
        CHECK (
            OCTET_LENGTH(request_key) = 64
            AND request_key REGEXP '^[0-9a-f]{64}$'
        ),
    CONSTRAINT chk_sim_problem_submission_context
        CHECK (
            performance_context = ''
            OR OCTET_LENGTH(performance_context) = 19
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE simulation_problem_grade (
    grade_id BIGINT NOT NULL AUTO_INCREMENT,
    submission_id BIGINT NOT NULL,
    grading_no INT NOT NULL,
    grading_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    request_metadata JSON NOT NULL,
    claim_token CHAR(32)
        CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    score DECIMAL(7,2) DEFAULT NULL,
    max_score DECIMAL(7,2) DEFAULT NULL,
    feedback_json JSON DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    graded_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (grade_id),

    UNIQUE KEY uq_sim_problem_grade_attempt (
        submission_id, grading_no
    ),
    KEY idx_sim_problem_grade_queue (
        grading_status, created_at, grade_id
    ),

    CONSTRAINT fk_sim_problem_grade_submission
        FOREIGN KEY (submission_id)
        REFERENCES simulation_problem_submission (submission_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_sim_problem_grade_no
        CHECK (grading_no > 0),
    CONSTRAINT chk_sim_problem_grade_status
        CHECK (
            grading_status IN ('pending', 'running', 'graded', 'error')
        ),
    CONSTRAINT chk_sim_problem_grade_max_score
        CHECK (max_score IS NULL OR max_score > 0),
    CONSTRAINT chk_sim_problem_grade_score
        CHECK (
            score IS NULL
            OR (
                max_score IS NOT NULL
                AND score >= 0
                AND score <= max_score
            )
        ),
    CONSTRAINT chk_sim_problem_grade_claim
        CHECK (
            claim_token IS NULL
            OR (
                OCTET_LENGTH(claim_token) = 32
                AND claim_token REGEXP '^[0-9a-f]{32}$'
            )
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
