-- 수행모드 초안을 일반 초안과 분리하여 보관한다.
-- 시작 시각은 학생에게 적용되는 가장 이른 활성 수행모드 기준이다.
-- 기존 course_simulation_draft의 데이터는 변경하지 않는다.

CREATE TABLE IF NOT EXISTS course_simulation_performance_draft (
    activity_id INT NOT NULL,
    user_id VARCHAR(48) NOT NULL,
    performance_started_at DATETIME NOT NULL,
    source_code MEDIUMTEXT NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (
        activity_id,
        user_id,
        performance_started_at
    ),
    KEY idx_simulation_performance_draft_user (
        user_id,
        performance_started_at
    ),
    CONSTRAINT fk_simulation_performance_draft_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_simulation (activity_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
