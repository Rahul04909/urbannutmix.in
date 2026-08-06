<?php
/**
 * UrbanNutMix - User Dashboard Sidebar Component
 */

declare(strict_types=1);

if (!isset($user) || empty($user)) {
    $user = get_logged_in_user();
}

$current_subpage = basename($_SERVER['PHP_SELF']);
$initials = '';
if ($user) {
    $nameParts = explode(' ', $user['name']);
    $initials = strtoupper(substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
    } else {
        $initials .= strtoupper(substr($user['name'], 1, 1));
    }
}
?>

<aside class="dashboard-sidebar">
    <!-- User Card info -->
    <div class="dashboard-user-card">
        <div class="user-avatar-placeholder">
            <?= htmlspecialchars($initials ?: 'U') ?>
        </div>
        <div class="user-welcome-info">
            <div class="welcome-text">Hello,</div>
            <h3 class="user-display-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
        </div>
    </div>

    <!-- Navigation links -->
    <nav aria-label="Dashboard Navigation">
        <ul class="dashboard-nav-list">
            <li>
                <a href="index.php" class="dashboard-nav-link <?= ($current_subpage === 'index.php') ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="orders.php" class="dashboard-nav-link <?= ($current_subpage === 'orders.php') ? 'active' : '' ?>">
                    <i class="fas fa-box"></i> My Orders
                </a>
            </li>
            <li>
                <a href="address.php" class="dashboard-nav-link <?= ($current_subpage === 'address.php') ? 'active' : '' ?>">
                    <i class="fas fa-map-marker-alt"></i> Manage Address
                </a>
            </li>
            <li>
                <a href="profile.php" class="dashboard-nav-link <?= ($current_subpage === 'profile.php') ? 'active' : '' ?>">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
            </li>
            <li>
                <a href="logout.php" class="dashboard-nav-link" style="margin-top: 15px; border-top: 1px solid var(--border-light); padding-top: 15px; border-radius: 0;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
</aside>
