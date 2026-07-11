<?php
/**
 * UrbanNutMix – Multi-Category Product Sections Component
 * Renders Panchmeva / Exotic / Classic / Flavored sections
 */

if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
}

/* ── Image CDN shortcuts ──────────────────────────────────────────── */
$img = [
    'family_red'  => 'https://ministryofnuts.in/cdn/shop/files/Family_pack_750G_1_78ca8d14-47de-4513-a0ed-9a7d11946de6_600x.webp?v=1766041228',
    'family_blue' => 'https://ministryofnuts.in/cdn/shop/files/Blue_8e85bff1-8815-4132-98b2-f61b257fdec8_600x.jpg?v=1766041284',
    'jaggery'     => 'https://ministryofnuts.in/cdn/shop/files/SugarcanejaggerypowderFront500g_600x.jpg?v=1712217016',
    'alm_pp'      => 'https://ministryofnuts.in/cdn/shop/files/cazry-almonds-peri-peri-pdp-100-_1_600x.jpg?v=1746694439',
    'alm_ps'      => 'https://ministryofnuts.in/cdn/shop/files/cazry-almonds-pink-salt-pdp-100g_600x.jpg?v=1746685688',
    'almonds'     => 'https://ministryofnuts.in/cdn/shop/files/Almonds200g_600x.jpg?v=1766039372',
    'cashews'     => 'https://ministryofnuts.in/cdn/shop/files/Cashews200g_600x.jpg?v=1766039456',
    'pistachios'  => 'https://ministryofnuts.in/cdn/shop/files/Pistapdp200g_600x.jpg?v=1766039549',
    'walnuts'     => 'https://ministryofnuts.in/cdn/shop/files/walnutpdp200g_600x.jpg?v=1766039645',
    'chilli'      => 'https://ministryofnuts.in/cdn/shop/files/chilli-flavour-100_600x.jpg?v=1746694426',
    'psbp'        => 'https://ministryofnuts.in/cdn/shop/files/pink-salt--black-pepper-pdp-100_600x.jpg?v=1746694262',
    'ps'          => 'https://ministryofnuts.in/cdn/shop/files/pink-salt-pdp-100_600x.jpg?v=1746693762',
];

/* ── All Category Sections ────────────────────────────────────────── */
$sections = [

    /* ── 1. Panchmeva Pack ── */
    [
        'id'      => 'panchmeva',
        'heading' => 'Panchmeva &ndash; Pack of 5 Dryfruits',
        'alt_bg'  => false,
        'products' => [
            ['bg'=>'pink',    'image'=>$img['family_red'],  'alt'=>'Family Pack Red',    'badge'=>'750g', 'name'=>'Panchmeva – Family Pack Of 5 Premium Dry Fruits 750g | Red',     'price'=>'₹1,399.00'],
            ['bg'=>'mint',    'image'=>$img['family_blue'], 'alt'=>'Family Pack Blue',   'badge'=>'750g', 'name'=>'Family Pack Of 5 Premium Dry Fruits | Blue | 750g | Combo Pack', 'price'=>'₹1,599.00'],
            ['bg'=>'magenta', 'image'=>$img['family_red'],  'alt'=>'Family Pack Magenta','badge'=>'750g', 'name'=>'Family Pack Of 5 Premium Dry Fruits | Magenta | 750g | Dry Fruits Box','price'=>'₹1,499.00'],
            ['bg'=>'lavender','image'=>$img['family_blue'], 'alt'=>'Family Pack Purple', 'badge'=>'750g', 'name'=>'Family Pack Of 5 Premium Dry Fruits | Purple | 750g | Gift Pack',  'price'=>'₹1,699.00'],
        ],
    ],

    /* ── 2. Exotic Dry Fruits ── */
    [
        'id'      => 'exotic',
        'heading' => 'Exotic Dry Fruits',
        'alt_bg'  => true,
        'products' => [
            ['bg'=>'golden', 'image'=>$img['jaggery'],  'alt'=>'Sugarcane Jaggery Powder', 'badge'=>'500g', 'name'=>'Sugarcane Jaggery Powder – Natural &amp; Unrefined | 500g',       'price'=>'₹299.00'],
            ['bg'=>'coral',  'image'=>$img['alm_pp'],   'alt'=>'Peri Peri Almonds',        'badge'=>'100g', 'name'=>'Cazry Almonds – Peri Peri Flavour | Roasted &amp; Seasoned | 100g','price'=>'₹249.00'],
            ['bg'=>'sage',   'image'=>$img['walnuts'],  'alt'=>'Premium Walnuts 200g',     'badge'=>'200g', 'name'=>'Premium California Walnuts | Kernels | 200g',                    'price'=>'₹399.00'],
            ['bg'=>'sky',    'image'=>$img['cashews'],  'alt'=>'Premium Cashews 200g',     'badge'=>'200g', 'name'=>'Premium Whole Cashews W240 Grade | 200g',                        'price'=>'₹349.00'],
        ],
    ],

    /* ── 3. Classic Dry Fruits ── */
    [
        'id'      => 'classic',
        'heading' => 'Classic Dry Fruits',
        'alt_bg'  => false,
        'products' => [
            ['bg'=>'cream', 'image'=>$img['almonds'],    'alt'=>'Premium Almonds 200g',    'badge'=>'200g', 'name'=>'Premium California Almonds | Raw &amp; Unprocessed | 200g', 'price'=>'₹299.00'],
            ['bg'=>'linen', 'image'=>$img['cashews'],    'alt'=>'Premium Cashews 200g',    'badge'=>'200g', 'name'=>'Premium W240 Whole Cashews | Crunchy &amp; Fresh | 200g',   'price'=>'₹349.00'],
            ['bg'=>'fern',  'image'=>$img['pistachios'], 'alt'=>'Premium Pistachios 200g', 'badge'=>'200g', 'name'=>'Premium Roasted Pistachios | Salted | 200g',                'price'=>'₹499.00'],
            ['bg'=>'blush', 'image'=>$img['walnuts'],    'alt'=>'Premium Walnuts 200g',    'badge'=>'200g', 'name'=>'Premium California Walnut Kernels | Rich &amp; Nutty | 200g','price'=>'₹399.00'],
        ],
    ],

    /* ── 4. Flavored Dry Fruits ── */
    [
        'id'      => 'flavored',
        'heading' => 'Flavored Dry Fruits',
        'alt_bg'  => true,
        'products' => [
            ['bg'=>'chilli', 'image'=>$img['chilli'], 'alt'=>'Chilli Flavour Cashews',          'badge'=>'100g', 'name'=>'Cazry Cashews – Chilli Flavour | Roasted &amp; Spiced | 100g',      'price'=>'₹199.00'],
            ['bg'=>'violet', 'image'=>$img['psbp'],   'alt'=>'Pink Salt Black Pepper Cashews',  'badge'=>'100g', 'name'=>'Cazry Cashews – Pink Salt &amp; Black Pepper | 100g',               'price'=>'₹199.00'],
            ['bg'=>'peach',  'image'=>$img['alm_ps'], 'alt'=>'Pink Salt Almonds',               'badge'=>'100g', 'name'=>'Cazry Almonds – Pink Salt | Light &amp; Crunchy | 100g',            'price'=>'₹199.00'],
            ['bg'=>'rose',   'image'=>$img['ps'],     'alt'=>'Pink Salt Flavour Cashews',       'badge'=>'100g', 'name'=>'Cazry Cashews – Pink Salt Flavour | Premium Roasted | 100g',        'price'=>'₹199.00'],
        ],
    ],

];
?>

