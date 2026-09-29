-- 출제자 표시 문구와 p{pid} 관리 권한을 분리한다.
ALTER TABLE problem
    ADD COLUMN creator VARCHAR(200)
        NOT NULL DEFAULT '';

-- 기존 수정 화면에 표시되던 활성 p{pid} 보유자 값을 보존한다.
-- 존재하지 않는 계정 문자열도 표시 문구로만 복사한다.
-- privilege 행은 수정하지 않는다.
UPDATE problem AS problem_item
INNER JOIN privilege AS grant_item
    ON grant_item.rightstr =
       CONCAT('p', problem_item.problem_id)
   AND grant_item.defunct = 'N'
SET problem_item.creator =
    grant_item.user_id
WHERE problem_item.creator = '';
