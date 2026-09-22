CREATE TABLE class_share_school (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    school_code VARCHAR(20) NULL,
    school_name VARCHAR(100) NOT NULL,
    slug VARCHAR(80) NOT NULL,

    page_title VARCHAR(150) NOT NULL
        DEFAULT '수업나눔한마당',

    introduction TEXT NULL,

    status ENUM(
        'active',
        'inactive',
        'archived'
    ) NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_school_code
        (school_code),

    UNIQUE KEY uk_class_share_school_slug
        (slug),

    KEY idx_class_share_school_status
        (status)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_admin (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    login_id VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    display_name VARCHAR(60) NOT NULL,
    email VARCHAR(255) NULL,

    is_super_admin TINYINT UNSIGNED NOT NULL
        DEFAULT 0,

    status ENUM(
        'active',
        'locked',
        'disabled'
    ) NOT NULL DEFAULT 'active',

    failed_login_count SMALLINT UNSIGNED NOT NULL
        DEFAULT 0,

    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    password_changed_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_admin_login_id
        (login_id),

    KEY idx_class_share_admin_status
        (status),

    KEY idx_class_share_admin_locked_until
        (locked_until)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_admin_school (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    admin_id BIGINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NOT NULL,

    role ENUM(
        'school_admin',
        'editor',
        'viewer'
    ) NOT NULL DEFAULT 'viewer',

    active TINYINT UNSIGNED NOT NULL
        DEFAULT 1,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_class_share_admin_school
        (admin_id, school_id),

    KEY idx_class_share_admin_school_school
        (school_id, active),

    CONSTRAINT fk_class_share_admin_school_admin
        FOREIGN KEY (admin_id)
        REFERENCES class_share_admin (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_class_share_admin_school_school
        FOREIGN KEY (school_id)
        REFERENCES class_share_school (id)
        ON DELETE RESTRICT
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE class_share_admin_login_attempt (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    admin_id BIGINT UNSIGNED NULL,
    login_id VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,

    was_successful TINYINT UNSIGNED NOT NULL
        DEFAULT 0,

    failure_reason VARCHAR(50) NULL,
    user_agent VARCHAR(255) NULL,

    attempted_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_class_share_login_id_time
        (login_id, attempted_at),

    KEY idx_class_share_login_ip_time
        (ip_address, attempted_at),

    KEY idx_class_share_login_admin_time
        (admin_id, attempted_at),

    CONSTRAINT fk_class_share_login_attempt_admin
        FOREIGN KEY (admin_id)
        REFERENCES class_share_admin (id)
        ON DELETE SET NULL
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
