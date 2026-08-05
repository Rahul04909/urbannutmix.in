<?php
/**
 * UrbanNutMix - Secure Checkout Page with Razorpay Payment Integration
 * Features: Stock checking inside transactions, customer details validations, Razorpay Order generation.
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
require_once __DIR__ . '/vendor/autoload.php';
use Razorpay\Api\Api;
Session::start();

// Redirect to cart if empty
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

$page_title = "Secure Checkout | UrbanNutMix";
$extra_css = ['assets/css/cart.css']; // Reuses the checkout trackers and summary box styles

try {
    $pdo = Database::getConnection();
    
    // Self-healing database schema: Ensure Razorpay columns exist on current database connection
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'razorpay_order_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `razorpay_order_id` VARCHAR(100) DEFAULT NULL AFTER `coupon_code`");
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'razorpay_payment_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `razorpay_payment_id` VARCHAR(100) DEFAULT NULL AFTER `razorpay_order_id`");
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'razorpay_signature'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `razorpay_signature` VARCHAR(255) DEFAULT NULL AFTER `razorpay_payment_id`");
        }
    } catch (\Throwable $migrationError) {
        error_log("Non-blocking DB Migration Notice: " . $migrationError->getMessage());
    }
} catch (\Throwable $e) {
    error_log("Checkout DB Connection Error: " . $e->getMessage());
    die("A connection error occurred. Please try again later.");
}

// Fetch Cart items to build calculations
$cartItems = [];
$subtotal = 0.0;
try {
    $productIds = array_keys($cart);
    $inClause = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.slug, p.image, p.price, p.unit, p.quantity
         FROM products p
         WHERE p.id IN ($inClause) AND p.status = 'active'"
    );
    $stmt->execute($productIds);
    $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbProducts as $p) {
        $qty = (int)$cart[$p['id']];
        if ($qty > (float)$p['quantity']) {
            $qty = (int)floor((float)$p['quantity']);
            $_SESSION['cart'][$p['id']] = $qty; // auto-correct session
        }
        if ($qty > 0) {
            $p['cart_qty'] = $qty;
            $p['item_subtotal'] = (float)$p['price'] * $qty;
            $subtotal += $p['item_subtotal'];
            $cartItems[] = $p;
        } else {
            unset($_SESSION['cart'][$p['id']]);
        }
    }
} catch (\Throwable $e) {
    error_log("Checkout Page fetch cart items error: " . $e->getMessage());
}

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

// Calculate Summary Totals (Coupons completely removed)
$discount = 0.0;
$shipping = ($subtotal > 0 && $subtotal < 500) ? 50.00 : 0.0;
$grandTotal = $subtotal + $shipping;

$errors = [];
$customer_name = '';
$customer_email = '';
$customer_mobile = '';
$address_line1 = '';
$address_line2 = '';
$city = '';
$state = '';
$pincode = '';
$payment_method = 'Razorpay'; // COD completely removed

$show_razorpay_modal = false;
$razorpayOrderId = '';
$orderNumber = '';

// Check for payment cancellations or errors from verify-payment.php redirect
$payment_error_msg = null;
if (isset($_GET['payment_error'])) {
    $payment_error_msg = Session::get('flash_error', 'Payment verification failed. Please try again.');
    Session::remove('flash_error');
} elseif (isset($_GET['payment_cancelled'])) {
    $payment_error_msg = 'Payment was cancelled. You can retry paying to complete your order.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_mobile = trim($_POST['customer_mobile'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $payment_method = 'Razorpay'; // Cash on Delivery completely disabled

    // Validation
    if ($customer_name === '') $errors['customer_name'] = 'Full Name is required';
    if ($customer_email === '') {
        $errors['customer_email'] = 'Email is required';
    } elseif (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $errors['customer_email'] = 'Invalid email address';
    }
    if ($customer_mobile === '') {
        $errors['customer_mobile'] = 'Mobile number is required';
    } elseif (!preg_match('/^[0-9]{10}$/', $customer_mobile)) {
        $errors['customer_mobile'] = 'Enter a valid 10-digit mobile number';
    }
    if ($address_line1 === '') $errors['address_line1'] = 'Address Line 1 is required';
    if ($city === '') $errors['city'] = 'City is required';
    if ($state === '') $errors['state'] = 'State is required';
    if ($pincode === '') {
        $errors['pincode'] = 'Pincode is required';
    } elseif (!preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors['pincode'] = 'Enter a valid 6-digit pincode';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Lock and re-validate stock for each item
            $verifiedItems = [];
            foreach ($cart as $productId => $qty) {
                $checkStmt = $pdo->prepare("SELECT id, name, price, quantity, unit FROM products WHERE id = :id AND status = 'active' FOR UPDATE");
                $checkStmt->execute(['id' => $productId]);
                $prod = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if (!$prod) {
                    throw new Exception("Product is no longer available.");
                }

                if ((float)$prod['quantity'] < $qty) {
                    throw new Exception("Sorry, '" . htmlspecialchars($prod['name']) . "' has only " . (int)$prod['quantity'] . " units in stock. Please adjust your cart.");
                }

                $prod['cart_qty'] = $qty;
                $prod['item_subtotal'] = (float)$prod['price'] * $qty;
                $verifiedItems[] = $prod;
            }

            // Calculations verification
            $vSubtotal = 0.0;
            foreach ($verifiedItems as $vItem) {
                $vSubtotal += $vItem['item_subtotal'];
            }
            $vShipping = ($vSubtotal > 0 && $vSubtotal < 500) ? 50.00 : 0.0;
            $vGrandTotal = $vSubtotal + $vShipping;

            // Generate Order Details
            $orderNumber = 'UNM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $paymentStatus = 'pending';

            // Insert into orders table
            $orderQuery = $pdo->prepare(
                "INSERT INTO orders (order_number, customer_name, customer_email, customer_mobile, 
                                    address_line1, address_line2, city, state, pincode, 
                                    payment_method, payment_status, order_status, 
                                    subtotal, discount, shipping, grand_total, coupon_code)
                 VALUES (:order_number, :customer_name, :customer_email, :customer_mobile, 
                         :address_line1, :address_line2, :city, :state, :pincode, 
                         :payment_method, :payment_status, 'pending', 
                         :subtotal, 0.00, :shipping, :grand_total, NULL)"
            );

            $orderQuery->execute([
                'order_number' => $orderNumber,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_mobile' => $customer_mobile,
                'address_line1' => $address_line1,
                'address_line2' => $address_line2,
                'city' => $city,
                'state' => $state,
                'pincode' => $pincode,
                'payment_method' => $payment_method,
                'payment_status' => $paymentStatus,
                'subtotal' => $vSubtotal,
                'shipping' => $vShipping,
                'grand_total' => $vGrandTotal
            ]);

            $orderId = $pdo->lastInsertId();

            // Insert Order Items and update product stock
            $itemQuery = $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_name, price, quantity, unit, total_price)
                 VALUES (:order_id, :product_id, :product_name, :price, :quantity, :unit, :total_price)"
            );

            $stockQuery = $pdo->prepare(
                "UPDATE products SET quantity = quantity - :qty WHERE id = :id"
            );

            foreach ($verifiedItems as $vItem) {
                $itemQuery->execute([
                    'order_id' => $orderId,
                    'product_id' => $vItem['id'],
                    'product_name' => $vItem['name'],
                    'price' => $vItem['price'],
                    'quantity' => $vItem['cart_qty'],
                    'unit' => $vItem['unit'],
                    'total_price' => $vItem['item_subtotal']
                ]);

                $stockQuery->execute([
                    'qty' => $vItem['cart_qty'],
                    'id' => $vItem['id']
                ]);
            }

            // Generate Razorpay Order
            $api = new Api($_ENV['RAZORPAY_KEY_ID'], $_ENV['RAZORPAY_KEY_SECRET']);
            $rzOrder = $api->order->create([
                'receipt'         => $orderNumber,
                'amount'          => (int)round($vGrandTotal * 100), // amount in paise
                'currency'        => 'INR',
                'payment_capture' => 1
            ]);
            
            $razorpayOrderId = $rzOrder['id'];
            
            // Update orders table with the order ID
            $updateRz = $pdo->prepare("UPDATE orders SET razorpay_order_id = :rz_id WHERE id = :id");
            $updateRz->execute(['rz_id' => $razorpayOrderId, 'id' => $orderId]);
            
            $pdo->commit();
            $show_razorpay_modal = true;

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['transaction'] = "Order generation error: " . $e->getMessage();
        }
    }
}

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

<style>
/* ──────────────────────────────────────────────────────── */
/* BRAND SPECIFIC PREMIUM CHECKOUT SYSTEM                   */
/* ──────────────────────────────────────────────────────── */
:root {
    --brand-gold: #cf6e0c;
    --brand-gold-dark: #b05c08;
    --brand-cream: #faf8f5;
    --border-beige: #ebdccb;
    --text-charcoal: #2d2620;
    --shadow-premium: 0 10px 40px rgba(45, 38, 32, 0.04);
}

