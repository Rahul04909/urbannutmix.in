<?php
/**
 * UrbanNutMix - Return & Refund Policy
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$page_title = "Return & Refund Policy | UrbanNutMix";
$extra_css = ['assets/css/info-pages.css'];
include_once 'includes/header.php';
?>

<!-- Hero Banner -->
<section class="unm-info-hero">
    <div class="unm-info-hero-inner">
        <h1>Return &amp; Refund Policy</h1>
        <p>Your satisfaction is our guarantee. Learn about our 7-day easy returns and refund policies at UrbanNutMix.</p>
    </div>
</section>

<!-- Content Wrapper -->
<main class="unm-info-wrapper">
    <div class="unm-info-container">
        
        <!-- Sticky Sidebar Navigation -->
        <aside class="unm-info-sidebar">
            <h3 class="unm-info-sidebar-title">Policy Sections</h3>
            <ul class="unm-info-nav">
                <li><a href="#overview" class="unm-info-nav-link active"><i class="fas fa-clipboard-check"></i> Policy Overview</a></li>
                <li><a href="#eligibility" class="unm-info-nav-link"><i class="fas fa-tasks"></i> Return Eligibility</a></li>
                <li><a href="#refund" class="unm-info-nav-link"><i class="fas fa-wallet"></i> Refund Process</a></li>
                <li><a href="#exchange" class="unm-info-nav-link"><i class="fas fa-sync-alt"></i> Exchange Policy</a></li>
                <li><a href="#initiate" class="unm-info-nav-link"><i class="fas fa-paper-plane"></i> How to Initiate</a></li>
            </ul>
        </aside>

        <!-- Main Content Card -->
        <article class="unm-info-content-card">
            
            <!-- Section 1: Overview -->
            <section class="unm-info-section" id="overview">
                <h2><i class="fas fa-clipboard-check"></i> 1. Policy Overview (7-Day Guarantee)</h2>
                <p>At UrbanNutMix, we take great pride in sourcing the highest quality premium nuts, dry fruits, seeds, and hampers. However, if you are not fully satisfied with your purchase, we offer a <strong>7-day quality check guarantee</strong>.</p>
                <p>Under this guarantee, you can request a return, replacement, or refund within <strong>7 days from the date of delivery</strong> of your order. Any requests made after 7 days from delivery will not be eligible for returns or refunds.</p>
                
                <!-- Highlights Grid -->
                <div class="unm-info-highlight-grid">
                    <div class="unm-info-highlight-card">
                        <i class="fas fa-history unm-info-highlight-icon"></i>
                        <h4 class="unm-info-highlight-title">7-Day Window</h4>
                        <p class="unm-info-highlight-text">Request returns within 7 calendar days of order delivery.</p>
                    </div>
                    <div class="unm-info-highlight-card">
                        <i class="fas fa-shield-alt unm-info-highlight-icon"></i>
                        <h4 class="unm-info-highlight-title">100% Assurance</h4>
                        <p class="unm-info-highlight-text">Full refunds/replacements for defective or incorrect orders.</p>
                    </div>
                    <div class="unm-info-highlight-card">
                        <i class="fas fa-truck-loading unm-info-highlight-icon"></i>
                        <h4 class="unm-info-highlight-title">Reverse Pickup</h4>
                        <p class="unm-info-highlight-text">Free reverse shipping arranged by our courier network.</p>
                    </div>
                </div>
            </section>

            <!-- Section 2: Eligibility -->
            <section class="unm-info-section" id="eligibility">
                <h2><i class="fas fa-tasks"></i> 2. Return Eligibility Criteria</h2>
                <p>To ensure fairness and quality standards, items are eligible for returns or exchanges only under the following conditions:</p>
                <ul>
                    <li><strong>Damaged in Transit:</strong> If the packaging was punctured, crushed, or vacuum seals were broken before delivery.</li>
                    <li><strong>Quality Defects:</strong> If the contents of the pack show quality deterioration, mould, or bad odor upon opening.</li>
                    <li><strong>Wrong Item Received:</strong> If the item delivered does not match your purchase invoice.</li>
                    <li><strong>Unopened Items:</strong> If you changed your mind, items must be unopened, in their original vacuum/resealable packs, with all tags and barcodes intact.</li>
                </ul>
                <p>Please note: opened packs of products where more than 10% of the contents have been consumed are not eligible for returns unless there is a proven product quality issue.</p>
            </section>

            <!-- Section 3: Refund Process -->
            <section class="unm-info-section" id="refund">
                <h2><i class="fas fa-wallet"></i> 3. Refund Process &amp; Modes</h2>
                <p>Once a return is received and inspected at our warehouse, we will notify you of the approval or rejection of your refund. If approved, refunds are processed according to your payment method:</p>
                
                <div class="unm-info-table-wrap">
                    <table class="unm-info-table">
                        <thead>
                            <tr>
                                <th>Original Payment Method</th>
                                <th>Refund Mode</th>
                                <th>Processing Timeline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Credit / Debit Card</td>
                                <td>Bank Account (Original Card)</td>
                                <td>5-7 Working Days</td>
                            </tr>
                            <tr>
                                <td>UPI / Net Banking</td>
                                <td>Source Account via Razorpay</td>
                                <td>2-3 Working Days</td>
                            </tr>
                            <tr>
                                <td>COD (Cash on Delivery)</td>
                                <td>Direct Bank Transfer (NEFT) or Wallet Credits</td>
                                <td>3-5 Working Days</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <p>Note: Shipping charges paid at the time of purchase are non-refundable unless the return is due to our error (e.g. wrong/damaged item).</p>
            </section>

            <!-- Section 4: Exchange Policy -->
            <section class="unm-info-section" id="exchange">
                <h2><i class="fas fa-sync-alt"></i> 4. Exchange &amp; Replacement Policy</h2>
                <p>If you received a damaged, stale, or incorrect product, we recommend opting for a free replacement. Replacements are shipped via express delivery immediately upon verification of your request, without waiting for the reverse pickup to reach our warehouse.</p>
                <p>To qualify for a free replacement, please share a clear photograph or a short unboxing video showing the issue (like broken seal, damage, or wrong label) when initiating the request.</p>
            </section>

            <!-- Section 5: How to Initiate -->
            <section class="unm-info-section" id="initiate">
                <h2><i class="fas fa-paper-plane"></i> 5. How to Initiate a Return Request</h2>
                <p>You can quickly start a return or exchange request by following these simple steps:</p>
                <ol>
                    <li>Take a picture of the product packet (showing the barcode and the issue, if any).</li>
                    <li>Write an email to our helpdesk with your <strong>Order Number</strong> (e.g., UNM-XXXXXX).</li>
                    <li>Specify whether you want a **Replacement (Exchange)** or a **Refund**.</li>
                </ol>
                <p>Our customer service team will respond within 24 hours and coordinate the free reverse pickup from your registered shipping address.</p>

                <!-- Contact Widget -->
                <div class="unm-info-contact-card">
                    <div class="unm-info-contact-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="unm-info-contact-body">
                        <h4>Contact Helpdesk for Returns</h4>
                        <p>Need help with your refund or return? Get in touch with our operations team:</p>
                        <div class="unm-info-contact-links">
                            <a href="mailto:urbannutmix@gmail.com" class="unm-info-contact-link"><i class="fas fa-envelope"></i> urbannutmix@gmail.com</a>
                            <a href="tel:+918700792154" class="unm-info-contact-link"><i class="fas fa-phone"></i> +91-8700792154</a>
                        </div>
                    </div>
                </div>
            </section>

        </article>

    </div>
</main>

<!-- Scroll Spy JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.unm-info-section');
    const navLinks = document.querySelectorAll('.unm-info-nav-link');
    
    function activateLink() {
        let index = sections.length;
        while(--index && window.scrollY + 160 < sections[index].offsetTop) {}
        navLinks.forEach((link) => link.classList.remove('active'));
        if (navLinks[index]) {
            navLinks[index].classList.add('active');
            
            // Auto scroll mobile nav slider
            const parent = navLinks[index].parentElement.parentElement;
            if (parent && window.innerWidth <= 991) {
                const offsetLeft = navLinks[index].offsetLeft;
                parent.scrollTo({
                    left: offsetLeft - 20,
                    behavior: 'smooth'
                });
            }
        }
    }
    
    activateLink();
    window.addEventListener('scroll', activateLink);

    // Smooth scroll for nav links click
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                window.scrollTo({
                    top: targetSection.offsetTop - 140,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
