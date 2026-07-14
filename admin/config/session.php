<?php

declare(strict_types=1);

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? 7200);

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => ($_ENV['SESSION_SECURE_COOKIE'] ?? 'true') === 'true',
                'httponly' => ($_ENV['SESSION_HTTPONLY'] ?? 'true') === 'true',
                'samesite' => $_ENV['SESSION_SAME_SITE'] ?? 'Strict',
            ]);

            session_start();

            if (!isset($_SESSION['_CREATED'])) {
                $_SESSION['_CREATED'] = time();
            } elseif (time() - $_SESSION['_CREATED'] > $lifetime) {
                self::destroy();
                self::start();
            }

            if (!isset($_SESSION['_IP'])) {
                $_SESSION['_IP'] = self::getClientIP();
            }
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function isAuthenticated(): bool
    {
        return self::has('admin_id') && self::has('admin_logged_in') && self::get('admin_logged_in') === true;
    }

    public static function getClientIP(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $ip = explode(',', $ip)[0];
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