body {
    background-color: #fdfcfb;
}

.unm-cart-wrapper {
    background-color: #fdfcfb;
    padding: 50px 0;
}

.unm-checkout-card {
    background: #ffffff !important;
    border: 1px solid var(--border-beige) !important;
    border-radius: 20px !important;
    box-shadow: var(--shadow-premium) !important;
    padding: 35px !important;
}

.checkout-section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-charcoal);
    margin-top: 35px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1.5px solid var(--border-beige);
    padding-bottom: 10px;
    letter-spacing: -0.2px;
}

.checkout-section-title i {
    color: var(--brand-gold);
}

/* Custom form labels */
.form-label {
    font-size: 0.8rem !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #827870 !important;
    margin-bottom: 8px;
}

/* Input group addon styles */
.input-group-text {
    border: 1.5px solid var(--border-beige) !important;
    border-radius: 12px 0 0 12px !important;
    background-color: var(--brand-cream) !important;
    color: #827870;
    padding-left: 16px;
    padding-right: 16px;
}

/* Form fields customization */
.form-control {
    border: 1.5px solid var(--border-beige) !important;
    border-radius: 12px !important;
    background-color: #faf8f5 !important;
    padding: 13px 18px !important;
    font-size: 0.95rem !important;
    color: var(--text-charcoal) !important;
    transition: all 0.25s ease-in-out !important;
    box-shadow: none !important;
}

