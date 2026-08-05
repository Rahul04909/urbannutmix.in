<?php
/**
 * UrbanNutMix - Premium Shop Catalog Page
 * Features: Multi-faceted filters (Category, Search, Pricing, Sorting), Responsive mobile layout, AJAX Add to Cart
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$page_title = "Shop Premium Dry Fruits & Nuts | UrbanNutMix";
$meta_description = "Browse our catalog of premium quality almonds, cashews, pistachios, berries, seeds, and curated gift boxes.";
$extra_css = ['assets/css/shop.css'];

// Database connection
try {
    $pdo = Database::getConnection();
} catch (\Throwable $e) {
    error_log("Shop Page DB connection error: " . $e->getMessage());
    die("A connection error occurred. Please try again later.");
}

// Fetch active categories
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM product_categories WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Shop Page fetch categories error: " . $e->getMessage());
}

// Read parameters
$q = trim($_GET['search'] ?? $_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$minPriceInput = isset($_GET['min_price']) ? trim($_GET['min_price']) : '';
$maxPriceInput = isset($_GET['max_price']) ? trim($_GET['max_price']) : '';

$minPrice = is_numeric($minPriceInput) && (float)$minPriceInput >= 0 ? (float)$minPriceInput : 0.0;
$maxPrice = is_numeric($maxPriceInput) && (float)$maxPriceInput >= 0 ? (float)$maxPriceInput : 0.0;

// Query conditions
$whereConditions = ["p.status = 'active'"];
$params = [];

if ($q !== '') {
    $whereConditions[] = "(p.name LIKE :search OR p.slug LIKE :search OR p.short_description LIKE :search)";
    $params['search'] = '%' . $q . '%';
}

$activeCategory = null;
if ($categorySlug !== '') {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $activeCategory = $cat;
            break;
        }
    }
    if ($activeCategory) {
        $whereConditions[] = "p.category_id = :category_id";
        $params['category_id'] = $activeCategory['id'];
    }
}

if ($minPrice > 0) {
    $whereConditions[] = "p.price >= :min_price";
    $params['min_price'] = $minPrice;
}
if ($maxPrice > 0) {
    $whereConditions[] = "p.price <= :max_price";
    $params['max_price'] = $maxPrice;
}

$whereSql = "WHERE " . implode(" AND ", $whereConditions);

// Sorting
$orderBy = "p.id DESC"; // default newest
if ($sort === 'price_asc') {
    $orderBy = "p.price ASC";
} elseif ($sort === 'price_desc') {
    $orderBy = "p.price DESC";
} elseif ($sort === 'alpha') {
    $orderBy = "p.name ASC";
}

// Count total matching items
$totalCount = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM products p $whereSql");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
} catch (\Throwable $e) {
    error_log("Shop Page count products error: " . $e->getMessage());
}

$perPage = 12;
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

// Fetch products
$products = [];
try {
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.slug, p.image, p.price, p.mrp, p.unit, p.quantity, pc.name AS category_name, pc.slug AS category_slug
         FROM products p
         LEFT JOIN product_categories pc ON pc.id = p.category_id
         $whereSql
         ORDER BY $orderBy
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Shop Page fetch products error: " . $e->getMessage());
}

// Resolve product image source path
if (!function_exists('get_product_img_src')) {
    function get_product_img_src(?string $imgName): string {
        if (empty($imgName) || $imgName === 'default.png') {
            return BASE_URL . 'assets/images/logo-bg.jpg';
        }
        $physPath = __DIR__ . '/admin/src/images/products/' . $imgName;
        if (file_exists($physPath)) {
            return BASE_URL . 'admin/src/images/products/' . rawurlencode($imgName);
        }
        return BASE_URL . 'assets/images/logo-bg.jpg';
    }
}

// Active page CSS configurations or helpers
include_once 'includes/header.php';
?>

<!-- Shop Hero Banner -->
<div class="unm-shop-hero">
    <div class="unm-shop-hero-inner">
        <h1 class="unm-shop-hero-title">UrbanNutMix Shop</h1>
        <p class="unm-shop-hero-subtitle">Premium quality dry fruits, natural nuts, seeds, and healthy gifting hampers</p>
    </div>
</div>

<div class="unm-shop-wrapper">
    <div class="unm-shop-container">
        
        <!-- Mobile Filters Drawer Toggle Button (Fixed/sticky floating bar on mobile bottom) -->
        <div class="unm-mobile-filter-bar">
            <button type="button" class="unm-mobile-filter-btn" id="openMobileFilters">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters &amp; Sort
            </button>
        </div>

        <div class="unm-shop-layout">
            
            <!-- Sidebar: Filters (Hidden on mobile via CSS, shown in slide-up overlay) -->
            <aside class="unm-shop-sidebar" id="shopFiltersSidebar">
                <div class="unm-sidebar-header">
                    <h3 class="unm-sidebar-title">Filter Options</h3>
                    <button type="button" class="unm-sidebar-close-btn" id="closeMobileFilters" aria-label="Close Filters">&times;</button>
                </div>
                
                <form method="GET" action="shop.php" class="unm-sidebar-form">
                    <!-- Preserve search and sort if set outside the sidebar -->
                    <?php if ($q !== ''): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($q) ?>">
                    <?php endif; ?>
                    
                    <!-- Search Input Widget (Inside filters for convenience) -->
                    <div class="unm-filter-group">
                        <h4 class="unm-filter-title">Search Catalog</h4>
                        <div class="unm-filter-search-box">
                            <input type="text" name="q" placeholder="Type keyword..." value="<?= htmlspecialchars($q) ?>" class="unm-sidebar-search-input">
                            <button type="submit" class="unm-sidebar-search-btn" aria-label="Search"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="unm-filter-group">
                        <h4 class="unm-filter-title">Categories</h4>
                        <div class="unm-filter-categories-list">
                            <a href="shop.php?<?= http_build_query(array_filter(['search' => $q, 'sort' => $sort, 'min_price' => $minPriceInput, 'max_price' => $maxPriceInput])) ?>" 
                               class="unm-category-filter-item <?= $categorySlug === '' ? 'active' : '' ?>">
                               All Products
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="shop.php?<?= http_build_query(array_filter(['category' => $cat['slug'], 'search' => $q, 'sort' => $sort, 'min_price' => $minPriceInput, 'max_price' => $maxPriceInput])) ?>" 
                                   class="unm-category-filter-item <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($categorySlug !== ''): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Price Filter -->
                    <div class="unm-filter-group">
                        <h4 class="unm-filter-title">Price Range (₹)</h4>
                        <div class="unm-price-range-inputs">
                            <input type="number" name="min_price" placeholder="Min" min="0" value="<?= htmlspecialchars($minPriceInput) ?>" class="unm-price-input-box">
                            <span class="unm-price-range-sep">to</span>
                            <input type="number" name="max_price" placeholder="Max" min="0" value="<?= htmlspecialchars($maxPriceInput) ?>" class="unm-price-input-box">
                        </div>
                    </div>

                    <!-- Sorting Filter -->
                    <div class="unm-filter-group">
                        <h4 class="unm-filter-title">Sort By</h4>
                        <select name="sort" class="unm-sidebar-select" onchange="this.form.submit()">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="alpha" <?= $sort === 'alpha' ? 'selected' : '' ?>>Alphabetical: A-Z</option>
                        </select>
                    </div>

                    <div class="unm-filter-actions">
                        <button type="submit" class="unm-apply-filters-btn">Apply Filters</button>
                        <a href="shop.php" class="unm-clear-filters-btn">Clear All</a>
                    </div>
                </form>
            </aside>
            
            <!-- Products Grid Panel -->
            <main class="unm-shop-main">
                
                <!-- Shop Controls Bar (Desktop only, handles quick stats and sort dropdown) -->
                <div class="unm-shop-controls">
                    <div class="unm-shop-results-count text-muted">
                        Showing <span class="fw-semibold text-dark"><?= count($products) ?></span> of <span class="fw-semibold text-dark"><?= $totalCount ?></span> results
                        <?php if ($activeCategory): ?>
                            in <strong><?= htmlspecialchars($activeCategory['name']) ?></strong>
                        <?php endif; ?>
                    </div>
                    
                    <div class="unm-shop-sort-desktop">
                        <label class="unm-sort-label text-muted small me-2">Sort by:</label>
                        <select class="unm-desktop-sort-select" onchange="location = this.value;">
                            <option value="shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'newest'])) ?>" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                            <option value="shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'price_asc'])) ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'price_desc'])) ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'alpha'])) ?>" <?= $sort === 'alpha' ? 'selected' : '' ?>>Alphabetical: A-Z</option>
                        </select>
                    </div>
                </div>

                <!-- Active Filter Tags Display -->
                <?php if ($q !== '' || $categorySlug !== '' || $minPrice > 0 || $maxPrice > 0): ?>
                    <div class="unm-active-filters-bar">
                        <span class="unm-active-filters-label">Active Filters:</span>
                        <div class="unm-active-tags-list">
                            <?php if ($q !== ''): ?>
                                <span class="unm-filter-tag">Search: "<?= htmlspecialchars($q) ?>" <a href="shop.php?<?= http_build_query(array_filter(['category' => $categorySlug, 'sort' => $sort, 'min_price' => $minPriceInput, 'max_price' => $maxPriceInput])) ?>">&times;</a></span>
                            <?php endif; ?>
                            <?php if ($activeCategory): ?>
                                <span class="unm-filter-tag">Category: <?= htmlspecialchars($activeCategory['name']) ?> <a href="shop.php?<?= http_build_query(array_filter(['search' => $q, 'sort' => $sort, 'min_price' => $minPriceInput, 'max_price' => $maxPriceInput])) ?>">&times;</a></span>
                            <?php endif; ?>
                            <?php if ($minPrice > 0): ?>
                                <span class="unm-filter-tag">Min: ₹<?= $minPrice ?> <a href="shop.php?<?= http_build_query(array_filter(['category' => $categorySlug, 'search' => $q, 'sort' => $sort, 'max_price' => $maxPriceInput])) ?>">&times;</a></span>
                            <?php endif; ?>
                            <?php if ($maxPrice > 0): ?>
                                <span class="unm-filter-tag">Max: ₹<?= $maxPrice ?> <a href="shop.php?<?= http_build_query(array_filter(['category' => $categorySlug, 'search' => $q, 'sort' => $sort, 'min_price' => $minPriceInput])) ?>">&times;</a></span>
                            <?php endif; ?>
                            <a href="shop.php" class="unm-clear-tags-link">Clear All</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Grid Products List -->
                <?php if (empty($products)): ?>
                    <div class="unm-shop-empty-state">
                        <svg viewBox="0 0 24 24" width="60" height="60" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        <h3>No Products Match Filters</h3>
                        <p class="text-muted">We couldn't find any products matching your specific selection. Try broadening your keywords or clearing the active filters.</p>
                        <a href="shop.php" class="unm-shop-reset-btn">Reset Catalog</a>
                    </div>
                <?php else: ?>
                    <ul class="unm-products-grid">
                        <?php
                        $bgPalette = ['pink', 'mint', 'golden', 'coral', 'sage', 'sky'];
                        foreach ($products as $idx => $prod):
                            $imgUrl = get_product_img_src($prod['image']);
                            $price = (float)$prod['price'];
                            $mrp = (float)$prod['mrp'];
                            $hasMrp = $mrp > $price && $mrp > 0;
                            $inStock = (float)$prod['quantity'] > 0;
                            $bg = $bgPalette[$idx % count($bgPalette)];
                            
                            $discount = 0;
                            if ($hasMrp) {
                                $discount = (int)round((($mrp - $price) / $mrp) * 100);
                            }
                        ?>
                        <li class="unm-product-card" data-bg="<?= $bg ?>">
                            <div class="unm-product-img-zone">
                                <a href="product.php?slug=<?= urlencode($prod['slug']) ?>">
                                    <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="unm-product-img" loading="lazy">
                                </a>
                                <div class="unm-product-badge">
                                    <span class="unm-product-badge-num"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$prod['quantity'], 2), '0'), '.')) ?></span>
                                    <span class="unm-product-badge-unit"><?= strtoupper(substr($prod['unit'], 0, 1)) ?></span>
                                </div>
                                <?php if ($hasMrp && $discount >= 5): ?>
                                    <div class="unm-product-discount-tag"><?= $discount ?>% OFF</div>
                                <?php endif; ?>
                            </div>
                            <div class="unm-product-body">
                                <p class="unm-product-name">
                                    <a href="product.php?slug=<?= urlencode($prod['slug']) ?>" style="color:inherit; text-decoration:none;">
                                        <?= htmlspecialchars($prod['name']) ?>
                                    </a>
                                </p>
                                <div class="unm-product-price-wrap">
                                    <span class="unm-product-price">&#8377;<?= number_format($price, 2) ?></span>
                                    <?php if ($hasMrp): ?>
                                        <span class="unm-product-mrp">&#8377;<?= number_format($mrp, 2) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="unm-product-spacer"></div>
                                
                                <div class="unm-product-actions-zone">
                                    <?php if ($inStock): ?>
                                        <button type="button" class="unm-product-btn-add-cart" onclick="addToCartShop(<?= $prod['id'] ?>, this)">
                                            Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="unm-product-btn-out-stock" disabled>
                                            Out of Stock
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Advanced Shop Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="unm-shop-pagination-nav">
                            <ul class="unm-shop-pagination">
                                <!-- First Page -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="shop.php?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                
                                <!-- Prev Page -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="shop.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" title="Previous Page">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>

                                <?php
                                $rangeLimit = 2;
                                $paginationRange = [];
                                for ($i = 1; $i <= $totalPages; $i++) {
                                    if ($i === 1 || $i === $totalPages || ($i >= $page - $rangeLimit && $i <= $page + $rangeLimit)) {
                                        $paginationRange[] = $i;
                                    }
                                }
                                $paginationRange = array_unique($paginationRange);
                                sort($paginationRange);

                                $prevP = 0;
                                foreach ($paginationRange as $p):
                                    if ($prevP > 0 && $p - $prevP > 1):
                                ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php 
                                    endif;
                                    $activeClass = $p === $page ? 'active' : '';
                                ?>
                                    <li class="page-item <?= $activeClass ?>">
                                        <a class="page-link" href="shop.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                    </li>
                                <?php
                                    $prevP = $p;
                                endforeach;
                                ?>

                                <!-- Next Page -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="shop.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" title="Next Page">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>

                                <!-- Last Page -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="shop.php?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>

            </main>
        </div>
    </div>
</div>

<!-- Scripts for Shop Filters drawer toggles and AJAX Add to Cart -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const openBtn = document.getElementById('openMobileFilters');
    const closeBtn = document.getElementById('closeMobileFilters');
    const sidebar = document.getElementById('shopFiltersSidebar');

    if (openBtn && sidebar) {
        openBtn.addEventListener('click', function() {
            sidebar.classList.add('active');
            document.body.style.overflow = 'hidden'; // prevent scrolling behind overlay
        });
    }

    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});

function addToCartShop(productId, btn) {
    if (btn.classList.contains('adding')) return;

    btn.classList.add('adding');
    const origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 14px; height: 14px; margin-right: 5px; border-width: 2px;"></span>Adding...';

    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'add_ajax',
            product_id: productId,
            qty: 1
        })
    })
    .then(res => {
        if (!res.ok) throw new Error("HTTP error " + res.status);
        return res.json();
    })
    .then(data => {
        btn.classList.remove('adding');
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check" style="margin-right: 5px;"></i>Added!';
            btn.style.backgroundColor = '#28a745';
            btn.style.color = '#ffffff';

            // Update badge counts in the header
            document.querySelectorAll('.unm-cart-badge').forEach(badge => {
                badge.textContent = data.cart_count;
                badge.style.transform = 'scale(1.3)';
                setTimeout(() => badge.style.transform = '', 300);
            });

            // SweetAlert notice
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Added to Cart!',
                    text: data.message,
                    confirmButtonColor: '#cf6e0c',
                    timer: 1500,
                    showConfirmButton: false
                });
            }

            setTimeout(() => {
                btn.innerHTML = origText;
                btn.style.backgroundColor = '';
                btn.style.color = '';
            }, 1500);
        } else {
            btn.innerHTML = origText;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock Alert',
                    text: data.message,
                    confirmButtonColor: '#cf6e0c'
                });
            } else {
                alert(data.message);
            }
        }
    })
    .catch(err => {
        btn.classList.remove('adding');
        btn.innerHTML = origText;
        console.error("AJAX add to cart failed: ", err);
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>
