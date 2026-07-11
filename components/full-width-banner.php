<?php
/**
 * UrbanNutMix – Full Width Promo Banner Component
 * Displays a single high-impact widescreen category banner
 */

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}
?>

<div class="unm-full-banner-section">
    <a href="<?php echo BASE_URL; ?>shop.php" class="unm-full-banner-wrap" aria-label="Shop Honey Dry Fruits">
        <img 
            src="https://www.jewelfarmer.com/cdn/shop/files/JF-NUTS.png?v=1773119189&width=2400" 
            alt="Premium Honey Dry Fruits 200g Promo Banner" 
            class="unm-full-banner-img"
            loading="lazy"
            draggable="false"
        >
    </a>
</div>
