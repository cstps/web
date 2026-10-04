-- ============================================================
-- users 테이블 InnoDB 전환
--
-- student_code_draft.user_id가 users.user_id를
-- 외래키로 참조하기 전에 users 테이블이
-- InnoDB여야 한다.
--
-- 이미 InnoDB인 경우에는 변경하지 않는다.
-- ============================================================

SET @users_engine = (
    SELECT ENGINE
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
    LIMIT 1
);

SET @users_engine_sql =
    IF(
        @users_engine = 'InnoDB',
        'SELECT ''users already InnoDB'' AS result',
        'ALTER TABLE `users` ENGINE=InnoDB'
    );

PREPARE users_engine_stmt
FROM @users_engine_sql;

EXECUTE users_engine_stmt;

DEALLOCATE PREPARE users_engine_stmt;
