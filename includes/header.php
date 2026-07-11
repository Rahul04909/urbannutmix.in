<?php
/**
 * UrbanNutMix - Interactive & Mobile Responsive Header Component
 */

// Calculate base URL dynamically to handle subfolders (e.g. localhost/urbannutmix.in/)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];

// Get project root absolute path (dirname(dirname(__FILE__)) because header is inside includes/)
$project_root = str_replace('\\', '/', dirname(dirname(__FILE__)));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

// Normalize casing for Windows directory structures
$project_root_normalized = strtolower($project_root);
$doc_root_normalized = strtolower($doc_root);

if (strpos($project_root_normalized, $doc_root_normalized) === 0) {
    $relative_path = substr($project_root, strlen($doc_root));
} else {
    // Fallback for symlink/alias configurations
    $relative_path = dirname($_SERVER['SCRIPT_NAME']);
}

$base_url = $protocol . $domain . '/' . ltrim($relative_path, '/');
$base_url = rtrim($base_url, '/\\') . '/';

if (!defined('BASE_URL')) {
    define('BASE_URL', $base_url);
}

// Current page detection for active class highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UrbanNutMix - Premium quality dry fruits, nuts, berries, seeds, and healthy gift hampers delivered directly to your doorstep.">
    <meta name="keywords" content="dry fruits, nuts, almonds, cashews, pistachios, healthy snacks, gifting, diwali gifting, UrbanNutMix">
    <title><?php echo isset($page_title) ? $page_title . " | UrbanNutMix" : "UrbanNutMix | Premium Dry Fruits & Nuts"; ?></title>
    
    <!-- Favicon link -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>favicon.png">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/header.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/hero.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/categories.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/products.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/footer.css">
</head>
<body>

