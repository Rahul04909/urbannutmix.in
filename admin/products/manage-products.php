<?php
// admin/products/manage-products.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = Session::get('flash_success', '');
Session::remove('flash_success');
$error = Session::get('flash_error', '');
Session::remove('flash_error');

$products = [];
$totalPages = 1;
$totalCount = 0;
$categories = [];

try {
    $pdo = Database::getConnection();
    Database::ensureProductColumns();

    // Fetch categories for search filters
    $categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('manage-products load category error: ' . $e->getMessage());
    $error = 'Database connection error.';
}

// Handle Product Deletion via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('manage_products', $csrf)) {
        Session::set('flash_error', 'Invalid request - session token expired.');
        header('Location: manage-products.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('SELECT id, name, image FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $product = $stmt->fetch();

            if ($product) {
                $uploadDir = __DIR__ . '/../src/images/products/';

                // 1. Delete gallery images physically
                $gStmt = $pdo->prepare('SELECT image FROM product_images WHERE product_id = :id');
                $gStmt->execute(['id' => $id]);
                $gallery = $gStmt->fetchAll();
                foreach ($gallery as $gImg) {
                    if ($gImg['image'] !== 'default.png') {
                        $gPath = $uploadDir . $gImg['image'];
                        if (file_exists($gPath)) {
                            @unlink($gPath);
                        }
                    }
                }

                // 2. Delete main image physically
                if ($product['image'] !== 'default.png') {
                    $mPath = $uploadDir . $product['image'];
                    if (file_exists($mPath)) {
                        @unlink($mPath);
                    }
                }

                // 3. Delete database record (cascading deletes associated gallery images/reviews)
                $delStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
                $delStmt->execute(['id' => $id]);

                Session::set('flash_success', 'Product "' . htmlspecialchars($product['name']) . '" deleted successfully.');
            } else {
                Session::set('flash_error', 'Product not found or already deleted.');
            }
        } catch (\Throwable $e) {
            error_log('manage-products POST delete error: ' . $e->getMessage());
            Session::set('flash_error', 'Database error: ' . htmlspecialchars($e->getMessage()));
        }

        header('Location: manage-products.php');
        exit;
    }
}

// Load stats (Total, Active, Inactive, Low Stock)
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'low_stock' => 0
];

try {
    $stats['total'] = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $stats['active'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $stats['inactive'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'inactive'")->fetchColumn();
    $stats['low_stock'] = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE quantity < 10')->fetchColumn();
} catch (\Throwable $e) {
    error_log('manage-products load stats error: ' . $e->getMessage());
}

// Parse search and filter parameters
$q = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 25, 50, 100], true)) {
    $perPage = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));

// Build database query filters
$whereConditions = [];
$params = [];

