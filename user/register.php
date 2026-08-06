<?php
/**
 * UrbanNutMix - User Registration Page
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$errors = [];
$name = '';
$email = '';
$mobile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if ($name === '') {
        $errors['name'] = 'Full Name is required.';
    }
    if ($email === '') {
        $errors['email'] = 'Email Address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($mobile === '') {
        $errors['mobile'] = 'Mobile Number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Database unique check
    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'An account with this email already exists.';
            }

            // Check if mobile already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = :mobile LIMIT 1");
            $stmt->execute(['mobile' => $mobile]);
            if ($stmt->fetch()) {
                $errors['mobile'] = 'An account with this mobile number already exists.';
            }

            // If no unique violations, proceed to create account
            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                $insert_stmt = $pdo->prepare("
                    INSERT INTO users (name, email, mobile, password)
                    VALUES (:name, :email, :mobile, :password)
                ");
                $insert_stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'password' => $hashed_password
                ]);

                $new_user_id = $pdo->lastInsertId();

                // Automatically log user in
                $_SESSION['user_id'] = (int)$new_user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_logged_in'] = true;

                // Redirect to dashboard home
                header("Location: index.php");
                exit;
            }
        } catch (\Throwable $e) {
            error_log("User registration execution error: " . $e->getMessage());
            $errors['system'] = 'A system error occurred. Please try again later.';
        }
    }
}

$page_title = "Create Account | UrbanNutMix";
$extra_css = ['assets/css/user-dashboard.css'];
include_once '../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Register to manage orders and track shipments</p>
        </div>

        <?php if (isset($errors['system'])): ?>
            <div class="alert alert-danger text-center rounded-3 p-2 font-semibold mb-3" style="font-size:0.85rem;">
                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($errors['system']) ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="auth-form" novalidate>
            <!-- Full Name -->
            <div class="form-group-custom">
                <label for="name" class="form-label-custom">Full Name *</label>
                <input type="text" name="name" id="name" class="form-input-custom" value="<?= htmlspecialchars($name) ?>" placeholder="e.g. Rahul Dhiman" required>
                <?php if (isset($errors['name'])): ?>
                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['name'] ?></span>
                <?php endif; ?>
            </div>

            <!-- Email Address -->
            <div class="form-group-custom">
                <label for="email" class="form-label-custom">Email Address *</label>
                <input type="email" name="email" id="email" class="form-input-custom" value="<?= htmlspecialchars($email) ?>" placeholder="e.g. name@domain.com" required>
                <?php if (isset($errors['email'])): ?>
                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['email'] ?></span>
                <?php endif; ?>
            </div>

            <!-- Mobile Number -->
            <div class="form-group-custom">
                <label for="mobile" class="form-label-custom">Mobile Number *</label>
                <input type="tel" name="mobile" id="mobile" class="form-input-custom" value="<?= htmlspecialchars($mobile) ?>" placeholder="10-digit phone number" required>
                <?php if (isset($errors['mobile'])): ?>
                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['mobile'] ?></span>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group-custom">
                <label for="password" class="form-label-custom">Password *</label>
                <input type="password" name="password" id="password" class="form-input-custom" placeholder="Minimum 8 characters" required>
                <?php if (isset($errors['password'])): ?>
                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['password'] ?></span>
                <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="form-group-custom">
                <label for="confirm_password" class="form-label-custom">Confirm Password *</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-input-custom" placeholder="Re-type password" required>
                <?php if (isset($errors['confirm_password'])): ?>
                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['confirm_password'] ?></span>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-custom mt-2">
                <i class="fas fa-user-plus"></i> Sign Up
            </button>
        </form>

        <div class="text-center mt-4">
            <span style="font-size:0.88rem; color:#6b7280;">Already have an account? <a href="login.php" style="color:var(--primary-gold); font-weight:700; text-decoration:none;">Log In</a></span>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
