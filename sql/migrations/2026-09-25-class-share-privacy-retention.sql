/*
 * 개인정보 보관 기한 만료 후 비식별화를 위한 스키마 변경
 *
 * 신청 행의 행사·프로그램·상태·일시는 통계용으로 유지하고,
 * 직접 식별 정보와 인증 정보는 NULL로 파기한다.
 */

ALTER TABLE class_share_application
    MODIFY COLUMN application_code
        CHAR(32) NULL,
    MODIFY COLUMN applicant_name
        VARCHAR(60) NULL,
    MODIFY COLUMN applicant_school
        VARCHAR(100) NULL,
    MODIFY COLUMN phone_ciphertext
        VARCHAR(255) NULL,
    MODIFY COLUMN phone_lookup_hash
        CHAR(64) NULL,
    MODIFY COLUMN phone_last4
        CHAR(4) NULL,
    MODIFY COLUMN password_hash
        VARCHAR(255) NULL,
    ADD COLUMN privacy_destroyed_at
        DATETIME NULL
        COMMENT '개인정보 파기 일시'
        AFTER privacy_agreed_at,
    ADD KEY idx_class_share_application_privacy
        (
            privacy_destroyed_at,
            event_id
        );
