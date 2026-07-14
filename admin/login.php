<?php

declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

Session::start();

if (Session::isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals(Session::get('csrf_token', ''), $csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT id, name, email, mobile, username, password, profile_pic, role, status
                 FROM admin_users
                 WHERE (username = :username OR email = :username)
                 AND status = :status
                 LIMIT 1'
            );
            $stmt->execute([
                'username' => $username,
                'status' => 'active',
            ]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                if (password_needs_rehash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $updateStmt = $pdo->prepare('UPDATE admin_users SET password = :password WHERE id = :id');
                    $updateStmt->execute(['password' => $newHash, 'id' => $admin['id']]);
                }

                Session::regenerate();

                Session::set('admin_id', (int) $admin['id']);
                Session::set('admin_logged_in', true);
                Session::set('admin_user', [
                    'id' => (int) $admin['id'],
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'mobile' => $admin['mobile'],
                    'username' => $admin['username'],
                    'profile_pic' => $admin['profile_pic'],
                    'role' => $admin['role'],
                ]);

                $ip = Session::getClientIP();
                $updateStmt = $pdo->prepare(
                    'UPDATE admin_users SET last_login = NOW(), last_login_ip = :ip WHERE id = :id'
                );
                $updateStmt->execute(['ip' => $ip, 'id' => $admin['id']]);

                $redirect = Session::get('redirect_after_login', 'index.php');
                Session::remove('redirect_after_login');

                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'An internal error occurred. Please try again later.';
        }
    }
}

$csrf_token = bin2hex(random_bytes(32));
Session::set('csrf_token', $csrf_token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Admin Login | UrbanNutMix</title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(34, 197, 94, 0.03) 0%, transparent 50%),
                        radial-gradient(circle at 70% 50%, rgba(34, 197, 94, 0.02) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
        }

        @keyframes bgShift {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-2%, -2%) rotate(3deg); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            padding: 48px 36px 36px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-logo {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.25);
        }

        .login-logo i {
            font-size: 32px;
            color: #fff;
        }

        .login-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            font-size: 14px;
            color: #64748b;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrapper .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            font-size: 16px;
            background: none;
            border: none;
            padding: 0;
            transition: color 0.2s;
        }

        .input-wrapper .toggle-password:hover {
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #0f172a;
            background: #f8fafc;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: #22c55e;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }

        .form-control:focus ~ .input-icon {
            color: #22c55e;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        .btn-login.loading .spinner {
            display: block;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-danger i {
            font-size: 16px;
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
        }

        .login-footer p {
            font-size: 12px;
            color: #94a3b8;
        }

        .login-footer a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-leaf"></i>
                </div>
                <h1>Admin Login</h1>
                <p>Sign in to access the admin dashboard</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            placeholder="Enter your username or email"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            required
                            autofocus
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-password" id="togglePassword" tabindex="-1" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; <?= date('Y') ?> <a href="https://urbannutmix.in" target="_blank">UrbanNutMix</a>. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');

            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;

                if (!username || !password) {
                    e.preventDefault();
                    return;
                }

                loginBtn.disabled = true;
                loginBtn.classList.add('loading');
            });
        })();
    </script>
</body>
</html>
