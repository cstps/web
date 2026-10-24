-- 기존 임시 워드클라우드 테이블은 실제 데이터가 없으므로 삭제 후 새 구조로 재생성한다.

DROP TABLE IF EXISTS wordcloud_entry;
DROP TABLE IF EXISTS wordcloud_response;
DROP TABLE IF EXISTS wordcloud_room;
DROP TABLE IF EXISTS wordcloud;

CREATE TABLE wordcloud_room (
    room_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id VARCHAR(48) DEFAULT NULL,
    title VARCHAR(120) NOT NULL,
    access_code VARCHAR(32) NOT NULL,
    manage_password_hash VARCHAR(255) DEFAULT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    allow_anonymous TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id),
    UNIQUE KEY uq_wordcloud_room_code (access_code),
    KEY idx_wordcloud_room_owner (owner_user_id, status, room_id),
    KEY idx_wordcloud_room_status (status, room_id),
    CONSTRAINT chk_wordcloud_room_status
        CHECK (status IN (0, 1)),
    CONSTRAINT chk_wordcloud_room_anonymous
        CHECK (allow_anonymous IN (0, 1))
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE wordcloud_entry (
    entry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id VARCHAR(48) DEFAULT NULL,
    display_name VARCHAR(80) DEFAULT NULL,
    text_content TEXT NOT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entry_id),
    KEY idx_wordcloud_entry_room (room_id, entry_id),
    KEY idx_wordcloud_entry_user (user_id, created_at),
    CONSTRAINT fk_wordcloud_entry_room
        FOREIGN KEY (room_id)
        REFERENCES wordcloud_room (room_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_wordcloud_entry_text
        CHECK (CHAR_LENGTH(text_content) BETWEEN 1 AND 1000)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
