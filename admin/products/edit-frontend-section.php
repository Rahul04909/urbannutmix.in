<?php
// admin/products/edit-frontend-section.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$error      = '';
$categories = [];
$sec        = null;

$id = (int) ($_GET['id'] ?? 0);

try {
    $pdo = Database::getConnection();
    $categories = $pdo->query(
        "SELECT id, name FROM product_categories WHERE status = 'active' ORDER BY name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM frontend_product_sections WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $sec = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('edit-frontend-section load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

if (!$sec && $error === '') {
    Session::set('flash_error', 'Section not found.');
    header('Location: manage-frontend-sections.php');
    exit;
}

$form = $sec ?? [
    'section_title'  => '',
    'category_id'    => '',
    'products_limit' => 8,
    'sort_order'     => 0,
    'status'         => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('edit_fps_' . $id, $csrf)) {
        $error = 'Invalid request – session token expired, please submit again.';
    } else {
        $form = [
            'section_title'  => trim($_POST['section_title'] ?? ''),
            'category_id'    => (int) ($_POST['category_id'] ?? 0),
            'products_limit' => max(1, min(16, (int) ($_POST['products_limit'] ?? 8))),
            'sort_order'     => (int) ($_POST['sort_order'] ?? 0),
            'status'         => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];

        if ($form['section_title'] === '' || mb_strlen($form['section_title']) > 150) {
            $error = 'Section title is required and must be under 150 characters.';
        } elseif ($form['category_id'] <= 0) {
            $error = 'Please select a category.';
        } else {
            try {
                $pdo = Database::getConnection();
                $catCheck = $pdo->prepare("SELECT id FROM product_categories WHERE id = :id LIMIT 1");
                $catCheck->execute(['id' => $form['category_id']]);
                if (!$catCheck->fetch()) {
                    $error = 'Selected category not found.';
                } else {
                    $pdo->prepare(
                        'UPDATE frontend_product_sections
                         SET category_id = :cid, section_title = :title,
                             sort_order = :sort, products_limit = :limit, status = :status
                         WHERE id = :id'
                    )->execute([
                        'cid'    => $form['category_id'],
                        'title'  => $form['section_title'],
                        'sort'   => $form['sort_order'],
                        'limit'  => $form['products_limit'],
                        'status' => $form['status'],
                        'id'     => $id,
                    ]);
                    Session::set('flash_success', 'Section "' . htmlspecialchars($form['section_title']) . '" updated successfully.');
                    header('Location: manage-frontend-sections.php');
                    exit;
                }
            } catch (\Throwable $e) {
                error_log('edit-frontend-section POST error: ' . $e->getMessage());
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

$csrf_token = Session::csrfToken('edit_fps_' . $id);

// Override breadcrumb title
$pageTitleOverride = 'Edit Frontend Section';

include __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-md-8 col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Frontend Section</h3>
            </div>
            <div class="card-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="editFpsForm">
                    <!-- UNM-CSRF-V2 -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <!-- Section Title -->
                    <div class="mb-3">
                        <label for="section_title" class="form-label fw-semibold">
                            Section Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="section_title" name="section_title"
                            required maxlength="150" placeholder="e.g. Classic Dry Fruits"
                            value="<?= htmlspecialchars($form['section_title']) ?>">
                        <small class="text-muted">This heading appears above the product cards on the homepage.</small>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label for="category_id" class="form-label fw-semibold">
                            Product Category <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">-- Select a Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"
                                    <?= (int) $form['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        // Show warning if current saved category no longer in active list
                        $catIds = array_column($categories, 'id');
                        if ($form['category_id'] && !in_array((string) $form['category_id'], $catIds, false)):
                        ?>
                            <div class="alert alert-warning mt-2 py-1 small">
                                <i class="fas fa-exclamation-triangle"></i>
                                Previously selected category is now inactive or deleted. Please pick a new one.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <!-- Products Limit -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="products_limit" class="form-label fw-semibold">Max Products to Show</label>
                                <input type="number" class="form-control" id="products_limit" name="products_limit"
                                    min="1" max="16" value="<?= (int) $form['products_limit'] ?>">
                                <small class="text-muted">How many product cards appear (1–16).</small>
                            </div>
                        </div>
                        <!-- Sort Order -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order"
                                    min="0" value="<?= (int) $form['sort_order'] ?>">
                                <small class="text-muted">Lower number = appears first on homepage.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Status</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusActive" value="active"
                                    <?= $form['status'] === 'active' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="statusActive">
                                    <span class="badge bg-success">Active</span> — Visible on homepage
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive"
                                    <?= $form['status'] === 'inactive' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="statusInactive">
                                    <span class="badge bg-secondary">Inactive</span> — Hidden from homepage
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="manage-frontend-sections.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Meta info -->
    <div class="col-md-4 col-lg-5">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-info-circle"></i> Section Info</h5></div>
            <div class="card-body small">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Section ID</td><td><code>#<?= (int) ($sec['id'] ?? 0) ?></code></td></tr>
                    <tr><td class="text-muted">Created</td><td><?= htmlspecialchars($sec['created_at'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Last Updated</td><td><?= htmlspecialchars($sec['updated_at'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
