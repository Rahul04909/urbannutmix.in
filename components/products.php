<?php
/**
 * UrbanNutMix – Frontend Product Sections Component
 *
 * Renders homepage product sections driven entirely from the database.
 * Admin controls: Products > Frontend Sections
 *
 * Flow:
 *  1. Fetch active frontend_product_sections (ordered by sort_order)
 *  2. For each section, fetch active products of that category
 *  3. Render a <section> per row with product cards
 */

/* ── Bootstrap / BASE_URL ─────────────────────────────────────────── */
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}

/* ── DB connection ────────────────────────────────────────────────── */
if (!class_exists('Database')) {
    $dbConfig = __DIR__ . '/../admin/config/database.php';
    if (file_exists($dbConfig)) {
        require_once $dbConfig;
    }
}

/* ── Card background colour palette (auto-cycled) ────────────────── */
$bgPalette = [
    'pink', 'mint', 'golden', 'coral', 'sage', 'sky',
    'lavender', 'magenta', 'cream', 'fern', 'blush',
    'linen', 'violet', 'peach', 'rose', 'chilli',
];

/* ── Image URL helper ─────────────────────────────────────────────── */
function unm_product_img_url(string $image): string
{
    if ($image === '' || $image === 'default.png') {
        return '';
    }
    // Physical path check
    $physPath = __DIR__ . '/../admin/src/images/products/' . $image;
    if (file_exists($physPath)) {
        return BASE_URL . 'admin/src/images/products/' . rawurlencode($image);
    }
    return '';
}

/* ── Fetch sections + products from DB ───────────────────────────── */
$productSections = [];

try {
    if (class_exists('Database')) {
        $pdo = Database::getConnection();

        // Fetch active frontend sections
        $secStmt = $pdo->query(
            "SELECT fps.id, fps.section_title, fps.category_id, fps.products_limit, fps.sort_order
             FROM frontend_product_sections fps
             INNER JOIN product_categories pc ON pc.id = fps.category_id AND pc.status = 'active'
             WHERE fps.status = 'active'
             ORDER BY fps.sort_order ASC, fps.id ASC"
        );
        $dbSections = $secStmt ? $secStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // For each section, fetch its products
        $prodStmt = $pdo->prepare(
            "SELECT p.id, p.name, p.slug, p.image, p.short_description,
                    p.price, p.mrp, p.unit, p.quantity
             FROM products p
             WHERE p.category_id = :cid AND p.status = 'active'
             ORDER BY p.id ASC
             LIMIT :lim"
        );

        foreach ($dbSections as $sec) {
            $prodStmt->bindValue(':cid', (int) $sec['category_id'], PDO::PARAM_INT);
            $prodStmt->bindValue(':lim', (int) $sec['products_limit'], PDO::PARAM_INT);
            $prodStmt->execute();
            $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

            $productSections[] = [
                'id'      => 'section-fps-' . (int) $sec['id'],
                'heading' => htmlspecialchars($sec['section_title']),
                'products'=> $products,
            ];
        }
    }
} catch (\Throwable $e) {
    error_log('components/products.php DB error: ' . $e->getMessage());
    // Fail silently on frontend
}

/* ── Nothing to render ────────────────────────────────────────────── */
if (empty($productSections)) {
    return; // No output — admin hasn't configured sections yet
}
?>

<?php
$sectionAlt = false; // Toggle alternate background
foreach ($productSections as $secIdx => $section):
    $sectionAlt = !$sectionAlt;
    $products   = $section['products'];
    if (empty($products)) { continue; } // Skip empty sections silently
?>
<section
    class="unm-products-section<?php echo $sectionAlt ? ' unm-products-section--alt' : ''; ?>"
    id="<?php echo htmlspecialchars($section['id']); ?>"
    aria-label="<?php echo strip_tags($section['heading']); ?>"
