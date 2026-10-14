-- Course 과제 상세 안내 및 제출 정책
-- 기존 Course/Activity 데이터는 변경하지 않는다.

CREATE TABLE IF NOT EXISTS course_assignment (
    activity_id INT NOT NULL,
    content_html MEDIUMTEXT NOT NULL,

    due_at DATETIME NULL DEFAULT NULL,
    allow_late TINYINT(1) NOT NULL DEFAULT 0,
    allow_resubmit TINYINT(1) NOT NULL DEFAULT 1,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (activity_id),

    CONSTRAINT fk_course_assignment_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_activity (activity_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
