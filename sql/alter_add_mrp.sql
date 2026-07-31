-- ------------------------------------------------------------------------
-- UrbanNutMix - Add MRP column to `products` table (for existing databases)
--
-- Usage: run this once in phpMyAdmin (Import) or via MySQL CLI on the
--        `mineib_i1_urbannutmix` database.
--
-- This is safe to run multiple times: if the column already exists,
-- the ALTER is simply skipped.
--
-- NOTE: admin/setup.php ("Create All Tables" button) also adds this
--       column automatically, so this file is only needed when you
--       want to do it manually.
-- ------------------------------------------------------------------------

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'mrp'
);

SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `products`
     ADD COLUMN `mrp` DECIMAL(10,2) NOT NULL DEFAULT 0.00
     COMMENT ''MRP in INR; discount = (mrp - price)/mrp * 100, 0 = no discount''
     AFTER `price`',
    'SELECT ''mrp column already exists - nothing to do'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
