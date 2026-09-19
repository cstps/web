ALTER TABLE contest
    ADD COLUMN is_archived TINYINT NOT NULL DEFAULT 0
        COMMENT '0: 일반, 1: 보관'
        AFTER is_stopped,
    ADD COLUMN archived_at DATETIME NULL
        AFTER is_archived,
    ADD COLUMN archived_by VARCHAR(48) NULL
        AFTER archived_at,
    ALGORITHM=INSTANT;
