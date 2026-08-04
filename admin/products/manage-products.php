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
$activeCount = 0;

// Helper functions (wrapped in function_exists guards to avoid redeclaration)
if (!function_exists('unm_build_page_url')) {
    function unm_build_page_url($targetPage, $q = '', $catId = 0, $perPage = 10) {
        $query = ['page' => max(1, $targetPage)];
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($catId > 0) {
            $query['category_id'] = $catId;
        }
        if ($perPage !== 10) {
            $query['per_page'] = $perPage;
        }
        return 'manage-products.php?' . http_build_query($query);
    }
}

if (!function_exists('unm_get_product_thumbnail')) {
    function unm_get_product_thumbnail($product) {
        $img = trim(isset($product['image']) ? $product['image'] : '');
        $name = strtolower(trim(isset($product['name']) ? $product['name'] : ''));
        $slug = strtolower(trim(isset($product['slug']) ? $product['slug'] : ''));

        if ($img !== '' && $img !== 'default.png') {
            $filename = basename($img);
            if (file_exists(__DIR__ . '/../src/images/products/' . $filename)) {
                return '../src/images/products/' . rawurlencode($filename);
            }
            if (file_exists(__DIR__ . '/../../src/images/products/' . $filename)) {
                return '../../src/images/products/' . rawurlencode($filename);
            }
            if (file_exists(__DIR__ . '/../../assets/images/' . $filename)) {
                return '../../assets/images/' . rawurlencode($filename);
            }
        }

        // Smart keyword fallbacks
        if (strpos($name, 'almond') !== false || strpos($slug, 'almond') !== false || strpos($name, 'badam') !== false) {
            return '../../assets/images/hero-banners/almonds.png';
        }
        if (strpos($name, 'cashew') !== false || strpos($slug, 'cashew') !== false || strpos($name, 'kaju') !== false) {
            return '../../assets/images/hero-banners/cashews.png';
        }
        if (strpos($name, 'pista') !== false || strpos($slug, 'pista') !== false || strpos($name, 'pistachio') !== false) {
            return '../../assets/images/hero-banners/pista.png';
        }
        if (strpos($name, 'raisin') !== false || strpos($slug, 'raisin') !== false || strpos($name, 'kishmish') !== false) {
            return '../../assets/images/hero-banners/raisins.png';
        }

        return '../src/images/logo.png';
    }
}

if (!function_exists('unm_get_discount_percent')) {
    function unm_get_discount_percent($mrp, $price) {
        if ($mrp <= 0.001 || $mrp <= $price) {
            return 0;
        }
        return (int) round((($mrp - $price) / $mrp) * 100);
    }
}

