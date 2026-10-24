-- 시뮬레이션 문제 목록과 버전별 원본 보관.
-- 기존 수업 활동과 학생 기록은 변경하지 않는다.

CREATE TABLE simulation_problem (
    problem_id BIGINT NOT NULL AUTO_INCREMENT,
    owner_user_id VARCHAR(48) NOT NULL,
    sharing VARCHAR(16) NOT NULL DEFAULT 'private',
    status TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (problem_id),
    KEY idx_sim_problem_owner (owner_user_id, status, problem_id),
    KEY idx_sim_problem_sharing (sharing, status, problem_id),
    CONSTRAINT chk_sim_problem_sharing
        CHECK (sharing IN ('private', 'shared')),
    CONSTRAINT chk_sim_problem_status
        CHECK (status IN (0, 1))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 저장할 때마다 새 버전을 추가한다.
-- 차시에 가져온 뒤 원본 버전이 바뀌어도 기존 활동에는 자동 반영하지 않는다.
CREATE TABLE simulation_problem_version (
    version_id BIGINT NOT NULL AUTO_INCREMENT,
    problem_id BIGINT NOT NULL,
    version_no INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    simulation_mode VARCHAR(16) NOT NULL,
    starter_code MEDIUMTEXT NOT NULL,
    canvas_width SMALLINT UNSIGNED NOT NULL DEFAULT 400,
    canvas_height SMALLINT UNSIGNED NOT NULL DEFAULT 400,
    p5_version VARCHAR(32) NOT NULL DEFAULT '2.3.4',
    allow_edit TINYINT NOT NULL DEFAULT 1,
    allow_submit TINYINT NOT NULL DEFAULT 1,
    created_by VARCHAR(48) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version_id),
    UNIQUE KEY uq_sim_problem_version (problem_id, version_no),
    CONSTRAINT fk_sim_problem_version_problem
        FOREIGN KEY (problem_id)
        REFERENCES simulation_problem (problem_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_sim_problem_version_no
        CHECK (version_no > 0),
    CONSTRAINT chk_sim_problem_version_mode
        CHECK (simulation_mode IN ('free', 'drawing')),
    CONSTRAINT chk_sim_problem_version_canvas
        CHECK (
            canvas_width BETWEEN 100 AND 2000
            AND canvas_height BETWEEN 100 AND 2000
        ),
    CONSTRAINT chk_sim_problem_version_edit
        CHECK (allow_edit IN (0, 1)),
    CONSTRAINT chk_sim_problem_version_submit
        CHECK (allow_submit IN (0, 1)),
    CONSTRAINT chk_sim_problem_version_code
        CHECK (OCTET_LENGTH(starter_code) BETWEEN 1 AND 200000)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 교사용 정답은 학생에게 제공하는 문제 본문과 분리한다.
-- 정답이 없는 자유 실습 문제는 이 테이블에 행을 만들지 않는다.
CREATE TABLE simulation_problem_reference (
    version_id BIGINT NOT NULL,
    source_code MEDIUMTEXT NOT NULL,
    render_config JSON NOT NULL,
    grading_config JSON NOT NULL,
    max_score DECIMAL(7,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version_id),
    CONSTRAINT fk_sim_problem_reference_version
        FOREIGN KEY (version_id)
        REFERENCES simulation_problem_version (version_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_sim_problem_reference_score
        CHECK (max_score > 0),
    CONSTRAINT chk_sim_problem_reference_code
        CHECK (OCTET_LENGTH(source_code) BETWEEN 1 AND 200000)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 가져올 때 새 활동과 독립된 정답 버전을 생성하고 출처만 기록한다.
-- 학생 초안·제출·채점 이력은 복사하지 않는다.
CREATE TABLE course_simulation_problem_import (
    activity_id INT NOT NULL,
    version_id BIGINT NOT NULL,
    imported_by VARCHAR(48) NOT NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_id),
    KEY idx_sim_problem_import_version (version_id),
    CONSTRAINT fk_sim_problem_import_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_simulation (activity_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_sim_problem_import_version
        FOREIGN KEY (version_id)
        REFERENCES simulation_problem_version (version_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
