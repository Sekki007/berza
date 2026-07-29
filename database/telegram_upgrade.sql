ALTER TABLE users
    ADD COLUMN notify_telegram TINYINT(1) NOT NULL DEFAULT 0 AFTER notify_email,
    ADD COLUMN notify_telegram_messages TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_telegram,
    ADD COLUMN notify_telegram_alerts TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_telegram_messages,
    ADD COLUMN notify_telegram_system TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_telegram_alerts,
    ADD COLUMN telegram_chat_id VARCHAR(64) NULL AFTER notify_telegram_system,
    ADD COLUMN telegram_username VARCHAR(80) NULL AFTER telegram_chat_id,
    ADD COLUMN telegram_link_code VARCHAR(16) NULL AFTER telegram_username,
    ADD COLUMN telegram_link_expires_at DATETIME NULL AFTER telegram_link_code,
    ADD COLUMN telegram_linked_at DATETIME NULL AFTER telegram_link_expires_at;
