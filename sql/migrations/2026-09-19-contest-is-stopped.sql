ALTER TABLE contest
    ADD COLUMN is_stopped TINYINT NOT NULL DEFAULT 0
        COMMENT '0: 중지 아님, 1: 명시적 운영 중지'
        AFTER allow_copy,
    ALGORITHM=INSTANT;