>
    <div class="unm-products-inner">

        <!-- Section Heading -->
        <div class="unm-products-heading">
            <h2><?php echo $section['heading']; ?></h2>
        </div>

        <!-- Product Grid -->
        <ul class="unm-products-grid">
            <?php foreach ($products as $pIdx => $p):
                /* ── Resolve colour ── */
                $bgColor = $bgPalette[$pIdx % count($bgPalette)];

                /* ── Resolve image ── */
                $imgUrl  = unm_product_img_url($p['image'] ?? '');

                /* ── Badge: quantity + unit ── */
                $qty  = rtrim(rtrim(number_format((float) ($p['quantity'] ?? 0), 2), '0'), '.');
                $unit = strtolower(trim($p['unit'] ?? 'gram'));
                $badge = $qty . ($unit === 'gram' ? 'g' : ($unit === 'kg' ? 'kg' : $unit[0]));

                /* Split badge e.g. "500g" → num="500", unit="G" */
                preg_match('/^(\d+(?:\.\d+)?)(.*)$/', $badge, $bm);
                $bnum  = $bm[1] ?? $badge;
                $bunit = strtoupper($bm[2] ?? '');

                /* ── Price / MRP ── */
                $price   = (float) ($p['price'] ?? 0);
                $mrp     = (float) ($p['mrp']   ?? 0);
                $hasMrp  = $mrp > $price && $mrp > 0;
                $discount= $hasMrp ? (int) round(($mrp - $price) / $mrp * 100) : 0;
                $priceStr = '₹' . number_format($price, 2);
                $mrpStr   = $hasMrp ? '₹' . number_format($mrp, 2) : '';

                /* ── Product details link ── */
                $pdpUrl = BASE_URL . 'product.php?slug=' . urlencode($p['slug'] ?? '');
            ?>
            <li class="unm-product-card" data-bg="<?php echo htmlspecialchars($bgColor); ?>">

                <!-- Coloured Image Zone -->
                <div class="unm-product-img-zone">
                    <a href="<?php echo $pdpUrl; ?>" style="display:block; width:100%; height:100%; text-decoration:none;">
                    <?php if ($imgUrl !== ''): ?>
                    <img
                        src="<?php echo $imgUrl; ?>"
                        alt="<?php echo $altText; ?>"
                        class="unm-product-img"
                        loading="lazy"
                        draggable="false"
                    >
                    <?php else: ?>
                    <!-- Placeholder icon when no image uploaded yet -->
                    <div class="unm-product-img-placeholder" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="4" width="56" height="56" rx="6"/>
                            <circle cx="22" cy="22" r="6"/>
                            <path d="M4 44 l16-16 10 10 10-14 20 20"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                    </a>

                    <!-- Weight / Size Badge -->
                    <div class="unm-product-badge" aria-hidden="true">
                        <span class="unm-product-badge-num"><?php echo htmlspecialchars($bnum); ?></span>
                        <span class="unm-product-badge-unit"><?php echo htmlspecialchars($bunit); ?></span>
                    </div>

                    <!-- Discount Badge -->
                    <?php if ($hasMrp && $discount >= 5): ?>
                    <div class="unm-product-discount-badge" aria-hidden="true">
                        <?php echo $discount; ?>% OFF
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Card Body -->
                <div class="unm-product-body">
                    <p class="unm-product-name">
                        <a href="<?php echo $pdpUrl; ?>" style="color:inherit; text-decoration:none;">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </a>
                    </p>

                    <div class="unm-product-price-wrap">
                        <span class="unm-product-price"><?php echo $priceStr; ?></span>
                        <?php if ($hasMrp): ?>
                        <span class="unm-product-mrp"><?php echo $mrpStr; ?></span>
                        <?php endif; ?>
                        <span class="unm-product-tax">(Incl. all taxes)</span>
                    </div>

                    <div class="unm-product-spacer"></div>

                    <a href="<?php echo $pdpUrl; ?>" class="unm-product-btn" aria-label="View <?php echo $altText; ?> details">
                        View Product
                    </a>
                </div>

            </li>
            <?php endforeach; ?>
        </ul>

    </div>
</section>
<?php
endforeach;
/* ── End of Product Sections ──── */

