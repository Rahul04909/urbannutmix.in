<?php
// admin/products/manage-category.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = '';
$error = '';
$categories = [];

try {
    $pdo = Database::getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Session::csrfVerify('delete_category', $csrf)) {
            $error = 'Invalid request - session token expired, please click submit once more.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, name, image FROM product_categories WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $category = $stmt->fetch();

            if (!$category) {
                $error = 'Category not found or already deleted.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM product_categories WHERE id = :id');
                $stmt->execute(['id' => $id]);

                if ($category['image'] !== 'default.png') {
                    $imagePath = __DIR__ . '/../src/images/category/' . $category['image'];
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }

                $success = 'Category "' . htmlspecialchars($category['name']) . '" deleted successfully.';
            }
        }
    }

    $categories = $pdo->query('SELECT * FROM product_categories ORDER BY id DESC')->fetchAll();
} catch (\Throwable $e) {
    error_log('Manage categories error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

$csrf_token = Session::csrfToken('delete_category');

include __DIR__ . '/../header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-tags"></i> All Product Categories (<?= count($categories) ?>)</h3>
        <div class="card-tools">
            <a href="add-product-category.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Add New Category
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

        <?php if (count($categories) === 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <p class="text-muted">No categories yet. Click "Add New Category" to create your first one.</p>
                <a href="add-product-category.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Category
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead>
                        <tr>
                            <th style="width:60px;">#</th>
                            <th style="width:90px;">Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $index => $category): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?php if ($category['image'] !== 'default.png' && file_exists(__DIR__ . '/../src/images/category/' . $category['image'])): ?>
                                        <img src="../src/images/category/<?= htmlspecialchars($category['image']) ?>"
                                            alt="<?= htmlspecialchars($category['name']) ?>" class="img-thumbnail"
                                            style="width:60px;height:60px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light border"
                                            style="width:60px;height:60px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold"><?= htmlspecialchars($category['name']) ?></td>
                                <td><code><?= htmlspecialchars($category['slug']) ?></code></td>
                                <td>
                                    <?php if ($category['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($category['created_at'])) ?></td>
                                <td>
                                    <a href="edit-product-category.php?id=<?= (int) $category['id'] ?>"
                                        class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display:inline;" class="delete-category-form">
                                        <!-- UNM-CSRF-V2 -->
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                            data-category-name="<?= htmlspecialchars($category['name']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.delete-category-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = this.querySelector('button').getAttribute('data-category-name');
            Swal.fire({
                title: 'Delete category?',
                text: 'Category "' + name + '" will be permanently deleted.',
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
