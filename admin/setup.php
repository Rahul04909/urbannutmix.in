<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'urbannutmix';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbname}`");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admin_users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `username` VARCHAR(50) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `profile_pic` VARCHAR(255) DEFAULT 'default.png',
                `role` ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
                `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                `last_login` DATETIME DEFAULT NULL,
                `last_login_ip` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_email` (`email`),
                UNIQUE KEY `uk_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM admin_users");
        $row = $stmt->fetch();

        if ((int)$row['cnt'] === 0) {
            $hash = password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("
                INSERT INTO `admin_users` (`name`, `email`, `mobile`, `username`, `password`, `profile_pic`, `role`, `status`)
                VALUES (:name, :email, :mobile, :username, :password, :profile_pic, :role, :status)
            ")->execute([
                'name' => 'Rahul Dhiman',
                'email' => 'rahul@urbannutmix.in',
                'mobile' => '+91-8059982049',
                'username' => 'admin',
                'password' => $hash,
                'profile_pic' => 'default.png',
                'role' => 'super_admin',
                'status' => 'active',
            ]);
            $message = 'Setup complete! Default admin account created.';
        } else {
            $message = 'Setup complete! Admin users already exist.';
        }

        $message .= ' <a href="login.php" style="color:#22c55e;font-weight:600;">Proceed to Login &rarr;</a>';
    } catch (PDOException $e) {
        $error = 'Setup failed: ' . $e->getMessage();
    }
}

$status = Database::testConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup | UrbanNutMix</title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 560px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .status-dot.ok { background: #22c55e; }
        .status-dot.fail { background: #ef4444; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="text-center mb-4">
            <h4 class="fw-bold">UrbanNutMix — Setup</h4>
            <p class="text-muted">Database &amp; Admin Configuration</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="mb-4">
            <h6 class="fw-semibold mb-3">System Status</h6>
            <table class="table table-borderless table-sm">
                <tr>
                    <td><span class="status-dot <?= $status['connected'] ? 'ok' : 'fail' ?>"></span> MySQL Connection</td>
                    <td><?= $status['connected'] ? 'Connected' : 'Failed' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['db_exists'] ? 'ok' : 'fail' ?>"></span> Database <code><?= htmlspecialchars($dbname) ?></code></td>
                    <td><?= $status['db_exists'] ? 'Exists' : 'Not Found' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['table_exists'] ? 'ok' : 'fail' ?>"></span> Table <code>admin_users</code></td>
                    <td><?= $status['table_exists'] ? 'Exists' : 'Not Found' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['has_users'] ? 'ok' : ($status['table_exists'] ? 'fail' : 'fail') ?>"></span> Admin Users</td>
                    <td><?= $status['has_users'] ? 'Found' : 'None' ?></td>
                </tr>
            </table>
            <?php if ($status['error']): ?>
                <div class="text-danger small"><?= htmlspecialchars($status['error']) ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$status['has_users']): ?>
            <form method="POST">
                <button type="submit" name="install" value="1" class="btn btn-success w-100 py-2 fw-semibold"
                    <?= !$status['connected'] ? 'disabled' : '' ?>>
                    Run Setup (Create Database &amp; Admin)
                </button>
            </form>
            <p class="text-muted small text-center mt-2">Default login: <strong>admin</strong> / <strong>Admin@123456</strong></p>
        <?php else: ?>
            <a href="login.php" class="btn btn-success w-100 py-2 fw-semibold">Go to Login &rarr;</a>
        <?php endif; ?>
    </div>
</body>
</html>
