-- 문제은행 정답 버전별 목표 이미지 생성 요청과 결과.
-- 기존 수업 활동의 이미지 테이블과 대기열은 변경하지 않는다.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS simulation_problem_reference_image (
    version_id BIGINT NOT NULL,

    source_sha256 CHAR(64) NOT NULL,
    settings_sha256 CHAR(64) NOT NULL,
    request_metadata JSON NOT NULL,

    image_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    claim_token CHAR(32) DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,

    png_data MEDIUMBLOB,
    png_sha256 CHAR(64) DEFAULT NULL,
    render_metadata JSON DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    generated_at DATETIME DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (version_id),

    KEY idx_sim_problem_image_queue (image_status, created_at, version_id),

    CONSTRAINT fk_sim_problem_image_reference
        FOREIGN KEY (version_id)
        REFERENCES simulation_problem_reference (version_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_sim_problem_image_status
        CHECK (image_status IN ('pending', 'running', 'ready', 'error')),

    CONSTRAINT chk_sim_problem_image_request
        CHECK (JSON_TYPE(request_metadata) = 'OBJECT'),

    CONSTRAINT chk_sim_problem_image_metadata
        CHECK (
            render_metadata IS NULL
            OR JSON_TYPE(render_metadata) = 'OBJECT'
        ),

    CONSTRAINT chk_sim_problem_image_running
        CHECK (
            image_status <> 'running'
            OR (
                claim_token IS NOT NULL
                AND started_at IS NOT NULL
            )
        ),

    CONSTRAINT chk_sim_problem_image_ready
        CHECK (
            image_status <> 'ready'
            OR (
                png_data IS NOT NULL
                AND png_sha256 IS NOT NULL
                AND render_metadata IS NOT NULL
                AND generated_at IS NOT NULL
            )
        ),

    CONSTRAINT chk_sim_problem_image_size
        CHECK (
            png_data IS NULL
            OR OCTET_LENGTH(png_data) BETWEEN 1 AND 1048576
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
