<?php
/**
 * UrbanNutMix – Featured Products Component
 * Displays a 4-column (desktop) / 2×2 (mobile) product grid
 */

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}

$products = [
    [
        'bg'       => 'pink',
        'image'    => 'https://ministryofnuts.in/cdn/shop/files/Family_pack_750G_1_78ca8d14-47de-4513-a0ed-9a7d11946de6_600x.webp?v=1766041228',
        'alt'      => 'Family Pack Red – 750g',
        'badge_num'=> '750',
        'badge_unit'=> 'G',
        'name'     => 'Panchmeva – Family Pack Of 5 Premium Dry Fruits 750 Grams | Red',
        'price'    => '₹1,399.00',
        'tax_label'=> '(MRP Incl. all taxes)',
        'link'     => '#',
    ],
    [
        'bg'       => 'mint',
        'image'    => 'https://ministryofnuts.in/cdn/shop/files/Blue_8e85bff1-8815-4132-98b2-f61b257fdec8_600x.jpg?v=1766041284',
        'alt'      => 'Family Pack Blue – 750g',
        'badge_num'=> '750',
        'badge_unit'=> 'G',
        'name'     => 'Family Pack of 5 Premium Dry Fruits | Blue | 750g | Combo Pack',
        'price'    => '₹1,599.00',
        'tax_label'=> '(MRP Incl. all taxes)',
        'link'     => '#',
    ],
    [
        'bg'       => 'magenta',
        'image'    => 'https://ministryofnuts.in/cdn/shop/files/Family_pack_750G_1_78ca8d14-47de-4513-a0ed-9a7d11946de6_600x.webp?v=1766041228',
        'alt'      => 'Family Pack Magenta – 750g',
        'badge_num'=> '750',
        'badge_unit'=> 'G',
        'name'     => 'Family Pack of 5 Premium Dry Fruits | Magenta | 750g | Dry Fruits Box',
        'price'    => '₹1,499.00',
        'tax_label'=> '(MRP Incl. all taxes)',
        'link'     => '#',
    ],
    [
        'bg'       => 'lavender',
        'image'    => 'https://ministryofnuts.in/cdn/shop/files/Blue_8e85bff1-8815-4132-98b2-f61b257fdec8_600x.jpg?v=1766041284',
        'alt'      => 'Family Pack Purple – 750g',
        'badge_num'=> '750',
        'badge_unit'=> 'G',
        'name'     => 'Family Pack of 5 Premium Dry Fruits | Purple | 750g | Gift Pack',
        'price'    => '₹1,699.00',
        'tax_label'=> '(MRP Incl. all taxes)',
        'link'     => '#',
    ],
];
?>

<section class="unm-products-section" aria-label="Featured Products">
    <div class="unm-products-inner">

        <!-- Heading -->
        <div class="unm-products-heading">
            <h2>Panchmeva &ndash; Pack of 5 Dryfruits</h2>
        </div>

        <!-- Product Grid -->
        <ul class="unm-products-grid">
            <?php foreach ($products as $product): ?>
            <li class="unm-product-card" data-bg="<?php echo htmlspecialchars($product['bg']); ?>">

                <!-- Colored Image Zone -->
                <div class="unm-product-img-zone">
                    <img
                        src="<?php echo htmlspecialchars($product['image']); ?>"
                        alt="<?php echo htmlspecialchars($product['alt']); ?>"
                        class="unm-product-img"
                        loading="lazy"
                        draggable="false"
                    >
                    <!-- Weight Badge -->
                    <div class="unm-product-badge" aria-hidden="true">
                        <span class="unm-product-badge-num"><?php echo htmlspecialchars($product['badge_num']); ?></span>
                        <span class="unm-product-badge-unit"><?php echo htmlspecialchars($product['badge_unit']); ?></span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="unm-product-body">
                    <p class="unm-product-name"><?php echo htmlspecialchars($product['name']); ?></p>

                    <div class="unm-product-price-wrap">
                        <span class="unm-product-price">Price &ndash; <?php echo htmlspecialchars($product['price']); ?></span>
                        <span class="unm-product-tax"><?php echo htmlspecialchars($product['tax_label']); ?></span>
                    </div>

                    <div class="unm-product-spacer"></div>

                    <a
                        href="<?php echo htmlspecialchars($product['link']); ?>"
                        class="unm-product-btn"
                        aria-label="Add <?php echo htmlspecialchars($product['alt']); ?> to cart"
                    >Add to Cart</a>
                </div>

            </li>
            <?php endforeach; ?>
        </ul>

    </div>
</section>
