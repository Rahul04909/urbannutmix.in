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
        'image' => 'assets/images/hero-banners/banner-4.png',
        'alt'   => 'UrbanNutMix Specialty Healthy Nuts Banner',
        'link'  => BASE_URL . 'shop.php?category=healthy-nuts',
    ],
    [
        'image' => 'assets/images/hero-banners/banner-5.png',
        'alt'   => 'UrbanNutMix Premium Seeds & Mixes Banner',
        'link'  => BASE_URL . 'shop.php?category=seeds-mixes',
    ],
    [
        'image' => 'assets/images/hero-banners/banner-6.png',
        'alt'   => 'UrbanNutMix Exclusive Festive Hampers Banner',
        'link'  => BASE_URL . 'shop.php?category=festive-hampers',
    ],
    [
        'image' => 'assets/images/hero-banners/banner-7.png',
        'alt'   => 'UrbanNutMix Exotic Berries & Dates Banner',
        'link'  => BASE_URL . 'shop.php?category=exotic-berries',
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
