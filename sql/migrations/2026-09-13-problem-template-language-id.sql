ALTER TABLE problem_template
    ADD COLUMN language_id SMALLINT UNSIGNED NULL
    AFTER problem_id;

UPDATE problem_template
SET language_id =
    CASE lang
        WHEN 'C' THEN 0
        WHEN 'C++' THEN 1
        WHEN 'Pascal' THEN 2
        WHEN 'Java' THEN 3
        WHEN 'Ruby' THEN 4
        WHEN 'Bash' THEN 5
        WHEN 'Python' THEN 6
        WHEN 'PHP' THEN 7
        WHEN 'Perl' THEN 8
        WHEN 'C#' THEN 9
        WHEN 'Obj-C' THEN 10
        WHEN 'FreeBasic' THEN 11
        WHEN 'Scheme' THEN 12
        WHEN 'Clang' THEN 13
        WHEN 'Clang++' THEN 14
        WHEN 'Lua' THEN 15
        WHEN 'JavaScript' THEN 16
        WHEN 'Go' THEN 17
        WHEN 'SQL' THEN 18
        WHEN 'Fortran' THEN 19
        WHEN 'Matlab' THEN 20
        WHEN 'UnknownLanguage' THEN 21
        ELSE NULL
    END;

ALTER TABLE problem_template
    MODIFY COLUMN language_id SMALLINT UNSIGNED NOT NULL;

ALTER TABLE problem_template
    ADD UNIQUE KEY uk_problem_language_kind
        (problem_id, language_id, kind);
