-- Course -> Lesson -> Activity
-- 신규 테이블만 생성한다.
-- 기존 course_contest와 제출 기록은 변경하지 않는다.
-- Lesson과 Course 소속 관계를 복합 외래 키로 검증한다.

SET @course_lesson_index_sql = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'course_lesson'
          AND INDEX_NAME = 'uq_course_lesson_id_course'
    ) = 0,
    'ALTER TABLE course_lesson ADD UNIQUE KEY uq_course_lesson_id_course (lesson_id, course_id)',
    'SELECT 1'
);

PREPARE course_lesson_index_stmt
FROM @course_lesson_index_sql;

EXECUTE course_lesson_index_stmt;

DEALLOCATE PREPARE course_lesson_index_stmt;

CREATE TABLE IF NOT EXISTS course_activity (
    activity_id INT NOT NULL AUTO_INCREMENT,
    course_id INT NOT NULL,
    lesson_id INT NULL,
    activity_type VARCHAR(32) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(48) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (activity_id),

    KEY idx_ca_course_lesson (
        course_id, lesson_id, sort_order
    ),

    KEY idx_ca_type (
        activity_type
    ),

    KEY idx_ca_lesson_course (
        lesson_id, course_id
    ),

    UNIQUE KEY uq_ca_id_course (
        activity_id, course_id
    ),

    CONSTRAINT fk_ca_course
        FOREIGN KEY (course_id)
        REFERENCES course (course_id),

    CONSTRAINT fk_ca_lesson
        FOREIGN KEY (lesson_id, course_id)
        REFERENCES course_lesson (lesson_id, course_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE IF NOT EXISTS course_activity_contest (
    activity_id INT NOT NULL,
    course_contest_id INT NOT NULL,

    PRIMARY KEY (activity_id),

    UNIQUE KEY uq_cac_course_contest (
        course_contest_id
    ),

    CONSTRAINT fk_cac_activity
        FOREIGN KEY (activity_id)
        REFERENCES course_activity (activity_id),

    CONSTRAINT fk_cac_course_contest
        FOREIGN KEY (course_contest_id)
        REFERENCES course_contest (id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
