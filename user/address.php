<?php
/**
 * UrbanNutMix - User Address Management
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
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');

    // Validation
    if ($address_line1 === '') {
        $errors['address_line1'] = 'Address Line 1 is required.';
    }
    if ($city === '') {
        $errors['city'] = 'City is required.';
    }
    if ($state === '') {
        $errors['state'] = 'State is required.';
    }
    if ($pincode === '') {
        $errors['pincode'] = 'Pincode is required.';
    } elseif (!preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors['pincode'] = 'Enter a valid 6-digit pincode.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET address_line1 = :line1, address_line2 = :line2, city = :city, state = :state, pincode = :pincode 
                WHERE id = :id
            ");
            $stmt->execute([
                'line1' => $address_line1,
                'line2' => $address_line2,
                'city' => $city,
                'state' => $state,
                'pincode' => $pincode,
                'id' => $user['id']
            ]);

            $success_msg = 'Shipping address updated successfully!';
            
            // Reload user details
            $user = get_logged_in_user();
        } catch (\Throwable $e) {
            error_log("Failed to update address: " . $e->getMessage());
            $errors['system'] = 'Failed to update address due to a system error. Please try again.';
        }
    }
}

$page_title = "Manage Address | UrbanNutMix";
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
                    <h1 class="content-main-title">Manage Delivery Address</h1>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success text-center rounded-3 p-2 font-semibold mb-4" style="font-size:0.88rem;">
                        <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['system'])): ?>
                    <div class="alert alert-danger text-center rounded-3 p-2 font-semibold mb-4" style="font-size:0.88rem;">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($errors['system']) ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Column 1: Current Address display card -->
                    <div class="col-md-5">
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-charcoal); margin-bottom: 20px; padding-bottom: 8px; border-bottom: 1.5px solid var(--border-light);">
                            Current Default Address
                        </h3>

                        <div class="address-box">
                            <span class="box-tag">Default</span>
                            <div class="box-name"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="box-text">
                                <?php if (empty($user['address_line1'])): ?>
                                    <span style="font-style:italic; color:#9ca3af;">No address configured yet. Please use the form on the right to add your shipping details.</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($user['address_line1']) ?><br>
                                    <?php if (!empty($user['address_line2'])): ?>
                                        <?= htmlspecialchars($user['address_line2']) ?><br>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($user['city']) ?>, <?= htmlspecialchars($user['state']) ?> - <?= htmlspecialchars($user['pincode']) ?><br>
                                    India
                                <?php endif; ?>
                            </div>
                            
                            <div style="font-size: 0.8rem; font-weight: 600; color: #8c857e; border-top: 1px solid var(--border-light); padding-top: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($user['mobile']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Edit Address form -->
                    <div class="col-md-7">
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-charcoal); margin-bottom: 20px; padding-bottom: 8px; border-bottom: 1.5px solid var(--border-light);">
                            Update Shipping Details
                        </h3>

                        <form action="address.php" method="POST" novalidate>
                            <!-- Address Line 1 -->
                            <div class="form-group-custom">
                                <label for="address_line1" class="form-label-custom">Address Line 1 *</label>
                                <input type="text" name="address_line1" id="address_line1" class="form-input-custom" value="<?= htmlspecialchars($user['address_line1'] ?? '') ?>" placeholder="Flat/House No., Building, Street Name" required>
                                <?php if (isset($errors['address_line1'])): ?>
                                    <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['address_line1'] ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Address Line 2 -->
                            <div class="form-group-custom">
                                <label for="address_line2" class="form-label-custom">Area, Sector, Locality (Optional)</label>
                                <input type="text" name="address_line2" id="address_line2" class="form-input-custom" value="<?= htmlspecialchars($user['address_line2'] ?? '') ?>" placeholder="Apartment, unit, landmark etc.">
                            </div>

                            <div class="row">
                                <!-- City -->
                                <div class="col-md-4">
                                    <div class="form-group-custom">
                                        <label for="city" class="form-label-custom">City *</label>
                                        <input type="text" name="city" id="city" class="form-input-custom" value="<?= htmlspecialchars($user['city'] ?? '') ?>" placeholder="City" required>
                                        <?php if (isset($errors['city'])): ?>
                                            <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['city'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- State -->
                                <div class="col-md-4">
                                    <div class="form-group-custom">
                                        <label for="state" class="form-label-custom">State *</label>
                                        <input type="text" name="state" id="state" class="form-input-custom" value="<?= htmlspecialchars($user['state'] ?? '') ?>" placeholder="State" required>
                                        <?php if (isset($errors['state'])): ?>
                                            <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['state'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Pincode -->
                                <div class="col-md-4">
                                    <div class="form-group-custom">
                                        <label for="pincode" class="form-label-custom">Pincode *</label>
                                        <input type="text" name="pincode" id="pincode" class="form-input-custom" value="<?= htmlspecialchars($user['pincode'] ?? '') ?>" placeholder="6-digit ZIP" maxlength="6" required>
                                        <?php if (isset($errors['pincode'])): ?>
                                            <span style="color:#ef4444; font-size:0.75rem; font-weight:600; margin-top:4px; display:block;"><?= $errors['pincode'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit button -->
                            <button type="submit" class="btn-custom mt-2" style="width: auto;">
                                <i class="fas fa-save"></i> Save Address
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>
</main>

<?php include_once '../includes/footer.php'; ?>
