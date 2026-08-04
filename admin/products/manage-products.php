<?php
// admin/products/manage-products.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = Session::get('flash_success', '');
Session::remove('flash_success');
$error = Session::get('flash_error', '');
Session::remove('flash_error');
$products = [];
$categories = [];
$totalPages = 1;
$totalCount = 0;

try {
    $pdo = Database::getConnection();

    // Fetch active categories for search filter
    $categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Session::csrfVerify('delete_product', $csrf)) {
            $error = 'Invalid request - session token expired, please click submit once more.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, name, image FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $product = $stmt->fetch();

            if (!$product) {
                $error = 'Product not found or already deleted.';
            } else {
                $stmt = $pdo->prepare('SELECT image FROM product_images WHERE product_id = :id');
                $stmt->execute(['id' => $id]);
                $images = $stmt->fetchAll();

                $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
                $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute(['id' => $id]);

                $productDir = __DIR__ . '/../src/images/products/';
                foreach (array_merge([$product['image']], array_column($images, 'image')) as $imageFile) {
                    if ($imageFile !== '' && $imageFile !== 'default.png') {
                        $path = $productDir . $imageFile;
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                }

                $success = 'Product "' . htmlspecialchars($product['name']) . '" deleted successfully.';
            }
        }
    }

    $q = trim($_GET['q'] ?? '');
    $catId = (int) ($_GET['category_id'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 25, 50, 100], true)) {
        $perPage = 10;
    }

    $whereConditions = [];
    $params = [];

    if ($q !== '') {
        $whereConditions[] = '(p.name LIKE :q OR p.slug LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    if ($catId > 0) {
        $whereConditions[] = 'p.category_id = :cid';
        $params['cid'] = $catId;
    }

    $where = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM products p $where");
    $stmt->execute($params);
    $totalCount = (int) ($stmt->fetch()['cnt'] ?? 0);
    $totalPages = ($perPage > 0) ? max(1, (int) ceil($totalCount / $perPage)) : 1;
    $page = min($page, $totalPages);
    $offset = max(0, ($page - 1) * $perPage);

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN product_categories c ON c.id = p.category_id
         $where
         ORDER BY p.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('Manage products error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

$csrf_token = Session::csrfToken('delete_product');

// Helper function to build pagination URLs preserving all GET query parameters
function build_page_url(int $targetPage, array $overrideParams = []): string {
    $query = $_GET;
    $query['page'] = max(1, $targetPage);
    foreach ($overrideParams as $key => $val) {
        if ($val === null || $val === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $val;
        }
    }
    return 'manage-products.php?' . http_build_query($query);
}

// Helper function to safely calculate discount percentage without division by zero
function get_discount_percentage(float $mrp, float $price): int {
    if ($mrp <= 0.001 || $mrp <= $price) {
        return 0;
    }
    return (int) round((($mrp - $price) / $mrp) * 100);
}

include __DIR__ . '/../header.php';
?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">
            <i class="fas fa-box"></i> All Products
            <span class="badge bg-secondary ms-2"><?= $totalCount ?></span>
        </h3>
        <div class="card-tools">
            <a href="add-product.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Controls Bar -->
        <form method="GET" class="row g-2 mb-4 align-items-center">
            <div class="col-md-5 col-lg-4">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search by product name or slug..."
                        value="<?= htmlspecialchars($q) ?>">
                    <button type="submit" class="btn btn-primary" title="Search"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <select name="category_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $catId === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-lg-2">
                <select name="per_page" class="form-select" onchange="this.form.submit()" title="Items per page">
                    <?php foreach ([10, 25, 50, 100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?> per page</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($q !== '' || $catId > 0 || $perPage !== 10): ?>
                <div class="col-md-2 col-lg-1">
                    <a href="manage-products.php" class="btn btn-outline-secondary w-100" title="Reset Filters"><i class="fas fa-undo"></i> Reset</a>
                </div>
            <?php endif; ?>
        </form>

        <?php if (count($products) === 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">No products found matching your search criteria.</p>
                <a href="add-product.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:80px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th style="width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $index => $product): ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td>
                                    <?php if ($product['image'] !== 'default.png' && file_exists(__DIR__ . '/../src/images/products/' . $product['image'])): ?>
                                        <img src="../src/images/products/<?= htmlspecialchars($product['image']) ?>"
                                            alt="<?= htmlspecialchars($product['name']) ?>" class="img-thumbnail"
                                            style="width:55px;height:55px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light border"
                                            style="width:55px;height:55px;border-radius:4px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($product['name']) ?>
                                    <div class="text-muted small"><code><?= htmlspecialchars($product['slug']) ?></code></div>
                                </td>
                                <td>
                                    <?php if ($product['category_name']): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($product['category_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Uncategorized</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold">&#8377;<?= number_format((float) ($product['price'] ?? 0), 2) ?></span>
                                    <?php
                                        $mrp = (float) ($product['mrp'] ?? 0);
                                        $price = (float) ($product['price'] ?? 0);
                                        $discount = get_discount_percentage($mrp, $price);
                                        if ($discount > 0):
                                    ?>
                                        <div>
                                            <span class="text-muted text-decoration-line-through small">&#8377;<?= number_format($mrp, 2) ?></span>
                                            <span class="badge bg-success ms-1"><?= $discount ?>% OFF</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= rtrim(rtrim(number_format((float) ($product['quantity'] ?? 0), 2), '0'), '.') ?> <?= htmlspecialchars($product['unit'] ?? 'gram') ?></td>
                                <td>
                                    <?php if (($product['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit-product.php?id=<?= (int) $product['id'] ?>"
                                        class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../../product.php?slug=<?= urlencode($product['slug'] ?? '') ?>" target="_blank"
                                        class="btn btn-sm btn-outline-secondary" title="View Live Product">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <form method="POST" style="display:inline;" class="delete-product-form">
                                        <!-- UNM-CSRF-V2 -->
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                            data-product-name="<?= htmlspecialchars($product['name'] ?? '') ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Professional Custom Pagination Styling -->
            <style>
            .pagination-custom .page-link {
                color: #28a745;
                background-color: #ffffff;
                border: 1px solid #dee2e6;
                border-radius: 6px;
                margin: 0 2px;
                padding: 0.35rem 0.75rem;
                font-weight: 500;
                font-size: 0.875rem;
                transition: all 0.2s ease-in-out;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .pagination-custom .page-link:hover {
                color: #1e7e34;
                background-color: #e8f5e9;
                border-color: #c8e6c9;
            }
            .pagination-custom .page-item.active .page-link {
                background-color: #28a745;
                border-color: #28a745;
                color: #ffffff;
                font-weight: 600;
                box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
            }
            .pagination-custom .page-item.disabled .page-link {
                color: #6c757d;
                background-color: #f8f9fa;
                border-color: #e9ecef;
                opacity: 0.65;
            }
            </style>

            <?php
            // Calculate visible pages window for professional pagination look
            $visiblePages = [];
            if ($totalPages <= 7) {
                for ($i = 1; $i <= $totalPages; $i++) {
                    $visiblePages[] = $i;
                }
            } else {
                if ($page <= 4) {
                    $visiblePages = [1, 2, 3, 4, 5, '...', $totalPages];
                } elseif ($page >= $totalPages - 3) {
                    $visiblePages = [1, '...', $totalPages - 4, $totalPages - 3, $totalPages - 2, $totalPages - 1, $totalPages];
                } else {
                    $visiblePages = [1, '...', $page - 1, $page, $page + 1, '...', $totalPages];
                }
            }
            ?>

            <!-- Professional Pagination Footer Bar -->
            <div class="card-footer bg-white border-top py-3 mt-3">
                <div class="row align-items-center g-3">
                    <!-- Left: Summary Info & Per-Page Quick Select -->
                    <div class="col-12 col-xl-4 d-flex flex-wrap align-items-center justify-content-between justify-content-xl-start gap-3">
                        <div class="text-muted small">
                            Showing <span class="fw-bold text-dark"><?= $totalCount > 0 ? $offset + 1 : 0 ?></span> to
                            <span class="fw-bold text-dark"><?= min($totalCount, $offset + count($products)) ?></span> of
                            <span class="fw-bold text-dark"><?= $totalCount ?></span> products
                            <?php if ($q !== '' || $catId > 0): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" title="Filters Applied"><i class="fas fa-filter me-1"></i>Filtered</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-1 text-muted small">
                            <label for="perPageSelectBottom" class="mb-0 text-nowrap">Per page:</label>
                            <select id="perPageSelectBottom" class="form-select form-select-sm w-auto" onchange="window.location.href=this.value">
                                <?php foreach ([10, 25, 50, 100] as $pp): ?>
                                    <option value="<?= build_page_url(1, ['per_page' => $pp]) ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Middle: Page Navigation Buttons -->
                    <div class="col-12 col-xl-5 d-flex justify-content-center">
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Products Pagination">
                                <ul class="pagination pagination-custom mb-0 flex-wrap justify-content-center">
                                    <!-- First Page -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $page > 1 ? build_page_url(1) : '#' ?>" title="First Page" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>

                                    <!-- Previous Page -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $page > 1 ? build_page_url($page - 1) : '#' ?>" title="Previous Page" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-angle-left"></i> Previous
                                        </a>
                                    </li>

                                    <!-- Page Number Buttons -->
                                    <?php foreach ($visiblePages as $p): ?>
                                        <?php if ($p === '...'): ?>
                                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                        <?php else: ?>
                                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= build_page_url((int)$p) ?>"><?= $p ?></a>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <!-- Next Page -->
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $page < $totalPages ? build_page_url($page + 1) : '#' ?>" title="Next Page" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            Next <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>

                                    <!-- Last Page -->
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= $page < $totalPages ? build_page_url($totalPages) : '#' ?>" title="Last Page" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Page Jump Input Form -->
                    <div class="col-12 col-xl-3 d-flex justify-content-center justify-content-xl-end">
                        <?php if ($totalPages > 1): ?>
                            <form method="GET" action="manage-products.php" class="d-flex align-items-center gap-1 text-muted small">
                                <?php foreach ($_GET as $key => $val): ?>
                                    <?php if ($key !== 'page'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars((string)$val) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <label for="jumpToPage" class="mb-0 text-nowrap">Page:</label>
                                <input type="number" id="jumpToPage" name="page" class="form-control form-control-sm text-center" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" style="width: 65px;" required>
                                <span class="text-nowrap me-1">of <?= $totalPages ?></span>
                                <button type="submit" class="btn btn-sm btn-outline-success px-2 py-1" title="Jump to page">Go</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.delete-product-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = this.querySelector('button').getAttribute('data-product-name');
            Swal.fire({
                title: 'Delete product?',
                text: 'Product "' + name + '" and all its gallery images will be permanently deleted.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
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