if ($q !== '') {
    $whereConditions[] = '(p.name LIKE :q OR p.slug LIKE :q OR p.short_description LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

if ($categoryFilter !== '') {
    $whereConditions[] = 'p.category_id = :category_id';
    $params['category_id'] = (int)$categoryFilter;
}

if ($statusFilter !== '') {
    $whereConditions[] = 'p.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get total filtered count
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM products p $whereSql");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetch()['cnt'];

    // Adjust page numbers if bounds exceeded
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // Retrieve product rows
    $stmt = $pdo->prepare(
        "SELECT p.*, pc.name AS category_name
         FROM products p
         LEFT JOIN product_categories pc ON pc.id = p.category_id
         $whereSql
         ORDER BY p.id DESC
         LIMIT $perPage OFFSET $offset"
     );
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('manage-products list fetch error: ' . $e->getMessage());
    $error = 'Database error loading product lists.';
}

$csrf_token = Session::csrfToken('manage_products');
include __DIR__ . '/../header.php';
?>

<!-- Statistics Summary Banner -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= $stats['total'] ?></h3>
                <p>Total Products</p>
            </div>
            <div class="icon">
                <i class="fas fa-boxes"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= $stats['active'] ?></h3>
                <p>Active Products</p>
            </div>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-secondary">
            <div class="inner">
                <h3><?= $stats['inactive'] ?></h3>
                <p>Inactive Products</p>
            </div>
            <div class="icon">
                <i class="fas fa-pause-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning text-dark">
            <div class="inner text-white">
                <h3 class="text-white"><?= $stats['low_stock'] ?></h3>
                <p class="text-white">Low Stock Items (< 10)</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Main Products Table Card -->
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title mb-0">
            <i class="fas fa-box-open me-2 text-primary"></i> Product Catalog
            <span class="badge bg-primary ms-2"><?= $totalCount ?> total matches</span>
        </h3>
        <div class="card-tools">
            <a href="add-product.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus-circle me-1"></i> Add New Product
            </a>
        </div>
    </div>
    <div class="card-body">

        <!-- Flash messages -->
        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Filters Panel -->
        <form method="GET" class="row g-2 mb-4 align-items-end">
            <!-- Search Keyword -->
            <div class="col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="q" class="form-control text-sm" placeholder="Search by name, slug, summary..." value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>

            <!-- Category Filter -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-secondary">Category</label>
                <select name="category_id" class="form-select text-sm" onchange="this.form.submit()">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= (string)$categoryFilter === (string)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary">Status</label>
                <select name="status" class="form-select text-sm" onchange="this.form.submit()">
                    <option value="">-- All Statuses --</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <!-- Page Size -->
            <div class="col-md-1 col-sm-6">
                <label class="form-label small fw-semibold text-secondary">Show</label>
                <select name="per_page" class="form-select text-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $sz): ?>
                        <option value="<?= $sz ?>" <?= $perPage === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Actions -->
            <div class="col-md-2 col-sm-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 btn-sm text-nowrap"><i class="fas fa-filter me-1"></i> Apply</button>
                <?php if ($q !== '' || $categoryFilter !== '' || $statusFilter !== '' || $perPage !== 10): ?>
                    <a href="manage-products.php" class="btn btn-outline-secondary w-100 btn-sm text-nowrap"><i class="fas fa-undo me-1"></i> Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Products Data Table -->
        <?php if (empty($products)): ?>
            <div class="text-center py-5 border rounded bg-light">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h5 class="text-secondary">No products found</h5>
                <p class="text-muted small">Try modifying your filters, search term, or add a new product to begin.</p>
                <a href="add-product.php" class="btn btn-success mt-2">
                    <i class="fas fa-plus-circle me-1"></i> Add Product
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light text-center small text-uppercase">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 80px;">Thumbnail</th>
                            <th class="text-start">Product Details</th>
                            <th style="width: 140px;">Pricing (INR)</th>
                            <th style="width: 140px;">Stock</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 120px;">Created</th>
                            <th style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $index => $prod): ?>
                            <tr>
                                <td class="text-center font-monospace text-muted small"><?= $offset + $index + 1 ?></td>
                                <td class="text-center">
                                    <?php 
                                    $imageFile = $prod['image'];
                                    $imagePath = __DIR__ . '/../src/images/products/' . $imageFile;
                                    if ($imageFile !== 'default.png' && file_exists($imagePath)): 
                                    ?>
                                        <img src="../src/images/products/<?= htmlspecialchars($imageFile) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="img-thumbnail" style="width: 55px; height: 55px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light border rounded text-muted mx-auto" style="width: 55px; height: 55px;">
                                            <i class="fas fa-image fa-lg"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark text-lg lh-sm"><?= htmlspecialchars($prod['name']) ?></div>
                                    <div class="small text-muted mt-1 d-flex flex-wrap align-items-center gap-2">
                                        <span>Category: 
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($prod['category_name'] ?? 'Uncategorized') ?></span>
                                        </span>
                                        <span class="text-muted">|</span>
                                        <span>Slug: <code><?= htmlspecialchars($prod['slug']) ?></code></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="fw-semibold text-primary font-monospace">₹<?= number_format((float)$prod['price'], 2) ?></div>
                                    <?php if ((float)$prod['mrp'] > (float)$prod['price']): ?>
                                        <div class="text-muted small text-decoration-line-through font-monospace">₹<?= number_format((float)$prod['mrp'], 2) ?></div>
                                        <?php 
                                        $discPercent = round((((float)$prod['mrp'] - (float)$prod['price']) / (float)$prod['mrp']) * 100);
                                        ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-1 small font-monospace mt-1"><?= $discPercent ?>% OFF</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace text-sm">
                                    <?php if ((float)$prod['quantity'] <= 0): ?>
                                        <span class="text-danger fw-bold"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                    <?php else: ?>
                                        <?= (float)$prod['quantity'] ?> <?= htmlspecialchars($prod['unit']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($prod['status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-minus-circle me-1"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-sm text-muted">
                                    <?= date('d M Y', strtotime($prod['created_at'])) ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="edit-product.php?id=<?= (int)$prod['id'] ?>" class="btn btn-sm btn-primary" title="Edit Product">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" class="delete-product-form d-inline-block">
                                            <!-- CSRF token -->
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Product" data-product-name="<?= htmlspecialchars($prod['name']) ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

             <!-- Professional Pagination Footer Section -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 gap-2">
                <!-- Info count indicator -->
                <?php 
                $startItem = $totalCount > 0 ? $offset + 1 : 0;
                $endItem = min($offset + $perPage, $totalCount);
                ?>
                <div class="text-sm text-muted">
                    Showing <span class="fw-semibold text-dark"><?= $startItem ?></span> to 
                    <span class="fw-semibold text-dark"><?= $endItem ?></span> of 
                    <span class="fw-semibold text-dark"><?= $totalCount ?></span> products
                </div>

                <!-- Pagination navigation items -->
                <nav>
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <!-- First Page Button -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="manage-products.php?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" title="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>

                        <!-- Previous Button -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="manage-products.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" title="Previous">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <!-- Dynamic page items with window and ellipsis -->
                        <?php 
                        $rangeLimit = 2; // window of 2 pages around current page
                        $paginationRange = [];
                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i === 1 || $i === $totalPages || ($i >= $page - $rangeLimit && $i <= $page + $rangeLimit)) {
                                $paginationRange[] = $i;
                            }
                        }
                        if (empty($paginationRange)) {
                            $paginationRange = [1];
                        }
                        $paginationRange = array_unique($paginationRange);
                        sort($paginationRange);

                        $prevP = 0;
                        foreach ($paginationRange as $p):
                            if ($prevP > 0 && $p - $prevP > 1):
                        ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php 
                            endif; 
                            $activeClass = ($p === $page) ? 'active' : '';
                        ?>
                            <li class="page-item <?= $activeClass ?>">
                                <a class="page-link" href="manage-products.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php 
                            $prevP = $p;
                        endforeach; 
                        ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="manage-products.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" title="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>

                        <!-- Last Page Button -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="manage-products.php?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" title="Last Page">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SweetAlert2 Delete Dialog Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteForms = document.querySelectorAll('.delete-product-form');
    deleteForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const productName = this.querySelector('button').getAttribute('data-product-name');
            
            Swal.fire({
                title: 'Delete Product?',
                text: 'Are you sure you want to delete "' + productName + '"? This will permanently remove the product and all associated gallery images.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete product',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
