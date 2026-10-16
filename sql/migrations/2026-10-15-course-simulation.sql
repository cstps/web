-- Course p5.js 시뮬레이션 및 그림 문제 설정
-- 기존 Course, Activity, Contest 데이터는 변경하지 않는다.
-- free: 일반 시뮬레이션
-- drawing: 그림 문제 자동채점

CREATE TABLE IF NOT EXISTS course_simulation (
    activity_id INT NOT NULL,

    simulation_mode VARCHAR(16) NOT NULL DEFAULT 'free',

    starter_code MEDIUMTEXT NOT NULL,

    canvas_width SMALLINT UNSIGNED NOT NULL DEFAULT 400,
    canvas_height SMALLINT UNSIGNED NOT NULL DEFAULT 400,

    allow_edit TINYINT(1) NOT NULL DEFAULT 1,
    allow_submit TINYINT(1) NOT NULL DEFAULT 1,

    grading_config JSON NULL,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (activity_id),

    CONSTRAINT fk_course_simulation_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_activity (activity_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_course_simulation_mode
        CHECK (simulation_mode IN ('free', 'drawing')),

    CONSTRAINT chk_course_simulation_canvas
        CHECK (
            canvas_width BETWEEN 100 AND 2000
            AND canvas_height BETWEEN 100 AND 2000
        )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
