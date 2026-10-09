-- 기존 Course Contest를 Activity로 등록
-- 기존 데이터는 수정하지 않는다.
-- 재실행 시 이미 연결된 Contest는 건너뛴다.

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_course_contest_activity$$

CREATE PROCEDURE migrate_course_contest_activity()
BEGIN
    DECLARE v_done INT DEFAULT 0;

    DECLARE v_cc_id INT;
    DECLARE v_course_id INT;
    DECLARE v_lesson_id INT;
    DECLARE v_title VARCHAR(255);
    DECLARE v_sort_order INT UNSIGNED;
    DECLARE v_visible TINYINT;
    DECLARE v_status TINYINT;
    DECLARE v_created_by VARCHAR(48);
    DECLARE v_activity_id INT;
    DECLARE v_lock_acquired INT DEFAULT 0;
    DECLARE v_remaining INT DEFAULT 0;

    DECLARE contest_cursor CURSOR FOR
        SELECT
            cc.id,
            cc.course_id,
            cc.lesson_id,
            c.title,
            cc.sort_order,
            cc.visible,
            cc.status,
            cc.created_by
        FROM course_contest cc
        JOIN contest c
            ON c.contest_id = cc.contest_id
        LEFT JOIN course_activity_contest cac
            ON cac.course_contest_id = cc.id
        WHERE cac.course_contest_id IS NULL
        ORDER BY cc.id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET v_done = 1;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;

        IF v_lock_acquired = 1 THEN
            DO RELEASE_LOCK('jol_course_activity_backfill');
        END IF;

        RESIGNAL;
    END;

    SELECT GET_LOCK(
        'jol_course_activity_backfill',
        0
    )
    INTO v_lock_acquired;

    IF COALESCE(v_lock_acquired, 0) <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Activity 이관 작업이 이미 실행 중입니다.';
    END IF;

    START TRANSACTION;

    OPEN contest_cursor;

    migration_loop: LOOP

        FETCH contest_cursor INTO
            v_cc_id,
            v_course_id,
            v_lesson_id,
            v_title,
            v_sort_order,
            v_visible,
            v_status,
            v_created_by;

        IF v_done = 1 THEN
            LEAVE migration_loop;
        END IF;

        INSERT INTO course_activity
        (
            course_id,
            lesson_id,
            activity_type,
            title,
            description,
            sort_order,
            visible,
            status,
            created_by
        )
        VALUES
        (
            v_course_id,
            v_lesson_id,
            'contest',
            v_title,
            NULL,
            v_sort_order,
            v_visible,
            v_status,
            v_created_by
        );

        SET v_activity_id = LAST_INSERT_ID();

        INSERT INTO course_activity_contest
        (
            activity_id,
            course_contest_id
        )
        VALUES
        (
            v_activity_id,
            v_cc_id
        );

    END LOOP;

    CLOSE contest_cursor;

    SELECT COUNT(*)
    INTO v_remaining
    FROM course_contest cc
    LEFT JOIN course_activity_contest cac
        ON cac.course_contest_id = cc.id
    WHERE cac.activity_id IS NULL;

    IF v_remaining > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT =
                'Activity 이관 후 미연결 Contest가 남았습니다.';
    END IF;

    COMMIT;

    DO RELEASE_LOCK('jol_course_activity_backfill');

END$$

DELIMITER ;

CALL migrate_course_contest_activity();

DROP PROCEDURE IF EXISTS migrate_course_contest_activity;
