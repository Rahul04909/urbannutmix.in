<?php
// admin/products/add-product-category.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = '';
$error = '';
$name = '';
$status = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('add_category', $csrf)) {
        $error = 'Invalid request - session token expired, please click submit once more.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        try {
            $pdo = Database::getConnection();

            if ($name === '' || mb_strlen($name) > 100) {
                $error = 'Category name is required and must be under 100 characters.';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
                if ($slug === '') {
                    $slug = 'category-' . time();
                }

                $stmt = $pdo->prepare('SELECT id FROM product_categories WHERE slug = :slug LIMIT 1');
                $stmt->execute(['slug' => $slug]);
                if ($stmt->fetch()) {
                    $error = 'A category with this name already exists.';
                } else {
                    $image = 'default.png';

                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 2 * 1024 * 1024;
                        $file = $_FILES['image'];

                        if (!in_array($file['type'], $allowedTypes, true)) {
                            $error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
                        } elseif ($file['size'] > $maxSize) {
                            $error = 'Image size must be less than 2MB.';
                        } else {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            $filename = 'cat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $uploadDir = __DIR__ . '/../src/images/category/';

                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0755, true);
                            }

                            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                                $image = $filename;
                            } else {
                                $error = 'Failed to upload image. Please try again.';
                            }
                        }
                    }

                    if ($error === '') {
                        $stmt = $pdo->prepare('INSERT INTO product_categories (name, slug, image, status) VALUES (:name, :slug, :image, :status)');
                        $stmt->execute([
                            'name' => $name,
                            'slug' => $slug,
                            'image' => $image,
                            'status' => $status,
                        ]);

                        $success = 'Category "' . htmlspecialchars($name) . '" added successfully.';
                        $name = '';
                        $status = 'active';
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Add category error: ' . $e->getMessage());
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$csrf_token = Session::csrfToken('add_category');

include __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Product Category</h3>
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

                <form method="POST" enctype="multipart/form-data">
                    <!-- UNM-CSRF-V2 -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                            value="<?= htmlspecialchars($name) ?>" required maxlength="100"
                            placeholder="e.g. Dry Fruits, Nuts, Seeds">
                        <small class="text-muted">A URL-friendly slug will be generated automatically.</small>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Category Image</label>
                        <input type="file" class="form-control" id="image" name="image"
                            accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="text-muted">JPG, PNG, GIF or WebP. Max 2MB. Optional.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="statusActive" value="active"
                                <?= $status === 'active' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="statusActive">Active</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive"
                                <?= $status === 'inactive' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="statusInactive">Inactive</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Category
                    </button>
                    <a href="manage-category.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Categories
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
