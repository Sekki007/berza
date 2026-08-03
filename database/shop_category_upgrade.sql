-- Optional: relational column if migrating ads out of json_documents.
-- Current runtime stores shop_category_id on ads.json / json_documents payload.

ALTER TABLE ads
  ADD COLUMN shop_category_id VARCHAR(40) NULL AFTER shop_name,
  ADD INDEX idx_ads_shop_category (shop_category_id);
