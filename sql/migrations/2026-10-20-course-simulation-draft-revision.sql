-- 시뮬레이션 초안의 여러 탭 저장 충돌 검사에 사용할 버전

SET @simulation_revision_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'course_simulation_draft'
          AND COLUMN_NAME = 'revision'
    ),
    'SELECT ''초안 버전 필드가 이미 있습니다.'' AS result',
    'ALTER TABLE course_simulation_draft ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 1'
);
PREPARE simulation_revision_stmt FROM @simulation_revision_sql;
EXECUTE simulation_revision_stmt;
DEALLOCATE PREPARE simulation_revision_stmt;
