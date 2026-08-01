<?php
// admin/products/manage-reviews.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = Session::get('flash_success', '');
Session::remove('flash_success');
$error = Session::get('flash_error', '');
Session::remove('flash_error');

$reviews = [];
$totalPages = 1;
$totalCount = 0;

// Ensure table exists
try {
    $pdo = Database::getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_reviews` (
        `id`                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
        `product_id`        INT UNSIGNED   NOT NULL,
        `reviewer_name`     VARCHAR(100)   NOT NULL,
        `reviewer_email`    VARCHAR(150)   NOT NULL,
        `rating`            TINYINT UNSIGNED NOT NULL DEFAULT 5,
        `review_title`      VARCHAR(150)   DEFAULT NULL,
        `review_text`       TEXT           NOT NULL,
        `verified_purchase` TINYINT(1)     NOT NULL DEFAULT 1,
        `status`            ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved',
        `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_product_status` (`product_id`, `status`),
        CONSTRAINT `fk_reviews_product`
            FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $e) {
    error_log('manage-reviews table ensure error: ' . $e->getMessage());
}

// Handle POST actions (approve / reject / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('manage_reviews', $csrf)) {
        Session::set('flash_error', 'Invalid request - session token expired.');
        header('Location: manage-reviews.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        $pdo = Database::getConnection();

        if ($action === 'delete') {
            $stmt = $pdo->prepare('SELECT id, reviewer_name FROM product_reviews WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM product_reviews WHERE id = :id')->execute(['id' => $id]);
                Session::set('flash_success', 'Review by "' . htmlspecialchars($row['reviewer_name']) . '" deleted.');
            }
        } elseif ($action === 'approve') {
            $pdo->prepare("UPDATE product_reviews SET status = 'approved' WHERE id = :id")->execute(['id' => $id]);
            Session::set('flash_success', 'Review approved.');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE product_reviews SET status = 'rejected' WHERE id = :id")->execute(['id' => $id]);
            Session::set('flash_success', 'Review rejected.');
        }
    } catch (\Throwable $e) {
        error_log('manage-reviews POST error: ' . $e->getMessage());
        Session::set('flash_error', 'Database error: ' . htmlspecialchars($e->getMessage()));
    }

    header('Location: manage-reviews.php');
    exit;
}

// Load Reviews with Search & Filter
try {
    $pdo = Database::getConnection();
    $q            = trim($_GET['q'] ?? '');
    $statusFilter = trim($_GET['status'] ?? '');
    $page         = max(1, (int)($_GET['page'] ?? 1));
    $perPage      = 12;

    $whereConditions = [];
    $params = [];

    if ($q !== '') {
        $whereConditions[] = '(pr.reviewer_name LIKE :q OR pr.reviewer_email LIKE :q OR pr.review_title LIKE :q OR p.name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    if (in_array($statusFilter, ['approved', 'pending', 'rejected'], true)) {
        $whereConditions[] = 'pr.status = :status';
        $params['status']  = $statusFilter;
    }

    $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM product_reviews pr LEFT JOIN products p ON p.id = pr.product_id $whereSql");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetch()['cnt'];

    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT pr.*, p.name AS product_name, p.slug AS product_slug, p.image AS product_image
         FROM product_reviews pr
         LEFT JOIN products p ON p.id = pr.product_id
         $whereSql
         ORDER BY pr.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (\Throwable $e) {
    error_log('manage-reviews load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

$csrf_token = Session::csrfToken('manage_reviews');
include __DIR__ . '/../header.php';
?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">
            <i class="fas fa-star text-warning"></i> Customer Product Reviews
            <span class="badge bg-secondary ms-2"><?= $totalCount ?></span>
        </h3>
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

        <!-- Search and Filter Bar -->
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-5 col-lg-4">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search by name, email, product..."
                        value="<?= htmlspecialchars($q ?? '') ?>">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="col-md-3 col-lg-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All Statuses --</option>
                    <option value="approved" <?= ($statusFilter ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="pending" <?= ($statusFilter ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="rejected" <?= ($statusFilter ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <?php if (($q ?? '') !== '' || ($statusFilter ?? '') !== ''): ?>
            <div class="col-md-2">
                <a href="manage-reviews.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
            <?php endif; ?>
        </form>

        <?php if (empty($reviews)): ?>
            <div class="text-center py-5">
                <i class="fas fa-star-half-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">No reviews found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:180px;">Product</th>
                            <th style="width:180px;">Reviewer</th>
                            <th style="width:100px;">Rating</th>
                            <th>Review Content</th>
                            <th style="width:90px;">Status</th>
                            <th style="width:110px;">Date</th>
                            <th style="width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $index => $rev): ?>
                        <tr>
                            <td><?= $offset + $index + 1 ?></td>
                            <td>
                                <?php if ($rev['product_name']): ?>
                                    <div class="fw-semibold text-truncate" style="max-width:170px;" title="<?= htmlspecialchars($rev['product_name']) ?>">
                                        <?= htmlspecialchars($rev['product_name']) ?>
                                    </div>
                                    <a href="../../product.php?slug=<?= urlencode($rev['product_slug'] ?? '') ?>" target="_blank" class="small text-primary">View Product &nearr;</a>
                                <?php else: ?>
                                    <span class="text-muted">Product Deleted</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($rev['reviewer_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($rev['reviewer_email']) ?></div>
                                <?php if ($rev['verified_purchase']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle small px-1">Verified Buyer</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-warning">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="<?= $s <= (int)$rev['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="fw-bold text-dark"><?= (int)$rev['rating'] ?> / 5</small>
                            </td>
                            <td>
                                <?php if (!empty($rev['review_title'])): ?>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($rev['review_title']) ?></div>
                                <?php endif; ?>
                                <div class="small text-secondary"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></div>
                            </td>
                            <td>
                                <?php if ($rev['status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($rev['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($rev['created_at'])) ?></td>
                            <td>
                                <!-- Approve / Reject Buttons -->
                                <?php if ($rev['status'] !== 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$rev['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success" title="Approve Review">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($rev['status'] !== 'rejected'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$rev['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-warning" title="Reject Review">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Delete Button -->
                                <form method="POST" style="display:inline;" class="delete-review-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$rev['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete Review"
                                        data-reviewer="<?= htmlspecialchars($rev['reviewer_name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="manage-reviews.php?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="manage-reviews.php?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="manage-reviews.php?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-review-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const reviewer = this.querySelector('[data-reviewer]').getAttribute('data-reviewer');
            Swal.fire({
                title: 'Delete review?',
                text: 'Review by "' + reviewer + '" will be permanently removed.',
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
