<?php
/**
 * UrbanNutMix - Order Management Admin Dashboard
 * Features: Statistics, Search & Pagination, Status updates (CSRF protected), AJAX detailed item modals.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = Session::get('flash_success', '');
Session::remove('flash_success');
$error = Session::get('flash_error', '');
Session::remove('flash_error');

try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    error_log("Manage Orders Page DB Connection Error: " . $e->getMessage());
    $error = "A database connection error occurred.";
}

// --- AJAX GET ORDER DETAILS ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    header('Content-Type: application/json');
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $orderId]);
        $order = $orderStmt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }

        $itemsStmt = $pdo->prepare(
            "SELECT oi.*, p.image 
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :id"
        );
        $itemsStmt->execute(['id' => $orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format dates and outputs
        $order['created_at_fmt'] = date('d M Y, h:i A', strtotime($order['created_at']));
        $order['subtotal_fmt'] = number_format((float)$order['subtotal'], 2);
        $order['discount_fmt'] = number_format((float)$order['discount'], 2);
        $order['shipping_fmt'] = number_format((float)$order['shipping'], 2);
        $order['grand_total_fmt'] = number_format((float)$order['grand_total'], 2);

        foreach ($items as &$it) {
            $it['price_fmt'] = number_format((float)$it['price'], 2);
            $it['total_price_fmt'] = number_format((float)$it['total_price'], 2);
            if (empty($it['image']) || $it['image'] === 'default.png') {
                $it['img_src'] = BASE_URL . 'assets/images/logo-bg.jpg';
            } else {
                $it['img_src'] = BASE_URL . 'admin/src/images/products/' . $it['image'];
            }
        }

        echo json_encode([
            'success' => true,
            'order' => $order,
            'items' => $items
        ]);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// --- POST ORDER STATUS UPDATE ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newOrderStatus = trim($_POST['order_status'] ?? '');
    $newPaymentStatus = trim($_POST['payment_status'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Session::csrfVerify('manage_orders', $csrfToken)) {
        Session::set('flash_error', 'Security token expired or invalid. Please try again.');
        header('Location: manage-orders.php');
        exit;
    }

    if ($orderId > 0) {
        try {
            $updateStmt = $pdo->prepare(
                "UPDATE orders 
                 SET order_status = :order_status, payment_status = :payment_status 
                 WHERE id = :id"
            );
            $updateStmt->execute([
                'order_status' => $newOrderStatus,
                'payment_status' => $newPaymentStatus,
                'id' => $orderId
            ]);

            Session::set('flash_success', 'Order state updated successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', 'Failed to update order state: ' . $e->getMessage());
        }
    }
    header('Location: manage-orders.php');
    exit;
}

// --- DASHBOARD STATISTICS SUMMARY ---
$stats = [
    'total_count' => 0,
    'revenue' => 0.00,
    'pending_orders' => 0,
    'delivered_orders' => 0
];
try {
    $stats['total_count'] = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['revenue'] = (float)$pdo->query("SELECT SUM(grand_total) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $stats['pending_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
    $stats['delivered_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'")->fetchColumn();
} catch (\Throwable $e) {
    error_log("Failed to load admin order stats: " . $e->getMessage());
}

// --- FILTER & PAGINATION LOGIC ---
$searchTerm = trim($_GET['search'] ?? '');
$filterOrderStatus = trim($_GET['order_status'] ?? '');
$filterPaymentStatus = trim($_GET['payment_status'] ?? '');

$whereConditions = [];
$params = [];

if ($searchTerm !== '') {
    $whereConditions[] = "(order_number LIKE :search OR customer_name LIKE :search OR customer_email LIKE :search OR customer_mobile LIKE :search)";
    $params['search'] = '%' . $searchTerm . '%';
}
if ($filterOrderStatus !== '') {
    $whereConditions[] = "order_status = :order_status";
    $params['order_status'] = $filterOrderStatus;
}
if ($filterPaymentStatus !== '') {
    $whereConditions[] = "payment_status = :payment_status";
    $params['payment_status'] = $filterPaymentStatus;
}

$whereSql = '';
if (!empty($whereConditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Count items for pagination
$totalOrders = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders $whereSql");
    $countStmt->execute($params);
    $totalOrders = (int)$countStmt->fetchColumn();
} catch (\Throwable $e) {
    error_log("Failed to count admin orders: " . $e->getMessage());
}

$perPage = 10;
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$orders = [];
try {
    $query = "SELECT * FROM orders $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset";
    $selectStmt = $pdo->prepare($query);
    $selectStmt->execute($params);
    $orders = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Failed to fetch admin orders: " . $e->getMessage());
}

$page_title = "Manage Customer Orders";
$breadcrumb_Items = [
    ["title" => "Dashboard", "url" => "index.php"],
    ["title" => "Orders", "url" => "#"]
];
include_once __DIR__ . '/../header.php';
?>

<!-- Info cards -->
<div class="row">
    <!-- Total Orders -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-white border rounded shadow-sm p-3">
            <div class="inner">
                <h3 class="fw-bold mb-1 text-dark"><?= $stats['total_count'] ?></h3>
                <p class="text-muted mb-0">Total Orders</p>
            </div>
            <div class="icon text-primary position-absolute" style="top:10px; right:15px; font-size: 2.2rem; opacity: 0.3;">
                <i class="fas fa-shopping-bag"></i>
            </div>
        </div>
    </div>
    <!-- Total Revenue -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-white border rounded shadow-sm p-3">
            <div class="inner">
                <h3 class="fw-bold mb-1 text-success">₹<?= number_format($stats['revenue'], 2) ?></h3>
                <p class="text-muted mb-0">Total Revenue (Paid)</p>
            </div>
            <div class="icon text-success position-absolute" style="top:10px; right:15px; font-size: 2.2rem; opacity: 0.3;">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
    </div>
    <!-- Pending Orders -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-white border rounded shadow-sm p-3">
            <div class="inner">
                <h3 class="fw-bold mb-1 text-warning"><?= $stats['pending_orders'] ?></h3>
                <p class="text-muted mb-0">Pending Orders</p>
            </div>
            <div class="icon text-warning position-absolute" style="top:10px; right:15px; font-size: 2.2rem; opacity: 0.3;">
                <i class="fas fa-clock"></i>
            </div>
        </div>
    </div>
    <!-- Delivered Orders -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-white border rounded shadow-sm p-3">
            <div class="inner">
                <h3 class="fw-bold mb-1 text-info"><?= $stats['delivered_orders'] ?></h3>
                <p class="text-muted mb-0">Delivered Orders</p>
            </div>
            <div class="icon text-info position-absolute" style="top:10px; right:15px; font-size: 2.2rem; opacity: 0.3;">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Alert messages -->
<?php if ($success !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check me-2"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filters Form -->
<div class="card card-default border shadow-sm rounded mb-4">
    <div class="card-body p-3">
        <form method="GET" action="manage-orders.php" class="row g-2">
            <div class="col-md-4 col-sm-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer, order ID..." value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-3 col-6">
                <select name="order_status" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Order Status --</option>
                    <option value="pending" <?= $filterOrderStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="processing" <?= $filterOrderStatus === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $filterOrderStatus === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $filterOrderStatus === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $filterOrderStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-3 col-6">
                <select name="payment_status" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Payment Status --</option>
                    <option value="pending" <?= $filterPaymentStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $filterPaymentStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="failed" <?= $filterPaymentStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="refunded" <?= $filterPaymentStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-2 col-12 d-grid">
                <a href="manage-orders.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card card-default border shadow-sm rounded">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 180px;">Order No.</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                        <th class="text-center" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-30"></i>
                                <p class="mb-0">No customer orders found matching current filters.</p>
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($orders as $ord): 
                            // Badges styling
                            $payBadgeClass = 'bg-secondary';
                            if ($ord['payment_status'] === 'paid') $payBadgeClass = 'bg-success';
                            elseif ($ord['payment_status'] === 'pending') $payBadgeClass = 'bg-warning text-dark';
                            elseif ($ord['payment_status'] === 'failed') $payBadgeClass = 'bg-danger';

                            $ordBadgeClass = 'bg-secondary';
                            if ($ord['order_status'] === 'delivered') $ordBadgeClass = 'bg-success';
                            elseif ($ord['order_status'] === 'pending') $ordBadgeClass = 'bg-warning text-dark';
                            elseif ($ord['order_status'] === 'processing') $ordBadgeClass = 'bg-info text-white';
                            elseif ($ord['order_status'] === 'shipped') $ordBadgeClass = 'bg-primary';
                            elseif ($ord['order_status'] === 'cancelled') $ordBadgeClass = 'bg-danger';
                    ?>
                        <tr>
                            <td class="ps-3 font-monospace fw-semibold"><?= htmlspecialchars($ord['order_number']) ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                <div class="small text-muted"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($ord['customer_email']) ?></div>
                            </td>
                            <td><?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></td>
                            <td class="font-monospace fw-semibold text-dark">₹<?= number_format((float)$ord['grand_total'], 2) ?></td>
                            <td><span class="badge <?= $payBadgeClass ?>"><?= strtoupper($ord['payment_status']) ?></span></td>
                            <td><span class="badge <?= $ordBadgeClass ?>"><?= strtoupper($ord['order_status']) ?></span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="viewOrderDetails(<?= $ord['id'] ?>)" title="View Order Details"><i class="fas fa-eye"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="openUpdateStatusModal(<?= $ord['id'] ?>, '<?= $ord['order_status'] ?>', '<?= $ord['payment_status'] ?>', '<?= htmlspecialchars($ord['order_number']) ?>')" title="Update Status"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Simple Bootstrap Pagination inside card footer -->
<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-3 mb-5">
        <nav aria-label="Orders Pagination">
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="manage-orders.php?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"><i class="fas fa-angle-double-left"></i></a>
                </li>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="manage-orders.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="fas fa-angle-left"></i></a>
                </li>
                <?php
                for ($p = 1; $p <= $totalPages; $p++):
                    if ($p === 1 || $p === $totalPages || ($p >= $page - 2 && $p <= $page + 2)):
                ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="manage-orders.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endif; endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="manage-orders.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="fas fa-angle-right"></i></a>
                </li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="manage-orders.php?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><i class="fas fa-angle-double-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<!-- ── DETAIL MODAL VIEW ────────────────────────────────────── -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="orderDetailsModalLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsModalContent">
                <!-- Loaded dynamically via JS AJAX -->
                <div class="text-center py-4">
                    <span class="spinner-border text-primary" role="status"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ── EDIT STATUS MODAL VIEW ───────────────────────────────── -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="updateStatusModalLabel">Update Order State</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage-orders.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="csrf_token" value="<?= Session::csrfToken('manage_orders') ?>">
                <input type="hidden" name="order_id" id="editOrderId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Order Reference</label>
                        <input type="text" class="form-control bg-light text-dark font-monospace fw-bold" id="editOrderNumber" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="editOrderStatus" class="form-label small fw-bold">Order Status</label>
                        <select name="order_status" id="editOrderStatus" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="editPaymentStatus" class="form-label small fw-bold">Payment Status</label>
                        <select name="payment_status" id="editPaymentStatus" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-header border-top justify-content-end bg-light p-2">
                    <button type="button" class="btn btn-secondary me-2 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewOrderDetails(orderId) {
    const modalEl = document.getElementById('orderDetailsModal');
    const contentEl = document.getElementById('orderDetailsModalContent');
    const modal = new bootstrap.Modal(modalEl);
    
    // Show spinner
    contentEl.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;"></div>
            <p class="text-muted mt-2">Fetching order records...</p>
        </div>`;
    
    modal.show();

    fetch('manage-orders.php?action=get_details&id=' + orderId)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const order = data.order;
            const items = data.items;
            
            // Build items HTML
            let itemsHtml = '';
            items.forEach(it => {
                itemsHtml += `
                    <tr class="border-bottom">
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="${it.img_src}" alt="${it.product_name}" class="rounded border me-3" style="width: 42px; height: 42px; object-fit: cover;">
                                <div>
                                    <strong class="text-dark small d-block">${it.product_name}</strong>
                                    <span class="text-muted small">${it.quantity} ${it.unit}</span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center font-monospace small">₹${it.price_fmt}</td>
                        <td class="text-center small">${parseInt(it.quantity)}</td>
                        <td class="text-end font-monospace fw-semibold text-dark small">₹${it.total_price_fmt}</td>
                    </tr>`;
            });

            contentEl.innerHTML = `
                <div class="row g-3 mb-4">
                    <div class="col-md-6 border-end">
                        <h6 class="text-uppercase text-muted fw-bold small mb-2"><i class="fas fa-user-circle me-1"></i> Customer Profile</h6>
                        <p class="mb-1 text-dark"><strong>Name:</strong> ${order.customer_name}</p>
                        <p class="mb-1 text-muted"><strong>Email:</strong> ${order.customer_email}</p>
                        <p class="mb-1 text-muted"><strong>Mobile:</strong> ${order.customer_mobile}</p>
                        <p class="mb-0 text-muted"><strong>Created At:</strong> ${order.created_at_fmt}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-muted fw-bold small mb-2"><i class="fas fa-map-marker-alt me-1"></i> Delivery Location</h6>
                        <p class="mb-1 text-muted">${order.address_line1}</p>
                        ${order.address_line2 ? `<p class="mb-1 text-muted">${order.address_line2}</p>` : ''}
                        <p class="mb-0 text-muted">${order.city}, ${order.state} - ${order.pincode}</p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6 border-end">
                        <h6 class="text-uppercase text-muted fw-bold small mb-2"><i class="fas fa-credit-card me-1"></i> Billing &amp; Payment</h6>
                        <p class="mb-1"><strong>Method:</strong> ${order.payment_method}</p>
                        <p class="mb-0"><strong>Payment Status:</strong> <span class="badge ${order.payment_status === 'paid' ? 'bg-success' : 'bg-warning text-dark'}">${order.payment_status.toUpperCase()}</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-muted fw-bold small mb-2"><i class="fas fa-truck me-1"></i> Logistics Status</h6>
                        <p class="mb-0"><strong>Delivery Status:</strong> <span class="badge ${order.order_status === 'delivered' ? 'bg-success' : (order.order_status === 'cancelled' ? 'bg-danger' : 'bg-info')}">${order.order_status.toUpperCase()}</span></p>
                    </div>
                </div>

                <div class="border rounded p-3 bg-light">
                    <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2"><i class="fas fa-shopping-basket me-1"></i> Order Items</h6>
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <thead>
                            <tr class="border-bottom text-muted small">
                                <th>Product</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                            <!-- Calculations -->
                            <tr>
                                <td colspan="2"></td>
                                <td class="text-end text-muted small py-1">Subtotal:</td>
                                <td class="text-end font-monospace text-muted small py-1">₹${order.subtotal_fmt}</td>
                            </tr>
                            ${parseFloat(order.discount) > 0 ? `
                            <tr>
                                <td colspan="2"></td>
                                <td class="text-end text-success small py-1">Discount:</td>
                                <td class="text-end font-monospace text-success small py-1">-₹${order.discount_fmt}</td>
                            </tr>` : ''}
                            <tr>
                                <td colspan="2"></td>
                                <td class="text-end text-muted small py-1">Shipping:</td>
                                <td class="text-end font-monospace text-muted small py-1">${parseFloat(order.shipping) > 0 ? '₹' + order.shipping_fmt : 'FREE'}</td>
                            </tr>
                            <tr class="fw-bold fs-6">
                                <td colspan="2"></td>
                                <td class="text-end text-dark py-2">Grand Total:</td>
                                <td class="text-end font-monospace text-primary py-2">₹${order.grand_total_fmt}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            contentEl.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(err => {
        contentEl.innerHTML = `<div class="alert alert-danger">An error occurred loading order data: ${err}</div>`;
    });
}

function openUpdateStatusModal(orderId, orderStatus, paymentStatus, orderNumber) {
    document.getElementById('editOrderId').value = orderId;
    document.getElementById('editOrderNumber').value = orderNumber;
    document.getElementById('editOrderStatus').value = orderStatus;
    document.getElementById('editPaymentStatus').value = paymentStatus;
    
    const modalEl = document.getElementById('updateStatusModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
