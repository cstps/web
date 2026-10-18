-- 정답 버전별 목표 그림 이미지 저장.
-- 기존 정답·제출·채점 기록은 변경하지 않는다.

CREATE TABLE IF NOT EXISTS course_simulation_reference_image (
    reference_id BIGINT NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    image_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    png_data MEDIUMBLOB NULL,
    png_sha256 CHAR(64) NULL,
    render_metadata JSON NULL,
    error_message VARCHAR(500) NULL,
    generated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (reference_id),

    KEY idx_simulation_reference_image_status (
        image_status,
        created_at
    ),

    CONSTRAINT fk_simulation_reference_image_reference
        FOREIGN KEY (reference_id)
        REFERENCES course_simulation_reference (reference_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_simulation_reference_image_status
        CHECK (
            image_status IN ('pending', 'running', 'ready', 'error')
        ),

    CONSTRAINT chk_simulation_reference_image_size
        CHECK (
            png_data IS NULL OR
            OCTET_LENGTH(png_data) BETWEEN 1 AND 1048576
        ),

    CONSTRAINT chk_simulation_reference_image_ready
        CHECK (
            image_status <> 'ready' OR (
                png_data IS NOT NULL AND
                png_sha256 IS NOT NULL AND
                render_metadata IS NOT NULL AND
                generated_at IS NOT NULL
            )
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
