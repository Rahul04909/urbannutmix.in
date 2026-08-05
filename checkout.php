<?php
/**
 * UrbanNutMix - Secure Checkout Page with Razorpay Payment Integration
 * Features: High-fidelity Shopify-style layout, stock checks, database transaction safety.
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

// Calculate Summary Totals
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
$payment_method = 'Razorpay';

$show_razorpay_modal = false;
$razorpayOrderId = '';
$orderNumber = '';

// Check for payment cancellations or errors
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
                'amount'          => (int)round($vGrandTotal * 100), // amount in paise
                'currency'        => 'INR',
                'receipt'         => $orderNumber,
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

// Resolve product image source path
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | UrbanNutMix</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gold: #cf6e0c;
            --primary-gold-dark: #b05c08;
            --text-charcoal: #2d2620;
            --border-light: #e1d8cf;
            --bg-cream: #faf6f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: var(--text-charcoal);
            margin: 0;
            padding: 0;
        }

        /* Full page split layout */
        .split-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Left Side: Form Details */
        .split-left {
            flex: 1.25;
            background-color: #ffffff;
            padding: 50px 8% 50px 12%;
            border-right: 1px solid var(--border-light);
        }

        /* Right Side: Order Summary */
        .split-right {
            flex: 0.85;
            background-color: #f4f6f8;
            padding: 50px 12% 50px 5%;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        /* Brand & Security header bar */
        .brand-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .checkout-logo {
            height: 48px;
            object-fit: contain;
            border-radius: 8px;
        }

        .trust-badges-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .trust-badge-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #4b5563;
            background-color: #f3f4f6;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .trust-badge-pill i {
            font-size: 0.85rem;
        }

        /* Announcement banner */
        .announcement-banner {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #92400e;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Section Titles */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 18px;
            margin-top: 15px;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-charcoal);
            margin: 0;
        }

        .section-header-link {
            font-size: 0.85rem;
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
        }

        .section-header-link:hover {
            color: var(--primary-gold);
            text-decoration: underline;
        }

        /* Input Styles */
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 6px;
            display: block;
        }

        .form-control, .form-select {
            border: 1.5px solid #d1d5db !important;
            border-radius: 8px !important;
            padding: 11px 14px !important;
            font-size: 0.92rem !important;
            background-color: #ffffff !important;
            color: var(--text-charcoal) !important;
            box-shadow: none !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-gold) !important;
            box-shadow: 0 0 0 4px rgba(207, 110, 12, 0.08) !important;
        }

        .form-control.is-invalid {
            border-color: #ef4444 !important;
        }

        .invalid-feedback {
            font-size: 0.8rem;
            color: #ef4444;
            font-weight: 600;
            margin-top: 5px;
        }

        /* Checkbox */
        .form-check-input {
            width: 17px;
            height: 17px;
            accent-color: var(--primary-gold) !important;
            border: 1.5px solid #d1d5db;
            border-radius: 4px;
            margin-top: 3px;
        }

        .form-check-label {
            font-size: 0.85rem;
            color: #4b5563;
            user-select: none;
            cursor: pointer;
        }

        /* Right Summary design */
        .summary-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-charcoal);
            margin-bottom: 24px;
        }

        .summary-items-list {
            list-style: none;
            padding: 0;
            margin: 0 0 24px 0;
        }

        .summary-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .summary-product-img-wrapper {
            position: relative;
            width: 64px;
            height: 64px;
            margin-right: 16px;
        }

        .summary-product-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            background-color: #ffffff;
        }

        .summary-product-qty-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary-gold);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .summary-product-title {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-charcoal);
            margin: 0;
        }

        .summary-product-unit {
            font-size: 0.78rem;
            color: #6b7280;
            display: block;
            margin-top: 2px;
        }

        .summary-product-price {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-charcoal);
        }

        /* Calculation details */
        .summary-divider {
            border: 0;
            border-top: 1px solid var(--border-light);
            margin: 20px 0;
        }

        .summary-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 12px;
        }

        .summary-grand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--border-light);
        }

        .summary-grand-label {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-charcoal);
        }

        .summary-grand-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-gold);
        }

        /* Pay now button */
        .btn-pay-now {
            background-color: var(--primary-gold) !important;
            border: none !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 1.05rem !important;
            padding: 16px 24px !important;
            border-radius: 8px !important;
            transition: background-color 0.2s, transform 0.1s !important;
            box-shadow: 0 4px 12px rgba(207, 110, 12, 0.15) !important;
        }

        .btn-pay-now:hover {
            background-color: var(--primary-gold-dark) !important;
            transform: translateY(-1px);
        }

        .btn-pay-now:active {
            transform: translateY(0);
        }

        .secure-footer-text {
            text-align: center;
            font-size: 0.78rem;
            color: #6b7280;
            margin-top: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* Fullscreen processing loader overlay */
        .payment-loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.98);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 999999;
        }

        .loader-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #ebdccb;
            border-top: 4px solid var(--primary-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loader-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-charcoal);
            margin-bottom: 6px;
        }

        .loader-subtext {
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* Responsive styling */
        @media (max-width: 991px) {
            .split-layout {
                flex-direction: column-reverse;
            }

            .split-left {
                padding: 40px 20px;
                border-right: none;
            }

            .split-right {
                padding: 30px 20px;
                height: auto;
                position: static;
                border-bottom: 1px solid var(--border-light);
            }
        }
    </style>
</head>
<body>

    <div class="split-layout">
        
        <!-- LEFT COLUMN: SHIPPING DETAILS FORM -->
        <div class="split-left">
            
            <!-- Brand header row with secure badges -->
            <div class="brand-header-row">
                <a href="shop.php">
                    <img src="<?= BASE_URL ?>assets/images/logo-bg.jpg" alt="UrbanNutMix" class="checkout-logo">
                </a>
                <div class="trust-badges-row">
                    <div class="trust-badge-pill">
                        <i class="fas fa-shield-alt text-success"></i> McAfee Secure
                    </div>
                    <div class="trust-badge-pill">
                        <i class="fas fa-lock text-success"></i> SSL Encrypted
                    </div>
                </div>
            </div>

            <!-- Announcement free shipping banner -->
            <div class="announcement-banner">
                <i class="fas fa-truck"></i> Your order qualifies for <strong>FREE Express Shipping</strong> today!
            </div>

            <!-- Display payment errors or warning tags -->
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

            <form method="POST" action="checkout.php" id="checkoutForm">
                
                <!-- Section 1: Contact Information -->
                <div class="section-header">
                    <h2 class="section-title">Contact Information</h2>
                    <a href="javascript:void(0)" class="section-header-link">Already have an account? Login</a>
                </div>
                
                <div class="row g-3 mb-4">
                    <!-- Email -->
                    <div class="col-12">
                        <label for="customer_email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control <?= isset($errors['customer_email']) ? 'is-invalid' : '' ?>" id="customer_email" name="customer_email" value="<?= htmlspecialchars($customer_email) ?>" placeholder="Email (e.g. name@domain.com)">
                        <?php if (isset($errors['customer_email'])): ?>
                            <div class="invalid-feedback"><?= $errors['customer_email'] ?></div>
                        <?php endif; ?>
                    </div>
                    <!-- Subscribe check -->
                    <div class="col-12 mt-2">
                        <div class="form-check d-flex align-items-center gap-2">
                            <input class="form-check-input" type="checkbox" id="subscribe_news" checked>
                            <label class="form-check-label" for="subscribe_news">
                                Keep me up to date on news and exclusive offers
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Shipping Address -->
                <div class="section-header mt-4">
                    <h2 class="section-title">Shipping Address</h2>
                </div>

                <div class="row g-3">
                    <!-- Country Selector (Standard Shopify style) -->
                    <div class="col-12">
                        <label for="country" class="form-label">Country/Region</label>
                        <select class="form-select" id="country" disabled>
                            <option>India</option>
                        </select>
                    </div>

                    <!-- Full Name -->
                    <div class="col-md-6">
                        <label for="customer_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control <?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>" id="customer_name" name="customer_name" value="<?= htmlspecialchars($customer_name) ?>" placeholder="First and last name">
                        <?php if (isset($errors['customer_name'])): ?>
                            <div class="invalid-feedback"><?= $errors['customer_name'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Mobile Phone -->
                    <div class="col-md-6">
                        <label for="customer_mobile" class="form-label">Mobile Number *</label>
                        <input type="text" class="form-control <?= isset($errors['customer_mobile']) ? 'is-invalid' : '' ?>" id="customer_mobile" name="customer_mobile" value="<?= htmlspecialchars($customer_mobile) ?>" placeholder="10-digit mobile number">
                        <?php if (isset($errors['customer_mobile'])): ?>
                            <div class="invalid-feedback"><?= $errors['customer_mobile'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Address Line 1 -->
                    <div class="col-12">
                        <label for="address_line1" class="form-label">Address Line 1 *</label>
                        <input type="text" class="form-control <?= isset($errors['address_line1']) ? 'is-invalid' : '' ?>" id="address_line1" name="address_line1" value="<?= htmlspecialchars($address_line1) ?>" placeholder="Flat/House No., Building, Street Name">
                        <?php if (isset($errors['address_line1'])): ?>
                            <div class="invalid-feedback"><?= $errors['address_line1'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Address Line 2 -->
                    <div class="col-12">
                        <label for="address_line2" class="form-label">Area, Sector, Locality (Optional)</label>
                        <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= htmlspecialchars($address_line2) ?>" placeholder="Apartment, suite, unit etc.">
                    </div>

                    <!-- City -->
                    <div class="col-md-4">
                        <label for="city" class="form-label">City *</label>
                        <input type="text" class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>" id="city" name="city" value="<?= htmlspecialchars($city) ?>" placeholder="City">
                        <?php if (isset($errors['city'])): ?>
                            <div class="invalid-feedback"><?= $errors['city'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- State -->
                    <div class="col-md-4">
                        <label for="state" class="form-label">State *</label>
                        <input type="text" class="form-control <?= isset($errors['state']) ? 'is-invalid' : '' ?>" id="state" name="state" value="<?= htmlspecialchars($state) ?>" placeholder="State">
                        <?php if (isset($errors['state'])): ?>
                            <div class="invalid-feedback"><?= $errors['state'] ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Pincode -->
                    <div class="col-md-4">
                        <label for="pincode" class="form-label">Pincode *</label>
                        <input type="text" class="form-control <?= isset($errors['pincode']) ? 'is-invalid' : '' ?>" id="pincode" name="pincode" value="<?= htmlspecialchars($pincode) ?>" placeholder="6-digit ZIP">
                        <?php if (isset($errors['pincode'])): ?>
                            <div class="invalid-feedback"><?= $errors['pincode'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submit Button Area -->
                <div class="mt-5 pt-3 d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-warning btn-pay-now w-100 py-3 rounded-3 fw-bold">
                        <i class="fas fa-lock me-2"></i> Pay &amp; Place Order
                    </button>
                    <div class="text-center mt-2">
                        <a href="cart.php" class="text-decoration-none text-muted small"><i class="fas fa-chevron-left me-1"></i> Return to Shopping Cart</a>
                    </div>
                </div>
            </form>
            
            <!-- Secure footer branding -->
            <div class="secure-footer-text">
                <i class="fas fa-shield-alt text-success"></i> 256-Bit SSL Encrypted &amp; Secured by Razorpay
            </div>

        </div>

        <!-- RIGHT COLUMN: ORDER SUMMARY SIDEBAR -->
        <div class="split-right">
            <h2 class="summary-title">Order Summary</h2>
            
            <ul class="summary-items-list">
                <?php foreach ($cartItems as $cItem): 
                    $cImg = get_product_img_src($cItem['image']);
                ?>
                    <li class="summary-item-row">
                        <div class="d-flex align-items-center">
                            <div class="summary-product-img-wrapper">
                                <img src="<?= $cImg ?>" alt="<?= htmlspecialchars($cItem['name']) ?>" class="summary-product-img">
                                <span class="summary-product-qty-badge"><?= $cItem['cart_qty'] ?></span>
                            </div>
                            <div>
                                <h4 class="summary-product-title"><?= htmlspecialchars($cItem['name']) ?></h4>
                                <span class="summary-product-unit"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$cItem['quantity'], 2), '0'), '.') . ' ' . $cItem['unit']) ?></span>
                            </div>
                        </div>
                        <span class="summary-product-price">₹<?= number_format($cItem['item_subtotal'], 2) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <hr class="summary-divider">

            <!-- Subtotal -->
            <div class="summary-total-row">
                <span>Subtotal</span>
                <span class="fw-semibold text-dark">₹<?= number_format($subtotal, 2) ?></span>
            </div>

            <!-- Shipping -->
            <div class="summary-total-row">
                <span>Shipping</span>
                <span class="fw-semibold text-dark"><?= $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE' ?></span>
            </div>

            <!-- Grand Total -->
            <div class="summary-grand-row">
                <span class="summary-grand-label">Grand Total</span>
                <span class="summary-grand-price">₹<?= number_format($grandTotal, 2) ?></span>
            </div>

        </div>

    </div>

    <!-- ── RAZORPAY MODAL PAYMENT SCRIPT & LOADER OVERLAY ───────── -->
    <?php if ($show_razorpay_modal): ?>
        <!-- Fullscreen Connection Loader -->
        <div class="payment-loader-overlay" id="paymentLoader">
            <div class="loader-spinner"></div>
            <div class="loader-title">Opening Secure Gateway...</div>
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
                    document.getElementById('paymentLoader').style.display = 'none';
                    window.location.href = "checkout.php?payment_cancelled=1&order_number=<?= htmlspecialchars($orderNumber) ?>";
                }
            }
        };

        var retryCount = 0;
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
                retryCount++;
                if (retryCount > 60) {
                    alert("Razorpay payment gateway script failed to load. Please check your internet connection, disable any ad-blockers, and refresh the page to retry.");
                    document.getElementById('paymentLoader').style.display = 'none';
                } else {
                    setTimeout(openRazorpay, 100);
                }
            }
        }

        openRazorpay();
        </script>
    <?php endif; ?>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
