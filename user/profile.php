<?php
/**
 * UrbanNutMix - User Profile Settings
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Enforce login
require_login();

// Fetch current user details
$user = get_logged_in_user();
if (!$user) {
    header("Location: logout.php");
    exit;
}

$success_msg = null;
$profile_errors = [];
$password_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Details
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        // Validation
        if ($name === '') {
            $profile_errors['name'] = 'Full Name is required.';
        }
        if ($email === '') {
            $profile_errors['email'] = 'Email Address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors['email'] = 'Enter a valid email address.';
        }
        if ($mobile === '') {
            $profile_errors['mobile'] = 'Mobile Number is required.';
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $profile_errors['mobile'] = 'Enter a valid 10-digit mobile number.';
        }

        if (empty($profile_errors)) {
            try {
                // Check if email already taken
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
                $stmt->execute(['email' => $email, 'id' => $user['id']]);
                if ($stmt->fetch()) {
                    $profile_errors['email'] = 'This email is already taken by another account.';
                }

                // Check if mobile already taken
                $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = :mobile AND id != :id LIMIT 1");
                $stmt->execute(['mobile' => $mobile, 'id' => $user['id']]);
                if ($stmt->fetch()) {
                    $profile_errors['mobile'] = 'This mobile number is already taken.';
                }

                if (empty($profile_errors)) {
                    // Update user
                    $update_stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = :name, email = :email, mobile = :mobile 
                        WHERE id = :id
                    ");
                    $update_stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'mobile' => $mobile,
                        'id' => $user['id']
                    ]);

                    // Sync session details
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    // Reload user variable
                    $user = get_logged_in_user();
                    $success_msg = 'Profile details updated successfully!';
                }
            } catch (\Throwable $e) {
                error_log("Failed to update profile: " . $e->getMessage());
                $profile_errors['system'] = 'Failed to update details due to a system error.';
            }
        }
    }

    // Action 2: Change Password
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '') {
            $password_errors['current_password'] = 'Current password is required.';
        }
        if ($new_password === '') {
            $password_errors['new_password'] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $password_errors['new_password'] = 'Password must be at least 8 characters long.';
        }
        if ($new_password !== $confirm_password) {
            $password_errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($password_errors)) {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $password_errors['current_password'] = 'Current password is incorrect.';
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
                    $update_stmt->execute(['pass' => $hashed_password, 'id' => $user['id']]);

                    $success_msg = 'Password changed successfully!';
                } catch (\Throwable $e) {
                    error_log("Failed to change password: " . $e->getMessage());
                    $password_errors['system'] = 'Failed to update password due to a system error.';
                }
            }
        }
    }
}

$page_title = "Profile Settings | UrbanNutMix";
$extra_css = ['assets/css/user-dashboard.css'];
include_once '../includes/header.php';
?>

<main class="dashboard-wrapper">
    <div class="dashboard-container">
        
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <?php include_once 'sidebar.php'; ?>

            <!-- Main Content Area -->
            <div class="dashboard-content-card">
                <div class="content-title-header">
                    <h1 class="content-main-title">Profile Settings</h1>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success text-center rounded-3 p-2 font-semibold mb-4" style="font-size:0.88rem;">
                        <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Column 1: Profile Details Form -->
                    <div class="col-md-6">
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-charcoal); margin-bottom: 20px; padding-bottom: 8px; border-bottom: 1.5px solid var(--border-light);">
                            Personal Information
                        </h3>

                        <?php if (isset($profile_errors['system'])): ?>
                            <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-bottom:10px; display:block;"><?= $profile_errors['system'] ?></span>
                        <?php endif; ?>

                        <form action="profile.php" method="POST" novalidate>
                            <input type="hidden" name="action" value="update_profile">

                            <!-- Name -->
                            <div class="form-group-custom">
                                <label for="name" class="form-label-custom">Full Name *</label>
                                <input type="text" name="name" id="name" class="form-input-custom" value="<?= htmlspecialchars($user['name']) ?>" required>
                                <?php if (isset($profile_errors['name'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $profile_errors['name'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Email -->
                            <div class="form-group-custom">
                                <label for="email" class="form-label-custom">Email Address *</label>
                                <input type="email" name="email" id="email" class="form-input-custom" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <?php if (isset($profile_errors['email'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $profile_errors['email'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Mobile -->
                            <div class="form-group-custom">
                                <label for="mobile" class="form-label-custom">Mobile Number *</label>
                                <input type="tel" name="mobile" id="mobile" class="form-input-custom" value="<?= htmlspecialchars($user['mobile']) ?>" required>
                                <?php if (isset($profile_errors['mobile'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $profile_errors['mobile'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Save changes -->
                            <button type="submit" class="btn-custom mt-2" style="width: auto;">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Column 2: Change Password Form -->
                    <div class="col-md-6">
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-charcoal); margin-bottom: 20px; padding-bottom: 8px; border-bottom: 1.5px solid var(--border-light);">
                            Security &amp; Password
                        </h3>

                        <?php if (isset($password_errors['system'])): ?>
                            <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-bottom:10px; display:block;"><?= $password_errors['system'] ?></span>
                        <?php endif; ?>

                        <form action="profile.php" method="POST" novalidate>
                            <input type="hidden" name="action" value="update_password">

                            <!-- Current Password -->
                            <div class="form-group-custom">
                                <label for="current_password" class="form-label-custom">Current Password *</label>
                                <input type="password" name="current_password" id="current_password" class="form-input-custom" placeholder="Type current password" required>
                                <?php if (isset($password_errors['current_password'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $password_errors['current_password'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- New Password -->
                            <div class="form-group-custom">
                                <label for="new_password" class="form-label-custom">New Password *</label>
                                <input type="password" name="new_password" id="new_password" class="form-input-custom" placeholder="Minimum 8 characters" required>
                                <?php if (isset($password_errors['new_password'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $password_errors['new_password'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Confirm Password -->
                            <div class="form-group-custom">
                                <label for="confirm_password" class="form-label-custom">Confirm New Password *</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-input-custom" placeholder="Re-type new password" required>
                                <?php if (isset($password_errors['confirm_password'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $password_errors['confirm_password'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Save changes -->
                            <button type="submit" class="btn-custom mt-2" style="width: auto; background-color: var(--text-charcoal);">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>
</main>

<?php include_once '../includes/footer.php'; ?>
