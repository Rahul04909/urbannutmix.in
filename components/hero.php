<?php
/**
 * UrbanNutMix - Premium Banner Hero Slider (Slides only)
 */

$slides = [
    [
        'image' => 'assets/images/hero-banners/hero-banner-1.png',
        'alt'   => 'UrbanNutMix Roasted Makhana Banner'
    ],
    [
        'image' => 'https://ministryofnuts.in/cdn/shop/files/SAVE_20251029_184601_45461320-5bb3-4b4c-a685-e097c25510ce_2100x.jpg?v=1761743999',
        'alt'   => 'UrbanNutMix Special Offers Banner'
    ],
    [
        'image' => 'https://ministryofnuts.in/cdn/shop/files/27-3-2025_website_banner_2100x.jpg?v=1743419467',
        'alt'   => 'UrbanNutMix Premium Nuts Assortment'
    ]
];
?>

<div class="unm-hero-slider-container grab" id="unmHeroSliderContainer">
    <div class="unm-hero-slider">
        <div class="unm-slider-track" id="unmSliderTrack">
            <?php foreach ($slides as $index => $slide): ?>
                <div class="unm-slide" data-index="<?php echo $index; ?>">
                    <img src="<?php echo $slide['image']; ?>" alt="<?php echo htmlspecialchars($slide['alt']); ?>" class="unm-slide-img" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Navigation Arrows (Subtle chevrons visible on desktop hover only) -->
    <button class="unm-slider-arrow unm-slider-arrow-prev" id="unmSliderPrevBtn" aria-label="Previous Slide">
        <svg class="unm-slider-arrow-icon" viewBox="0 0 24 24">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
    <button class="unm-slider-arrow unm-slider-arrow-next" id="unmSliderNextBtn" aria-label="Next Slide">
        <svg class="unm-slider-arrow-icon" viewBox="0 0 24 24">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('unmHeroSliderContainer');
    const track = document.getElementById('unmSliderTrack');
    const prevBtn = document.getElementById('unmSliderPrevBtn');
    const nextBtn = document.getElementById('unmSliderNextBtn');
    const slides = track.querySelectorAll('.unm-slide');
    const slideCount = slides.length;

    let currentIndex = 0;
    let startX = 0;
    let currentX = 0;
    let isDragging = false;
    let autoplayTimer = null;
    const autoplayInterval = 5000; // 5 seconds
    const swipeThreshold = 50; // min distance in px to register a swipe

    // --- Slide positioning ---
    function updateSlidePosition(offset = 0) {
        // Calculate the base transform based on the current slide index
        const baseTranslate = -currentIndex * 100;
        
        if (isDragging) {
            // Apply real-time translation when dragging, converting px offset to container relative width %
            const containerWidth = container.offsetWidth;
            const percentageOffset = (offset / containerWidth) * 100;
            track.style.transition = 'none'; // Disable transition timing for real-time tracking
            track.style.transform = `translateX(${baseTranslate + percentageOffset}%)`;
        } else {
            // Smooth slide snap transition
            track.style.transition = 'transform 0.6s cubic-bezier(0.25, 1, 0.5, 1)';
            track.style.transform = `translateX(${baseTranslate}%)`;
        }
    }

    function nextSlide() {
        currentIndex = (currentIndex + 1) % slideCount;
        updateSlidePosition();
    }

    function prevSlide() {
        currentIndex = (currentIndex - 1 + slideCount) % slideCount;
        updateSlidePosition();
    }

    // --- Autoplay Controls ---
    function startAutoplay() {
        stopAutoplay();
        autoplayTimer = setInterval(nextSlide, autoplayInterval);
    }

    function stopAutoplay() {
        if (autoplayTimer) {
            clearInterval(autoplayTimer);
            autoplayTimer = null;
        }
    }

    // --- Drag & Touch Actions ---
    function dragStart(e) {
        isDragging = true;
        startX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        currentX = startX;
        
        container.classList.remove('grab');
        container.classList.add('grabbing');
        stopAutoplay();
    }

    function dragMove(e) {
        if (!isDragging) return;
        
        // Prevent default behavior to avoid scrolling while sliding
        if (e.type.includes('touch')) {
            currentX = e.touches[0].clientX;
        } else {
            e.preventDefault();
            currentX = e.clientX;
        }

        const diffX = currentX - startX;
        updateSlidePosition(diffX);
    }

    function dragEnd() {
        if (!isDragging) return;
        isDragging = false;
        
        container.classList.remove('grabbing');
        container.classList.add('grab');

        const diffX = currentX - startX;

        // Snapping logic based on movement threshold
        if (Math.abs(diffX) > swipeThreshold) {
            if (diffX > 0) {
                // Dragged right -> Go to previous
                prevSlide();
            } else {
                // Dragged left -> Go to next
                nextSlide();
            }
        } else {
            // Reset to current slide if threshold not met
            updateSlidePosition();
        }

        startAutoplay();
    }

    // --- Desktop Arrow Click Events ---
    nextBtn.addEventListener('click', function() {
        stopAutoplay();
        nextSlide();
        startAutoplay();
    });

    prevBtn.addEventListener('click', function() {
        stopAutoplay();
        prevSlide();
        startAutoplay();
    });

    // --- Touch Event Listeners ---
    track.addEventListener('touchstart', dragStart, { passive: true });
    track.addEventListener('touchmove', dragMove, { passive: false });
    track.addEventListener('touchend', dragEnd);

    // --- Mouse Event Listeners ---
    track.addEventListener('mousedown', dragStart);
    window.addEventListener('mousemove', dragMove);
    window.addEventListener('mouseup', dragEnd);

    // Pause autoplay on hovering the slider (Desktop feature)
    container.addEventListener('mouseenter', stopAutoplay);
    container.addEventListener('mouseleave', startAutoplay);

    // Visibility awareness to save resources when tab is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoplay();
        } else {
            startAutoplay();
        }
    });

    // --- Initialize ---
    updateSlidePosition();
    startAutoplay();
});
</script>
