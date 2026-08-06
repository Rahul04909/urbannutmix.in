<?php
/**
 * UrbanNutMix - User Orders History
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

$orders = [];

try {
    // Fetch all orders matching customer email or mobile
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE customer_email = :email OR customer_mobile = :mobile
        ORDER BY created_at DESC
    ");
    $stmt->execute(['email' => $user['email'], 'mobile' => $user['mobile']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Populate order items and images
    foreach ($orders as &$ord) {
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
    error_log("Failed to fetch dashboard orders history: " . $e->getMessage());
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

// Function to calculate tracking progress bar width
function get_tracking_progress_width(string $status): int {
    return match (strtolower($status)) {
        'pending' => 0,
        'processing' => 33,
        'shipped' => 66,
        'delivered' => 100,
        default => 0
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

$page_title = "My Orders | UrbanNutMix";
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
                    <h1 class="content-main-title">Order History</h1>
                </div>

                <?php if (empty($orders)): ?>
                    <!-- Empty Orders State -->
                    <div style="text-align:center; padding:60px 20px; border:1px dashed var(--border-light); border-radius:10px; background-color:#fafaf9;">
                        <div style="font-size: 3.5rem; color:#d1d5db; margin-bottom:15px;"><i class="fas fa-box-open"></i></div>
                        <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-charcoal); margin-bottom:8px;">You haven't ordered anything yet</h3>
                        <p style="font-size:0.88rem; color:#6b7280; margin-bottom:20px; max-width:400px; margin-left:auto; margin-right:auto;">Explore our handpicked premium nuts, seeds, and dry fruits. Add them to your cart to experience health and crunchiness!</p>
                        <a href="<?= BASE_URL ?>shop.php" class="btn-custom" style="display:inline-flex; width:auto; padding:12px 28px;">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <!-- Orders Cards list -->
                    <?php foreach ($orders as $order): 
                        $status = strtolower($order['order_status']);
                        $isCancelled = ($status === 'cancelled');
                        $progressWidth = get_tracking_progress_width($status);
                    ?>
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

                            <!-- Tracking Timeline / Progress Stepper -->
                            <?php if ($isCancelled): ?>
                                <div class="order-tracking-timeline" style="background-color: #fef2f2; border-top: 1px solid #fecaca;">
                                    <div style="display:flex; align-items:center; gap:8px; color:#ef4444; font-size:0.82rem; font-weight:600;">
                                        <i class="fas fa-times-circle"></i> This order was cancelled. Please feel free to re-add items to your cart to retry checkout.
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="order-tracking-timeline">
                                    <div class="stepper-timeline">
                                        <div class="stepper-progress" style="width: <?= $progressWidth ?>%;"></div>
                                        
                                        <!-- Step 1: Placed -->
                                        <div class="stepper-step completed">
                                            <div class="step-dot"><i class="fas fa-check"></i></div>
                                            <div class="step-label">Ordered</div>
                                        </div>

                                        <!-- Step 2: Processing -->
                                        <div class="stepper-step <?= ($progressWidth >= 33) ? 'completed' : (($status === 'pending') ? 'active' : '') ?>">
                                            <div class="step-dot">
                                                <?php if ($progressWidth >= 33): ?><i class="fas fa-check"></i><?php else: ?>2<?php endif; ?>
                                            </div>
                                            <div class="step-label">Processing</div>
                                        </div>

                                        <!-- Step 3: Shipped -->
                                        <div class="stepper-step <?= ($progressWidth >= 66) ? 'completed' : (($status === 'processing') ? 'active' : '') ?>">
                                            <div class="step-dot">
                                                <?php if ($progressWidth >= 66): ?><i class="fas fa-check"></i><?php else: ?>3<?php endif; ?>
                                            </div>
                                            <div class="step-label">Shipped</div>
                                        </div>

                                        <!-- Step 4: Delivered -->
                                        <div class="stepper-step <?= ($progressWidth === 100) ? 'completed' : (($status === 'shipped') ? 'active' : '') ?>">
                                            <div class="step-dot">
                                                <?php if ($progressWidth === 100): ?><i class="fas fa-check"></i><?php else: ?>4<?php endif; ?>
                                            </div>
                                            <div class="step-label">Delivered</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

    </div>
</main>

<?php include_once '../includes/footer.php'; ?>
