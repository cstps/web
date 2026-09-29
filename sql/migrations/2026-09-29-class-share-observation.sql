-- 행사별 참관록 접수 설정
CREATE TABLE class_share_observation_setting (
    event_id BIGINT UNSIGNED NOT NULL,

    enabled TINYINT(1) NOT NULL DEFAULT 0,
    open_at DATETIME NULL,
    close_at DATETIME NULL,

    privacy_notice TEXT NOT NULL,
    retention_until DATE NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (event_id),

    CONSTRAINT fk_class_share_observation_setting_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON DELETE RESTRICT
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


-- 신청 여부와 관계없이 제출하는 참관록
CREATE TABLE class_share_observation (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NULL,

    author_name VARCHAR(100) NOT NULL,
    affiliation VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,

    privacy_notice_snapshot TEXT NOT NULL,
    retention_until DATE NOT NULL,
    privacy_consented_at DATETIME NOT NULL,

    -- 재전송으로 같은 참관록이 중복 저장되는 것을 방지
    submission_key CHAR(64) NOT NULL,

    submitted_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_observation_submission
        (submission_key),

    KEY idx_class_share_observation_event_date
        (event_id, submitted_at),

    KEY idx_class_share_observation_event_class
        (event_id, class_id, submitted_at),

    KEY idx_class_share_observation_retention
        (retention_until, id),

    CONSTRAINT fk_class_share_observation_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_class_share_observation_class
        FOREIGN KEY (class_id)
        REFERENCES class_share_class (id)
        ON DELETE RESTRICT
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
