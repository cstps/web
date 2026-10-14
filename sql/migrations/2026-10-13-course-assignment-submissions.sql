-- Course 학생 과제 제출 이력
-- HUSTOJ solution 테이블과 분리하여 저장한다.
-- 기존 Course 및 Activity 데이터는 변경하지 않는다.

CREATE TABLE IF NOT EXISTS course_assignment_submission (
    submission_id BIGINT NOT NULL AUTO_INCREMENT,
    activity_id INT NOT NULL,
    user_id VARCHAR(48) NOT NULL,
    submission_no INT NOT NULL,
    content_text MEDIUMTEXT NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (submission_id),

    UNIQUE KEY uk_assignment_student_attempt (
        activity_id,
        user_id,
        submission_no
    ),

    KEY idx_assignment_student_latest (
        activity_id,
        user_id,
        submitted_at
    ),

    CONSTRAINT fk_assignment_submission_assignment
        FOREIGN KEY (activity_id)
        REFERENCES course_assignment (activity_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;


-- 제출별 첨부파일 메타데이터
-- 실제 파일은 웹 공개 경로 밖에 보관한다.

CREATE TABLE IF NOT EXISTS course_assignment_submission_file (
    file_id BIGINT NOT NULL AUTO_INCREMENT,
    submission_id BIGINT NOT NULL,

    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,

    file_size BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    sha256 CHAR(64) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (file_id),

    UNIQUE KEY uk_assignment_submission_stored_name (
        stored_name
    ),

    KEY idx_assignment_submission_file (
        submission_id
    ),

    CONSTRAINT fk_assignment_submission_file_submission
        FOREIGN KEY (submission_id)
        REFERENCES course_assignment_submission (submission_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
