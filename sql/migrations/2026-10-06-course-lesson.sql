-- ============================================================
-- 1024.kr Course Lesson 계층 추가
-- 2026-10-06
--
-- 목적
-- - 기존 Course -> Contest 구조 사이에 Lesson 계층을 추가한다.
-- - 현재 활성 course_contest만 course_lesson으로 승격한다.
-- - 비활성 course_contest(status=0)는 과거 기록으로 보존하고
--   lesson_id를 연결하지 않는다.
-- - 기존 Course / Contest 동작은 그대로 유지한다.
-- ============================================================


-- ------------------------------------------------------------
-- 1. course_lesson
-- ------------------------------------------------------------

CREATE TABLE course_lesson (
    lesson_id INT NOT NULL AUTO_INCREMENT,

    course_id INT NOT NULL,
    lesson_no INT NOT NULL,

    title VARCHAR(255) NOT NULL,
    description TEXT NULL,

    start_time DATETIME NULL,
    end_time DATETIME NULL,

    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    status TINYINT(1) NOT NULL DEFAULT 1,

    created_by VARCHAR(48) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (lesson_id),

    UNIQUE KEY uk_course_lesson_no (
        course_id,
        lesson_no
    ),

    KEY idx_course_lesson_course_status (
        course_id,
        status
    ),

    KEY idx_course_lesson_sort (
        course_id,
        sort_order,
        lesson_no
    )

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;


-- ------------------------------------------------------------
-- 2. 기존 course_contest에 lesson_id 연결 컬럼 추가
--
-- NULL 허용:
-- 기존 비활성/삭제 차시는 새 Lesson으로 승격하지 않는다.
-- ------------------------------------------------------------

ALTER TABLE course_contest
ADD COLUMN lesson_id INT NULL
AFTER course_id,
ADD KEY idx_course_contest_lesson_id (lesson_id);


-- ------------------------------------------------------------
-- 3. 현재 활성 Contest 차시를 course_lesson으로 승격
--
-- 현재 조사 결과:
-- - 활성 course_contest: 30개
-- - 동일 course_id + lesson_no 중복: 0개
--
-- Contest 제목과 시간을 Lesson의 초기값으로 사용한다.
-- ------------------------------------------------------------

INSERT INTO course_lesson (
    course_id,
    lesson_no,
    title,
    description,
    start_time,
    end_time,
    sort_order,
    visible,
    status,
    created_by,
    created_at,
    updated_at
)
SELECT
    cc.course_id,
    cc.lesson_no,

    COALESCE(
        NULLIF(TRIM(c.title), ''),
        CONCAT(cc.lesson_no, '차시')
    ),

    NULL,

    c.start_time,
    c.end_time,

    cc.sort_order,
    cc.visible,
    1,

    cc.created_by,
    cc.created_at,
    cc.updated_at

FROM course_contest cc

LEFT JOIN contest c
       ON c.contest_id = cc.contest_id

WHERE cc.status = 1

ORDER BY
    cc.course_id,
    cc.lesson_no,
    cc.sort_order,
    cc.contest_id;


-- ------------------------------------------------------------
-- 4. 활성 course_contest와 새 Lesson 연결
--
-- 비활성 course_contest는 lesson_id=NULL 상태를 유지한다.
-- ------------------------------------------------------------

UPDATE course_contest cc

INNER JOIN course_lesson cl
        ON cl.course_id = cc.course_id
       AND cl.lesson_no = cc.lesson_no

SET cc.lesson_id = cl.lesson_id

WHERE cc.status = 1;


-- ------------------------------------------------------------
-- 완료
-- ------------------------------------------------------------
