<?php
/**
 * UrbanNutMix - User Dashboard Bootstrapper & Initialization
 */

declare(strict_types=1);

// Locate project root directory
$project_root = dirname(__DIR__);

// Load config and database files
require_once $project_root . '/admin/config/database.php';
require_once $project_root . '/admin/config/session.php';

// Start Session
Session::start();

// Calculate base URL dynamically to handle subfolders (e.g. localhost/urbannutmix.in/)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

$project_root_normalized = strtolower(str_replace('\\', '/', $project_root));
$doc_root_normalized = strtolower($doc_root);

if (strpos($project_root_normalized, $doc_root_normalized) === 0) {
    $relative_path = substr(str_replace('\\', '/', $project_root), strlen($doc_root));
} else {
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $relative_path = preg_replace('/\/user$/i', '', $script_dir);
}

$base_url = $protocol . $domain . '/' . ltrim($relative_path, '/');
$base_url = rtrim($base_url, '/\\') . '/';

if (!defined('BASE_URL')) {
    define('BASE_URL', $base_url);
}

// Database Connection
try {
    $pdo = Database::getConnection();
    
    // Self-healing database schema: Auto-create `users` table if missing
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `mobile` VARCHAR(20) NOT NULL,
            `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed password',
            `address_line1` VARCHAR(255) DEFAULT NULL,
            `address_line2` VARCHAR(255) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `state` VARCHAR(100) DEFAULT NULL,
            `pincode` VARCHAR(10) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_users_email` (`email`),
            UNIQUE KEY `uk_users_mobile` (`mobile`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (\Throwable $e) {
    error_log("User initialization database error: " . $e->getMessage());
    die("A connection error occurred. Please try again later.");
}

/**
 * Check if customer is logged in
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

/**
 * Enforce authentication: Redirect to login page if guest
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "user/login.php");
        exit;
    }
}

/**
 * Fetch current user details
 */
function get_logged_in_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\Throwable $e) {
        error_log("Failed to fetch logged in user: " . $e->getMessage());
        return null;
    }
}
