-- ============================================================
-- UrbanNutMix - Product Categories Table
-- ============================================================
-- Run this SQL file to create the product_categories table.
--
-- Usage:
--   mysql -u root -p urbannutmix < sql/product_categories.sql
--   OR import via phpMyAdmin (hPanel -> Databases -> phpMyAdmin)
-- ============================================================

-- -----------------------------------------------------------
-- Table: product_categories
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL COMMENT 'URL friendly name, auto-generated',
    `image` VARCHAR(255) NOT NULL DEFAULT 'default.png' COMMENT 'Relative path from admin/src/images/category/',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
