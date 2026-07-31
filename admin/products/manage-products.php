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

try {
    $pdo = Database::getConnection();

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
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 10;

    $where = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE p.name LIKE :q OR p.slug LIKE :q';
        $params['q'] = '%' . $q . '%';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM products p $where");
    $stmt->execute($params);
    $totalCount = (int) $stmt->fetch()['cnt'];
    $totalPages = max(1, (int) ceil($totalCount / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN product_categories c ON c.id = p.category_id
         $where
         ORDER BY p.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (\Throwable $e) {
    error_log('Manage products error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

$csrf_token = Session::csrfToken('delete_product');

include __DIR__ . '/../header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-box"></i> All Products (<?= $totalCount ?>)</h3>
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

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6 col-lg-4">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search by name or slug..."
                        value="<?= htmlspecialchars($q) ?>">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
                    <?php if ($q !== ''): ?>
                        <a href="manage-products.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if (count($products) === 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">No products found. Click "Add New Product" to create your first one.</p>
                <a href="add-product.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:80px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th style="width:160px;">Actions</th>
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
                                            style="width:60px;height:60px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light border"
                                            style="width:60px;height:60px;">
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
                                    <span class="fw-semibold">&#8377;<?= number_format((float) $product['price'], 2) ?></span>
                                    <?php
                                        $mrp = (float) ($product['mrp'] ?? 0);
                                        if ($mrp > (float) $product['price']):
                                            $discount = (int) round(($mrp - (float) $product['price']) / $mrp * 100);
                                    ?>
                                        <div>
                                            <span class="text-muted text-decoration-line-through small">&#8377;<?= number_format($mrp, 2) ?></span>
                                            <span class="badge bg-success ms-1"><?= $discount ?>% OFF</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= rtrim(rtrim(number_format((float) $product['quantity'], 2), '0'), '.') ?> <?= htmlspecialchars($product['unit']) ?></td>
                                <td>
                                    <?php if ($product['status'] === 'active'): ?>
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
                                    <form method="POST" style="display:inline;" class="delete-product-form">
                                        <!-- UNM-CSRF-V2 -->
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                            data-product-name="<?= htmlspecialchars($product['name']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link"
                                href="manage-products.php?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link"
                                    href="manage-products.php?page=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link"
                                href="manage-products.php?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
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
