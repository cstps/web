-- 학생 공개와 다른 교사의 가져오기 허용 여부를 분리한다.
ALTER TABLE simulation_problem
    ADD COLUMN published TINYINT NOT NULL DEFAULT 0 AFTER sharing,
    ADD CONSTRAINT chk_sim_problem_published
        CHECK (published IN (0, 1)),
    ADD KEY idx_sim_problem_public (status, published, problem_id);
