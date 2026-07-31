-- ============================================================
-- UrbanNutMix - Products & Product Images Tables
-- ============================================================
-- Run this SQL file to create the products and product_images
-- tables (import AFTER sql/product_categories.sql).
--
-- Usage:
--   mysql -u root -p urbannutmix < sql/products.sql
--   OR import via phpMyAdmin (hPanel -> Databases -> phpMyAdmin)
-- ============================================================

-- -----------------------------------------------------------
-- Table: products
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK product_categories.id; NULL = uncategorized',
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(255) NOT NULL COMMENT 'URL friendly name, auto-generated',
    `image` VARCHAR(255) NOT NULL DEFAULT 'default.png' COMMENT 'Main image, relative to admin/src/images/products/',
    `short_description` TEXT COMMENT 'Quick summary shown on cards/listings',
    `description` LONGTEXT COMMENT 'Full description (Trumbowyg editor)',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Selling price in INR',
    `mrp` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'MRP in INR; discount = (mrp - price)/mrp * 100, 0 = no discount',
    `unit` VARCHAR(20) NOT NULL DEFAULT 'gram' COMMENT 'gram | kg | piece | packet | box',
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Stock quantity for the selected unit',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO; empty = auto from product name',
    `meta_description` TEXT COMMENT 'SEO; empty = auto from short description',
    `meta_keywords` VARCHAR(255) DEFAULT NULL COMMENT 'SEO keywords',
    `og_title` VARCHAR(255) DEFAULT NULL COMMENT 'Open Graph; empty = auto from meta title',
    `og_description` TEXT COMMENT 'Open Graph; empty = auto from meta description',
    `og_image` VARCHAR(255) DEFAULT NULL COMMENT 'Open Graph image; empty = auto from main image',
    `schema_json` TEXT COMMENT 'Custom Product JSON-LD; empty = auto-generated on save',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_category` (`category_id`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
        REFERENCES `product_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- For databases created BEFORE the mrp column existed, run:
--   ALTER TABLE `products` ADD COLUMN `mrp` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'MRP in INR' AFTER `price`;
-- (admin/setup.php also adds this automatically when missing)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL COMMENT 'FK products.id',
    `image` VARCHAR(255) NOT NULL COMMENT 'Relative to admin/src/images/products/',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
