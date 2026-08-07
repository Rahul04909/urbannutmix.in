<?php
/**
 * UrbanNutMix - Terms & Conditions
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$page_title = "Terms & Conditions | UrbanNutMix";
$extra_css = ['assets/css/info-pages.css'];
include_once 'includes/header.php';
?>

<!-- Hero Banner -->
<section class="unm-info-hero">
    <div class="unm-info-hero-inner">
        <h1>Terms &amp; Conditions</h1>
        <p>Please read these terms carefully. They define the guidelines and legal agreements for shopping with UrbanNutMix.</p>
    </div>
</section>

<!-- Content Wrapper -->
<main class="unm-info-wrapper">
    <div class="unm-info-container">
        
        <!-- Sticky Sidebar Navigation -->
        <aside class="unm-info-sidebar">
            <h3 class="unm-info-sidebar-title">Terms Sections</h3>
            <ul class="unm-info-nav">
                <li><a href="#agreement" class="unm-info-nav-link active"><i class="fas fa-file-signature"></i> User Agreement</a></li>
                <li><a href="#conduct" class="unm-info-nav-link"><i class="fas fa-user-check"></i> Account Conduct</a></li>
                <li><a href="#billing" class="unm-info-nav-link"><i class="fas fa-shopping-bag"></i> Orders &amp; Payments</a></li>
                <li><a href="#intellectual" class="unm-info-nav-link"><i class="fas fa-copyright"></i> Intellectual Property</a></li>
                <li><a href="#liability" class="unm-info-nav-link"><i class="fas fa-gavel"></i> Liability &amp; Law</a></li>
            </ul>
        </aside>

        <!-- Main Content Card -->
        <article class="unm-info-content-card">
            
            <!-- Section 1: User Agreement -->
            <section class="unm-info-section" id="agreement">
                <h2><i class="fas fa-file-signature"></i> 1. User Agreement &amp; Acceptance</h2>
                <p>These Terms &amp; Conditions ("Terms") constitute a legally binding agreement made between you, whether personally or on behalf of an entity ("you") and UrbanNutMix ("we", "us", "our"), concerning your access to and use of the https://urbannutmix.in website as well as any other media form, channel, or mobile website related, linked, or otherwise connected thereto.</p>
                <p>By accessing the website and purchasing our premium dry fruits and nuts, you acknowledge that you have read, understood, and agree to be bound by all of these Terms. If you do not agree with all of these Terms, then you are expressly prohibited from using the site and must discontinue use immediately.</p>
            </section>

            <!-- Section 2: Account Conduct -->
            <section class="unm-info-section" id="conduct">
                <h2><i class="fas fa-user-check"></i> 2. User Accounts &amp; Conduct Guidelines</h2>
                <p>To access certain features of the website, including checking order history or tracking details, you may be required to register an account. You agree to:</p>
                <ul>
                    <li>Provide accurate, current, and complete information during registration.</li>
                    <li>Keep your login credentials secure and confidential.</li>
                    <li>Accept full responsibility for all activities that occur under your account.</li>
                    <li>Immediately notify us if you suspect any unauthorized access or breach of security.</li>
                </ul>
                <p>We reserve the right to terminate accounts, remove content, or cancel orders at our sole discretion if we detect fraudulent activity, spamming, or violation of these terms.</p>
            </section>

            <!-- Section 3: Orders & Payments -->
            <section class="unm-info-section" id="billing">
                <h2><i class="fas fa-shopping-bag"></i> 3. Orders, Payments, &amp; Pricing Details</h2>
                <p>All prices listed on our website are in Indian Rupees (INR) and are inclusive of GST. We make every effort to display the details, pricing, and availability of our fresh stock accurately. However, we reserve the right to correct errors, modify prices, or refuse/cancel orders if:</p>
                <ol>
                    <li>A product is listed with incorrect pricing or weight parameters due to typographical errors.</li>
                    <li>The product goes out of stock or becomes unavailable.</li>
                    <li>We suspect unauthorized or fraudulent payment methods are being used.</li>
                </ol>
                <p>Payments must be made securely using Razorpay gateway. Cash on Delivery (COD) availability depends on courier networks and is subjected to confirmation calls.</p>
            </section>

            <!-- Section 4: Intellectual Property -->
            <section class="unm-info-section" id="intellectual">
                <h2><i class="fas fa-copyright"></i> 4. Intellectual Property Rights</h2>
                <p>Unless otherwise indicated, the website and its entire content, including source code, database architectures, graphics, product photography, trademarks, brand names, and logos ("Intellectual Property") are owned or licensed by UrbanNutMix, and are protected by copyright, trademark, and other proprietary rights of India and international treaties.</p>
                <p>No part of the website or content may be copied, reproduced, aggregated, republished, uploaded, posted, publicly displayed, encoded, translated, transmitted, distributed, sold, licensed, or otherwise exploited for any commercial purpose whatsoever without our express prior written permission.</p>
            </section>

            <!-- Section 5: Liability & Law -->
            <section class="unm-info-section" id="liability">
                <h2><i class="fas fa-gavel"></i> 5. Limitation of Liability &amp; Governing Law</h2>
                <p>UrbanNutMix shall not be liable for any direct, indirect, incidental, special, or consequential damages resulting from the use or the inability to use our website, products, or logistics partners. We do not guarantee that the site will be error-free or uninterrupted.</p>
                <p>These terms and conditions are governed by and construed in accordance with the laws of India. Any legal actions, disputes, or claims arising out of or in connection with these Terms shall be subject to the exclusive jurisdiction of the courts located in Faridabad, Haryana, India.</p>

                <!-- Contact Widget -->
                <div class="unm-info-contact-card">
                    <div class="unm-info-contact-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="unm-info-contact-body">
                        <h4>Contact Legal Department</h4>
                        <p>For questions or formal clarifications regarding our Terms &amp; Conditions, contact us:</p>
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
