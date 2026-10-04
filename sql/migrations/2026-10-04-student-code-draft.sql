CREATE TABLE `student_code_draft` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `user_id` VARCHAR(48) NOT NULL,
    `problem_id` INT NOT NULL,
    `contest_id` INT NOT NULL DEFAULT 0,
    `language` INT UNSIGNED NOT NULL DEFAULT 0,

    `source` MEDIUMTEXT NOT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uk_student_code_draft_user_problem_contest`
        (`user_id`, `problem_id`, `contest_id`),

    KEY `idx_student_code_draft_contest_updated`
        (`contest_id`, `updated_at`),

    KEY `idx_student_code_draft_problem_updated`
        (`problem_id`, `updated_at`),

    CONSTRAINT `fk_student_code_draft_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
