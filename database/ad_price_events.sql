-- Pokreće Sead ručno posle pregleda. PHP za sada piše JSON data/ad_price_events.json.
CREATE TABLE IF NOT EXISTS ad_price_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_id INT UNSIGNED NOT NULL,
    event ENUM('created','price_change','sold','deactivated','reactivated') NOT NULL,
    price DECIMAL(10,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    model_id VARCHAR(80) NULL,
    storage VARCHAR(40) NULL,
    condition_state VARCHAR(40) NULL,
    city VARCHAR(120) NULL,
    estimated TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_price_events_model (model_id, created_at)
);
