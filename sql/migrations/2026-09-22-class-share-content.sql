CREATE TABLE class_share_event (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    school_id BIGINT UNSIGNED NOT NULL,

    slug VARCHAR(80) NOT NULL,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(255) NULL,

    academic_year SMALLINT UNSIGNED NOT NULL,

    event_start_at DATETIME NULL,
    event_end_at DATETIME NULL,

    application_start_at DATETIME NULL,
    application_end_at DATETIME NULL,

    privacy_policy_version VARCHAR(50) NOT NULL,
    privacy_notice TEXT NOT NULL,
    retention_until DATE NULL,

    status ENUM(
        'draft',
        'published',
        'closed',
        'archived'
    ) NOT NULL DEFAULT 'draft',

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_event_school_slug
        (school_id, slug),

    KEY idx_class_share_event_school_status
        (school_id, status),

    KEY idx_class_share_event_year
        (academic_year),

    CONSTRAINT fk_class_share_event_school
        FOREIGN KEY (school_id)
        REFERENCES class_share_school (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_class_share_event_created_admin
        FOREIGN KEY (created_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL,

    CONSTRAINT fk_class_share_event_updated_admin
        FOREIGN KEY (updated_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_class (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    event_id BIGINT UNSIGNED NOT NULL,
    public_id CHAR(32) NOT NULL,

    subject VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    teacher_name VARCHAR(100) NOT NULL,
    target VARCHAR(100) NOT NULL,

    class_start_at DATETIME NOT NULL,
    class_end_at DATETIME NULL,

    place VARCHAR(150) NOT NULL,
    application_deadline DATETIME NOT NULL,

    capacity SMALLINT UNSIGNED NOT NULL,
    description TEXT NOT NULL,

    sort_order INT UNSIGNED NOT NULL
        DEFAULT 0,

    status ENUM(
        'draft',
        'published',
        'closed',
        'cancelled',
        'archived'
    ) NOT NULL DEFAULT 'draft',

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_class_public_id
        (public_id),

    KEY idx_class_share_class_event_status
        (event_id, status),

    KEY idx_class_share_class_schedule
        (class_start_at),

    KEY idx_class_share_class_sort
        (event_id, sort_order, id),

    CONSTRAINT fk_class_share_class_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_class_share_class_created_admin
        FOREIGN KEY (created_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL,

    CONSTRAINT fk_class_share_class_updated_admin
        FOREIGN KEY (updated_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_notice (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    event_id BIGINT UNSIGNED NOT NULL,

    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,

    important TINYINT UNSIGNED NOT NULL
        DEFAULT 0,

    status ENUM(
        'draft',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft',

    published_at DATETIME NULL,

    sort_order INT UNSIGNED NOT NULL
        DEFAULT 0,

    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_class_share_notice_event_status
        (event_id, status),

    KEY idx_class_share_notice_sort
        (
            event_id,
            important,
            sort_order,
            id
        ),

    CONSTRAINT fk_class_share_notice_event
        FOREIGN KEY (event_id)
        REFERENCES class_share_event (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_class_share_notice_created_admin
        FOREIGN KEY (created_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL,

    CONSTRAINT fk_class_share_notice_updated_admin
        FOREIGN KEY (updated_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_application (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    class_id BIGINT UNSIGNED NOT NULL,
    application_code CHAR(32) NOT NULL,

    applicant_name VARCHAR(60) NOT NULL,
    applicant_school VARCHAR(100) NOT NULL,

    phone_ciphertext VARCHAR(255) NOT NULL,
    phone_lookup_hash CHAR(64) NOT NULL,
    phone_last4 CHAR(4) NOT NULL,

    password_hash VARCHAR(255) NOT NULL,

    status ENUM(
        'applied',
        'cancelled',
        'approved',
        'waiting',
        'rejected',
        'attended',
        'absent'
    ) NOT NULL DEFAULT 'applied',

    privacy_policy_version VARCHAR(50) NOT NULL,
    privacy_agreed_at DATETIME NOT NULL,

    cancelled_at DATETIME NULL,

    processed_by BIGINT UNSIGNED NULL,
    admin_note TEXT NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_application_code
        (application_code),

    KEY idx_class_share_application_class_status
        (class_id, status),

    KEY idx_class_share_application_duplicate
        (
            class_id,
            phone_lookup_hash,
            status
        ),

    KEY idx_class_share_application_lookup
        (
            phone_lookup_hash,
            created_at
        ),

    CONSTRAINT fk_class_share_application_class
        FOREIGN KEY (class_id)
        REFERENCES class_share_class (id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_class_share_application_admin
        FOREIGN KEY (processed_by)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    school_id BIGINT UNSIGNED NULL,
    admin_id BIGINT UNSIGNED NULL,

    actor_type ENUM(
        'admin',
        'applicant',
        'system'
    ) NOT NULL,

    action VARCHAR(64) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id BIGINT UNSIGNED NULL,

    before_data JSON NULL,
    after_data JSON NULL,

    ip_address VARCHAR(45) NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_class_share_audit_school_time
        (school_id, created_at),

    KEY idx_class_share_audit_admin_time
        (admin_id, created_at),

    KEY idx_class_share_audit_target
        (
            target_type,
            target_id,
            created_at
        ),

    CONSTRAINT fk_class_share_audit_school
        FOREIGN KEY (school_id)
        REFERENCES class_share_school (id)
        ON DELETE SET NULL,

    CONSTRAINT fk_class_share_audit_admin
        FOREIGN KEY (admin_id)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
