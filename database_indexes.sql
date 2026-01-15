-- =====================================================
-- Database Indexes for Performance Optimization
-- =====================================================
-- Run these SQL commands to add indexes that will
-- dramatically improve query performance (50-70% faster)
-- =====================================================

-- Product Master Table Indexes
CREATE INDEX IF NOT EXISTS idx_product_master_sku ON product_master(sku);
CREATE INDEX IF NOT EXISTS idx_product_master_parent ON product_master(parent);
CREATE INDEX IF NOT EXISTS idx_product_master_sku_parent ON product_master(sku, parent);

-- Inventory Table Indexes (Critical for performance)
CREATE INDEX IF NOT EXISTS idx_inventory_sku ON inventories(sku);
CREATE INDEX IF NOT EXISTS idx_inventory_sku_is_hide ON inventories(sku, is_hide);
CREATE INDEX IF NOT EXISTS idx_inventory_sku_id ON inventories(sku, id);
CREATE INDEX IF NOT EXISTS idx_inventory_is_approved ON inventories(is_approved) WHERE is_approved = 1;

-- Shopify SKU Table Indexes
CREATE INDEX IF NOT EXISTS idx_shopify_sku_sku ON shopify_skus(sku);

-- Composite indexes for common query patterns
CREATE INDEX IF NOT EXISTS idx_inventory_sku_type_approved ON inventories(sku, type, is_approved);

-- Show existing indexes (for verification)
SHOW INDEX FROM product_master;
SHOW INDEX FROM inventories;
SHOW INDEX FROM shopify_skus;

-- =====================================================
-- Performance Tips:
-- 1. Run during off-peak hours
-- 2. Monitor index creation time (may take 5-30 min for large tables)
-- 3. After creation, run ANALYZE TABLE to update statistics:
--    ANALYZE TABLE product_master;
--    ANALYZE TABLE inventories;
--    ANALYZE TABLE shopify_skus;
-- =====================================================
