-- Course 학습 자료 첨부파일 메타데이터
-- 실제 파일은 /home/judge/course_files 에 저장한다.
-- 기존 Course, Lesson, Activity 데이터는 변경하지 않는다.

CREATE TABLE IF NOT EXISTS course_material_file (

    file_id INT NOT NULL AUTO_INCREMENT,

    activity_id INT NOT NULL,

    original_name VARCHAR(255) NOT NULL,

    stored_name VARCHAR(80)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    mime_type VARCHAR(127) NOT NULL,

    file_size BIGINT UNSIGNED NOT NULL,

    file_sha256 CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    sort_order INT UNSIGNED NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (file_id),

    UNIQUE KEY uk_course_material_file_stored (
        stored_name
    ),

    KEY idx_course_material_file_activity (
        activity_id,
        sort_order,
        file_id
    ),

    CONSTRAINT fk_course_material_file_material
        FOREIGN KEY (activity_id)
        REFERENCES course_material (activity_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
