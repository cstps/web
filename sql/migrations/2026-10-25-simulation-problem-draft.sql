-- 문제은행의 독립 풀이 초안
-- 수행 기준 시각은 서버에서 확인한다. 일반 모드는 빈 문자열을 사용한다.

CREATE TABLE simulation_problem_draft (
    version_id BIGINT NOT NULL,
    user_id VARCHAR(48) NOT NULL,
    performance_context VARCHAR(19)
        CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT '',
    source_code MEDIUMTEXT NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (version_id, user_id, performance_context),
    KEY idx_sim_problem_draft_user_updated (user_id, updated_at),

    CONSTRAINT fk_sim_problem_draft_version
        FOREIGN KEY (version_id)
        REFERENCES simulation_problem_version (version_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_sim_problem_draft_code
        CHECK (OCTET_LENGTH(source_code) <= 200000),

    CONSTRAINT chk_sim_problem_draft_revision
        CHECK (revision > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
