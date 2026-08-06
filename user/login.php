<?php
/**
 * UrbanNutMix - User Login Page
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Both fields are required.';
    } else {
        try {
            // Support logging in via Email or Mobile number
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE email = :id OR mobile = :id 
                LIMIT 1
            ");
            $stmt->execute(['id' => $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Set session details
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_logged_in'] = true;

                // Redirect to dashboard home
                header("Location: index.php");
                exit;
            } else {
                $error = 'Invalid email/mobile number or password.';
            }
        } catch (\Throwable $e) {
            error_log("User login verification database error: " . $e->getMessage());
            $error = 'A database connection error occurred. Please try again later.';
        }
    }
}

$page_title = "Log In | UrbanNutMix";
$extra_css = ['assets/css/user-dashboard.css'];
include_once '../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Log in to your account to check order status</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center rounded-3 p-2 font-semibold mb-3" style="font-size:0.85rem;">
                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="auth-form" novalidate>
            <!-- Email / Mobile -->
            <div class="form-group-custom">
                <label for="identifier" class="form-label-custom">Email or Mobile Number *</label>
                <input type="text" name="identifier" id="identifier" class="form-input-custom" value="<?= htmlspecialchars($identifier) ?>" placeholder="e.g. name@domain.com or 10-digit number" required>
            </div>

            <!-- Password -->
            <div class="form-group-custom">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="password" class="form-label-custom" style="margin-bottom: 0;">Password *</label>
                    <a href="javascript:void(0)" style="color:var(--primary-gold); font-size:0.75rem; font-weight:700; text-decoration:none;">Forgot?</a>
                </div>
                <input type="password" name="password" id="password" class="form-input-custom" placeholder="Enter your password" required>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-custom mt-2">
                <i class="fas fa-sign-in-alt"></i> Log In
            </button>
        </form>

        <div class="text-center mt-4">
            <span style="font-size:0.88rem; color:#6b7280;">New to UrbanNutMix? <a href="register.php" style="color:var(--primary-gold); font-weight:700; text-decoration:none;">Sign Up</a></span>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
