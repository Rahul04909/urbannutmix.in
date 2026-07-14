<?php

require_once __DIR__ . '/inc/auth_check.php';

$adminUser = $GLOBALS['admin_user'];
$pdo = Database::getConnection();
$success = '';
$error = '';

$stmt = $pdo->prepare('SELECT id, name, email, mobile, username, profile_pic, role, last_login, last_login_ip FROM admin_users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $adminUser['id']]);
$admin = $stmt->fetch();

if (!$admin) {
    Session::destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($name === '' || $email === '' || $username === '') {
            $error = 'Name, email, and username are required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $error = 'Username must be 3-50 characters (letters, numbers, underscores only).';
        } else {
            $checkStmt = $pdo->prepare('SELECT id FROM admin_users WHERE (email = :email OR username = :username) AND id != :id LIMIT 1');
            $checkStmt->execute(['email' => $email, 'username' => $username, 'id' => $admin['id']]);
            if ($checkStmt->fetch()) {
                $error = 'Email or username is already taken by another admin.';
            } else {
                $updateStmt = $pdo->prepare('UPDATE admin_users SET name = :name, email = :email, mobile = :mobile, username = :username WHERE id = :id');
                $updateStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'mobile' => $mobile,
                    'username' => $username,
                    'id' => $admin['id'],
                ]);

                $admin['name'] = $name;
                $admin['email'] = $email;
                $admin['mobile'] = $mobile;
                $admin['username'] = $username;

                $_SESSION['admin_user']['name'] = $name;
                $_SESSION['admin_user']['email'] = $email;
                $_SESSION['admin_user']['mobile'] = $mobile;
                $_SESSION['admin_user']['username'] = $username;

                $success = 'Profile updated successfully.';
            }
        }
    } elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirm password do not match.';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $error = 'Password must be between 8 and 72 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
        } else {
            $stmt = $pdo->prepare('SELECT password FROM admin_users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $admin['id']]);
            $stored = $stmt->fetch();

            if (!$stored || !password_verify($currentPassword, $stored['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $updateStmt = $pdo->prepare('UPDATE admin_users SET password = :password WHERE id = :id');
                $updateStmt->execute(['password' => $newHash, 'id' => $admin['id']]);

                $success = 'Password changed successfully.';
            }
        }
    } elseif ($action === 'update_photo') {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024;
            $file = $_FILES['profile_pic'];

            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'Image size must be less than 2MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'admin_' . $admin['id'] . '_' . time() . '.' . $ext;
                $uploadPath = __DIR__ . '/src/images/profile_picture/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $oldPic = $admin['profile_pic'];
                    $updateStmt = $pdo->prepare('UPDATE admin_users SET profile_pic = :pic WHERE id = :id');
                    $updateStmt->execute(['pic' => $filename, 'id' => $admin['id']]);

                    if ($oldPic && $oldPic !== 'default.png' && file_exists(__DIR__ . '/src/images/profile_picture/' . $oldPic)) {
                        @unlink(__DIR__ . '/src/images/profile_picture/' . $oldPic);
                    }

                    $admin['profile_pic'] = $filename;
                    $_SESSION['admin_user']['profile_pic'] = $filename;

                    $success = 'Profile picture updated successfully.';
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        } else {
            $error = 'Please select an image to upload.';
        }
    }
}

$profilePic = $admin['profile_pic'] ?? 'default.png';
$profilePicSrc = ($profilePic !== 'default.png' && file_exists(__DIR__ . '/src/images/profile_picture/' . $profilePic))
    ? './src/images/profile_picture/' . htmlspecialchars($profilePic)
    : './src/images/user-avtar.png';

include __DIR__ . '/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <img src="<?= $profilePicSrc ?>" alt="Profile Picture" class="img-circle elevation-2" style="width:150px;height:150px;object-fit:cover;border:4px solid #f4f6f9;">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($admin['name']) ?></h5>
                <p class="text-muted mb-0"><?= ucfirst(str_replace('_', ' ', $admin['role'])) ?></p>

                <hr>

                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="action" value="update_photo">
                    <div class="mb-3">
                        <label class="btn btn-outline-primary btn-sm w-100" style="cursor:pointer;">
                            <i class="fas fa-camera"></i> Change Photo
                            <input type="file" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="this.form.submit();">
                        </label>
                    </div>
                </form>

                <div class="mt-3">
                    <small class="text-muted">Member since: <?= date('M Y', strtotime($admin['id'] ?? 'now')) ?></small>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-key"></i> Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                        <small class="text-muted">Min 8 characters with uppercase, lowercase & number.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?= htmlspecialchars($admin['name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="mobile" name="mobile"
                                    value="<?= htmlspecialchars($admin['mobile'] ?? '') ?>"
                                    placeholder="+91-XXXXXXXXXX">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?= htmlspecialchars($admin['username']) ?>" required
                                    pattern="[a-zA-Z0-9_]{3,50}">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle"></i> Account Details</h3>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold" style="width:200px;">Role</td>
                        <td><span class="badge bg-<?= $admin['role'] === 'super_admin' ? 'danger' : 'info' ?>">
                            <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Last Login</td>
                        <td><?= htmlspecialchars($admin['last_login'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Last Login IP</td>
                        <td><?= htmlspecialchars($admin['last_login_ip'] ?? 'N/A') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
