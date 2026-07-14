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
$showSetupLink = false;

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
            $errorMsg = $e->getMessage();
            error_log('Login error: ' . $errorMsg);

            if (str_contains($errorMsg, 'Connection refused') || str_contains($errorMsg, 'can\'t connect') || str_contains($errorMsg, '2002')) {
                $error = 'Database server is not reachable. Please contact the administrator.';
            } elseif (str_contains($errorMsg, 'Unknown database') || str_contains($errorMsg, '1049')) {
                $error = 'Database not found. Please run setup.';
                $showSetupLink = true;
            } elseif (str_contains($errorMsg, 'Access denied') || str_contains($errorMsg, '1045')) {
                $error = 'Database credentials are incorrect. Please check your .env configuration.';
            } elseif (str_contains($errorMsg, 'Base table or view not found') || str_contains($errorMsg, '1146')) {
                $error = 'Admin table not found. Please run setup.';
                $showSetupLink = true;
            } else {
                $error = 'An internal error occurred. Please try again later.';
            }
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
    <title>Admin Login | UrbanNutMix</title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            display: flex;
            width: 900px;
            max-width: 95vw;
            min-height: 560px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* Left Image Panel */
        .login-image-panel {
            flex: 0 0 50%;
            position: relative;
            background: #1a1a2e;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            min-height: 560px;
        }

        .login-image-panel img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .login-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.2) 100%);
            z-index: 1;
        }

        .login-image-text {
            position: relative;
            z-index: 2;
            text-align: center;
            color: #fff;
            padding: 40px;
        }

        .login-image-text h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .login-image-text p {
            font-size: 14px;
            opacity: 0.85;
            line-height: 1.6;
            max-width: 320px;
            margin: 0 auto;
        }

        .login-image-text .brand-tag {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        /* Right Form Panel */
        .login-form-panel {
            flex: 0 0 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 44px;
        }

        .login-form-panel .logo-area {
            text-align: center;
            margin-bottom: 8px;
        }

        .login-form-panel .logo-area img {
            height: 48px;
            width: auto;
            margin-bottom: 6px;
        }

        .login-form-panel .logo-area h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -0.3px;
        }

        .login-form-panel .logo-area p {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
        }

        .login-form-panel .divider {
            display: flex;
            align-items: center;
            margin: 20px 0 24px;
        }

        .login-form-panel .divider::before,
        .login-form-panel .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .login-form-panel .divider span {
            padding: 0 16px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #9ca3af;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
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
            color: #9ca3af;
            font-size: 15px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1.5px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
            background: #f9fafb;
            transition: all 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: #22c55e;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .form-control:focus ~ .input-icon {
            color: #22c55e;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 16px;
            background: none;
            border: none;
            padding: 4px;
            transition: color 0.2s;
            line-height: 1;
        }

        .toggle-password:hover {
            color: #374151;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: #22c55e;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 4px;
        }

        .btn-login:hover {
            background: #16a34a;
        }

        .btn-login:active {
            transform: scale(0.98);
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
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-login.loading .btn-text { display: none; }
        .btn-login.loading .spinner { display: block; }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
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
            font-size: 15px;
            flex-shrink: 0;
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
        }

        .login-footer p {
            font-size: 12px;
            color: #9ca3af;
        }

        .login-footer a {
            color: #22c55e;
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .setup-link {
            text-align: center;
            margin-top: 12px;
        }

        .setup-link a {
            font-size: 12px;
            color: #6b7280;
            text-decoration: none;
        }

        .setup-link a:hover {
            color: #22c55e;
        }

        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 420px;
            }
            .login-image-panel {
                flex: 0 0 200px;
                min-height: 200px;
            }
            .login-image-panel img {
                position: relative;
            }
            .login-form-panel {
                padding: 32px 28px;
            }
            .login-image-text h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- Left: Image Panel -->
    <div class="login-image-panel">
        <img
            src="https://ministryofnuts.in/cdn/shop/files/About-Us-GIF_ec327cb1-9e10-4610-af1e-186cbba04482_900x.gif?v=1654513490"
            alt="Premium Dry Fruits"
            loading="lazy"
        >
        <div class="login-image-overlay"></div>
        <div class="login-image-text">
            <div class="brand-tag">Premium Quality</div>
            <h2>Welcome Back</h2>
            <p>Access your admin dashboard to manage products, orders, and more.</p>
        </div>
    </div>

    <!-- Right: Form Panel -->
    <div class="login-form-panel">
        <div class="logo-area">
            <img src="../favicon.ico" alt="UrbanNutMix" onerror="this.style.display='none'">
            <h1>UrbanNutMix</h1>
            <p>Admin Dashboard</p>
        </div>

        <div class="divider"><span>Sign In</span></div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($showSetupLink): ?>
            <div class="setup-link">
                <a href="setup.php">&rarr; Run Database Setup</a>
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
                        placeholder="Enter username or email"
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
                        placeholder="Enter password"
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
