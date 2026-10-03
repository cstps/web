CREATE TABLE `course_performance_session` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` INT NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '0: 종료, 1: 진행 중',

    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME DEFAULT NULL,

    `started_by` VARCHAR(48) NOT NULL,
    `ended_by` VARCHAR(48) DEFAULT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    KEY `idx_course_performance_course_status`
        (`course_id`, `status`),

    KEY `idx_course_performance_started_at`
        (`started_at`),

    CONSTRAINT `fk_course_performance_course`
        FOREIGN KEY (`course_id`)
        REFERENCES `course` (`course_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
