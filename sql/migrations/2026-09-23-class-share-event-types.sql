ALTER TABLE class_share_event
    ADD COLUMN event_type VARCHAR(32)
        CHARACTER SET ascii
        COLLATE ascii_general_ci
        NOT NULL
        DEFAULT 'class_share'
        COMMENT 'class_share, school_event, briefing, experience, training, other'
        AFTER academic_year,
    ADD COLUMN application_mode
        ENUM('none', 'event', 'program')
        NOT NULL
        DEFAULT 'program'
        COMMENT 'none=안내, event=행사 직접 신청, program=세부 프로그램 신청'
        AFTER event_type;
