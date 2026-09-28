CREATE TABLE IF NOT EXISTS admin_user_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id VARCHAR(48) NOT NULL,
    action VARCHAR(20) NOT NULL,
    actor_user_id VARCHAR(48) NOT NULL,
    actor_ip VARCHAR(46) NOT NULL DEFAULT '',
    details VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_admin_user_audit_user (
        user_id,
        id
    ),

    KEY idx_admin_user_audit_actor (
        actor_user_id,
        created_at
    ),

    KEY idx_admin_user_audit_action (
        action,
        created_at
    )
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
