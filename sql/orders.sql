-- ============================================================
-- UrbanNutMix - Orders & Order Items Tables
-- ============================================================
-- Run this SQL file to create the tables for orders and order items
-- (import AFTER sql/products.sql).
-- ============================================================

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(50) NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_email` VARCHAR(100) NOT NULL,
    `customer_mobile` VARCHAR(20) NOT NULL,
    `address_line1` VARCHAR(255) NOT NULL,
    `address_line2` VARCHAR(255) DEFAULT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(10) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'COD' COMMENT 'COD | Card | UPI',
    `payment_status` VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'pending | paid | failed | refunded',
    `order_status` VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'pending | processing | shipped | delivered | cancelled',
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shipping` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `coupon_code` VARCHAR(50) DEFAULT NULL,
    `razorpay_order_id` VARCHAR(100) DEFAULT NULL,
    `razorpay_payment_id` VARCHAR(100) DEFAULT NULL,
    `razorpay_signature` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_number` (`order_number`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_order_status` (`order_status`),
    KEY `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Quantity ordered',
    `unit` VARCHAR(20) NOT NULL DEFAULT 'gram',
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_product_id` (`product_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
