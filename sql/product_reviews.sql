-- ============================================================
-- UrbanNutMix – Product Reviews Table
-- ============================================================
-- Stores customer ratings, reviews and admin moderation status.
--
-- Usage:
--   mysql -u root -p urbannutmix < sql/product_reviews.sql
--   OR import via phpMyAdmin
-- ============================================================

CREATE TABLE IF NOT EXISTS `product_reviews` (
    `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `product_id`        INT UNSIGNED   NOT NULL COMMENT 'FK products.id',
    `reviewer_name`     VARCHAR(100)   NOT NULL,
    `reviewer_email`    VARCHAR(150)   NOT NULL,
    `rating`            TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 to 5 stars',
    `review_title`      VARCHAR(150)   DEFAULT NULL,
    `review_text`       TEXT           NOT NULL,
    `verified_purchase` TINYINT(1)     NOT NULL DEFAULT 1,
    `status`            ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved',
    `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_status` (`product_id`, `status`),
    CONSTRAINT `fk_reviews_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer ratings and reviews for products.';
