<?php
/**
 * UrbanNutMix - Premium Shopping Cart Page (FirstCry Redesign)
 * Features: Dynamic cart items list, AJAX real-time updates, FirstCry UI layouts, and bottom recommendations.
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$page_title = "Your Shopping Cart | UrbanNutMix";
$extra_css = ['assets/css/cart.css'];

try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    error_log("Cart Page DB connection error: " . $e->getMessage());
    die("A connection error occurred. Please try again later.");
}

// --- AJAX Requests Handler ---
if (isset($_POST['action']) || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // AJAX Add to Cart
    if ($action === 'add_ajax') {
        header('Content-Type: application/json');
        $productId = (int)$_POST['product_id'];
        $qty = (int)($_POST['qty'] ?? 1);
        if ($qty < 1) $qty = 1;

        if ($productId > 0) {
            $stmt = $pdo->prepare('SELECT id, quantity, name FROM products WHERE id = :id AND status = "active" LIMIT 1');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $cart = $_SESSION['cart'] ?? [];
                $currentQty = $cart[$productId] ?? 0;
                $newQty = $currentQty + $qty;

                if ($newQty > (float)$product['quantity']) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Only " . (int)$product['quantity'] . " units of " . htmlspecialchars($product['name']) . " are available.",
                        'cart_count' => array_sum($cart)
                    ]);
                    exit;
                }

                $cart[$productId] = $newQty;
                $_SESSION['cart'] = $cart;

                echo json_encode([
                    'success' => true,
                    'message' => '"' . htmlspecialchars($product['name']) . '" added to your cart successfully!',
                    'cart_count' => array_sum($cart)
                ]);
                exit;
            }
        }
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or unavailable.',
            'cart_count' => isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0
        ]);
        exit;
    }

    // AJAX Update Item Quantity
    if ($action === 'update_ajax') {
        header('Content-Type: application/json');
        $productId = (int)$_POST['product_id'];
        $qty = (int)($_POST['qty'] ?? 1);
        $warning = null;

        if ($productId > 0) {
            $stmt = $pdo->prepare('SELECT id, quantity, name FROM products WHERE id = :id AND status = "active" LIMIT 1');
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$productId]);
                } else {
                    if ($qty > (float)$product['quantity']) {
                        $qty = (int)floor((float)$product['quantity']);
                        $warning = "Only " . $qty . " units of " . htmlspecialchars($product['name']) . " are available in stock.";
                    }
                    $_SESSION['cart'][$productId] = $qty;
                }

                // Re-calculate totals
                $cart = $_SESSION['cart'] ?? [];
                $subtotal = 0.0;
                $cartCount = array_sum($cart);

                if (!empty($cart)) {
                    $productIds = array_keys($cart);
                    $inClause = implode(',', array_fill(0, count($productIds), '?'));
                    $tStmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($inClause) AND status = 'active'");
                    $tStmt->execute($productIds);
                    foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                        $subtotal += (float)$p['price'] * $cart[$p['id']];
                    }
                }

                $discount = 0.0;
                $shipping = ($subtotal > 0 && $subtotal < 500) ? 50.00 : 0.0;
                $grandTotal = $subtotal + $shipping;

                echo json_encode([
                    'success' => true,
                    'qty' => $qty,
                    'warning' => $warning,
                    'cart_count' => $cartCount,
                    'subtotal' => number_format($subtotal, 2),
                    'discount' => number_format($discount, 2),
                    'shipping' => number_format($shipping, 2),
                    'grand_total' => number_format($grandTotal, 2)
                ]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Invalid product parameters.']);
        exit;
    }
}

// Prepare Cart List
$cart = $_SESSION['cart'] ?? [];
$cartItems = [];
$subtotal = 0.0;

if (!empty($cart)) {
    $productIds = array_keys($cart);
    $inClause = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.slug, p.image, p.price, p.mrp, p.unit, p.quantity, pc.name AS category_name
         FROM products p
         LEFT JOIN product_categories pc ON pc.id = p.category_id
         WHERE p.id IN ($inClause) AND p.status = 'active'"
    );
    $stmt->execute($productIds);
    $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbProducts as $p) {
        $qty = (int)$cart[$p['id']];
        // Verify stock boundaries
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
}

// Calculate Summary Totals
$discount = 0.0;
$shipping = ($subtotal > 0 && $subtotal < 500) ? 50.00 : 0.0;
$grandTotal = $subtotal + $shipping;

// Fetch Recommended Products (Trending Items slider at the bottom)
$recProducts = [];
try {
    $recStmt = $pdo->prepare(
        "SELECT p.id, p.name, p.slug, p.image, p.price, p.mrp, p.unit, p.quantity 
         FROM products p 
         WHERE p.status = 'active' 
         ORDER BY p.id DESC 
         LIMIT 5"
    );
    $recStmt->execute();
    $recProducts = $recStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Failed to fetch recommended items: " . $e->getMessage());
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

include_once 'includes/header.php';
?>

<main class="unm-cart-wrapper">
    <div class="unm-cart-container">
        
        <!-- Cart Header & Progress Tracker -->
        <div class="unm-cart-header-zone">
            <h1 class="unm-cart-page-title">Shopping Cart</h1>
            
            <div class="unm-checkout-steps">
                <div class="unm-step active">
                    <span class="unm-step-num">1</span>
                    <span class="unm-step-label">Shopping Cart</span>
                </div>
                <div class="unm-step-line"></div>
                <div class="unm-step">
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

        <?php if (empty($cartItems)): ?>
            <!-- Empty Cart State -->
            <div class="unm-cart-empty-state">
                <div class="unm-cart-empty-icon">
                    <svg viewBox="0 0 24 24" width="70" height="70" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </div>
                <h2>Your Shopping Cart is Empty</h2>
                <p>Add some premium nuts, seeds, dry fruits, or hampers to get started!</p>
                <a href="shop.php" class="unm-cart-empty-btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <!-- Cart Layout Grid -->
            <div class="unm-cart-layout">
                
                <!-- Left Column: Pincode Check & Items list -->
                <div class="unm-cart-main">
                    
                    <!-- Pincode Check Card -->
                    <div class="unm-pincode-card">
                        <span class="pincode-label">
                            <i class="fas fa-map-marker-alt"></i> Delivery Pincode:
                        </span>
                        <div class="pincode-input-group">
                            <input type="text" id="pincodeCheckInput" placeholder="Enter Pincode" class="pincode-input" maxlength="6">
                            <button type="button" class="pincode-btn" onclick="checkDeliveryPincode()">Apply</button>
                        </div>
                    </div>

                    <!-- Items container card -->
                    <div class="unm-cart-items-card">
                        
                        <!-- Tabs line -->
                        <div class="unm-cart-tabs-header">
                            <div class="unm-cart-tab active">Shopping Cart (<?= array_sum($cart) ?>)</div>
                            <div class="unm-cart-tab">My Shortlist</div>
                        </div>

                        <!-- Items list -->
                        <?php foreach ($cartItems as $item): 
                            $imgUrl = get_product_img_src($item['image']);
                            $discountPct = ($item['mrp'] > $item['price']) ? (int)round((($item['mrp'] - $item['price']) / $item['mrp']) * 100) : 0;
                        ?>
                            <div class="unm-cart-row" data-product-id="<?= $item['id'] ?>">
                                <!-- Image -->
                                <div class="unm-cart-row-img-wrap">
                                    <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="unm-cart-row-img">
                                </div>

                                <!-- Right layout column -->
                                <div class="unm-cart-row-content">
                                    
                                    <!-- Left details -->
                                    <div class="unm-cart-row-details">
                                        <h3 class="unm-cart-row-name">
                                            <a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                        </h3>
                                        <span class="unm-cart-row-category"><?= htmlspecialchars($item['category_name'] ?? 'Dry Fruits') ?></span>
                                        <span class="unm-cart-row-unit">Pack Size: <?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') . ' ' . $item['unit']) ?></span>
                                        
                                        <!-- Quantity picker small -->
                                        <div class="unm-cart-row-qty-box">
                                            <span class="unm-cart-row-qty-label">Qty:</span>
                                            <div class="unm-qty-picker-small">
                                                <button type="button" class="unm-qty-btn-small" onclick="updateCartQty(<?= $item['id'] ?>, -1)">&minus;</button>
                                                <input type="number" class="unm-qty-input-small" id="qtyInput_<?= $item['id'] ?>" value="<?= $item['cart_qty'] ?>" readonly>
                                                <button type="button" class="unm-qty-btn-small" onclick="updateCartQty(<?= $item['id'] ?>, 1)">&plus;</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Pricing -->
                                    <div class="unm-cart-row-pricing">
                                        <span class="unm-cart-row-price" id="itemSubtotal_<?= $item['id'] ?>" data-unit-price="<?= $item['price'] ?>">
                                            ₹<?= number_format($item['item_subtotal'], 2) ?>
                                        </span>
                                        <?php if ($discountPct > 0): ?>
                                            <div class="unm-cart-row-mrp-row">
                                                <span class="unm-cart-row-mrp">MRP ₹<?= number_format((float)$item['mrp'] * $item['cart_qty'], 2) ?></span>
                                                <span class="unm-cart-row-discount"><?= $discountPct ?>% OFF</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Links footer row -->
                                    <div class="unm-cart-row-actions">
                                        <div class="unm-cart-action-link" onclick="removeCartItem(<?= $item['id'] ?>)">
                                            <i class="far fa-trash-alt"></i> REMOVE
                                        </div>
                                        <div class="unm-cart-action-link">
                                            <i class="far fa-heart"></i> MOVE TO SHORTLIST
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>

                    <!-- Bottom Checkout Action Bar -->
                    <div class="unm-cart-checkout-bar">
                        <div class="unm-bar-price-box">
                            Subtotal (<span id="totalItemsCount"><?= array_sum($cart) ?></span> Items): 
                            <strong id="barGrandTotal">₹<?= number_format($subtotal, 2) ?></strong>
                        </div>
                        <button type="button" class="unm-btn-place-order" onclick="triggerCheckout()">
                            PROCEED TO CHECKOUT
                        </button>
                    </div>

                </div>

                <!-- Right Column: Sidebar summaries -->
                <div class="unm-cart-sidebar">
                    
                    <!-- Bank Offers Card -->
                    <div class="unm-sidebar-card unm-bank-offers-card">
                        <div class="bank-offer-header">
                            <i class="fas fa-percent"></i> Payment Offers
                        </div>
                        <p class="bank-offer-text">Secure online payment via Razorpay. Pay via cards, UPI, or Netbanking securely.</p>
                    </div>

                    <!-- Shipping progress announcements -->
                    <div class="unm-meter-card" id="shipProgressBanner">
                        <i class="fas fa-truck"></i>
                        <span id="shipProgressText">
                            <?php if ($subtotal >= 500): ?>
                                You qualify for <strong>FREE Express Shipping</strong>!
                            <?php else: ?>
                                Add <strong>₹<?= number_format(500 - $subtotal, 2) ?></strong> more for <strong>FREE Shipping</strong>!
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Payment Information summary sheet -->
                    <div class="unm-sidebar-card">
                        <h3 class="unm-payment-title">Payment Information</h3>
                        
                        <div class="unm-payment-row">
                            <span>Value of Product(s)</span>
                            <span id="summarySubtotal" class="font-monospace fw-semibold">₹<?= number_format($subtotal, 2) ?></span>
                        </div>

                        <div class="unm-payment-row">
                            <span>Shipping (+)</span>
                            <span id="summaryShipping" class="font-monospace fw-semibold">
                                <?= $shipping > 0 ? '₹' . number_format($shipping, 2) : 'FREE' ?>
                            </span>
                        </div>

                        <div class="unm-payment-row grand-total">
                            <span>Final Payment</span>
                            <span id="summaryGrandTotal" class="font-monospace">₹<?= number_format($grandTotal, 2) ?></span>
                        </div>
                    </div>

                    <!-- Trust Cards -->
                    <div class="unm-sidebar-card">
                        <div class="unm-trust-bar">
                            <div class="unm-trust-item">
                                <i class="fas fa-sync-alt"></i>
                                Easy Returns
                            </div>
                            <div class="unm-trust-item">
                                <i class="fas fa-shield-alt"></i>
                                Secure Pay
                            </div>
                            <div class="unm-trust-item">
                                <i class="fas fa-certificate"></i>
                                Authentic
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- RECOMMENDED: Trending Items grid -->
            <?php if (!empty($recProducts)): ?>
                <div class="unm-trending-section">
                    <h2 class="unm-trending-title">Trending Items</h2>
                    
                    <div class="unm-trending-grid">
                        <?php foreach ($recProducts as $p): 
                            $rImg = get_product_img_src($p['image']);
                            $rDiscount = ($p['mrp'] > $p['price']) ? (int)round((($p['mrp'] - $p['price']) / $p['mrp']) * 100) : 0;
                        ?>
                            <a href="product.php?slug=<?= urlencode($p['slug']) ?>" class="unm-trending-card">
                                <img src="<?= $rImg ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="unm-trending-img">
                                <div class="unm-trending-info">
                                    <h4 class="unm-trending-name"><?= htmlspecialchars($p['name']) ?></h4>
                                    <span class="unm-trending-unit">Pack Size: <?= htmlspecialchars(rtrim(rtrim(number_format((float)$p['quantity'], 2), '0'), '.') . ' ' . $p['unit']) ?></span>
                                    
                                    <div class="unm-trending-pricing">
                                        <span class="unm-trending-price">₹<?= number_format((float)$p['price'], 2) ?></span>
                                        <?php if ($rDiscount > 0): ?>
                                            <span class="unm-trending-mrp">₹<?= number_format((float)$p['mrp'], 2) ?></span>
                                            <span class="unm-trending-discount"><?= $rDiscount ?>% OFF</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<script>
// Pincode client mock check
function checkDeliveryPincode() {
    const pincode = document.getElementById('pincodeCheckInput').value.trim();
    if (!/^[0-9]{6}$/.test(pincode)) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Pincode',
                text: 'Please enter a valid 6-digit numeric postal code.',
                confirmButtonColor: '#ff5a3a'
            });
        } else {
            alert('Please enter a valid 6-digit pincode.');
        }
        return;
    }
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Delivery Available!',
            text: 'Express delivery is active for pincode ' + pincode + '. Delivery within 2-3 working days.',
            confirmButtonColor: '#ff5a3a'
        });
    } else {
        alert('Delivery available for pincode ' + pincode + '!');
    }
}

// AJAX update quantities
function updateCartQty(productId, delta) {
    const input = document.getElementById('qtyInput_' + productId);
    if (!input) return;

    let currentQty = parseInt(input.value) || 1;
    let newQty = currentQty + delta;
    if (newQty < 1) {
        removeCartItem(productId);
        return;
    }

    const row = document.querySelector(`.unm-cart-row[data-product-id="${productId}"]`);
    if (row) row.classList.add('updating');

    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'update_ajax',
            product_id: productId,
            qty: newQty
        })
    })
    .then(res => res.json())
    .then(data => {
        if (row) row.classList.remove('updating');
        if (data.success) {
            input.value = data.qty;

            // Recalculate row total
            const subtotalCell = document.getElementById('itemSubtotal_' + productId);
            if (subtotalCell) {
                const price = parseFloat(subtotalCell.getAttribute('data-unit-price')) || 0;
                subtotalCell.textContent = '₹' + (price * data.qty).toFixed(2);
            }

            // Show warnings if capped
            if (data.warning && typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock Limit Reached',
                    text: data.warning,
                    confirmButtonColor: '#ff5a3a'
                });
            }

            // Update badges in header
            document.querySelectorAll('.unm-cart-badge').forEach(badge => {
                badge.textContent = data.cart_count;
            });
            
            const totalItemsCountEl = document.getElementById('totalItemsCount');
            if (totalItemsCountEl) totalItemsCountEl.textContent = data.cart_count;

            // Update Summary Totals
            updateSummarySection(data);
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#ff5a3a'
                });
            }
        }
    })
    .catch(err => {
        if (row) row.classList.remove('updating');
        console.error("AJAX quantity update failed: ", err);
    });
}

function removeCartItem(productId) {
    const doRemove = function() {
        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_ajax',
                product_id: productId,
                qty: 0
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`.unm-cart-row[data-product-id="${productId}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        if (data.cart_count === 0) {
                            window.location.reload();
                        }
                    }, 300);
                }

                // Update badges
                document.querySelectorAll('.unm-cart-badge').forEach(badge => {
                    badge.textContent = data.cart_count;
                });
                
                const totalItemsCountEl = document.getElementById('totalItemsCount');
                if (totalItemsCountEl) totalItemsCountEl.textContent = data.cart_count;

                // Update Summary
                updateSummarySection(data);
            }
        })
        .catch(err => {
            console.error("AJAX item delete failed: ", err);
        });
    };

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Remove item?',
            text: 'Are you sure you want to remove this product from your shopping cart?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff5a3a',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, remove it',
            cancelButtonText: 'Cancel'
        }).then(result => {
            if (result.isConfirmed) {
                doRemove();
            }
        });
    } else {
        if (confirm('Remove this product from your shopping cart?')) {
            doRemove();
        }
    }
}

function updateSummarySection(data) {
    const subtotalEl = document.getElementById('summarySubtotal');
    if (subtotalEl) subtotalEl.textContent = '₹' + data.subtotal;

    const barGrandTotalEl = document.getElementById('barGrandTotal');
    if (barGrandTotalEl) barGrandTotalEl.textContent = '₹' + data.subtotal;

    const shippingEl = document.getElementById('summaryShipping');
    if (shippingEl) {
        const shipVal = parseFloat(data.shipping) || 0;
        shippingEl.innerHTML = shipVal > 0 ? '₹' + data.shipping : 'FREE';
    }

    const grandTotalEl = document.getElementById('summaryGrandTotal');
    if (grandTotalEl) grandTotalEl.textContent = '₹' + data.grand_total;

    // Update free shipping meter
    const bannerEl = document.getElementById('shipProgressText');
    if (bannerEl) {
        const subNumeric = parseFloat(data.subtotal.replace(/,/g, '')) || 0;
        if (subNumeric >= 500) {
            bannerEl.innerHTML = 'You qualify for <strong>FREE Express Shipping</strong>!';
        } else {
            const needed = (500 - subNumeric).toFixed(2);
            bannerEl.innerHTML = `Add <strong>₹${needed}</strong> more for <strong>FREE Shipping</strong>!`;
        }
    }
}

function triggerCheckout() {
    window.location.href = 'checkout.php';
}
</script>

<?php include_once 'includes/footer.php'; ?>
