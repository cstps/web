CREATE TABLE test_run_result (
    solution_id INT NOT NULL,
    execution_result TINYINT UNSIGNED NOT NULL,
    stdout MEDIUMBLOB NOT NULL,
    stderr MEDIUMBLOB NOT NULL,
    stdout_truncated TINYINT UNSIGNED NOT NULL DEFAULT 0,
    stderr_truncated TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (solution_id),
    CONSTRAINT fk_test_run_result_solution
        FOREIGN KEY (solution_id)
        REFERENCES solution (solution_id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;
