-- 행사별 가변 신청서 기반 테이블. 기존 신청서와 신청 데이터는 변경하지 않습니다.
-- MySQL 8.0.16 이상 / InnoDB / utf8mb4.
-- 최초 적용용입니다. 이미 존재하는 테이블은 덮어쓰지 않습니다.

CREATE TABLE class_share_application_form_setting (
    event_id BIGINT UNSIGNED NOT NULL,
    schema_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    CONSTRAINT fk_cs_form_setting_event FOREIGN KEY (event_id)
        REFERENCES class_share_event (id) ON DELETE CASCADE,
    CONSTRAINT ck_cs_form_version CHECK (schema_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_share_application_form_field (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    label VARCHAR(100) NOT NULL,
    help_text VARCHAR(500) NOT NULL DEFAULT '',
    field_type ENUM('short_text','long_text','single_choice','multiple_choice','phone') NOT NULL,
    options_json JSON DEFAULT NULL,
    is_required TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cs_form_field_key (event_id, field_key),
    KEY idx_cs_form_field_order (event_id, is_active, sort_order, id),
    CONSTRAINT fk_cs_form_field_setting FOREIGN KEY (event_id)
        REFERENCES class_share_application_form_setting (event_id) ON DELETE CASCADE,
    CONSTRAINT ck_cs_form_field_required CHECK (is_required IN (0,1)),
    CONSTRAINT ck_cs_form_field_active CHECK (is_active IN (0,1)),
    CONSTRAINT ck_cs_form_field_phone CHECK (
        (field_key = 'phone' AND field_type = 'phone' AND is_required = 1 AND is_active = 1)
        OR (field_key <> 'phone' AND field_type <> 'phone')
    ),
    CONSTRAINT ck_cs_form_field_basic CHECK (
        field_key NOT IN ('name','school') OR field_type = 'short_text'
    ),
    CONSTRAINT ck_cs_form_field_options CHECK (
        (field_type IN ('single_choice','multiple_choice')
            AND options_json IS NOT NULL AND JSON_TYPE(options_json) = 'ARRAY'
            AND JSON_LENGTH(options_json) >= 1)
        OR (field_type NOT IN ('single_choice','multiple_choice') AND options_json IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 추가 질문의 답변과 당시 질문 정보를 신청별 JSON으로 저장합니다.
-- 이름/소속/전화번호는 기존 application 열을 사용하며 여기에는 중복 저장하지 않습니다.
-- 이후 구현에서 개인정보 보관 기간이 끝나면 이 응답 행도 삭제합니다.
CREATE TABLE class_share_application_form_response (
    application_id BIGINT UNSIGNED NOT NULL,
    schema_version INT UNSIGNED NOT NULL,
    answers_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (application_id),
    CONSTRAINT fk_cs_form_response_application FOREIGN KEY (application_id)
        REFERENCES class_share_application (id) ON DELETE CASCADE,
    CONSTRAINT ck_cs_form_response_version CHECK (schema_version >= 1),
    CONSTRAINT ck_cs_form_response_answers CHECK (JSON_TYPE(answers_json) = 'ARRAY')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
