<?php
/**
 * UrbanNutMix - Secure Checkout Page
 * Features: Stock checking inside transactions, customer details validations, payment options selection, cart details summary.
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
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
$coupon = $_SESSION['coupon'] ?? '';
$discount = ($coupon === 'NUTMIX10') ? $subtotal * 0.10 : 0.0;
$shipping = ($subtotal > 0 && $subtotal < 500) ? 50.00 : 0.0;
$grandTotal = $subtotal - $discount + $shipping;

$errors = [];
$customer_name = '';
$customer_email = '';
$customer_mobile = '';
$address_line1 = '';
$address_line2 = '';
$city = '';
$state = '';
$pincode = '';
$payment_method = 'COD';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_mobile = trim($_POST['customer_mobile'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'COD');

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
                    throw new Exception("Product '" . htmlspecialchars($prod['name']) . "' is no longer available.");
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
            $vDiscount = ($coupon === 'NUTMIX10') ? $vSubtotal * 0.10 : 0.0;
            $vShipping = ($vSubtotal > 0 && $vSubtotal < 500) ? 50.00 : 0.0;
            $vGrandTotal = $vSubtotal - $vDiscount + $vShipping;

            // Generate Order Details
            $orderNumber = 'UNM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $paymentStatus = ($payment_method === 'COD') ? 'pending' : 'paid';

            // Insert into orders table
            $orderQuery = $pdo->prepare(
                "INSERT INTO orders (order_number, customer_name, customer_email, customer_mobile, 
                                    address_line1, address_line2, city, state, pincode, 
                                    payment_method, payment_status, order_status, 
                                    subtotal, discount, shipping, grand_total, coupon_code)
                 VALUES (:order_number, :customer_name, :customer_email, :customer_mobile, 
                         :address_line1, :address_line2, :city, :state, :pincode, 
                         :payment_method, :payment_status, 'pending', 
                         :subtotal, :discount, :shipping, :grand_total, :coupon_code)"
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
                'discount' => $vDiscount,
                'shipping' => $vShipping,
                'grand_total' => $vGrandTotal,
                'coupon_code' => $coupon ?: null
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

            $pdo->commit();

            // Clean Cart Sessions
            unset($_SESSION['cart']);
            unset($_SESSION['coupon']);

            // Redirect
            header("Location: order-complete.php?order_number=" . urlencode($orderNumber));
            exit;

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['transaction'] = $e->getMessage();
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

        <?php if (isset($errors['transaction'])): ?>
            <div class="alert alert-danger mb-4 rounded-3 shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($errors['transaction']) ?>
            </div>
        <?php endif; ?>

        <!-- Checkout Form Grid -->
        <div class="unm-cart-layout">
            
            <!-- Left: Shipping info form -->
            <div class="unm-cart-main">
                <div class="card border border-2 border-light rounded-4 shadow-sm p-4 bg-white">
                    <h2 class="h4 text-dark fw-bold mb-4" style="font-family: 'Source Sans Pro', sans-serif;">Delivery Details</h2>
                    
                    <form method="POST" action="checkout.php" id="checkoutForm">
                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label small fw-semibold text-muted">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 <?= isset($errors['customer_name']) ? 'is-invalid' : '' ?>" id="customer_name" name="customer_name" value="<?= htmlspecialchars($customer_name) ?>" placeholder="e.g. Rahul Kumar">
                                <?php if (isset($errors['customer_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['customer_name'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="customer_email" class="form-label small fw-semibold text-muted">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control rounded-3 <?= isset($errors['customer_email']) ? 'is-invalid' : '' ?>" id="customer_email" name="customer_email" value="<?= htmlspecialchars($customer_email) ?>" placeholder="e.g. customer@gmail.com">
                                <?php if (isset($errors['customer_email'])): ?>
                                    <div class="invalid-feedback"><?= $errors['customer_email'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Phone -->
                            <div class="col-md-12">
                                <label for="customer_mobile" class="form-label small fw-semibold text-muted">Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 <?= isset($errors['customer_mobile']) ? 'is-invalid' : '' ?>" id="customer_mobile" name="customer_mobile" value="<?= htmlspecialchars($customer_mobile) ?>" placeholder="10-digit mobile number">
                                <?php if (isset($errors['customer_mobile'])): ?>
                                    <div class="invalid-feedback"><?= $errors['customer_mobile'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Address line 1 -->
                            <div class="col-12">
                                <label for="address_line1" class="form-label small fw-semibold text-muted">Flat/House No., Building, Street Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 <?= isset($errors['address_line1']) ? 'is-invalid' : '' ?>" id="address_line1" name="address_line1" value="<?= htmlspecialchars($address_line1) ?>" placeholder="Address Line 1">
                                <?php if (isset($errors['address_line1'])): ?>
                                    <div class="invalid-feedback"><?= $errors['address_line1'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Address line 2 -->
                            <div class="col-12">
                                <label for="address_line2" class="form-label small fw-semibold text-muted">Area, Sector, Locality (Optional)</label>
                                <input type="text" class="form-control rounded-3" id="address_line2" name="address_line2" value="<?= htmlspecialchars($address_line2) ?>" placeholder="Address Line 2">
                            </div>

                            <!-- City -->
                            <div class="col-md-4">
                                <label for="city" class="form-label small fw-semibold text-muted">City/District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 <?= isset($errors['city']) ? 'is-invalid' : '' ?>" id="city" name="city" value="<?= htmlspecialchars($city) ?>" placeholder="City">
                                <?php if (isset($errors['city'])): ?>
                                    <div class="invalid-feedback"><?= $errors['city'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- State -->
                            <div class="col-md-4">
                                <label for="state" class="form-label small fw-semibold text-muted">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 <?= isset($errors['state']) ? 'is-invalid' : '' ?>" id="state" name="state" value="<?= htmlspecialchars($state) ?>" placeholder="State">
                                <?php if (isset($errors['state'])): ?>
                                    <div class="invalid-feedback"><?= $errors['state'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Pincode -->
                            <div class="col-md-4">
                                <label for="pincode" class="form-label small fw-semibold text-muted">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 <?= isset($errors['pincode']) ? 'is-invalid' : '' ?>" id="pincode" name="pincode" value="<?= htmlspecialchars($pincode) ?>" placeholder="6-digit PIN">
                                <?php if (isset($errors['pincode'])): ?>
                                    <div class="invalid-feedback"><?= $errors['pincode'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Payment Options -->
                            <div class="col-12 mt-4">
                                <label class="form-label small fw-bold text-dark mb-2">Payment Method</label>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <div class="border rounded-3 p-3 d-flex align-items-center cursor-pointer select-pay-opt">
                                            <input class="form-check-input me-3" type="radio" name="payment_method" id="pay_cod" value="COD" <?= $payment_method === 'COD' ? 'checked' : '' ?> style="accent-color:var(--primary-color);">
                                            <label class="form-check-label flex-grow-1 cursor-pointer" for="pay_cod">
                                                <strong>Cash on Delivery (COD)</strong>
                                                <span class="d-block small text-muted">Pay at your doorstep</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="border rounded-3 p-3 d-flex align-items-center cursor-pointer select-pay-opt">
                                            <input class="form-check-input me-3" type="radio" name="payment_method" id="pay_card" value="Card" <?= $payment_method === 'Card' ? 'checked' : '' ?> style="accent-color:var(--primary-color);">
                                            <label class="form-check-label flex-grow-1 cursor-pointer" for="pay_card">
                                                <strong>Online Demo Payment</strong>
                                                <span class="d-block small text-muted">Simulate instant card/UPI</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                            <a href="cart.php" class="btn btn-outline-secondary rounded-3 px-4 py-2"><i class="fas fa-chevron-left me-1"></i> Return to Cart</a>
                            <button type="submit" class="btn btn-warning rounded-3 px-5 py-2 fw-semibold text-white" style="background-color: var(--primary-color); border-color: var(--primary-color);">Place Order</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Order Details items snapshot -->
            <div class="unm-cart-sidebar">
                <div class="card rounded-4 border-0 p-4 shadow-sm bg-white">
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

                    <?php if ($coupon !== ''): ?>
                        <div class="unm-summary-row text-success">
                            <span>Discount (NUTMIX10)</span>
                            <span class="font-monospace">-₹<?= number_format($discount, 2) ?></span>
                        </div>
                    <?php endif; ?>

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

<style>
.select-pay-opt {
    transition: border-color 0.2s, background-color 0.2s;
    cursor: pointer;
}
.select-pay-opt:hover {
    border-color: var(--primary-color) !important;
    background-color: #faf6f0;
}
.select-pay-opt input:checked + label {
    color: var(--primary-color) !important;
}
.select-pay-opt:has(input:checked) {
    border-color: var(--primary-color) !important;
    background-color: #faf6f0;
}
</style>

<?php include_once 'includes/footer.php'; ?>