try {
    $pdo = Database::getConnection();

    // Fetch active categories for dropdown
    $categories = $pdo->query("SELECT id, name FROM product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Handle Delete POST Action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!Session::csrfVerify('delete_product', $csrf)) {
            $error = 'Security session expired. Please refresh the page and try again.';
        } else {
            $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
            $stmt = $pdo->prepare('SELECT id, name, image FROM products WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $productToDelete = $stmt->fetch();

            if (!$productToDelete) {
                $error = 'Product not found or already deleted.';
            } else {
                $stmt = $pdo->prepare('SELECT image FROM product_images WHERE product_id = :id');
                $stmt->execute(['id' => $id]);
                $galleryImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
                $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute(['id' => $id]);

                $productDir = __DIR__ . '/../src/images/products/';
                $allImages = array_merge([$productToDelete['image']], array_column($galleryImages, 'image'));
                foreach ($allImages as $imageFile) {
                    if ($imageFile !== '' && $imageFile !== 'default.png') {
                        $filePath = $productDir . $imageFile;
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
                $success = 'Product "' . htmlspecialchars($productToDelete['name']) . '" deleted successfully.';
            }
        }
    }

    // Filter & Pagination parameters
    $q = trim(isset($_GET['q']) ? $_GET['q'] : '');
    $catId = (int) (isset($_GET['category_id']) ? $_GET['category_id'] : 0);
    $page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
    $perPage = (int) (isset($_GET['per_page']) ? $_GET['per_page'] : 10);
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

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Count totals
    $stmtCount = $pdo->prepare("SELECT COUNT(*) AS cnt FROM products p $whereClause");
    $stmtCount->execute($params);
    $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalCount = $countRow ? (int) $countRow['cnt'] : 0;

    $stmtActive = $pdo->query("SELECT COUNT(*) AS cnt FROM products WHERE status = 'active'");
    $activeRow = $stmtActive->fetch(PDO::FETCH_ASSOC);
    $activeCount = $activeRow ? (int) $activeRow['cnt'] : 0;

    $totalPages = ($perPage > 0) ? max(1, (int) ceil($totalCount / $perPage)) : 1;
    $page = min($page, $totalPages);
    $offset = max(0, ($page - 1) * $perPage);

    // Fetch paginated products
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN product_categories c ON c.id = p.category_id
         $whereClause
         ORDER BY p.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (\Exception $e) {
    error_log('Manage products error: ' . $e->getMessage());
    $error = 'Database Notice: ' . htmlspecialchars($e->getMessage());
}

$csrf_token = Session::csrfToken('delete_product');
include __DIR__ . '/../header.php';
?>

<!-- Styling for Modern Management Table & Custom Pagination -->
<style>
.unm-card {
    border: 1px solid #e3e6f0;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    background: #ffffff;
    overflow: hidden;
}
.unm-card-header {
    background: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
    padding: 1rem 1.25rem;
}
.unm-stats-badge {
    font-size: 0.85rem;
    padding: 0.35rem 0.65rem;
    border-radius: 20px;
}
.unm-thumb-box {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    object-fit: contain;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 2px;
}
.unm-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
    list-style: none;
    padding: 0;
    margin: 0;
}
.unm-page-item {
    display: inline-block;
}
.unm-page-link {
    display: inline-block;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 600;
    color: #28a745;
    background: #ffffff;
    border: 1px solid #28a745;
    border-radius: 6px;
    text-decoration: none !important;
    transition: all 0.2s ease-in-out;
}
.unm-page-link:hover {
    color: #ffffff !important;
    background-color: #28a745 !important;
}
.unm-page-item.active .unm-page-link {
    color: #ffffff !important;
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
}
.unm-page-item.disabled .unm-page-link {
    color: #a0aec0 !important;
    background-color: #f7fafc !important;
    border-color: #e2e8f0 !important;
    cursor: not-allowed;
    opacity: 0.7;
    pointer-events: none;
}
</style>

<div class="container-fluid py-3">
    <!-- Header Title & Action -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-2">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-boxes text-success me-2"></i>Product Catalog Management
            </h2>
            <p class="text-muted small mb-0">View, search, filter, and manage all store items and inventory.</p>
        </div>
        <div>
            <a href="add-product.php" class="btn btn-success px-3 shadow-sm fw-semibold">
                <i class="fas fa-plus-circle me-1"></i> Add New Product
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($success !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="unm-card">
        <!-- Card Header with Stats -->
        <div class="unm-card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success unm-stats-badge">
                    <i class="fas fa-cubes me-1"></i> Total: <?= $totalCount ?>
                </span>
                <span class="badge bg-primary unm-stats-badge">
                    <i class="fas fa-check me-1"></i> Active: <?= $activeCount ?>
                </span>
                <?php if ($q !== '' || $catId > 0): ?>
                    <span class="badge bg-warning text-dark unm-stats-badge">
                        <i class="fas fa-filter me-1"></i> Filter Active
                    </span>
                <?php endif; ?>
            </div>
            <div class="text-muted small">
                Showing <strong><?= $offset + 1 ?></strong> - <strong><?= min($totalCount, $offset + count($products)) ?></strong> of <strong><?= $totalCount ?></strong>
            </div>
        </div>

        <div class="card-body p-3">
            <!-- Filter & Search Controls -->
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-4 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name or slug..."
                            value="<?= htmlspecialchars($q) ?>">
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
                <div class="col-md-2 col-lg-2">
                    <select name="per_page" class="form-select" onchange="this.form.submit()" title="Items Per Page">
                        <?php foreach ([10, 25, 50, 100] as $pp): ?>
                            <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter"></i> Filter</button>
                    <?php if ($q !== '' || $catId > 0 || $perPage !== 10): ?>
                        <a href="manage-products.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fas fa-undo"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (count($products) === 0): ?>
                <div class="text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="fas fa-box-open fa-4x opacity-50"></i>
                    </div>
                    <h5 class="fw-semibold text-dark">No Products Found</h5>
                    <p class="text-muted small">Try clearing filters or adding a new product to your inventory.</p>
                    <a href="add-product.php" class="btn btn-success btn-sm mt-2">
                        <i class="fas fa-plus me-1"></i> Add Product Now
                    </a>
                </div>
            <?php else: ?>

                <?php
                // Pagination Window Range Calculation
                $startPage = max(1, $page - 2);
                $endPage   = min($totalPages, $page + 2);
                if ($page <= 3) {
                    $endPage = min($totalPages, 5);
                }
                if ($page >= $totalPages - 2) {
                    $startPage = max(1, $totalPages - 4);
                }
                ?>

                <!-- TOP PAGINATION BAR -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between p-2 mb-3 bg-light border rounded gap-2">
                        <div class="small text-muted">
                            Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                        </div>
                        <ul class="unm-pagination">
                            <li class="unm-page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="unm-page-link" href="<?= $page > 1 ? unm_build_page_url($page - 1, $q, $catId, $perPage) : '#' ?>">&laquo; Prev</a>
                            </li>

                            <?php if ($startPage > 1): ?>
                                <li class="unm-page-item"><a class="unm-page-link" href="<?= unm_build_page_url(1, $q, $catId, $perPage) ?>">1</a></li>
                                <?php if ($startPage > 2): ?>
                                    <li class="unm-page-item disabled"><span class="unm-page-link">&hellip;</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="unm-page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="unm-page-link" href="<?= unm_build_page_url($i, $q, $catId, $perPage) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="unm-page-item disabled"><span class="unm-page-link">&hellip;</span></li>
                                <?php endif; ?>
                                <li class="unm-page-item"><a class="unm-page-link" href="<?= unm_build_page_url($totalPages, $q, $catId, $perPage) ?>"><?= $totalPages ?></a></li>
                            <?php endif; ?>

                            <li class="unm-page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="unm-page-link" href="<?= $page < $totalPages ? unm_build_page_url($page + 1, $q, $catId, $perPage) : '#' ?>">Next &raquo;</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- PRODUCTS TABLE -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle border mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 75px;">Image</th>
                                <th>Product Details</th>
                                <th>Category</th>
                                <th>Pricing</th>
                                <th>Stock / Weight</th>
                                <th>Status</th>
                                <th style="width: 140px;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $index => $product): ?>
                                <tr>
                                    <td class="text-muted small"><?= $offset + $index + 1 ?></td>
                                    <td>
                                        <img src="<?= htmlspecialchars(unm_get_product_thumbnail($product)) ?>"
                                            alt="<?= htmlspecialchars($product['name']) ?>" class="unm-thumb-box shadow-sm">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="text-muted small"><code><?= htmlspecialchars($product['slug']) ?></code></div>
                                    </td>
                                    <td>
                                        <?php if ($product['category_name']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                                                <?= htmlspecialchars($product['category_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">Uncategorized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">&#8377;<?= number_format((float) (isset($product['price']) ? $product['price'] : 0), 2) ?></div>
                                        <?php
                                            $mrp = (float) (isset($product['mrp']) ? $product['mrp'] : 0);
                                            $price = (float) (isset($product['price']) ? $product['price'] : 0);
                                            $discount = unm_get_discount_percent($mrp, $price);
                                            if ($discount > 0):
                                        ?>
                                            <div class="small">
                                                <span class="text-muted text-decoration-line-through me-1">&#8377;<?= number_format($mrp, 2) ?></span>
                                                <span class="badge bg-danger ms-1"><?= $discount ?>% OFF</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= rtrim(rtrim(number_format((float) (isset($product['quantity']) ? $product['quantity'] : 0), 2), '0'), '.') ?></span>
                                        <span class="text-muted small"><?= htmlspecialchars(isset($product['unit']) ? $product['unit'] : 'gram') ?></span>
                                    </td>
                                    <td>
                                        <?php if ((isset($product['status']) ? $product['status'] : 'active') === 'active'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit-product.php?id=<?= (int) $product['id'] ?>"
                                                class="btn btn-outline-primary" title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../../product.php?slug=<?= urlencode(isset($product['slug']) ? $product['slug'] : '') ?>" target="_blank"
                                                class="btn btn-outline-secondary" title="View Live Product Page">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-delete-product"
                                                data-id="<?= (int) $product['id'] ?>"
                                                data-name="<?= htmlspecialchars(isset($product['name']) ? $product['name'] : '') ?>" title="Delete Product">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- BOTTOM PAGINATION BAR -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between p-2 mt-3 bg-light border rounded gap-2">
                        <div class="small text-muted">
                            Showing <strong><?= $offset + 1 ?></strong> - <strong><?= min($totalCount, $offset + count($products)) ?></strong> of <strong><?= $totalCount ?></strong>
                        </div>
                        <ul class="unm-pagination">
                            <li class="unm-page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="unm-page-link" href="<?= $page > 1 ? unm_build_page_url($page - 1, $q, $catId, $perPage) : '#' ?>">&laquo; Prev</a>
                            </li>

                            <?php if ($startPage > 1): ?>
                                <li class="unm-page-item"><a class="unm-page-link" href="<?= unm_build_page_url(1, $q, $catId, $perPage) ?>">1</a></li>
                                <?php if ($startPage > 2): ?>
                                    <li class="unm-page-item disabled"><span class="unm-page-link">&hellip;</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="unm-page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="unm-page-link" href="<?= unm_build_page_url($i, $q, $catId, $perPage) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="unm-page-item disabled"><span class="unm-page-link">&hellip;</span></li>
                                <?php endif; ?>
                                <li class="unm-page-item"><a class="unm-page-link" href="<?= unm_build_page_url($totalPages, $q, $catId, $perPage) ?>"><?= $totalPages ?></a></li>
                            <?php endif; ?>

                            <li class="unm-page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="unm-page-link" href="<?= $page < $totalPages ? unm_build_page_url($page + 1, $q, $catId, $perPage) : '#' ?>">Next &raquo;</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden Delete Submission Form -->
<form id="deleteProductForm" method="POST" style="display: none;">
    <!-- UNM-CSRF-V2 -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteProductId" value="0">
</form>

<!-- SweetAlert2 Script -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete-product');
    const deleteForm = document.getElementById('deleteProductForm');
    const deleteInput = document.getElementById('deleteProductId');

    deleteButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');

            Swal.fire({
                title: 'Delete product?',
                text: 'Product "' + name + '" and all attached gallery images will be permanently removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then(function(result) {
                if (result.isConfirmed) {
                    deleteInput.value = id;
                    deleteForm.submit();
                }
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
