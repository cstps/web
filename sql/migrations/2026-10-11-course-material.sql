-- Course 학습 자료 상세 본문
-- 기존 course_activity 데이터는 변경하지 않는다.

CREATE TABLE IF NOT EXISTS course_material (
    activity_id INT NOT NULL,

    content_html MEDIUMTEXT NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (activity_id),

    CONSTRAINT fk_course_material_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_activity (activity_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
