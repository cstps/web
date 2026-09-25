ALTER TABLE class_share_event
    ADD COLUMN application_capacity INT UNSIGNED NULL
        COMMENT '행사 직접 신청 정원, NULL은 제한 없음'
        AFTER application_mode;

ALTER TABLE class_share_application
    ADD COLUMN event_id BIGINT UNSIGNED NULL
        AFTER id,
    ADD COLUMN application_scope
        ENUM('event', 'program')
        NOT NULL
        DEFAULT 'program'
        AFTER class_id;

UPDATE class_share_application AS application
INNER JOIN class_share_class AS class_item
    ON class_item.id = application.class_id
SET application.event_id = class_item.event_id
WHERE application.event_id IS NULL;

ALTER TABLE class_share_application
    MODIFY COLUMN event_id BIGINT UNSIGNED NOT NULL,
    MODIFY COLUMN class_id BIGINT UNSIGNED NULL;

ALTER TABLE class_share_application
    ADD KEY idx_class_share_application_event_status
        (event_id, status),
    ADD KEY idx_class_share_application_event_duplicate
        (event_id, phone_lookup_hash, status),
    ADD CONSTRAINT fk_class_share_application_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT chk_class_share_application_target
        CHECK (
            (
                application_scope = 'event'
                AND class_id IS NULL
            )
            OR
            (
                application_scope = 'program'
                AND class_id IS NOT NULL
            )
        );
