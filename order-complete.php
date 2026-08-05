<?php
/**
 * UrbanNutMix - Order Confirmation Screen
 * Features: Success checklist animation, estimated delivery calculation, itemized order summaries, customer address details card.
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$orderNumber = trim($_GET['order_number'] ?? '');
if ($orderNumber === '') {
    header('Location: shop.php');
    exit;
}

$page_title = "Order Placed Successfully! | UrbanNutMix";
$extra_css = ['assets/css/cart.css'];

try {
    $pdo = Database::getConnection();

    // Fetch order record
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :num LIMIT 1");
    $orderStmt->execute(['num' => $orderNumber]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: shop.php');
        exit;
    }

    // Fetch order items
    $itemsStmt = $pdo->prepare(
        "SELECT oi.*, p.image, p.slug 
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = :id"
    );
    $itemsStmt->execute(['id' => $order['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (\Throwable $e) {
    error_log("Order Complete Page Query Error: " . $e->getMessage());
    die("An error occurred loading order information. Please contact support.");
}

// Estimate delivery date range (3-5 days from now)
$orderDate = strtotime($order['created_at']);
$estMin = date('d M', strtotime('+3 days', $orderDate));
$estMax = date('d M, Y', strtotime('+5 days', $orderDate));

// Resolve image
if (!function_exists('get_product_img_src')) {
    function get_product_img_src(?string $imgName): string {
        if (empty($imgName) || $imgName === 'default.png') {
            return BASE_URL . 'assets/images/logo-bg.jpg';
        }
        $physPath = __DIR__ . '/admin/src/images/products/' . $imgName;
        if (file_exists($physPath)) {
            return BASE_URL . 'admin/src/images/products/' . rawurlencode($imgName);
        }
        return BASE_URL . 'assets/images/logo-bg.jpg';
    }
}

include_once 'includes/header.php';
?>

<main class="unm-cart-wrapper">
    <div class="unm-cart-container" style="max-width: 900px;">
        
        <!-- Tracker steps (step 3 active) -->
        <div class="unm-cart-header-zone justify-content-center">
            <div class="unm-checkout-steps">
                <div class="unm-step">
                    <span class="unm-step-num">1</span>
                    <span class="unm-step-label">Shopping Cart</span>
                </div>
                <div class="unm-step-line"></div>
                <div class="unm-step">
                    <span class="unm-step-num">2</span>
                    <span class="unm-step-label">Secure Checkout</span>
                </div>
                <div class="unm-step-line"></div>
                <div class="unm-step active">
                    <span class="unm-step-num">3</span>
                    <span class="unm-step-label">Order Confirmed</span>
                </div>
            </div>
        </div>

        <!-- Success Message Content -->
        <div class="card rounded-4 border-0 shadow-sm p-4 p-md-5 bg-white text-center mb-4">
            
            <div class="unm-success-circle mb-4 mx-auto">
                <svg viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="#28a745" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>

            <h1 class="h2 fw-bold text-dark mb-2" style="font-family: 'Source Sans Pro', sans-serif;">Thank You For Your Order!</h1>
            <p class="text-muted mb-4 fs-6">We have received your order and are processing it. A confirmation containing receipt logs will be sent to your email.</p>
            
            <div class="bg-light p-3 rounded-4 inline-block mb-5 mx-auto" style="max-width: 500px;">
                <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="letter-spacing: 0.5px;">Order Reference Number</span>
                <span class="font-monospace fw-bold text-dark fs-5"><?= htmlspecialchars($order['order_number']) ?></span>
            </div>

            <!-- Grid Order details -->
            <div class="row text-start g-4">
                
                <!-- Shipping details Summary -->
                <div class="col-md-6">
                    <div class="border rounded-4 p-4 h-100 bg-white shadow-none">
                        <h3 class="h6 fw-bold text-dark mb-3"><i class="fas fa-shipping-fast text-primary me-2"></i> Shipping Address</h3>
                        <p class="mb-1 fw-semibold text-dark"><?= htmlspecialchars($order['customer_name']) ?></p>
                        <p class="mb-1 text-muted"><?= htmlspecialchars($order['address_line1']) ?></p>
                        <?php if ($order['address_line2'] !== ''): ?>
                            <p class="mb-1 text-muted"><?= htmlspecialchars($order['address_line2']) ?></p>
                        <?php endif; ?>
                        <p class="mb-1 text-muted"><?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> - <?= htmlspecialchars($order['pincode']) ?></p>
                        <p class="mb-0 text-muted"><i class="fas fa-phone-alt small me-1"></i> <?= htmlspecialchars($order['customer_mobile']) ?></p>
                    </div>
                </div>

                <!-- Estimated Dates & Payment details -->
                <div class="col-md-6">
                    <div class="border rounded-4 p-4 h-100 bg-white shadow-none">
                        <h3 class="h6 fw-bold text-dark mb-3"><i class="fas fa-calendar-check text-primary me-2"></i> Delivery Estimates</h3>
                        <p class="mb-1 text-dark">Estimated delivery: <strong><?= $estMin ?> - <?= $estMax ?></strong></p>
                        <p class="mb-3 small text-muted">Delivery times depend on your local pin code network.</p>

                        <h3 class="h6 fw-bold text-dark mb-2"><i class="fas fa-wallet text-primary me-2"></i> Payment Information</h3>
                        <p class="mb-1 text-muted">Method: <strong class="text-dark"><?= htmlspecialchars($order['payment_method']) ?></strong></p>
                        <p class="mb-0 text-muted">Status: <strong class="<?= $order['payment_status'] === 'paid' ? 'text-success' : 'text-warning' ?>"><?= strtoupper($order['payment_status']) ?></strong></p>
                    </div>
                </div>

                <!-- Order items table -->
                <div class="col-12 mt-4">
                    <div class="border rounded-4 p-4 bg-white shadow-none">
                        <h3 class="h6 fw-bold text-dark mb-3"><i class="fas fa-list text-primary me-2"></i> Order Items</h3>
                        
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="border-bottom text-muted small">
                                        <th>Product</th>
                                        <th class="text-center" style="width: 100px;">Price</th>
                                        <th class="text-center" style="width: 100px;">Qty</th>
                                        <th class="text-end" style="width: 120px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): 
                                        $img = get_product_img_src($item['image']);
                                    ?>
                                        <tr class="border-bottom">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="rounded-3 border me-3" style="width: 48px; height: 48px; object-fit: cover;">
                                                    <div>
                                                        <h4 class="h6 text-dark mb-0 fw-semibold" style="font-size: 0.90rem;"><?= htmlspecialchars($item['product_name']) ?></h4>
                                                        <span class="text-muted small"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') . ' ' . $item['unit']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center font-monospace text-muted small">₹<?= number_format((float)$item['price'], 2) ?></td>
                                            <td class="text-center fw-semibold"><?= (int)$item['quantity'] ?></td>
                                            <td class="text-end font-monospace fw-semibold text-dark">₹<?= number_format($item['total_price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Calculations -->
                                    <tr>
                                        <td colspan="2"></td>
                                        <td class="text-end text-muted small py-2">Subtotal:</td>
                                        <td class="text-end font-monospace text-muted small py-2">₹<?= number_format($order['subtotal'], 2) ?></td>
                                    </tr>
                                    <?php if ((float)$order['discount'] > 0): ?>
                                        <tr>
                                            <td colspan="2"></td>
                                            <td class="text-end text-success small py-1">Discount:</td>
                                            <td class="text-end font-monospace text-success small py-1">-₹<?= number_format($order['discount'], 2) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="2"></td>
                                        <td class="text-end text-muted small py-1">Shipping:</td>
                                        <td class="text-end font-monospace text-muted small py-1"><?= (float)$order['shipping'] > 0 ? '₹' . number_format($order['shipping'], 2) : 'FREE' ?></td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td colspan="2"></td>
                                        <td class="text-end text-dark py-2">Grand Total:</td>
                                        <td class="text-end font-monospace text-primary py-2">₹<?= number_format($order['grand_total'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- CTA -->
            <div class="mt-5 pt-3">
                <a href="shop.php" class="btn btn-warning rounded-3 px-5 py-3 fw-bold text-white shadow-sm" style="background-color: var(--primary-color); border-color: var(--primary-color);">Continue Shopping</a>
            </div>

        </div>

    </div>
</main>

<style>
.unm-success-circle {
    width: 90px;
    height: 90px;
    background-color: #d1fae5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
}
</style>

<?php include_once 'includes/footer.php'; ?>
