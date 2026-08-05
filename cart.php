<?php
/**
 * UrbanNutMix - Premium Shopping Cart Page
 * Features: Dynamic cart items list, AJAX real-time subtotal updates, Coupon discount code support, Progress tracker
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
        $productId = (int)($_POST['product_id'] ?? 0);
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
        $productId = (int)($_POST['product_id'] ?? 0);
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

                $coupon = $_SESSION['coupon'] ?? '';
                $discount = ($coupon === 'NUTMIX10') ? $subtotal * 0.10 : 0.0;
                $shipping = ($subtotal > 0 && $subtotal < 500) ? 50.00 : 0.0;
                $grandTotal = $subtotal - $discount + $shipping;

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

// --- Standard REDIRECT Requests Handler (PDP Buy Now / simple link actions) ---
$addId = (int)($_GET['add'] ?? 0);
if ($addId > 0) {
    $qty = (int)($_GET['qty'] ?? 1);
    if ($qty < 1) $qty = 1;

    $stmt = $pdo->prepare('SELECT id, quantity FROM products WHERE id = :id AND status = "active" LIMIT 1');
    $stmt->execute(['id' => $addId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $cart = $_SESSION['cart'] ?? [];
        $currentQty = $cart[$addId] ?? 0;
        $newQty = $currentQty + $qty;
        if ($newQty > (float)$product['quantity']) {
            $newQty = (float)$product['quantity'];
        }
        $cart[$addId] = $newQty;
        $_SESSION['cart'] = $cart;
    }
    header('Location: cart.php');
    exit;
}

$removeId = (int)($_GET['remove'] ?? 0);
if ($removeId > 0) {
    if (isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
    }
    header('Location: cart.php');
    exit;
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
        
        <!-- Cart Header & Progress Steps Banner -->
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
                    <svg viewBox="0 0 24 24" width="80" height="80" fill="none" stroke="currentColor" stroke-width="1.2">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </div>
                <h2>Your Shopping Cart is Empty</h2>
                <p>Add some premium nuts, seeds, dry fruits, or diwali hampers to get started!</p>
                <a href="shop.php" class="unm-cart-empty-btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <!-- Cart Layout Grid -->
            <div class="unm-cart-layout">
                
                <!-- Left: Items list -->
                <div class="unm-cart-main">
                    <div class="unm-cart-table-card">
                        <div class="table-responsive">
                            <table class="table unm-cart-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center" style="width:120px;">Price</th>
                                        <th class="text-center" style="width:140px;">Quantity</th>
                                        <th class="text-end" style="width:120px;">Total</th>
                                        <th class="text-center" style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item): 
                                        $imgUrl = get_product_img_src($item['image']);
                                    ?>
                                        <tr class="unm-cart-row" data-product-id="<?= $item['id'] ?>">
                                            <!-- Product Details -->
                                            <td>
                                                <div class="unm-cart-item-info">
                                                    <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="unm-cart-item-img">
                                                    <div class="unm-cart-item-desc">
                                                        <h3 class="unm-cart-item-name">
                                                            <a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                                        </h3>
                                                        <span class="unm-cart-item-category badge bg-light text-dark border"><?= htmlspecialchars($item['category_name'] ?? 'Dry Fruits') ?></span>
                                                        <span class="unm-cart-item-unit ms-2 text-muted small"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') . ' ' . $item['unit']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Unit Price -->
                                            <td class="text-center align-middle font-monospace fw-semibold">
                                                ₹<?= number_format((float)$item['price'], 2) ?>
                                            </td>
                                            
                                            <!-- Quantity selector controls -->
                                            <td class="text-center align-middle">
                                                <div class="unm-cart-qty-picker">
                                                    <button type="button" class="unm-cart-qty-btn" onclick="updateCartQty(<?= $item['id'] ?>, -1)">&minus;</button>
                                                    <input type="number" class="unm-cart-qty-input" id="qtyInput_<?= $item['id'] ?>" value="<?= $item['cart_qty'] ?>" readonly>
                                                    <button type="button" class="unm-cart-qty-btn" onclick="updateCartQty(<?= $item['id'] ?>, 1)">&plus;</button>
                                                </div>
                                            </td>
                                            
                                            <!-- Item Subtotal -->
                                            <td class="text-end align-middle font-monospace fw-bold text-dark" id="itemSubtotal_<?= $item['id'] ?>" data-unit-price="<?= $item['price'] ?>">
                                                ₹<?= number_format($item['item_subtotal'], 2) ?>
                                            </td>
                                            
                                            <!-- Remove button -->
                                            <td class="text-center align-middle">
                                                <button type="button" class="unm-cart-remove-btn" onclick="removeCartItem(<?= $item['id'] ?>)" aria-label="Remove item">
                                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="unm-cart-actions-footer">
                        <a href="shop.php" class="unm-cart-back-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 5px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                            Continue Shopping
                        </a>
                    </div>
                </div>

                <!-- Right: Summary Sidebar -->
                <div class="unm-cart-sidebar">
                    <div class="unm-cart-summary-card">
                        <h2 class="unm-summary-card-title">Order Summary</h2>
                        
                        <div class="unm-summary-row">
                            <span class="text-muted">Subtotal</span>
                            <span class="font-monospace fw-semibold text-dark" id="summarySubtotal">₹<?= number_format($subtotal, 2) ?></span>
                        </div>
                        


                        <div class="unm-summary-row">
                            <span class="text-muted">Shipping Charges</span>
                            <span class="font-monospace fw-semibold text-dark" id="summaryShipping">
                                <?= $shipping > 0 ? '₹' . number_format($shipping, 2) : '<span class="text-success fw-bold">FREE</span>' ?>
                            </span>
                        </div>

                        <hr class="my-3" style="border-color: #ebdccb;">

                        <div class="unm-summary-row grand-total-row">
                            <span class="fw-bold text-dark fs-5">Grand Total</span>
                            <span class="font-monospace fw-bold text-primary fs-5" id="summaryGrandTotal">₹<?= number_format($grandTotal, 2) ?></span>
                        </div>
                        
                        <div class="unm-free-ship-meter">
                            <span class="small text-muted" id="shipMeterText">
                                <?php if ($subtotal >= 500): ?>
                                    🎉 You qualify for <strong>FREE Express Shipping</strong>!
                                <?php else: ?>
                                    Add <strong>₹<?= number_format(500 - $subtotal, 2) ?></strong> more to get <strong>FREE Shipping</strong>!
                                <?php endif; ?>
                            </span>
                        </div>

                        <button type="button" class="unm-checkout-btn" onclick="triggerCheckout()">
                            Proceed to Checkout
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-left: 8px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Scripts for AJAX Cart items management -->
<script>
function updateCartQty(productId, delta) {
    const input = document.getElementById('qtyInput_' + productId);
    if (!input) return;

    let currentQty = parseInt(input.value) || 1;
    let newQty = currentQty + delta;
    if (newQty < 1) {
        removeCartItem(productId);
        return;
    }

    // Disable picker buttons temporarily during fetch
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
                    confirmButtonColor: '#cf6e0c'
                });
            }

            // Update badges in header
            document.querySelectorAll('.unm-cart-badge').forEach(badge => {
                badge.textContent = data.cart_count;
            });

            // Update Summary Totals
            updateSummarySection(data);
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#cf6e0c'
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
                // Animate and remove row
                const row = document.querySelector(`.unm-cart-row[data-product-id="${productId}"]`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        row.remove();
                        // If empty, reload to show empty state
                        if (data.cart_count === 0) {
                            window.location.reload();
                        }
                    }, 300);
                }

                // Update badges
                document.querySelectorAll('.unm-cart-badge').forEach(badge => {
                    badge.textContent = data.cart_count;
                });

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
            confirmButtonColor: '#dc3545',
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

    const discountEl = document.getElementById('summaryDiscount');
    if (discountEl) discountEl.textContent = '-₹' + data.discount;

    const shippingEl = document.getElementById('summaryShipping');
    if (shippingEl) {
        const shipVal = parseFloat(data.shipping) || 0;
        shippingEl.innerHTML = shipVal > 0 ? '₹' + data.shipping : '<span class="text-success fw-bold">FREE</span>';
    }

    const grandTotalEl = document.getElementById('summaryGrandTotal');
    if (grandTotalEl) grandTotalEl.textContent = '₹' + data.grand_total;

    // Update free shipping progress meter text
    const meterEl = document.getElementById('shipMeterText');
    if (meterEl) {
        const subNumeric = parseFloat(data.subtotal.replace(/,/g, '')) || 0;
        if (subNumeric >= 500) {
            meterEl.innerHTML = '🎉 You qualify for <strong>FREE Express Shipping</strong>!';
        } else {
            const needed = (500 - subNumeric).toFixed(2);
            meterEl.innerHTML = `Add <strong>₹${needed}</strong> more to get <strong>FREE Shipping</strong>!`;
        }
    }
}

function triggerCheckout() {
    window.location.href = 'checkout.php';
}
</script>

<?php include_once 'includes/footer.php'; ?>
