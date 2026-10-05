ALTER TABLE course
    ADD COLUMN block_code_clipboard TINYINT(1) NOT NULL DEFAULT 0
    AFTER status;
