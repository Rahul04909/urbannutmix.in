<?php
/**
 * UrbanNutMix – Professional Footer Component
 */
?>
<footer class="unm-footer" role="contentinfo">

    <!-- ── 1. PAYMENT GATEWAYS BAND ─────────────────────────── -->
    <div class="unm-footer-newsletter">
        <div class="unm-footer-newsletter-inner" style="justify-content: center; width: 100%;">
            <img 
                src="https://www.jewelfarmer.com/cdn/shop/files/Card_Wallets_UPI_Netbanking_4.png?v=1743075404&width=2250" 
                alt="Supported Payment Options: Cards, Wallets, UPI, Netbanking" 
                class="unm-footer-payment-img"
                loading="lazy"
            >
        </div>
    </div>

    <!-- ── 2. LOGO / CONTACT / FSSAI ────────────────────────── -->
    <div class="unm-footer-top">
        <div class="unm-footer-top-inner">

            <!-- Logo -->
            <div class="unm-footer-logo-wrap">
                <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>index.php" aria-label="UrbanNutMix Home">
                    <img
                        src="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>assets/images/logo-bg.jpg"
                        alt="UrbanNutMix Logo"
                        class="unm-footer-logo-img"
                        loading="lazy"
                    >
                </a>
            </div>

            <!-- Contact -->
            <ul class="unm-footer-contact-list">
                <li class="unm-footer-contact-item">
                    <!-- Pin icon -->
                    <svg class="unm-footer-contact-icon" viewBox="0 0 24 24">
                        <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>Office No. 702, 7th Floor, Sector 68, Noida, Uttar Pradesh 201307</span>
                </li>
                <li class="unm-footer-contact-item">
                    <!-- Phone icon -->
                    <svg class="unm-footer-contact-icon" viewBox="0 0 24 24">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.18 2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.56a16 16 0 0 0 5.72 5.72l.92-.93a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <a href="tel:+919876543210">+91-9876543210</a>
                </li>
                <li class="unm-footer-contact-item">
                    <!-- Mail icon -->
                    <svg class="unm-footer-contact-icon" viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <a href="mailto:support@urbannutmix.in">support@urbannutmix.in</a>
                </li>
            </ul>

            <!-- FSSAI / Certifications -->
            <div class="unm-footer-fssai">
                <span class="unm-footer-fssai-tag">FSSAI Certified</span>
                <span>FSSAI License No. – 10016051001876</span>
                <span>FSSAI License No. – 10017061000315</span>
            </div>

        </div>
    </div>

    <!-- ── 3. LINKS GRID ─────────────────────────────────────── -->
    <div class="unm-footer-links">
        <div class="unm-footer-links-inner">

            <!-- Company -->
            <div class="unm-footer-col">
                <h3 class="unm-footer-col-heading">Company</h3>
                <ul class="unm-footer-col-list">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Our Story</a></li>
                    <li><a href="#">Quality Assurance</a></li>
                    <li><a href="#">Certifications</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Awards & Recognition</a></li>
                </ul>
            </div>

            <!-- Quick Links -->
            <div class="unm-footer-col">
                <h3 class="unm-footer-col-heading">Quick Links</h3>
                <ul class="unm-footer-col-list">
                    <li><a href="#">Shop</a></li>
                    <li><a href="#">Blogs</a></li>
                    <li><a href="#">Diwali Gifting</a></li>
                    <li><a href="#">Bulk Order</a></li>
                    <li><a href="#">Become a Partner</a></li>
                    <li><a href="#">Gift Hampers</a></li>
                </ul>
            </div>

            <!-- Information -->
            <div class="unm-footer-col">
                <h3 class="unm-footer-col-heading">Information</h3>
                <ul class="unm-footer-col-list">
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Return &amp; Refund Policy</a></li>
                    <li><a href="#">Shipping &amp; Delivery Policy</a></li>
                    <li><a href="#">Terms &amp; Conditions</a></li>
                </ul>
            </div>

            <!-- Customer Services -->
            <div class="unm-footer-col">
                <h3 class="unm-footer-col-heading">Customer Services</h3>
                <ul class="unm-footer-col-list">
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Track Order</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Grievance Redressal</a></li>
                </ul>
            </div>

        </div>
    </div>

    <!-- ── 4. SOCIAL BAR ─────────────────────────────────────── -->
    <div class="unm-footer-social-bar">
        <div class="unm-footer-social-bar-inner">
            <span class="unm-footer-social-label">Follow Us:</span>

            <!-- Facebook -->
            <a href="#" class="unm-footer-social-link" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
                <svg class="unm-footer-social-icon" viewBox="0 0 24 24">
                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                </svg>
            </a>

            <!-- Instagram -->
            <a href="#" class="unm-footer-social-link" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
                <svg class="unm-footer-social-icon" viewBox="0 0 24 24">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                </svg>
            </a>

            <!-- LinkedIn -->
            <a href="#" class="unm-footer-social-link" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer">
                <svg class="unm-footer-social-icon" viewBox="0 0 24 24">
                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path>
                    <rect x="2" y="9" width="4" height="12"></rect>
                    <circle cx="4" cy="4" r="2"></circle>
                </svg>
            </a>

            <!-- YouTube -->
            <a href="#" class="unm-footer-social-link" aria-label="YouTube" target="_blank" rel="noopener noreferrer">
                <svg class="unm-footer-social-icon" viewBox="0 0 24 24">
                    <path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.4a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"></path>
                    <polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"></polygon>
                </svg>
            </a>

        </div>
    </div>

    <!-- ── 5. COPYRIGHT BAR ──────────────────────────────────── -->
    <div class="unm-footer-copyright">
        <div class="unm-footer-copyright-inner">
            <p>Copyright &copy; <?php echo date('Y'); ?> UrbanNutMix. All Rights Reserved.</p>
            <p>
                <strong>Important Notice:</strong>
                urbannutmix.in is the ONLY official website of UrbanNutMix. Please avoid buying from any other imposter websites.
                We are also available on trusted e-commerce sites:
                <a href="#" target="_blank">Amazon</a>,
                <a href="#" target="_blank">Flipkart</a>,
                <a href="#" target="_blank">Jiomart</a>,
                <a href="#" target="_blank">Zepto</a>,
                <a href="#" target="_blank">Blinkit</a> &amp; more.
            </p>
        </div>
    </div>

</footer>
