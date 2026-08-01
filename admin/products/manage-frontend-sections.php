<?php
// admin/products/manage-frontend-sections.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = Session::get('flash_success', '');
Session::remove('flash_success');
$error = Session::get('flash_error', '');
Session::remove('flash_error');

$sections = [];

// Auto-create table if missing
try {
    $pdo = Database::getConnection();
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
} catch (\Throwable $e) {
    error_log('manage-frontend-sections table ensure error: ' . $e->getMessage());
}

// Handle POST actions (delete / reorder)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('manage_fps', $csrf)) {
        Session::set('flash_error', 'Invalid request – session token expired.');
        header('Location: manage-frontend-sections.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        $pdo = Database::getConnection();

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, section_title FROM frontend_product_sections WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM frontend_product_sections WHERE id = :id')->execute(['id' => $id]);
                Session::set('flash_success', 'Section "' . htmlspecialchars($row['section_title']) . '" deleted.');
            } else {
                Session::set('flash_error', 'Section not found or already deleted.');
            }

        } elseif ($action === 'move_up' || $action === 'move_down') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, sort_order FROM frontend_product_sections WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch();
            if ($current) {
                $dir = $action === 'move_up' ? '<' : '>';
                $ord = $action === 'move_up' ? 'DESC' : 'ASC';
                $stmt = $pdo->prepare(
                    "SELECT id, sort_order FROM frontend_product_sections
                     WHERE sort_order $dir :so OR (sort_order = :so2 AND id $dir :id)
                     ORDER BY sort_order $ord, id $ord LIMIT 1"
                );
                $stmt->execute(['so' => $current['sort_order'], 'so2' => $current['sort_order'], 'id' => $id]);
                $swap = $stmt->fetch();
                if ($swap) {
                    $pdo->prepare('UPDATE frontend_product_sections SET sort_order = :so WHERE id = :id')
                        ->execute(['so' => $swap['sort_order'], 'id' => $current['id']]);
                    $pdo->prepare('UPDATE frontend_product_sections SET sort_order = :so WHERE id = :id')
                        ->execute(['so' => $current['sort_order'], 'id' => $swap['id']]);
                }
            }

        } elseif ($action === 'toggle_status') {
            $id  = (int) ($_POST['id'] ?? 0);
            $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';
            $pdo->prepare('UPDATE frontend_product_sections SET status = :st WHERE id = :id')
                ->execute(['st' => $newStatus, 'id' => $id]);
        }

    } catch (\Throwable $e) {
        error_log('manage-frontend-sections POST error: ' . $e->getMessage());
        Session::set('flash_error', 'Database error: ' . htmlspecialchars($e->getMessage()));
    }

    header('Location: manage-frontend-sections.php');
    exit;
}

// Load sections
try {
    $pdo = Database::getConnection();
    $sections = $pdo->query(
        'SELECT fps.*, pc.name AS category_name, pc.status AS category_status,
                (SELECT COUNT(*) FROM products p
                 WHERE p.category_id = fps.category_id AND p.status = \'active\') AS product_count
         FROM frontend_product_sections fps
         LEFT JOIN product_categories pc ON pc.id = fps.category_id
         ORDER BY fps.sort_order ASC, fps.id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('manage-frontend-sections load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

$csrf_token = Session::csrfToken('manage_fps');
include __DIR__ . '/../header.php';
?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">
            <i class="fas fa-th-list"></i> Frontend Product Sections
            <span class="badge bg-secondary ms-2"><?= count($sections) ?></span>
        </h3>
        <a href="add-frontend-section.php" class="btn btn-success btn-sm">
            <i class="fas fa-plus"></i> Add New Section
        </a>
    </div>
    <div class="card-body">

        <?php if ($success !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== '' || $success === '' && isset($_SESSION['_err'])): ?>
            <?php $err = $error ?: ''; ?>
            <?php if ($err !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $err ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="alert alert-info border-0 small mb-3" style="background:#e8f4fd;">
            <i class="fas fa-info-circle"></i>
            These sections appear on the <strong>homepage</strong> under the category name you set.
            Products shown = active products in the chosen category (up to the limit you set).
            Use the <strong>▲ ▼</strong> arrows to reorder sections.
        </div>

        <?php if (empty($sections)): ?>
            <div class="text-center py-5">
                <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                <p class="text-muted">No frontend sections yet.</p>
                <a href="add-frontend-section.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add First Section
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">Order</th>
                            <th>Section Title</th>
                            <th>Category</th>
                            <th style="width:100px;">Products</th>
                            <th style="width:80px;">Limit</th>
                            <th style="width:90px;">Status</th>
                            <th style="width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $i => $sec): ?>
                        <tr>
                            <td class="text-center">
                                <form method="POST" style="display:inline;">
                                    <!-- UNM-CSRF-V2 -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $sec['id'] ?>">
                                    <?php if ($i > 0): ?>
                                    <button type="submit" name="action" value="move_up"
                                        class="btn btn-outline-secondary btn-xs px-1 py-0" title="Move Up">▲</button>
                                    <?php endif; ?>
                                    <?php if ($i < count($sections) - 1): ?>
                                    <button type="submit" name="action" value="move_down"
                                        class="btn btn-outline-secondary btn-xs px-1 py-0" title="Move Down">▼</button>
                                    <?php endif; ?>
                                </form>
                                <div class="text-muted small"><?= (int) $sec['sort_order'] ?></div>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($sec['section_title']) ?></td>
                            <td>
                                <?php if ($sec['category_name']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($sec['category_name']) ?></span>
                                    <?php if ($sec['category_status'] === 'inactive'): ?>
                                        <span class="badge bg-warning text-dark ms-1">Cat Inactive</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-danger">Category Deleted</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?= (int) $sec['product_count'] ?> active</span>
                            </td>
                            <td class="text-center"><?= (int) $sec['products_limit'] ?></td>
                            <td>
                                <?php if ($sec['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Toggle Status -->
                                <form method="POST" style="display:inline;">
                                    <!-- UNM-CSRF-V2 -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $sec['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="new_status" value="<?= $sec['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="btn btn-sm btn-<?= $sec['status'] === 'active' ? 'warning' : 'success' ?>"
                                        title="<?= $sec['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                        <i class="fas fa-<?= $sec['status'] === 'active' ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <!-- Edit -->
                                <a href="edit-frontend-section.php?id=<?= (int) $sec['id'] ?>"
                                    class="btn btn-sm btn-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <!-- Delete -->
                                <form method="POST" style="display:inline;" class="delete-fps-form">
                                    <!-- UNM-CSRF-V2 -->
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $sec['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                        data-title="<?= htmlspecialchars($sec['section_title']) ?>">
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
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-fps-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const title = this.querySelector('[data-title]').getAttribute('data-title');
            Swal.fire({
                title: 'Delete section?',
                text: '"' + title + '" will be removed from the homepage.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then(function (result) {
                if (result.isConfirmed) { form.submit(); }
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
