<?php
/**
 * UrbanNutMix - Popular Searches FAQ Accordion Component
 * Centered, mobile-responsive, expandable Q&A section
 */

$popular_searches = [
    [
        'question' => 'Q. Dry Fruits Benefits',
        'answer'   => 'Dry Fruits are an <strong>excellent source of Antioxidants,</strong> particularly polyphenols. These Antioxidants can protect you from oxidative stress and enhance blood circulation. Additionally, Dry Fruits contain a significant amount of Insoluble Fiber, which is <strong>beneficial for Heart Health.</strong> They are also rich in Minerals such as Calcium, Magnesium, and Potassium, which <strong>help improve bone density.</strong>',
    ],
    [
        'question' => 'Q. Dry Fruits Price 1 Kg',
        'answer'   => 'UrbanNutMix offers <strong>1 Kg Big Saver Packs of Dry Fruits,</strong> including Almonds, Cashews, Walnuts, and Pistachios, <strong>starting from Rs. 999 and going up to Rs. 2200.</strong>',
    ],
    [
        'question' => 'Q. Box Dry Fruits',
        'answer'   => 'Dry Fruits are a great <strong>gift for special occasions</strong> and can create a lasting impression when <strong>gifted to clients and affiliates.</strong> UrbanNutMix offers elegantly packaged dry fruit gift boxes perfect for every occasion.',
    ],
    [
        'question' => 'Q. Dry Fruits Benefits For Female',
        'answer'   => 'Dry Fruits like <strong>Raisins and Dates</strong> are rich in nutrients that are beneficial for women. They contain <strong>Iron, Calcium, and Vitamin K</strong> which <strong>promote strong bones</strong> and help prevent low bone density issues in females in their 30s.',
    ],
    [
        'question' => 'Q. Mixed Dry Fruits',
        'answer'   => 'UrbanNutMix\'s <strong>Mixed Dry Fruits</strong> are a premium blend of Almonds, Cashews, Pistachios, Walnuts, and Raisins. They are an <strong>ideal snacking option</strong> that provides a balanced mix of proteins, healthy fats, and natural sugars for sustained energy throughout the day.',
    ],
    [
        'question' => 'Q. Dry Fruits For Weight Loss',
        'answer'   => 'Dry fruits like <strong>Almonds and Walnuts</strong> are high in healthy fats and fiber, helping you feel fuller for longer. <strong>Pistachios and Cashews</strong> are also great choices as they are relatively lower in calories and help in <strong>managing hunger cravings effectively.</strong>',
    ],
];
?>

<section class="unm-popular-searches-section" aria-label="Popular Searches FAQ">
    <div class="unm-popular-searches-inner">

        <div class="unm-popular-searches-heading">
            <h2>Popular Searches</h2>
        </div>

        <div class="unm-popular-searches-list" id="unm-faq-list">
            <?php foreach ($popular_searches as $index => $item): ?>
            <div class="unm-popular-search-item" id="unm-faq-item-<?php echo $index; ?>">
                <button
                    class="unm-popular-search-header"
                    aria-expanded="false"
                    aria-controls="unm-faq-body-<?php echo $index; ?>"
                    id="unm-faq-btn-<?php echo $index; ?>"
                >
                    <span class="unm-popular-search-question"><?php echo htmlspecialchars($item['question']); ?></span>
                    <svg class="unm-popular-search-toggle-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <div
                    class="unm-popular-search-body"
                    id="unm-faq-body-<?php echo $index; ?>"
                    role="region"
                    aria-labelledby="unm-faq-btn-<?php echo $index; ?>"
                >
                    <p class="unm-popular-search-answer"><?php echo $item['answer']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<script>
(function () {
    'use strict';

    var items = document.querySelectorAll('#unm-faq-list .unm-popular-search-item');

    items.forEach(function (item) {
        var btn  = item.querySelector('.unm-popular-search-header');
        var body = item.querySelector('.unm-popular-search-body');

        if (!btn || !body) return;

        btn.addEventListener('click', function () {
            var isOpen = item.classList.contains('active');

            // Close all open items first (accordion behaviour)
            items.forEach(function (other) {
                var otherBody = other.querySelector('.unm-popular-search-body');
                var otherBtn  = other.querySelector('.unm-popular-search-header');
                other.classList.remove('active');
                if (otherBtn)  otherBtn.setAttribute('aria-expanded', 'false');
                if (otherBody) otherBody.style.maxHeight = null;
            });

            // Toggle clicked item
            if (!isOpen) {
                item.classList.add('active');
                btn.setAttribute('aria-expanded', 'true');
                body.style.maxHeight = body.scrollHeight + 'px';
            }
        });
    });
})();
</script>
