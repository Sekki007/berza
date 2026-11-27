-- Pokreće Sead ručno. Ne menja postojeće redove osim što dodaje kolonu.
ALTER TABLE ads
    ADD COLUMN sold_at DATETIME NULL DEFAULT NULL AFTER is_sold;
