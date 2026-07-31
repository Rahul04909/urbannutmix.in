<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

class Database
{
    private static ?PDO $instance = null;

    private static function getConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'dbname' => $_ENV['DB_NAME'] ?? 'urbannutmix',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ];
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = self::getConfig();
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ];
            self::$instance = new PDO($dsn, $config['username'], $config['password'], $options);
        }
        return self::$instance;
    }

    public static function getConnectionNoDB(): PDO
    {
        $config = self::getConfig();
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function healthCheck(): array
    {
        $result = [
            'mysql' => false,
            'database' => false,
            'table' => false,
            'users' => false,
            'login_query' => false,
            'profile_query' => false,
            'categories' => false,
            'error' => '',
        ];

        try {
            $config = self::getConfig();
            $pdo = self::getConnectionNoDB();
            $result['mysql'] = true;

            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($config['dbname']));
            $result['database'] = (bool) $stmt->fetch();

            if ($result['database']) {
                $pdo->exec("USE `{$config['dbname']}`");
                $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
                $result['table'] = (bool) $stmt->fetch();

                if ($result['table']) {
                    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM admin_users");
                    $row = $stmt->fetch();
                    $result['users'] = ((int)$row['cnt']) > 0;

                    $stmt = $pdo->prepare(
                        'SELECT id, username FROM admin_users
                         WHERE (username = :username OR email = :email)
                         AND status = :status
                         LIMIT 1'
                    );
                    $stmt->execute(['username' => 'admin', 'email' => 'admin', 'status' => 'active']);
                    $result['login_query'] = true;

                    $stmt = $pdo->prepare(
                        'SELECT id, name, email, mobile, username, profile_pic, role, last_login, last_login_ip
                         FROM admin_users WHERE id = :id LIMIT 1'
                    );
                    $stmt->execute(['id' => 1]);
                    $result['profile_query'] = true;
                }

                $stmt = $pdo->query("SHOW TABLES LIKE 'product_categories'");
                $result['categories'] = (bool) $stmt->fetch();
            }
        } catch (PDOException $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public static function close(): void
    {
        self::$instance = null;
    }
}