<?php foreach ($sections as $section): ?>
<section
    class="unm-products-section<?php echo $section['alt_bg'] ? ' unm-products-section--alt' : ''; ?>"
    id="<?php echo htmlspecialchars($section['id']); ?>"
    aria-label="<?php echo strip_tags($section['heading']); ?>"
>
    <div class="unm-products-inner">

        <!-- Section Heading -->
        <div class="unm-products-heading">
            <h2><?php echo $section['heading']; ?></h2>
        </div>

        <!-- Product Grid -->
        <ul class="unm-products-grid">
            <?php foreach ($section['products'] as $p): ?>
            <li class="unm-product-card" data-bg="<?php echo htmlspecialchars($p['bg']); ?>">

                <!-- Colored Image Zone -->
                <div class="unm-product-img-zone">
                    <img
                        src="<?php echo htmlspecialchars($p['image']); ?>"
                        alt="<?php echo htmlspecialchars($p['alt']); ?>"
                        class="unm-product-img"
                        loading="lazy"
                        draggable="false"
                    >
                    <!-- Weight / Size Badge -->
                    <div class="unm-product-badge" aria-hidden="true">
                        <?php
                            // Split badge like "750g" → "750" + "g"
                            preg_match('/(\d+)(\D+)/', $p['badge'], $bm);
                            $bnum  = $bm[1] ?? $p['badge'];
                            $bunit = strtoupper($bm[2] ?? '');
                        ?>
                        <span class="unm-product-badge-num"><?php echo htmlspecialchars($bnum); ?></span>
                        <span class="unm-product-badge-unit"><?php echo htmlspecialchars($bunit); ?></span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="unm-product-body">
                    <p class="unm-product-name"><?php echo htmlspecialchars($p['name']); ?></p>

                    <div class="unm-product-price-wrap">
                        <span class="unm-product-price">Price &ndash; <?php echo htmlspecialchars($p['price']); ?></span>
                        <span class="unm-product-tax">(MRP Incl. all taxes)</span>
                    </div>

                    <div class="unm-product-spacer"></div>

                    <a href="#" class="unm-product-btn" aria-label="Add <?php echo htmlspecialchars($p['alt']); ?> to cart">
                        Add to Cart
                    </a>
                </div>

            </li>
            <?php endforeach; ?>
        </ul>

    </div>
</section>
<?php endforeach; ?>
