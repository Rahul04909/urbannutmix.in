<?php
// admin/products/add-frontend-section.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = '';
$error   = '';
$categories = [];
$nextSort = 0;

$form = [
    'section_title'  => '',
    'category_id'    => '',
    'products_limit' => 8,
    'sort_order'     => 0,
    'status'         => 'active',
];

try {
    $pdo = Database::getConnection();

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `frontend_product_sections` (
        `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
        `category_id`    INT UNSIGNED   NOT NULL,
        `section_title`  VARCHAR(150)   NOT NULL,
        `sort_order`     SMALLINT       NOT NULL DEFAULT 0,
        `products_limit` TINYINT UNSIGNED NOT NULL DEFAULT 8,
        `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_sort` (`sort_order`, `status`),
        KEY `idx_cat`  (`category_id`),
        CONSTRAINT `fk_fps_category`
            FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $categories = $pdo->query(
        "SELECT id, name FROM product_categories WHERE status = 'active' ORDER BY name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $row = $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM frontend_product_sections')->fetch();
    $nextSort = (int) ($row['next_sort'] ?? 0);
    $form['sort_order'] = $nextSort;

} catch (\Throwable $e) {
    error_log('add-frontend-section load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('add_fps', $csrf)) {
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

                // Validate category exists and is active
                $catCheck = $pdo->prepare("SELECT id FROM product_categories WHERE id = :id AND status = 'active' LIMIT 1");
                $catCheck->execute(['id' => $form['category_id']]);
                if (!$catCheck->fetch()) {
                    $error = 'Selected category not found or is inactive.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO frontend_product_sections
                         (category_id, section_title, sort_order, products_limit, status)
                         VALUES (:cid, :title, :sort, :limit, :status)'
                    );
                    $stmt->execute([
                        'cid'    => $form['category_id'],
                        'title'  => $form['section_title'],
                        'sort'   => $form['sort_order'],
                        'limit'  => $form['products_limit'],
                        'status' => $form['status'],
                    ]);
                    Session::set('flash_success', 'Section "' . htmlspecialchars($form['section_title']) . '" added successfully.');
                    header('Location: manage-frontend-sections.php');
                    exit;
                }
            } catch (\Throwable $e) {
                error_log('add-frontend-section POST error: ' . $e->getMessage());
                $error = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

$csrf_token = Session::csrfToken('add_fps');
include __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-md-8 col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add Frontend Section</h3>
            </div>
            <div class="card-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="alert alert-light border small mb-3">
                    <i class="fas fa-info-circle text-primary"></i>
                    A frontend section pulls <strong>active products</strong> from the chosen category and displays them
                    on the homepage under the title you set here.
                </div>

                <form method="POST" id="addFpsForm">
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
                        <small class="text-muted">Active products from this category will be displayed.</small>
                    </div>

                    <div class="row">
                        <!-- Products Limit -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="products_limit" class="form-label fw-semibold">Max Products to Show</label>
                                <input type="number" class="form-control" id="products_limit" name="products_limit"
                                    min="1" max="16" value="<?= (int) $form['products_limit'] ?>">
                                <small class="text-muted">How many products appear in this section (1–16).</small>
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
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Add Section
                        </button>
                        <a href="manage-frontend-sections.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="col-md-4 col-lg-5">
        <div class="card card-outline card-info">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-question-circle"></i> How it works</h5></div>
            <div class="card-body small">
                <ol class="ps-3">
                    <li class="mb-2">Choose a <strong>Category</strong> — its active products will be fetched.</li>
                    <li class="mb-2">Set a <strong>Section Title</strong> that appears as the section heading.</li>
                    <li class="mb-2">Adjust <strong>Max Products</strong> to control how many cards are shown.</li>
                    <li class="mb-2">Use <strong>Sort Order</strong> (lower = first) to position sections on the page.</li>
                    <li>Set <strong>Active</strong> to publish it live immediately.</li>
                </ol>
                <hr>
                <p class="text-muted mb-0">
                    <i class="fas fa-lightbulb text-warning"></i>
                    Products must be marked <em>Active</em> in their own settings to appear in this section.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
