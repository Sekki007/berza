-- Javni URL izloga odvojen od login username-a
ALTER TABLE users
  ADD COLUMN shop_slug VARCHAR(60) NULL AFTER username;

CREATE UNIQUE INDEX idx_users_shop_slug ON users (shop_slug);

-- Popuni postojeće (prilagodi ako već postoje konflikti)
UPDATE users
SET shop_slug = LOWER(REPLACE(REPLACE(REPLACE(username, '.', '-'), '_', '-'), ' ', '-'))
WHERE shop_slug IS NULL OR shop_slug = '';
