-- Миграция установки таблиц модуля обратной связи LIMIS
-- Создает:
-- 1. limis_tagasiside: основная таблица хранения отзывов жителей (хранение 180 дней).
-- 2. limis_tagasiside_nonce: учёт одноразовых токенов для предотвращения повторных отправок.

CREATE TABLE limis_tagasiside (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lang ENUM('et','ru') NOT NULL,
    purpose ENUM('eservices','procurement','property','account','appointment','other') NOT NULL,
    completed ENUM('yes','partial','no','none') NOT NULL,
    ease_rating TINYINT UNSIGNED NOT NULL,
    difficulty ENUM('search','application','ui','mobile','speed','none','other') NULL,
    comment VARCHAR(500) NULL,
    page_group ENUM('home','eservices','procurement','property','account','appointment','general','other') NULL,
    CONSTRAINT chk_rating CHECK (ease_rating BETWEEN 1 AND 5),
    INDEX idx_created (created_at),
    INDEX idx_created_lang (created_at, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE limis_tagasiside_nonce (
    nonce CHAR(32) PRIMARY KEY,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at)
) ENGINE=InnoDB;
