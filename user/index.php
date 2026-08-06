<?php
/**
 * UrbanNutMix - User Dashboard Home
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

// Fetch stats
$totalOrders = 0;
$deliveredOrders = 0;
$activeOrders = 0;
$recentOrders = [];

try {
    // Total Orders Count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM orders 
        WHERE customer_email = :email OR customer_mobile = :mobile
    ");
    $stmt->execute(['email' => $user['email'], 'mobile' => $user['mobile']]);
    $totalOrders = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Delivered Orders Count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM orders 
        WHERE (customer_email = :email OR customer_mobile = :mobile) 
          AND order_status = 'delivered'
    ");
    $stmt->execute(['email' => $user['email'], 'mobile' => $user['mobile']]);
    $deliveredOrders = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Active (Pending/Processing/Shipped) Orders Count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM orders 
        WHERE (customer_email = :email OR customer_mobile = :mobile) 
          AND order_status NOT IN ('delivered', 'cancelled')
    ");
    $stmt->execute(['email' => $user['email'], 'mobile' => $user['mobile']]);
    $activeOrders = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Fetch 2 most recent orders
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE customer_email = :email OR customer_mobile = :mobile
        ORDER BY created_at DESC 
        LIMIT 2
    ");
    $stmt->execute(['email' => $user['email'], 'mobile' => $user['mobile']]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Populate order items and images
    foreach ($recentOrders as &$ord) {
        $itemStmt = $pdo->prepare("
            SELECT oi.*, p.image 
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
        ");
        $itemStmt->execute(['order_id' => $ord['id']]);
        $ord['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($ord);

} catch (\Throwable $e) {
    error_log("Failed to fetch dashboard home statistics: " . $e->getMessage());
}

// Function to resolve status badge styling
function get_status_class(string $status): string {
    return match (strtolower($status)) {
        'pending' => 'bg-warning text-dark',
        'processing' => 'bg-info text-dark',
        'shipped' => 'bg-primary',
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary'
    };
}

// Resolve product image source path
if (!function_exists('get_product_img_src')) {
    function get_product_img_src(?string $imgName): string {
        if (empty($imgName) || $imgName === 'default.png') {
            return BASE_URL . 'assets/images/logo-bg.jpg';
        }
        $physPath = dirname(__DIR__) . '/admin/src/images/products/' . $imgName;
        if (file_exists($physPath)) {
            return BASE_URL . 'admin/src/images/products/' . rawurlencode($imgName);
        }
        return BASE_URL . 'assets/images/logo-bg.jpg';
    }
}

$page_title = "My Dashboard | UrbanNutMix";
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
                <div class="content-title-header" style="border-bottom: none; margin-bottom: 10px;">
                    <span style="font-size: 0.9rem; color: #8c857e; font-weight: 600; text-transform: uppercase;">Overview</span>
                    <h1 class="content-main-title" style="margin-top: 4px; font-size: 1.6rem;">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
                </div>

                <!-- Stats widgets -->
                <div class="stats-grid">
                    <!-- Widget 1: Total orders -->
                    <div class="stat-widget">
                        <div class="stat-info">
                            <span class="stat-label">Total Orders</span>
                            <h4 class="stat-value"><?= $totalOrders ?></h4>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>

                    <!-- Widget 2: Active orders -->
                    <div class="stat-widget">
                        <div class="stat-info">
                            <span class="stat-label">In Transit</span>
                            <h4 class="stat-value"><?= $activeOrders ?></h4>
                        </div>
                        <div class="stat-icon-box" style="background-color:#e0f2fe; color:#0284c7;">
                            <i class="fas fa-truck"></i>
                        </div>
                    </div>

                    <!-- Widget 3: Delivered orders -->
                    <div class="stat-widget">
                        <div class="stat-info">
                            <span class="stat-label">Delivered</span>
                            <h4 class="stat-value"><?= $deliveredOrders ?></h4>
                        </div>
                        <div class="stat-icon-box" style="background-color:#dcfce7; color:#16a34a;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders Section -->
                <div class="orders-section-header" style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:20px; border-bottom:1.5px solid var(--border-light); padding-bottom:10px;">
                    <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-charcoal); margin:0;">Recent Orders</h2>
                    <a href="orders.php" style="color:var(--primary-gold); font-weight:700; font-size:0.85rem; text-decoration:none;">View All Orders &rarr;</a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <!-- Empty Orders State -->
                    <div style="text-align:center; padding:40px 20px; border:1px dashed var(--border-light); border-radius:10px; background-color:#fafaf9; margin-bottom:30px;">
                        <div style="font-size: 2.2rem; color:#d1d5db; margin-bottom:12px;"><i class="fas fa-box-open"></i></div>
                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-charcoal); margin-bottom:4px;">No orders found</h4>
                        <p style="font-size:0.8rem; color:#9ca3af; margin-bottom:16px;">You haven't placed any orders yet. Explore our healthy dry fruits!</p>
                        <a href="<?= BASE_URL ?>shop.php" class="btn-custom btn-sm" style="display:inline-flex; width:auto; padding:8px 18px; font-size:0.85rem;">Shop Now</a>
                    </div>
                <?php else: ?>
                    <!-- Orders Cards list -->
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="order-card">
                            <!-- Card Header -->
                            <div class="order-header-band">
                                <div>
                                    <div class="band-info-label">Order Placed</div>
                                    <div class="band-info-value"><?= date('d M Y', strtotime($order['created_at'])) ?></div>
                                </div>
                                <div>
                                    <div class="band-info-label">Total Amount</div>
                                    <div class="band-info-value" style="color: var(--primary-gold);">₹<?= number_format((float)$order['grand_total'], 2) ?></div>
                                </div>
                                <div>
                                    <div class="band-info-label">Order Status</div>
                                    <div><span class="badge <?= get_status_class($order['order_status']) ?>"><?= ucfirst(htmlspecialchars($order['order_status'])) ?></span></div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="band-info-label">Order ID</div>
                                    <div class="band-info-value" style="font-family:monospace;"><?= htmlspecialchars($order['order_number']) ?></div>
                                </div>
                            </div>

                            <!-- Card Items -->
                            <div class="order-items-band">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item-row">
                                        <div class="order-item-info">
                                            <img src="<?= get_product_img_src($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="order-item-thumbnail">
                                            <div>
                                                <h4 class="order-item-title"><?= htmlspecialchars($item['product_name']) ?></h4>
                                                <div class="order-item-meta">Qty: <?= (int)$item['quantity'] ?> • Size: <?= htmlspecialchars($item['unit']) ?></div>
                                            </div>
                                        </div>
                                        <span class="order-item-price">₹<?= number_format((float)$item['total_price'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Quick Actions / Cards Grid -->
                <div class="quick-settings-section" style="margin-top: 40px;">
                    <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-charcoal); margin-bottom:20px; border-bottom:1.5px solid var(--border-light); padding-bottom:10px;">Quick Shortcuts</h2>
                    
                    <div class="address-grid">
                        <!-- Profile update shortcut -->
                        <a href="profile.php" style="text-decoration:none;" class="address-box">
                            <span class="box-tag">Account</span>
                            <div class="box-name"><i class="fas fa-user-cog" style="color:var(--primary-gold); margin-right:6px;"></i> Edit Profile</div>
                            <div class="box-text" style="margin-bottom:0;">Update your name, registered email address, mobile number, or change your account password.</div>
                        </a>

                        <!-- Address update shortcut -->
                        <a href="address.php" style="text-decoration:none;" class="address-box">
                            <span class="box-tag">Shipping</span>
                            <div class="box-name"><i class="fas fa-map-marker-alt" style="color:var(--primary-gold); margin-right:6px;"></i> Manage Address</div>
                            <div class="box-text" style="margin-bottom:0;">Set and manage your delivery details, pincode, flat/house number, city, and state parameters.</div>
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</main>

<?php include_once '../includes/footer.php'; ?>
