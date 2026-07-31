<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre>\n";

function errCode(Throwable $e): string
{
    return property_exists($e, 'errorInfo') ? (string) ($e->errorInfo[1] ?? '?') : '?';
}

try {
    $pdo = Database::getConnection();
    echo "1. DB connect: OK\n";
} catch (Throwable $e) {
    echo "1. DB connect FAILED: ", get_class($e), " [", errCode($e), "]: ", $e->getMessage(), "\n</pre>";
    exit;
}

try {
    $table = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    echo "2. Table admin_users: ", $table->fetch() ? "OK" : "MISSING", "\n";

    $stmt = $pdo->prepare(
        'SELECT id, name, email, mobile, username, password, profile_pic, role, status
         FROM admin_users
         WHERE (username = :username OR email = :email)
         AND status = :status
         LIMIT 1'
    );
    $stmt->execute(['username' => 'admin', 'email' => 'admin', 'status' => 'active']);
    $admin = $stmt->fetch();
    echo "3. Login SELECT: OK, row found: ", $admin ? "YES" : "NO", "\n";

    if ($admin) {
        echo "4. password_verify('Admin@123456'): ", password_verify('Admin@123456', $admin['password']) ? "TRUE" : "FALSE", "\n";
        echo "5. password_needs_rehash: ", password_needs_rehash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]) ? "YES" : "NO", "\n";
        echo "6. stored hash prefix: ", substr($admin['password'], 0, 7), "\n";
        echo "7. username/status in DB: ", $admin['username'], " / ", $admin['status'], "\n";

        $stmt2 = $pdo->prepare('UPDATE admin_users SET last_login = NOW(), last_login_ip = :ip WHERE id = :id');
        $stmt2->execute(['ip' => 'diag', 'id' => $admin['id']]);
        echo "8. last_login UPDATE: OK\n";
    }

    echo "\nALL DB CHECKS PASSED\n";
} catch (Throwable $e) {
    echo "EXCEPTION during login query: ", get_class($e), " [", errCode($e), "]: ", $e->getMessage(), "\n";
}

echo "</pre>\n";
