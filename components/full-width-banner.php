<?php
/**
 * UrbanNutMix – Full Width Promo Banner Component
 * Plays banner-video.mp4 continuously in an infinite loop
 */

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}
?>

<div class="unm-full-banner-section">
    <div class="unm-full-banner-wrap">
        <video 
            src="<?php echo BASE_URL; ?>assets/videos/banner-video.mp4" 
            class="unm-full-banner-video"
            autoplay
            loop
            muted
            playsinline
            aria-label="UrbanNutMix Promotional Video Banner"
        ></video>
    </div>
</div>