.form-control.border-start-0 {
    border-radius: 0 12px 12px 0 !important;
}

.form-control::placeholder {
    color: #b0a49b;
}

.form-control:focus {
    background-color: #ffffff !important;
    border-color: var(--brand-gold) !important;
    box-shadow: 0 0 0 4px rgba(207, 110, 12, 0.08) !important;
}

/* Invalid validations */
.form-control.is-invalid {
    border-color: #ef4444 !important;
    background-color: #fffbfa !important;
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08) !important;
}

.invalid-feedback {
    font-size: 0.8rem;
    color: #ef4444;
    font-weight: 600;
    margin-top: 5px;
}

/* Secure Payment Display Box */
.checkout-payment-info-box {
    background-color: #faf6f0;
    border: 1.5px dashed var(--brand-gold);
    border-radius: 14px;
    padding: 24px;
    margin-top: 25px;
}

.checkout-payment-info-box strong {
    color: var(--text-charcoal);
    font-size: 0.95rem;
}

/* Sidebar Order Details */
.sidebar-summary-card {
    background: #ffffff !important;
    border: 1.5px solid var(--border-beige) !important;
    border-radius: 20px !important;
    box-shadow: var(--shadow-premium) !important;
    padding: 30px !important;
    position: sticky;
    top: 40px;
}

/* CTA Checkout Button */
.btn-pay-now {
    background: linear-gradient(135deg, var(--brand-gold) 0%, #e67e15 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 18px 24px !important;
    font-size: 1.15rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px;
    color: #ffffff !important;
    box-shadow: 0 6px 20px rgba(207, 110, 12, 0.22) !important;
    transition: all 0.25s ease-in-out !important;
}

.btn-pay-now:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 10px 30px rgba(207, 110, 12, 0.35) !important;
}

