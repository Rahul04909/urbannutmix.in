-- ============================================================
-- UrbanNutMix – Frontend Product Sections Table
-- ============================================================
-- Controls which product categories appear as sections on the
-- homepage (components/products.php).
--
-- Usage:
--   mysql -u root -p urbannutmix < sql/frontend_product_sections.sql
--   OR import via phpMyAdmin (hPanel → Databases → phpMyAdmin)
--
-- Run AFTER sql/product_categories.sql and sql/products.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `frontend_product_sections` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `category_id`    INT UNSIGNED   NOT NULL COMMENT 'FK product_categories.id',
    `section_title`  VARCHAR(150)   NOT NULL COMMENT 'Custom heading shown on the homepage section',
    `sort_order`     SMALLINT       NOT NULL DEFAULT 0 COMMENT 'Lower number = appears first',
    `products_limit` TINYINT UNSIGNED NOT NULL DEFAULT 8 COMMENT 'Max products shown in this section (1–16)',
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sort`   (`sort_order`, `status`),
    KEY `idx_cat`    (`category_id`),
    CONSTRAINT `fk_fps_category`
        FOREIGN KEY (`category_id`)
        REFERENCES `product_categories` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configures which category sections appear on the frontend homepage.';
