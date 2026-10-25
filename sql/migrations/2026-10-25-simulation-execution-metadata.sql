-- 그림 문제·수업 활동·정답 버전의 실행 설정.
-- 기존 실행 경로와 p5_version은 유지한다.
-- MySQL DDL은 일반 트랜잭션으로 되돌릴 수 없으므로 적용 전 백업한다.

SET NAMES utf8mb4;

DELIMITER //

DROP PROCEDURE IF EXISTS migration_simulation_execution_20261025//

CREATE PROCEDURE migration_simulation_execution_20261025(
    IN target_table VARCHAR(64)
)
BEGIN
    DECLARE column_count INT DEFAULT 0;
    DECLARE valid_count INT DEFAULT 0;
    DECLARE table_count INT DEFAULT 0;

    IF target_table NOT IN (
        'simulation_problem_version',
        'course_simulation',
        'course_simulation_reference'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '허용하지 않는 마이그레이션 대상입니다.';
    END IF;

    SELECT COUNT(*) INTO table_count
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = target_table
      AND ENGINE = 'InnoDB';

    IF table_count <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '대상 테이블 또는 저장 엔진을 확인해 주세요.';
    END IF;

    SELECT COUNT(*) INTO column_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = target_table
      AND COLUMN_NAME IN (
          'source_language',
          'execution_engine',
          'execution_version'
      );

    IF column_count = 0 THEN

        -- 기존 버전과 다른 기본값을 잘못 기록하지 않도록 검사한다.
        IF target_table <> 'course_simulation' THEN
            SET @migration_invalid_versions = 0;
            SET @migration_sql = CONCAT(
                'SELECT COUNT(*) INTO @migration_invalid_versions FROM `',
                target_table,
                '` WHERE p5_version IS NULL OR ',
                'BINARY p5_version <> BINARY ''2.3.4'''
            );

            PREPARE migration_statement FROM @migration_sql;
            EXECUTE migration_statement;
            DEALLOCATE PREPARE migration_statement;

            IF @migration_invalid_versions <> 0 THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = '기존 p5.js 버전이 달라 자동 적용을 중단했습니다.';
            END IF;
        END IF;

        SET @migration_sql = CONCAT(
            'ALTER TABLE `', target_table, '` ',
            'ADD COLUMN source_language VARCHAR(16) ',
            'NOT NULL DEFAULT ''javascript'', ',
            'ADD COLUMN execution_engine VARCHAR(32) ',
            'NOT NULL DEFAULT ''p5js'', ',
            'ADD COLUMN execution_version VARCHAR(32) ',
            'NOT NULL DEFAULT ''2.3.4'''
        );

        PREPARE migration_statement FROM @migration_sql;
        EXECUTE migration_statement;
        DEALLOCATE PREPARE migration_statement;

    ELSEIF column_count <> 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '실행 설정 열이 일부만 존재합니다. 확인 후 진행해 주세요.';
    END IF;

    -- 재실행할 때도 이미 존재하는 열의 형식을 확인한다.
    SELECT COUNT(*) INTO valid_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = target_table
      AND DATA_TYPE = 'varchar'
      AND IS_NULLABLE = 'NO'
      AND (
          (
              COLUMN_NAME = 'source_language'
              AND CHARACTER_MAXIMUM_LENGTH = 16
              AND COLUMN_DEFAULT = 'javascript'
          )
          OR (
              COLUMN_NAME = 'execution_engine'
              AND CHARACTER_MAXIMUM_LENGTH = 32
              AND COLUMN_DEFAULT = 'p5js'
          )
          OR (
              COLUMN_NAME = 'execution_version'
              AND CHARACTER_MAXIMUM_LENGTH = 32
              AND COLUMN_DEFAULT = '2.3.4'
          )
      );

    IF valid_count <> 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = '실행 설정 열의 형식 또는 기본값이 예상과 다릅니다.';
    END IF;
END//

CALL migration_simulation_execution_20261025(
    'simulation_problem_version'
)//

CALL migration_simulation_execution_20261025(
    'course_simulation'
)//

CALL migration_simulation_execution_20261025(
    'course_simulation_reference'
)//

DROP PROCEDURE migration_simulation_execution_20261025//

DELIMITER ;
