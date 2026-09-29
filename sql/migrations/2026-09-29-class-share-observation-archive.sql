-- 원본 참관록에 관리자 검토를 거친 보존용 내용 저장
ALTER TABLE class_share_observation
    ADD COLUMN archive_body TEXT NULL
        AFTER body,
    ADD COLUMN archive_reviewed_at DATETIME NULL
        AFTER archive_body,
    ADD COLUMN archive_reviewed_by BIGINT UNSIGNED NULL
        AFTER archive_reviewed_at;

-- 기한 이후에는 검토된 내용과 행사·프로그램 연결만 보존
CREATE TABLE class_share_observation_archive (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NULL,
    body TEXT NOT NULL,

    PRIMARY KEY (id),

    KEY idx_class_share_observation_archive_event_class
        (event_id, class_id, id),

    CONSTRAINT fk_class_share_observation_archive_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_class_share_observation_archive_class
        FOREIGN KEY (class_id)
        REFERENCES class_share_class (id)
        ON DELETE RESTRICT
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
