-- 행사 신청 참여 구분 구조 추가
-- 한 번만 실행합니다. 기존 신청의 참여 구분은 NULL로 보존합니다.

SET @participation_before_count = (
    SELECT COUNT(*) FROM class_share_application
);

CREATE TABLE class_share_participation_option (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    capacity INT UNSIGNED NULL DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_cs_participation_id_event (id, event_id),

    KEY idx_cs_participation_event (
        event_id, is_active, sort_order, id
    ),

    CONSTRAINT fk_cs_participation_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,

    CONSTRAINT chk_cs_participation_capacity
        CHECK (capacity IS NULL OR capacity BETWEEN 1 AND 1000000),

    CONSTRAINT chk_cs_participation_active
        CHECK (is_active IN (0, 1))

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE class_share_event
    ADD COLUMN participation_enabled
        TINYINT UNSIGNED NOT NULL DEFAULT 0
        AFTER application_capacity,
    ADD CONSTRAINT chk_cs_event_participation_enabled
        CHECK (participation_enabled IN (0, 1));

ALTER TABLE class_share_application
    ADD COLUMN participation_option_id
        BIGINT UNSIGNED NULL DEFAULT NULL
        AFTER application_scope,

    ADD KEY idx_cs_application_participation_count (
        event_id, participation_option_id, application_scope, status
    ),

    ADD KEY idx_cs_application_participation_fk (
        participation_option_id, event_id
    ),

    ADD CONSTRAINT fk_cs_application_participation
        FOREIGN KEY (participation_option_id, event_id)
        REFERENCES class_share_participation_option (id, event_id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT;

SELECT
    @participation_before_count AS before_count,
    COUNT(*) AS after_count,
    COUNT(*) = @participation_before_count AS count_matches,
    COUNT(participation_option_id) AS assigned_count
FROM class_share_application;

SELECT
    COUNT(*) AS enabled_events
FROM class_share_event
WHERE participation_enabled = 1;

SELECT
    COUNT(*) AS option_count
FROM class_share_participation_option;
