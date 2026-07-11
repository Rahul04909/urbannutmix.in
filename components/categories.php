<?php
/**
 * UrbanNutMix – Shop by Dryfruits – Category Slider Component
 */

if (!defined('BASE_URL')) {
    /* Safety fallback if included independently */
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $base_url  = $protocol . $_SERVER['HTTP_HOST'] . '/';
    define('BASE_URL', $base_url);
}

$categories = [
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Pista_collection_slider.png?v=1723033847',
        'label' => 'Buy Pistachio',
        'link'  => BASE_URL . 'shop.php?category=pistachios',
    ],
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Walnuts_collection_slider.png?v=1723033847',
        'label' => 'Buy Walnut',
        'link'  => BASE_URL . 'shop.php?category=walnuts',
    ],
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Dried_anjeer_collection_slider.png?v=1723033847',
        'label' => 'Buy Dried Anjeer',
        'link'  => BASE_URL . 'shop.php?category=dried-anjeer',
    ],
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Raisins_collection_slider.png?v=1723033847',
        'label' => 'Buy Raisins',
        'link'  => BASE_URL . 'shop.php?category=raisins',
    ],
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Dates_collection_slider.png?v=1723033847',
        'label' => 'Buy Dates',
        'link'  => BASE_URL . 'shop.php?category=dates',
    ],
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Almonds_collection_slider.png?v=1723033848',
        'label' => 'Buy Almond',
        'link'  => BASE_URL . 'shop.php?category=almonds',
    ],
    [
        'image' => 'https://cdn.shopify.com/s/files/1/0585/4376/7652/files/Cashews_collection_slider.png?v=1723033847',
        'label' => 'Buy Cashew',
        'link'  => BASE_URL . 'shop.php?category=cashews',
    ],
];
?>

<section class="unm-categories-section" aria-label="Shop by Dryfruits">
    <!-- Heading -->
    <div class="unm-categories-heading">
        <h2>Shop by Dryfruits</h2>
    </div>

    <!-- Slider Wrapper -->
    <div class="unm-categories-slider-wrapper">

        <!-- Prev Arrow -->
        <button class="unm-cat-arrow unm-cat-arrow-prev" id="unmCatPrev" aria-label="Previous categories" disabled>
            <svg class="unm-cat-arrow-icon" viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <!-- Track -->
        <div class="unm-categories-track" id="unmCatTrack">
            <ul class="unm-categories-list" id="unmCatList">
                <?php foreach ($categories as $i => $cat): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($cat['link']); ?>"
                       class="unm-category-item<?php echo $i === 0 ? ' active' : ''; ?>"
                       aria-label="<?php echo htmlspecialchars($cat['label']); ?>">
                        <div class="unm-category-img-wrap">
                            <img
                                src="<?php echo htmlspecialchars($cat['image']); ?>"
                                alt="<?php echo htmlspecialchars($cat['label']); ?>"
                                class="unm-category-img"
                                loading="lazy"
                                draggable="false"
                            >
                        </div>
                        <span class="unm-category-label"><?php echo htmlspecialchars($cat['label']); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Next Arrow -->
        <button class="unm-cat-arrow unm-cat-arrow-next" id="unmCatNext" aria-label="Next categories">
            <svg class="unm-cat-arrow-icon" viewBox="0 0 24 24">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>

    </div>
</section>

<script>
(function () {
    'use strict';

    const track   = document.getElementById('unmCatTrack');
    const list    = document.getElementById('unmCatList');
    const prevBtn = document.getElementById('unmCatPrev');
    const nextBtn = document.getElementById('unmCatNext');

    if (!track || !list) return;

    const items     = list.querySelectorAll('.unm-category-item');
    const itemCount = items.length;

    let offset        = 0;   // current translateX (negative px)
    let visibleCount  = 6;   // will be recalculated on resize
    let itemWidth     = 0;
    let maxOffset     = 0;

    /* ---- Measure ---- */
    function measure() {
        visibleCount = getVisibleCount();
        const firstItem = list.querySelector('li');
        if (!firstItem) return;
        itemWidth  = firstItem.offsetWidth;
        maxOffset  = Math.max(0, (itemCount - visibleCount) * itemWidth);
        offset     = Math.min(offset, maxOffset);
        render(false);
        updateArrows();
    }

    function getVisibleCount() {
        const w = window.innerWidth;
        if (w <= 400) return 2;
        if (w <= 640) return 3;
        if (w <= 900) return 4;
        if (w <= 1200) return 5;
        return 6;
    }

    /* ---- Render ---- */
    function render(animated) {
        list.style.transition = animated
            ? 'transform 0.45s cubic-bezier(0.25, 1, 0.5, 1)'
            : 'none';
        list.style.transform = 'translateX(' + (-offset) + 'px)';
    }

    function updateArrows() {
        prevBtn.disabled = offset <= 0;
        nextBtn.disabled = offset >= maxOffset;
    }

    /* ---- Arrow Clicks ---- */
    function step(direction) {
        const stepSize = itemWidth * Math.max(1, Math.floor(visibleCount / 2));
        if (direction === 'next') {
            offset = Math.min(offset + stepSize, maxOffset);
        } else {
            offset = Math.max(offset - stepSize, 0);
        }
        render(true);
        updateArrows();
    }

    prevBtn.addEventListener('click', () => step('prev'));
    nextBtn.addEventListener('click', () => step('next'));

    /* ---- Touch / Mouse Swipe ---- */
    let startX  = 0;
    let startOff = 0;
    let dragging = false;

    function onDragStart(e) {
        dragging  = true;
        startX    = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        startOff  = offset;
        list.style.transition = 'none';
        track.style.cursor = 'grabbing';
    }

    function onDragMove(e) {
        if (!dragging) return;
        const x    = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const diff = startX - x;
        offset = Math.min(Math.max(startOff + diff, 0), maxOffset);
        list.style.transform = 'translateX(' + (-offset) + 'px)';
    }

    function onDragEnd() {
        if (!dragging) return;
        dragging = false;
        track.style.cursor = '';
        /* Snap to nearest item boundary */
        const snapped = Math.round(offset / itemWidth) * itemWidth;
        offset = Math.min(Math.max(snapped, 0), maxOffset);
        render(true);
        updateArrows();
    }

    list.addEventListener('mousedown',  onDragStart);
    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup',  onDragEnd);

    list.addEventListener('touchstart', onDragStart, { passive: true });
    list.addEventListener('touchmove',  onDragMove,  { passive: true });
    list.addEventListener('touchend',   onDragEnd);

    /* ---- Resize ---- */
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(measure, 100);
    });

    /* ---- Init ---- */
    window.addEventListener('load', measure);
    measure();
}());
</script>
