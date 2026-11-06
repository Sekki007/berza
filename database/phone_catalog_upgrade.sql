-- Dodatne tabele kataloga telefona. Ne menjaju postojeće kolone oglasa.
-- Relacioni MySQL je opcion. Živi sajt i dalje čita JSON dokumente
-- (fajl ili json_documents). Pokrenuti jednom, ili preko:
--   php tools/import_phone_catalog.php --apply --sql

CREATE TABLE IF NOT EXISTS phone_spec_sources (
    id VARCHAR(80) PRIMARY KEY,
    kind VARCHAR(40) NOT NULL,
    publisher VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    url VARCHAR(255) NOT NULL,
    retrieved_at DATE NULL,
    note TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phone_models (
    id VARCHAR(80) PRIMARY KEY,
    brand VARCHAR(60) NOT NULL,
    brand_slug VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    official_name VARCHAR(160) NULL,
    slug VARCHAR(120) NOT NULL,
    source_ids_json JSON NULL,
    colors_json JSON NULL,
    UNIQUE KEY uq_phone_model_path (brand_slug, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phone_model_variants (
    model_id VARCHAR(80) NOT NULL,
    variant_id VARCHAR(40) NOT NULL,
    storage VARCHAR(20) NOT NULL,
    source_id VARCHAR(80) NULL,
    PRIMARY KEY (model_id, variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phone_model_specs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id VARCHAR(80) NOT NULL,
    group_label VARCHAR(80) NOT NULL,
    spec_label VARCHAR(120) NOT NULL,
    spec_value VARCHAR(500) NOT NULL,
    source_id VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_phone_specs_model (model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opcione kolone ako se oglasi drže i u tabeli ads. JSON skladište ih ne traži.
-- Ako kolona već postoji, preskoči tu naredbu.
-- ALTER TABLE ads ADD COLUMN phone_model_id VARCHAR(80) NULL;
-- ALTER TABLE ads ADD COLUMN phone_variant_id VARCHAR(40) NULL;
-- ALTER TABLE ads ADD COLUMN phone_storage_norm VARCHAR(20) NULL;
