<?php
// admin/products/edit-product-category.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = '';
$error = '';
$categoryId = (int) ($_GET['id'] ?? 0);
$category = null;

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('SELECT * FROM product_categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $categoryId]);
    $category = $stmt->fetch();

    if (!$category) {
        $error = 'Category not found. It may have been deleted.';
    }
} catch (\Throwable $e) {
    error_log('Edit category load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('edit_category', $csrf)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        try {
            if ($category) {
                if ($name === '' || mb_strlen($name) > 100) {
                    $error = 'Category name is required and must be under 100 characters.';
                } else {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
                    if ($slug === '') {
                        $slug = 'category-' . time();
                    }

                    $stmt = $pdo->prepare('SELECT id FROM product_categories WHERE slug = :slug AND id != :id LIMIT 1');
                    $stmt->execute(['slug' => $slug, 'id' => $category['id']]);
                    if ($stmt->fetch()) {
                        $error = 'A category with this name already exists.';
                    } else {
                        $image = $category['image'];

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
                                    $oldImage = $image;
                                    $image = $filename;

                                    if ($oldImage !== 'default.png') {
                                        $oldPath = $uploadDir . $oldImage;
                                        if (file_exists($oldPath)) {
                                            @unlink($oldPath);
                                        }
                                    }
                                } else {
                                    $error = 'Failed to upload image. Please try again.';
                                }
                            }
                        }

                        if ($error === '') {
                            $stmt = $pdo->prepare('UPDATE product_categories SET name = :name, slug = :slug, image = :image, status = :status WHERE id = :id');
                            $stmt->execute([
                                'name' => $name,
                                'slug' => $slug,
                                'image' => $image,
                                'status' => $status,
                                'id' => $category['id'],
                            ]);

                            $category['name'] = $name;
                            $category['slug'] = $slug;
                            $category['image'] = $image;
                            $category['status'] = $status;

                            $success = 'Category "' . htmlspecialchars($name) . '" updated successfully.';
                        }
                    }
                }
            } else {
                $error = 'Category not found. It may have been deleted.';
            }
        } catch (\Throwable $e) {
            error_log('Edit category error: ' . $e->getMessage());
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$csrf_token = Session::csrfToken('edit_category');

include __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Product Category</h3>
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

                <?php if ($category): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="<?= htmlspecialchars($category['name']) ?>" required maxlength="100">
                            <small class="text-muted">Slug: <code><?= htmlspecialchars($category['slug']) ?></code> (auto-updates on save)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <div>
                                <?php if ($category['image'] !== 'default.png' && file_exists(__DIR__ . '/../src/images/category/' . $category['image'])): ?>
                                    <img src="../src/images/category/<?= htmlspecialchars($category['image']) ?>"
                                        alt="<?= htmlspecialchars($category['name']) ?>" class="img-thumbnail mb-2"
                                        style="width:120px;height:120px;object-fit:cover;">
                                <?php else: ?>
                                    <p class="text-muted mb-2"><i class="fas fa-image"></i> No image uploaded yet.</p>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" id="image" name="image"
                                accept="image/jpeg,image/png,image/gif,image/webp">
                            <small class="text-muted">Upload a new image to replace the current one. JPG, PNG, GIF or WebP. Max 2MB.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusActive" value="active"
                                    <?= $category['status'] === 'active' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="statusActive">Active</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive"
                                    <?= $category['status'] === 'inactive' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="statusInactive">Inactive</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Category
                        </button>
                        <a href="manage-category.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Categories
                        </a>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted"><?= $error !== '' ? $error : 'Category not found.' ?></p>
                        <a href="manage-category.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Categories
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