<div class="unm-header-wrapper">
    <!-- TOPBAR -->
    <div class="unm-topbar">
        A Website Powerd By Mineib Creative Technology
    </div>

    <!-- MAIN HEADER -->
    <header class="unm-main-header" id="unmHeader">
        <div class="unm-container">
            <!-- LOGO -->
            <a href="<?php echo BASE_URL; ?>index.php" class="unm-logo-link" aria-label="UrbanNutMix Home">
                <img src="<?php echo BASE_URL; ?>assets/images/logo-bg.jpg" alt="UrbanNutMix Logo" class="unm-logo-img">
            </a>

            <!-- DESKTOP NAVIGATION -->
            <nav class="unm-desktop-nav" aria-label="Main Desktop Navigation">
                <ul class="unm-nav-menu">
                    <li class="unm-nav-item">
                        <a href="<?php echo BASE_URL; ?>index.php" class="unm-nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a>
                    </li>
                    <li class="unm-nav-item">
                        <a href="<?php echo BASE_URL; ?>shop.php" class="unm-nav-link <?php echo ($current_page == 'shop.php') ? 'active' : ''; ?>">Shop</a>
                    </li>
                    <li class="unm-nav-item">
                        <a href="<?php echo BASE_URL; ?>partner.php" class="unm-nav-link <?php echo ($current_page == 'partner.php') ? 'active' : ''; ?>">Become a Partner</a>
                    </li>
                    <li class="unm-nav-item">
                        <a href="<?php echo BASE_URL; ?>blogs.php" class="unm-nav-link <?php echo ($current_page == 'blogs.php') ? 'active' : ''; ?>">Blogs</a>
                    </li>
                    <li class="unm-nav-item">
                        <a href="<?php echo BASE_URL; ?>diwali-gifting.php" class="unm-nav-link <?php echo ($current_page == 'diwali-gifting.php') ? 'active' : ''; ?>">Diwali Gifting</a>
                    </li>
                </ul>
            </nav>

            <!-- HEADER ACTIONS -->
            <div class="unm-header-actions">
                <!-- Search Trigger -->
                <button class="unm-action-btn" id="unmSearchBtn" aria-label="Open Search">
                    <svg class="unm-action-icon" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>

                <!-- Account -->
                <a href="<?php echo BASE_URL; ?>account.php" class="unm-action-btn" aria-label="User Account">
                    <svg class="unm-action-icon" viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </a>

                <!-- Cart -->
                <a href="<?php echo BASE_URL; ?>cart.php" class="unm-action-btn" aria-label="Shopping Cart">
                    <svg class="unm-action-icon" viewBox="0 0 24 24">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span class="unm-cart-badge">0</span>
                </a>

                <!-- Hamburger (Mobile Only) -->
                <button class="unm-hamburger" id="unmHamburger" aria-label="Toggle Mobile Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <!-- MOBILE DRAWER -->
    <div class="unm-drawer-overlay" id="unmDrawerOverlay"></div>
    <aside class="unm-mobile-drawer" id="unmMobileDrawer">
        <ul class="unm-mobile-nav">
            <li class="unm-mobile-item">
                <a href="<?php echo BASE_URL; ?>index.php" class="unm-mobile-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a>
            </li>
            <li class="unm-mobile-item">
                <a href="<?php echo BASE_URL; ?>shop.php" class="unm-mobile-link <?php echo ($current_page == 'shop.php') ? 'active' : ''; ?>">Shop</a>
            </li>
            <li class="unm-mobile-item">
                <a href="<?php echo BASE_URL; ?>partner.php" class="unm-mobile-link <?php echo ($current_page == 'partner.php') ? 'active' : ''; ?>">Become a Partner</a>
            </li>
            <li class="unm-mobile-item">
                <a href="<?php echo BASE_URL; ?>blogs.php" class="unm-mobile-link <?php echo ($current_page == 'blogs.php') ? 'active' : ''; ?>">Blogs</a>
            </li>
            <li class="unm-mobile-item">
                <a href="<?php echo BASE_URL; ?>diwali-gifting.php" class="unm-mobile-link <?php echo ($current_page == 'diwali-gifting.php') ? 'active' : ''; ?>">Diwali Gifting</a>
            </li>
        </ul>

        <div class="unm-mobile-drawer-footer">
            <div class="unm-mobile-contact">
                <span>Customer Support:</span>
                <a href="tel:+919876543210">+91 98765 43210</a>
                <a href="mailto:support@urbannutmix.in">support@urbannutmix.in</a>
            </div>
        </div>
    </aside>

    <!-- SEARCH OVERLAY -->
    <div class="unm-search-overlay" id="unmSearchOverlay">
        <div class="unm-search-container">
            <form action="<?php echo BASE_URL; ?>shop.php" method="GET" style="display: flex; flex-grow: 1; gap: 12px; align-items: center;">
                <svg class="unm-action-icon" viewBox="0 0 24 24" style="color: var(--text-light);">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="search" placeholder="Search premium dry fruits, nuts, gift boxes..." class="unm-search-input" id="unmSearchInput" autocomplete="off">
            </form>
            <button class="unm-search-close" id="unmSearchClose" aria-label="Close Search">
                <svg class="unm-action-icon" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const header = document.getElementById('unmHeader');
    const hamburgerBtn = document.getElementById('unmHamburger');
    const mobileDrawer = document.getElementById('unmMobileDrawer');
    const drawerOverlay = document.getElementById('unmDrawerOverlay');
    const searchBtn = document.getElementById('unmSearchBtn');
    const searchOverlay = document.getElementById('unmSearchOverlay');
    const searchClose = document.getElementById('unmSearchClose');
    const searchInput = document.getElementById('unmSearchInput');
    const mobileShopToggle = document.getElementById('unmMobileShopToggle');
    const mobileDropdownMenu = document.getElementById('unmMobileDropdownMenu');

    // --- Sticky Header Logic ---
    window.addEventListener('scroll', function() {
        if (window.scrollY > 120) {
            header.classList.add('unm-header-sticky');
        } else {
            header.classList.remove('unm-header-sticky');
        }
    });

    // --- Mobile Drawer Toggle Logic ---
    function toggleDrawer() {
        hamburgerBtn.classList.toggle('active');
        mobileDrawer.classList.toggle('active');
        drawerOverlay.classList.toggle('active');
        // Prevent body scroll when drawer is open
        document.body.style.overflow = mobileDrawer.classList.contains('active') ? 'hidden' : '';
    }

    hamburgerBtn.addEventListener('click', toggleDrawer);
    drawerOverlay.addEventListener('click', toggleDrawer);

    // --- Mobile Dropdown Accordion ---
    if (mobileShopToggle) {
        mobileShopToggle.addEventListener('click', function(e) {
            e.preventDefault();
            mobileDropdownMenu.classList.toggle('active');
            
            // Rotate arrow icon
            const arrow = mobileShopToggle.querySelector('.unm-nav-arrow');
            if (arrow) {
                if (mobileDropdownMenu.classList.contains('active')) {
                    arrow.style.transform = 'rotate(180deg)';
                } else {
                    arrow.style.transform = '';
                }
            }
        });
    }

    // --- Search Overlay Logic ---
    searchBtn.addEventListener('click', function() {
        searchOverlay.classList.add('active');
        setTimeout(() => searchInput.focus(), 200); // Focus input after animation
    });

    searchClose.addEventListener('click', function() {
        searchOverlay.classList.remove('active');
        searchInput.value = '';
    });

    // Escape key closes search & drawer
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (searchOverlay.classList.contains('active')) {
                searchOverlay.classList.remove('active');
            }
            if (mobileDrawer.classList.contains('active')) {
                toggleDrawer();
            }
        }
    });
});
</script>
