-- ============================================================
-- UrbanNutMix - Admin Users Table & Default Credentials
-- ============================================================
-- Run this SQL file to create the admin_users table and insert
-- the default administrator account.
--
-- Usage:
--   mysql -u root -p urbannutmix < sql/admin_users.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `urbannutmix` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `urbannutmix`;

-- -----------------------------------------------------------
-- Table: admin_users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed password',
    `profile_pic` VARCHAR(255) DEFAULT 'default.png' COMMENT 'Relative path from admin/src/images/profile_picture/',
    `role` ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Default Admin Account
-- -----------------------------------------------------------
-- Username: admin
-- Password: Admin@123456
-- IMPORTANT: Change the default password immediately after first login!
-- -----------------------------------------------------------
INSERT INTO `admin_users` (`name`, `email`, `mobile`, `username`, `password`, `profile_pic`, `role`, `status`)
VALUES (
    'Rahul Dhiman',
    'rahul@urbannutmix.in',
    '+91-8059982049',
    'admin',
    '$2y$12$UihXrjeHEqPmkCR4uAt1o.zfFVQwECjYUGwQnzeVlI9zS9T3NmTy2',
    'default.png',
    'super_admin',
    'active'
);
-- Generated password hash for: Admin@123456
-- To generate a new hash in PHP: echo password_hash('your-password', PASSWORD_BCRYPT, ['cost' => 12]);
