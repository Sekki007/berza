-- Uski brojači pregleda. Ne dira json_documents.
-- PHP ih pravi sam (config/ad_stats.php) i jednom prepisuje ads.json + ad_stats.json.
-- Ručno, ako treba pre prvog requesta:
--   mysql berza < database/ad_counter_upgrade.sql
--   php tools/migrate_ad_counters.php

CREATE TABLE IF NOT EXISTS ad_counter (
    ad_id INT UNSIGNED NOT NULL PRIMARY KEY,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    phone_reveals INT UNSIGNED NOT NULL DEFAULT 0,
    messages_started INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_counter_daily (
    ad_id INT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    phone_reveals INT UNSIGNED NOT NULL DEFAULT 0,
    messages_started INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ad_id, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_counter_meta (
    meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
    meta_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
