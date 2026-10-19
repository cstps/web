-- 채점 및 목표 이미지 생성의 실제 실행 시작 시각
-- 기존 기록의 실행 시각은 추정하여 채우지 않는다.

SET @simulation_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'course_simulation_grade'
          AND COLUMN_NAME = 'started_at'
    ),
    'SELECT ''채점 실행 시작 시각 필드가 이미 있습니다.'' AS result',
    'ALTER TABLE course_simulation_grade ADD COLUMN started_at DATETIME NULL DEFAULT NULL'
);
PREPARE simulation_stmt FROM @simulation_sql;
EXECUTE simulation_stmt;
DEALLOCATE PREPARE simulation_stmt;

SET @simulation_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'course_simulation_reference_image'
          AND COLUMN_NAME = 'started_at'
    ),
    'SELECT ''이미지 실행 시작 시각 필드가 이미 있습니다.'' AS result',
    'ALTER TABLE course_simulation_reference_image ADD COLUMN started_at DATETIME NULL DEFAULT NULL'
);
PREPARE simulation_stmt FROM @simulation_sql;
EXECUTE simulation_stmt;
DEALLOCATE PREPARE simulation_stmt;