.btn-pay-now:active {
    transform: translateY(0) !important;
}

/* Fullscreen Loader Cover Overlay */
.payment-loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(253, 252, 250, 0.98);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 999999;
}

.loader-spinner {
    width: 60px;
    height: 60px;
    border: 5px solid #ebdccb;
    border-top: 5px solid var(--brand-gold);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 24px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loader-text {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-charcoal);
    margin-bottom: 8px;
    font-family: 'Source Sans Pro', sans-serif;
}

.loader-subtext {
    font-size: 0.95rem;
    color: #827870;
}
</style>

<main class="unm-cart-wrapper">
    <div class="unm-cart-container">
        
        <!-- Tracker steps -->
        <div class="unm-cart-header-zone">
            <h1 class="unm-cart-page-title">Secure Checkout</h1>
            <div class="unm-checkout-steps">
                <div class="unm-step">
                    <span class="unm-step-num">1</span>
                    <span class="unm-step-label">Shopping Cart</span>
                </div>
                <div class="unm-step-line"></div>
                <div class="unm-step active">
                    <span class="unm-step-num">2</span>
                    <span class="unm-step-label">Secure Checkout</span>
                </div>
                <div class="unm-step-line"></div>
                <div class="unm-step">
                    <span class="unm-step-num">3</span>
                    <span class="unm-step-label">Order Confirmed</span>
                </div>
            </div>
        </div>

        <?php if ($payment_error_msg): ?>
            <div class="alert alert-warning mb-4 rounded-3 shadow-sm alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($payment_error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['transaction'])): ?>
            <div class="alert alert-danger mb-4 rounded-3 shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($errors['transaction']) ?>
            </div>
        <?php endif; ?>

        <!-- Checkout Form Grid -->
        <div class="unm-cart-layout">
            
            <!-- Left: Shipping info form -->
            <div class="unm-cart-main">
                <div class="unm-checkout-card">
                    <form method="POST" action="checkout.php" id="checkoutForm">
                        
                        <!-- SECTION 1: Contact Details -->
                        <h3 class="checkout-section-title" style="margin-top:0;"><i class="fas fa-user-circle"></i> Contact Information</h3>
                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user small"></i></span>
                                    <input type="text" class="form-control border-start-0 <?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>" id="customer_name" name="customer_name" value="<?= htmlspecialchars($customer_name) ?>" placeholder="e.g. Rahul Kumar">
                                    <?php if (isset($errors['customer_name'])): ?>
                                        <div class="invalid-feedback d-block w-100"><?= $errors['customer_name'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="customer_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope small"></i></span>
                                    <input type="email" class="form-control border-start-0 <?= isset($errors['customer_email']) ? 'is-invalid' : '' ?>" id="customer_email" name="customer_email" value="<?= htmlspecialchars($customer_email) ?>" placeholder="e.g. customer@gmail.com">
                                    <?php if (isset($errors['customer_email'])): ?>
                                        <div class="invalid-feedback d-block w-100"><?= $errors['customer_email'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Phone -->
                            <div class="col-md-12">
                                <label for="customer_mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone small"></i></span>
                                    <input type="text" class="form-control border-start-0 <?= isset($errors['customer_mobile']) ? 'is-invalid' : '' ?>" id="customer_mobile" name="customer_mobile" value="<?= htmlspecialchars($customer_mobile) ?>" placeholder="10-digit mobile number">
                                    <?php if (isset($errors['customer_mobile'])): ?>
                                        <div class="invalid-feedback d-block w-100"><?= $errors['customer_mobile'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: Shipping Address -->
                        <h3 class="checkout-section-title"><i class="fas fa-map-marker-alt"></i> Shipping Address</h3>
                        <div class="row g-3">
                            <!-- Address line 1 -->
                            <div class="col-12">
                                <label for="address_line1" class="form-label">Flat/House No., Building, Street Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-home small"></i></span>
                                    <input type="text" class="form-control border-start-0 <?= isset($errors['address_line1']) ? 'is-invalid' : '' ?>" id="address_line1" name="address_line1" value="<?= htmlspecialchars($address_line1) ?>" placeholder="Flat/House No., Street Name">
                                    <?php if (isset($errors['address_line1'])): ?>
                                        <div class="invalid-feedback d-block w-100"><?= $errors['address_line1'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Address line 2 -->
                            <div class="col-12">
                                <label for="address_line2" class="form-label">Area, Sector, Locality (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-signs small"></i></span>
                                    <input type="text" class="form-control border-start-0" id="address_line2" name="address_line2" value="<?= htmlspecialchars($address_line2) ?>" placeholder="Area, Sector, Locality">
                                </div>
                            </div>

                            <!-- City -->
                            <div class="col-md-4">
                                <label for="city" class="form-label">City/District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>" id="city" name="city" value="<?= htmlspecialchars($city) ?>" placeholder="City">
                                <?php if (isset($errors['city'])): ?>
                                    <div class="invalid-feedback"><?= $errors['city'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- State -->
                            <div class="col-md-4">
                                <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['state']) ? 'is-invalid' : '' ?>" id="state" name="state" value="<?= htmlspecialchars($state) ?>" placeholder="State">
                                <?php if (isset($errors['state'])): ?>
                                    <div class="invalid-feedback"><?= $errors['state'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Pincode -->
                            <div class="col-md-4">
                                <label for="pincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['pincode']) ? 'is-invalid' : '' ?>" id="pincode" name="pincode" value="<?= htmlspecialchars($pincode) ?>" placeholder="6-digit PIN">
                                <?php if (isset($errors['pincode'])): ?>
                                    <div class="invalid-feedback"><?= $errors['pincode'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- SECTION 3: Secure Online Payment Summary -->
                        <h3 class="checkout-section-title"><i class="fas fa-shield-alt"></i> Secure Payment</h3>
                        <div class="checkout-payment-info-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold"><i class="fas fa-lock text-success me-2"></i> Razorpay Payment Gateway</span>
                                <span class="badge bg-success text-white px-2.5 py-1">Secure Connection</span>
                            </div>
                            <p class="small text-muted mb-0">Payments are encrypted and processed securely. You can complete your order using Credit/Debit Cards, UPI, Netbanking, or mobile wallets via the secure Razorpay popup.</p>
                        </div>

                        <!-- CTA Place Order -->
                        <div class="mt-5 pt-3 border-top">
                            <button type="submit" class="btn btn-warning btn-pay-now w-100 rounded-3 px-5 py-3 fw-bold text-white fs-5 shadow-md">
                                <i class="fas fa-lock me-2"></i> Pay &amp; Place Order
                            </button>
                            <div class="text-center mt-3">
                                <a href="cart.php" class="btn btn-link text-decoration-none text-muted small"><i class="fas fa-chevron-left me-1"></i> Return to Shopping Cart</a>
                            </div>
                            
                            <!-- Trust badges -->
                            <div class="d-flex justify-content-center align-items-center gap-4 mt-4 pt-3 border-top text-muted small flex-wrap">
                                <div><i class="fas fa-shield-alt text-success me-1"></i> 256-Bit SSL Encryption</div>
                                <div><i class="fas fa-undo text-success me-1"></i> Easy Return Policy</div>
                                <div><i class="fas fa-award text-success me-1"></i> 100% Authentic Quality</div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Order Details items snapshot -->
            <div class="unm-cart-sidebar">
                <div class="sidebar-summary-card">
                    <h3 class="h5 fw-bold text-dark mb-4 border-bottom pb-2" style="font-family: 'Source Sans Pro', sans-serif;">Order Details</h3>
                    
                    <ul class="list-unstyled p-0 m-0 mb-4 overflow-y-auto" style="max-height: 320px;">
                        <?php foreach ($cartItems as $cItem): 
                            $cImg = get_product_img_src($cItem['image']);
                        ?>
                            <li class="d-flex align-items-center mb-3">
                                <img src="<?= $cImg ?>" alt="<?= htmlspecialchars($cItem['name']) ?>" class="rounded-3 border me-3" style="width: 55px; height: 55px; object-fit: cover;">
                                <div class="flex-grow-1 min-w-0">
                                    <h4 class="h6 text-truncate text-dark mb-0 fw-semibold" style="font-size: 0.9rem;"><?= htmlspecialchars($cItem['name']) ?></h4>
                                    <span class="small text-muted"><?= $cItem['cart_qty'] ?> &times; ₹<?= number_format((float)$cItem['price'], 2) ?></span>
                                </div>
                                <span class="font-monospace fw-semibold text-dark text-end ms-2" style="font-size: 0.9rem;">₹<?= number_format($cItem['item_subtotal'], 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="unm-summary-row mt-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="font-monospace text-dark">₹<?= number_format($subtotal, 2) ?></span>
                    </div>

                    <div class="unm-summary-row">
                        <span class="text-muted">Shipping</span>
                        <span class="font-monospace text-dark"><?= $shipping > 0 ? '₹' . number_format($shipping, 2) : '<span class="text-success fw-bold">FREE</span>' ?></span>
                    </div>

                    <hr class="my-3" style="border-color: #ebdccb;">

                    <div class="unm-summary-row grand-total-row bg-light p-2 rounded-3">
                        <span class="fw-bold text-dark fs-6">Grand Total</span>
                        <span class="font-monospace fw-bold text-primary fs-6">₹<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</main>

<!-- ── RAZORPAY MODAL PAYMENT SCRIPT & LOADER OVERLAY ───────── -->
<?php if ($show_razorpay_modal): ?>
    <!-- Fullscreen Payment Loader -->
    <div class="payment-loader-overlay" id="paymentLoader">
        <div class="loader-spinner"></div>
        <div class="loader-text">Securing Connection...</div>
        <div class="loader-subtext">Please do not reload this page or close the window.</div>
    </div>

    <form action="verify-payment.php" method="POST" id="rzpSubmitForm" style="display:none;">
        <input type="hidden" name="razorpay_payment_id" id="rzp_payment_id">
        <input type="hidden" name="razorpay_order_id" id="rzp_order_id" value="<?= htmlspecialchars($razorpayOrderId) ?>">
        <input type="hidden" name="razorpay_signature" id="rzp_signature">
        <input type="hidden" name="order_number" value="<?= htmlspecialchars($orderNumber) ?>">
    </form>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    var options = {
        "key": "<?= htmlspecialchars($_ENV['RAZORPAY_KEY_ID']) ?>",
        "amount": "<?= (int)round($vGrandTotal * 100) ?>",
        "currency": "INR",
        "name": "UrbanNutMix",
        "description": "Secure payment for order #<?= htmlspecialchars($orderNumber) ?>",
        "order_id": "<?= htmlspecialchars($razorpayOrderId) ?>",
        "prefill": {
            "name": "<?= htmlspecialchars($customer_name) ?>",
            "email": "<?= htmlspecialchars($customer_email) ?>",
            "contact": "<?= htmlspecialchars($customer_mobile) ?>"
        },
        "theme": {
            "color": "#cf6e0c"
        },
        "handler": function (response){
            document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
            document.getElementById('rzp_signature').value = response.razorpay_signature;
            document.getElementById('rzpSubmitForm').submit();
        },
        "modal": {
            "ondismiss": function(){
                // Remove fullscreen loader and redirect back
                document.getElementById('paymentLoader').style.display = 'none';
                window.location.href = "checkout.php?payment_cancelled=1&order_number=<?= htmlspecialchars($orderNumber) ?>";
            }
        }
    };
    
    function openRazorpay() {
        if (typeof Razorpay !== 'undefined') {
            try {
                var rzp1 = new Razorpay(options);
                rzp1.on('payment.failed', function (response){
                    alert("Payment Failed: " + response.error.description);
                });
                rzp1.open();
            } catch (err) {
                console.error("Razorpay open error: ", err);
                alert("Could not load payment gateway: " + err.message);
                document.getElementById('paymentLoader').style.display = 'none';
            }
        } else {
            // Retry script detection after 100ms
            setTimeout(openRazorpay, 100);
        }
    }
    
    window.onload = function() {
        openRazorpay();
    };
    </script>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>
