-- 정적 그림 정답 및 채점 기준의 버전별 보관
-- 기존 활동, 제출, 채점 결과는 변경하지 않는다.
-- 정답 수정 시 기존 행을 덮어쓰지 않고 새 버전을 추가한다.
-- 학생용 화면과 API에서는 이 테이블을 조회하지 않는다.
-- JSON 설정의 세부 형식은 저장 처리 구현 단계에서 검증한다.

CREATE TABLE IF NOT EXISTS course_simulation_reference (
    reference_id BIGINT NOT NULL AUTO_INCREMENT,
    activity_id INT NOT NULL,
    reference_no INT NOT NULL,

    source_code MEDIUMTEXT NOT NULL,

    canvas_width SMALLINT UNSIGNED NOT NULL DEFAULT 400,
    canvas_height SMALLINT UNSIGNED NOT NULL DEFAULT 400,
    p5_version VARCHAR(32) NOT NULL DEFAULT '2.3.4',

    render_config JSON NOT NULL,
    grading_config JSON NOT NULL,
    max_score DECIMAL(7,2) NOT NULL,

    created_by VARCHAR(48) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (reference_id),

    UNIQUE KEY uk_simulation_reference_version (
        activity_id,
        reference_no
    ),

    CONSTRAINT fk_simulation_reference_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_simulation (activity_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_simulation_reference_no
        CHECK (reference_no > 0),

    CONSTRAINT chk_simulation_reference_canvas
        CHECK (
            canvas_width BETWEEN 100 AND 2000
            AND canvas_height BETWEEN 100 AND 2000
        ),

    CONSTRAINT chk_simulation_reference_score
        CHECK (max_score > 0)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
