<?php
/**
 * UrbanNutMix - Privacy Policy
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

$page_title = "Privacy Policy | UrbanNutMix";
$extra_css = ['assets/css/info-pages.css'];
include_once 'includes/header.php';
?>

<!-- Hero Banner -->
<section class="unm-info-hero">
    <div class="unm-info-hero-inner">
        <h1>Privacy Policy</h1>
        <p>Your privacy matters to us. Learn how we collect, use, protect, and manage your data at UrbanNutMix.</p>
    </div>
</section>

<!-- Content Wrapper -->
<main class="unm-info-wrapper">
    <div class="unm-info-container">
        
        <!-- Sticky Sidebar Navigation -->
        <aside class="unm-info-sidebar">
            <h3 class="unm-info-sidebar-title">Privacy Sections</h3>
            <ul class="unm-info-nav">
                <li><a href="#intro" class="unm-info-nav-link active"><i class="fas fa-info-circle"></i> Introduction</a></li>
                <li><a href="#collect" class="unm-info-nav-link"><i class="fas fa-database"></i> Data Collection</a></li>
                <li><a href="#usage" class="unm-info-nav-link"><i class="fas fa-cog"></i> How We Use Data</a></li>
                <li><a href="#security" class="unm-info-nav-link"><i class="fas fa-shield-alt"></i> Security &amp; Storage</a></li>
                <li><a href="#cookies" class="unm-info-nav-link"><i class="fas fa-cookie-bite"></i> Cookies &amp; Tracking</a></li>
                <li><a href="#rights" class="unm-info-nav-link"><i class="fas fa-user-shield"></i> Your Rights</a></li>
            </ul>
        </aside>

        <!-- Main Content Card -->
        <article class="unm-info-content-card">
            
            <!-- Section 1: Introduction -->
            <section class="unm-info-section" id="intro">
                <h2><i class="fas fa-info-circle"></i> 1. Introduction &amp; Scope</h2>
                <p>Welcome to UrbanNutMix ("we", "our", "us"). We are committed to protecting your personal information and your right to privacy. This Privacy Policy governs the privacy practices of our website (https://urbannutmix.in) and describes how we handle the personal information collected from our customers, visitors, and partners.</p>
                <p>By accessing or using our services, you agree to the collection, storage, and use of your information as outlined in this policy. If you do not agree with any terms in this policy, please discontinue the use of our website immediately.</p>
            </section>

            <!-- Section 2: Data Collection -->
            <section class="unm-info-section" id="collect">
                <h2><i class="fas fa-database"></i> 2. Information We Collect</h2>
                <p>We collect personal information that you voluntarily provide to us when you register on our website, express an interest in obtaining information about us or our products, place an order, or contact us. The personal information we collect may include:</p>
                <ul>
                    <li><strong>Contact Details:</strong> Your name, email address, mobile number, shipping address, and billing address.</li>
                    <li><strong>Payment Information:</strong> We collect billing details necessary to process your payment (processed securely via Razorpay). We do not store raw credit card details on our servers.</li>
                    <li><strong>Account Credentials:</strong> Passwords and security details used for account authentication.</li>
                    <li><strong>Device &amp; Log Data:</strong> IP addresses, browser specifications, operating system parameters, referral URLs, and logs of your interactions on our website.</li>
                </ul>
            </section>

            <!-- Section 3: How We Use Data -->
            <section class="unm-info-section" id="usage">
                <h2><i class="fas fa-cog"></i> 3. How We Use Data</h2>
                <p>We process your personal information for purposes based on legitimate business interests, the fulfillment of our contract with you, and compliance with our legal obligations. These include:</p>
                <ol>
                    <li><strong>Order Processing &amp; Fulfillment:</strong> To manage purchases, shipments, payments, and delivery tracking.</li>
                    <li><strong>Account Administration:</strong> To facilitate user account creation, logon, and profile updates.</li>
                    <li><strong>Customer Support:</strong> To respond to inquiries, resolve issues, and process return/exchange requests.</li>
                    <li><strong>Marketing &amp; Promotions:</strong> To send newsletters, updates, and seasonal offers (which you can opt out of at any time).</li>
                    <li><strong>Service Improvements:</strong> To monitor system analytics and enhance our web designs and product range.</li>
                </ol>
            </section>

            <!-- Section 4: Security & Storage -->
            <section class="unm-info-section" id="security">
                <h2><i class="fas fa-shield-alt"></i> 4. Security &amp; Storage</h2>
                <p>We implement a variety of technical and organizational security measures designed to maintain the safety of your personal information. These include SSL encryption during data transmission, hashed password storage, and secure backend firewalls.</p>
                <p>Your details are stored securely as long as you maintain an account with us, or as long as necessary to comply with transaction records, tax codes, and legal disputes. Once no longer required, your data is securely deleted or anonymized.</p>
            </section>

            <!-- Section 5: Cookies & Tracking -->
            <section class="unm-info-section" id="cookies">
                <h2><i class="fas fa-cookie-bite"></i> 5. Cookies &amp; Third-party Tracking</h2>
                <p>Our website uses cookies and similar tracking technologies (like web beacons and pixels) to access or store information. Cookies help us keep you logged in, remember items in your shopping cart, and analyze traffic patterns.</p>
                <p>You can set your browser to refuse all or some browser cookies, or to alert you when websites set or access cookies. However, if you disable or refuse cookies, please note that some parts of our website may become inaccessible or not function properly.</p>
            </section>

            <!-- Section 6: Your Rights -->
            <section class="unm-info-section" id="rights">
                <h2><i class="fas fa-user-shield"></i> 6. Customer Rights &amp; Choices</h2>
                <p>Depending on your location, you may have specific rights regarding your personal information, including:</p>
                <ul>
                    <li>The right to request access to and receive a copy of your personal data.</li>
                    <li>The right to request correction of inaccurate details or complete missing information.</li>
                    <li>The right to request deletion of your account and personal details (under certain conditions).</li>
                    <li>The right to opt out of promotional emails by clicking the "unsubscribe" link in our newsletters.</li>
                </ul>
                <p>To exercise any of these rights, please contact our support team using the details in the block below.</p>

                <!-- Contact Widget -->
                <div class="unm-info-contact-card">
                    <div class="unm-info-contact-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="unm-info-contact-body">
                        <h4>Contact Privacy Desk</h4>
                        <p>For questions, requests, or concerns about this policy or your data rights, please contact us:</p>
                        <div class="unm-info-contact-links">
                            <a href="mailto:support@urbannutmix.in" class="unm-info-contact-link"><i class="fas fa-envelope"></i> support@urbannutmix.in</a>
                            <a href="tel:+919876543210" class="unm-info-contact-link"><i class="fas fa-phone"></i> +91-9876543210</a>
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
            
            // Auto scroll mobile nav slider to keep active link visible
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
