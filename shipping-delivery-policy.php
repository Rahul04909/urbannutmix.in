<?php
/**
 * UrbanNutMix - Shipping & Delivery Policy
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$page_title = "Shipping & Delivery Policy | UrbanNutMix";
$extra_css = ['assets/css/info-pages.css'];
include_once 'includes/header.php';
?>

<!-- Hero Banner -->
<section class="unm-info-hero">
    <div class="unm-info-hero-inner">
        <h1>Shipping &amp; Delivery Policy</h1>
        <p>Premium nuts deserve premium shipping. Read about our express transit networks, weight-based rates, and coverage.</p>
    </div>
</section>

<!-- Content Wrapper -->
<main class="unm-info-wrapper">
    <div class="unm-info-container">
        
        <!-- Sticky Sidebar Navigation -->
        <aside class="unm-info-sidebar">
            <h3 class="unm-info-sidebar-title">Policy Sections</h3>
            <ul class="unm-info-nav">
                <li><a href="#rates" class="unm-info-nav-link active"><i class="fas fa-hand-holding-usd"></i> Shipping Rates</a></li>
                <li><a href="#timelines" class="unm-info-nav-link"><i class="fas fa-shipping-fast"></i> Delivery Timelines</a></li>
                <li><a href="#coverage" class="unm-info-nav-link"><i class="fas fa-map-marked-alt"></i> Pin Code Coverage</a></li>
                <li><a href="#transit" class="unm-info-nav-link"><i class="fas fa-box-open"></i> Damaged &amp; Lost Packages</a></li>
                <li><a href="#partners" class="unm-info-nav-link"><i class="fas fa-handshake"></i> Courier Partners</a></li>
            </ul>
        </aside>

        <!-- Main Content Card -->
        <article class="unm-info-content-card">
            
            <!-- Section 1: Shipping Rates -->
            <section class="unm-info-section" id="rates">
                <h2><i class="fas fa-hand-holding-usd"></i> 1. Shipping Rates &amp; Delivery Charges</h2>
                <p>We calculate our shipping fees dynamically based on the total weight of the items in your shopping cart. This ensures that heavier bulk orders are incentivized with free transit, while lighter items cover direct courier costs:</p>
                
                <!-- Highlights Grid -->
                <div class="unm-info-highlight-grid">
                    <div class="unm-info-highlight-card" style="border-color: var(--primary-gold);">
                        <i class="fas fa-weight unm-info-highlight-icon"></i>
                        <h4 class="unm-info-highlight-title">Total Weight &lt; 5kg</h4>
                        <p class="unm-info-highlight-text" style="font-size: 1.15rem; font-weight: 700; color: var(--primary-gold);">₹90.00 Delivery Fee</p>
                        <p class="unm-info-highlight-text">Applied flat across India for total orders weighing under 5kg.</p>
                    </div>
                    <div class="unm-info-highlight-card" style="background-color: rgba(207, 110, 12, 0.05); border-color: var(--primary-gold);">
                        <i class="fas fa-gift unm-info-highlight-icon"></i>
                        <h4 class="unm-info-highlight-title">Total Weight &ge; 5kg</h4>
                        <p class="unm-info-highlight-text" style="font-size: 1.15rem; font-weight: 700; color: var(--primary-gold);">FREE Delivery</p>
                        <p class="unm-info-highlight-text">Add 5kg or more of nuts, seeds, or dry fruits to qualify for free shipping!</p>
                    </div>
                </div>

                <p>You can check the weight of your cart and see if you qualify for free delivery directly on the shopping cart page prior to proceeding to checkout.</p>
            </section>

            <!-- Section 2: Delivery Timelines -->
            <section class="unm-info-section" id="timelines">
                <h2><i class="fas fa-shipping-fast"></i> 2. Processing &amp; Delivery Timelines</h2>
                <p>All orders placed on our website are dispatched from our warehouse in Faridabad, Haryana. Our processing and delivery timelines are as follows:</p>
                <ul>
                    <li><strong>Order Processing:</strong> Orders placed before 2:00 PM are processed and handed over to couriers on the same working day. Orders placed after 2:00 PM are dispatched on the next working day. We do not dispatch orders on Sundays or National Holidays.</li>
                    <li><strong>Transit Times:</strong>
                        <ul>
                            <li><strong>Delhi-NCR:</strong> Delivery within 1-2 working days.</li>
                            <li><strong>Metros &amp; Major Cities:</strong> Delivery within 2-3 working days.</li>
                            <li><strong>Rest of India:</strong> Delivery within 3-5 working days.</li>
                        </ul>
                    </li>
                </ul>
            </section>

            <!-- Section 3: Pin Code Coverage -->
            <section class="unm-info-section" id="coverage">
                <h2><i class="fas fa-map-marked-alt"></i> 3. Pin Code Coverage &amp; COD Availability</h2>
                <p>We deliver to over 19,000 pin codes across India using our courier partners' networks. You can check delivery availability for your specific location on the shopping cart page using the Pincode Check input.</p>
                <p><strong>Cash on Delivery (COD):</strong> COD is available for select pin codes for orders up to ₹5,000. COD orders may require a telephonic verification call from our customer care team before dispatch. If the customer does not confirm the order via call/SMS within 48 hours, the order is cancelled.</p>
            </section>

            <!-- Section 4: Damaged & Lost Packages -->
            <section class="unm-info-section" id="transit">
                <h2><i class="fas fa-box-open"></i> 4. Transit Insurance &amp; Damaged Packages</h2>
                <p>Every shipment sent by UrbanNutMix is fully insured against theft and transit damages. If you receive a package that is visibly damaged, torn, or has its security seals broken:</p>
                <ol>
                    <li>Do not accept delivery from the courier agent.</li>
                    <li>Take a picture of the damaged box.</li>
                    <li>Contact us immediately at urbannutmix@gmail.com with your order number.</li>
                </ol>
                <p>We will immediately dispatch a fresh replacement package to you at no extra cost, while we handle the dispute with the courier partner.</p>
            </section>

            <!-- Section 5: Courier Partners -->
            <section class="unm-info-section" id="partners">
                <h2><i class="fas fa-handshake"></i> 5. Our Trusted Delivery Partners</h2>
                <p>To ensure that our premium vacuum-sealed packages reach you in perfect condition, we only partner with leading express logistics companies in India, including:</p>
                <ul>
                    <li><strong>Delhivery Express:</strong> Our primary partner for standard and bulk deliveries across India.</li>
                    <li><strong>BlueDart / DHL:</strong> Used for rapid air-express shipping to metro cities.</li>
                    <li><strong>XpressBees &amp; Shadowfax:</strong> Used for quick regional dispatches.</li>
                </ul>
                <p>Once your order is handed over to the logistics partner, you will receive a tracking link via email and SMS with the tracking ID (AWB Number) to monitor the transit progress in real-time.</p>

                <!-- Contact Widget -->
                <div class="unm-info-contact-card">
                    <div class="unm-info-contact-icon">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="unm-info-contact-body">
                        <h4>Contact Delivery Desk</h4>
                        <p>Need support tracking your parcel, or have delivery feedback? Get in touch with us:</p>
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
