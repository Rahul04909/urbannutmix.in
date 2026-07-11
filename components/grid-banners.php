<?php
/**
 * UrbanNutMix – Grid Banner Display Component
 * Renders a 2x2 responsive layout of visual category cards
 */

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}

$banners = [
    [
        'image' => 'https://www.jewelfarmer.com/cdn/shop/files/Berries-Category-Banner.webp?v=1781759387&width=1950',
        'alt'   => 'Premium Berries',
        'link'  => BASE_URL . 'shop.php?category=berries',
    ],
    [
        'image' => 'https://www.jewelfarmer.com/cdn/shop/files/cat01_4bc4ac30-8d8c-453d-aaf4-dcdc4173968d.png?v=1778742022&width=1950',
        'alt'   => 'Khajoor Burfi',
        'link'  => BASE_URL . 'shop.php?category=khajoor-burfi',
    ],
    [
        'image' => 'https://www.jewelfarmer.com/cdn/shop/files/Seeds-Category-Banner.webp?v=1781759464&width=1950',
        'alt'   => 'Premium Seeds',
        'link'  => BASE_URL . 'shop.php?category=seeds',
    ],
    [
        'image' => 'https://www.jewelfarmer.com/cdn/shop/files/Nuts-_-Dry-Fruits-Category-Banner.webp?v=1781759422&width=1950',
        'alt'   => 'Nuts & Dry Fruit',
        'link'  => BASE_URL . 'shop.php?category=nuts-dry-fruits',
    ],
];
?>

<section class="unm-grid-banners-section" aria-label="Product Category Banners">
    <div class="unm-grid-banners-inner">
        <ul class="unm-grid-banners-layout">
            <?php foreach ($banners as $banner): ?>
            <li>
                <a href="<?php echo htmlspecialchars($banner['link']); ?>" class="unm-grid-banner-card" aria-label="<?php echo htmlspecialchars($banner['alt']); ?>">
                    <img 
                        src="<?php echo htmlspecialchars($banner['image']); ?>" 
                        alt="<?php echo htmlspecialchars($banner['alt']); ?>" 
                        class="unm-grid-banner-img"
                        loading="lazy"
                        draggable="false"
                    >
                    <div class="unm-grid-banner-btn" aria-hidden="true">
                        Shop Now &rarr;
                    </div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
