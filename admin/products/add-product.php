<?php
// admin/products/add-product.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = '';
$error = '';
$categories = [];
$form = [
    'name' => '',
    'category_id' => '',
    'short_description' => '',
    'description' => '',
    'price' => '',
    'unit' => 'gram',
    'quantity' => '',
    'status' => 'active',
    'meta_title' => '',
    'meta_description' => '',
    'meta_keywords' => '',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'schema_json' => '',
];

try {
    $pdo = Database::getConnection();
    $categories = $pdo->query("SELECT id, name FROM product_categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} catch (\Throwable $e) {
    error_log('Add product load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string) Session::get('csrf_token', ''), (string) $csrf)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $form = [
            'name' => trim($_POST['name'] ?? ''),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'short_description' => trim($_POST['short_description'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price' => trim($_POST['price'] ?? ''),
            'unit' => in_array($_POST['unit'] ?? '', ['gram', 'kg', 'piece', 'packet', 'box'], true) ? $_POST['unit'] : 'gram',
            'quantity' => trim($_POST['quantity'] ?? ''),
            'status' => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
            'og_title' => trim($_POST['og_title'] ?? ''),
            'og_description' => trim($_POST['og_description'] ?? ''),
            'og_image' => trim($_POST['og_image'] ?? ''),
            'schema_json' => trim($_POST['schema_json'] ?? ''),
        ];

        try {
            $pdo = Database::getConnection();

            if ($form['name'] === '' || mb_strlen($form['name']) > 200) {
                $error = 'Product name is required and must be under 200 characters.';
            } elseif (!is_numeric($form['price']) || (float) $form['price'] < 0 || (float) $form['price'] > 99999999) {
                $error = 'Please enter a valid price (INR).';
            } elseif ($form['quantity'] === '' || !is_numeric($form['quantity']) || (float) $form['quantity'] < 0) {
                $error = 'Please enter a valid quantity.';
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $form['name']), '-'));
                if ($slug === '') {
                    $slug = 'product-' . time();
                }

                $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = :slug LIMIT 1');
                $stmt->execute(['slug' => $slug]);
                if ($stmt->fetch()) {
                    $error = 'A product with this name already exists.';
                } else {
                    $image = 'default.png';

                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 2 * 1024 * 1024;
                        $file = $_FILES['image'];

                        if (!in_array($file['type'], $allowedTypes, true)) {
                            $error = 'Only JPG, PNG, GIF, and WebP images are allowed for the main image.';
                        } elseif ($file['size'] > $maxSize) {
                            $error = 'Main image size must be less than 2MB.';
                        } else {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $uploadDir = __DIR__ . '/../src/images/products/';

                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0755, true);
                            }

                            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                                $image = $filename;
                            } else {
                                $error = 'Failed to upload main image. Please try again.';
                            }
                        }
                    }

                    if ($error === '') {
                        $categoryId = $form['category_id'] > 0 ? $form['category_id'] : null;
                        $metaTitle = $form['meta_title'] !== '' ? mb_substr($form['meta_title'], 0, 60) : mb_substr($form['name'], 0, 60);
                        $metaDescription = $form['meta_description'] !== ''
                            ? mb_substr($form['meta_description'], 0, 160)
                            : (mb_substr(trim(strip_tags($form['short_description'] !== '' ? $form['short_description'] : $form['description'])), 0, 160));
                        $metaKeywords = $form['meta_keywords'] !== ''
                            ? mb_substr($form['meta_keywords'], 0, 255)
                            : implode(', ', array_slice(array_unique(array_map('strtolower', preg_split('/\s+/', $form['name']))), 0, 8));
                        $ogTitle = $form['og_title'] !== '' ? $form['og_title'] : $metaTitle;
                        $ogDescription = $form['og_description'] !== '' ? $form['og_description'] : $metaDescription;

                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'urbannutmix.in';
                        $imageUrl = $image !== 'default.png' ? "$scheme://$host/admin/src/images/products/$image" : '';
                        $ogImage = $form['og_image'] !== '' ? $form['og_image'] : $imageUrl;

                        if ($form['schema_json'] === '') {
                            $schema = [
                                '@context' => 'https://schema.org',
                                '@type' => 'Product',
                                'name' => $form['name'],
                                'image' => $imageUrl !== '' ? [$imageUrl] : [],
                                'description' => trim(strip_tags($form['short_description'] !== '' ? $form['short_description'] : $form['description'])),
                                'sku' => 'UNM-PROD-' . time(),
                                'offers' => [
                                    '@type' => 'Offer',
                                    'priceCurrency' => 'INR',
                                    'price' => number_format((float) $form['price'], 2, '.', ''),
                                    'availability' => (float) $form['quantity'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                                ],
                            ];
                            $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        } else {
                            $schemaJson = $form['schema_json'];
                        }

                        $stmt = $pdo->prepare(
                            'INSERT INTO products
                             (category_id, name, slug, image, short_description, description, price, unit, quantity,
                              meta_title, meta_description, meta_keywords, og_title, og_description, og_image, schema_json, status)
                             VALUES
                             (:category_id, :name, :slug, :image, :short_description, :description, :price, :unit, :quantity,
                              :meta_title, :meta_description, :meta_keywords, :og_title, :og_description, :og_image, :schema_json, :status)'
                        );
                        $stmt->execute([
                            'category_id' => $categoryId,
                            'name' => $form['name'],
                            'slug' => $slug,
                            'image' => $image,
                            'short_description' => $form['short_description'],
                            'description' => $form['description'],
                            'price' => number_format((float) $form['price'], 2, '.', ''),
                            'unit' => $form['unit'],
                            'quantity' => number_format((float) $form['quantity'], 2, '.', ''),
                            'meta_title' => $metaTitle,
                            'meta_description' => $metaDescription,
                            'meta_keywords' => $metaKeywords,
                            'og_title' => $ogTitle,
                            'og_description' => $ogDescription,
                            'og_image' => $ogImage,
                            'schema_json' => $schemaJson,
                            'status' => $form['status'],
                        ]);
                        $productId = (int) $pdo->lastInsertId();

                        $galleryDir = __DIR__ . '/../src/images/products/';
                        if (!is_dir($galleryDir)) {
                            @mkdir($galleryDir, 0755, true);
                        }

                        $galleryFiles = $_FILES['gallery'] ?? [];
                        $uploadedCount = 0;
                        $galleryError = '';

                        if (!empty($galleryFiles['name']) && is_array($galleryFiles['name'])) {
                            foreach ($galleryFiles['name'] as $i => $fileName) {
                                if ($galleryFiles['error'][$i] !== UPLOAD_ERR_OK) {
                                    continue;
                                }
                                if (!in_array($galleryFiles['type'][$i], $allowedTypes, true)) {
                                    $galleryError = 'Only JPG, PNG, GIF, and WebP images are allowed in the gallery.';
                                    break;
                                }
                                if ($galleryFiles['size'][$i] > $maxSize) {
                                    $galleryError = 'Each gallery image must be less than 2MB.';
                                    break;
                                }

                                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                $galleryName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                                if (move_uploaded_file($galleryFiles['tmp_name'][$i], $galleryDir . $galleryName)) {
                                    $stmt = $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (:pid, :img, :sort)');
                                    $stmt->execute(['pid' => $productId, 'img' => $galleryName, 'sort' => $uploadedCount]);
                                    $uploadedCount++;
                                } else {
                                    $galleryError = 'Failed to upload one of the gallery images. Please try again.';
                                    break;
                                }
                            }
                        }

                        if ($galleryError !== '') {
                            $stmt = $pdo->prepare('SELECT image FROM product_images WHERE product_id = :pid');
                            $stmt->execute(['pid' => $productId]);
                            foreach ($stmt->fetchAll() as $gi) {
                                @unlink($galleryDir . $gi['image']);
                            }
                            $pdo->prepare('DELETE FROM product_images WHERE product_id = :pid')->execute(['pid' => $productId]);
                            $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $productId]);
                            $error = $galleryError;
                        } else {
                            Session::set('flash_success', 'Product "' . htmlspecialchars($form['name']) . '" added successfully.');
                            header('Location: manage-products.php');
                            exit;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Add product error: ' . $e->getMessage());
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$csrf_token = bin2hex(random_bytes(32));
Session::set('csrf_token', $csrf_token);

include __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Product</h3>
            </div>
            <div class="card-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <!-- General Information -->
                    <div class="card card-outline card-primary mb-3">
                        <div class="card-header"><h5 class="card-title">General Information</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required maxlength="200"
                                            value="<?= htmlspecialchars($form['name']) ?>" placeholder="e.g. Premium Almonds 500g">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">-- Uncategorized --</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= (int) $category['id'] ?>"
                                                    <?= (int) $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price (INR) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">&#8377;</span>
                                            <input type="number" class="form-control" id="price" name="price" required
                                                step="0.01" min="0" value="<?= htmlspecialchars($form['price']) ?>" placeholder="199.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="unit" class="form-label">Unit</label>
                                        <select class="form-select" id="unit" name="unit">
                                            <?php foreach (['gram', 'kg', 'piece', 'packet', 'box'] as $unit): ?>
                                                <option value="<?= $unit ?>" <?= $form['unit'] === $unit ? 'selected' : '' ?>><?= $unit ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Quantity / Stock <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" required
                                            step="0.01" min="0" value="<?= htmlspecialchars($form['quantity']) ?>" placeholder="500">
                                        <small class="text-muted">Stock quantity for the selected unit (e.g. 500 gram, 2 kg).</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="statusActive" value="active"
                                        <?= $form['status'] === 'active' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="statusActive">Active</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive"
                                        <?= $form['status'] === 'inactive' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="statusInactive">Inactive</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Media -->
                    <div class="card card-outline card-info mb-3">
                        <div class="card-header"><h5 class="card-title">Media</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Main Image</label>
                                        <input type="file" class="form-control" id="image" name="image"
                                            accept="image/jpeg,image/png,image/gif,image/webp">
                                        <div class="mt-2">
                                            <img id="imagePreview" src="#" alt="Main image preview"
                                                style="display:none;width:150px;height:150px;object-fit:cover;border:2px solid #dee2e6;border-radius:8px;">
                                        </div>
                                        <small class="text-muted">JPG, PNG, GIF or WebP. Max 2MB.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gallery" class="form-label">Gallery / Media <small class="text-muted">(one by one or multiple at once)</small></label>
                                        <input type="file" class="form-control" id="gallery" name="gallery[]" multiple
                                            accept="image/jpeg,image/png,image/gif,image/webp">
                                        <div class="d-flex flex-wrap gap-2 mt-2" id="galleryPreview"></div>
                                        <small class="text-muted">Select multiple files at once, or upload again later to add more images.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descriptions -->
                    <div class="card card-outline card-success mb-3">
                        <div class="card-header"><h5 class="card-title">Descriptions</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="short_description" class="form-label">Short Description</label>
                                <textarea class="form-control" id="short_description" name="short_description" rows="3"
                                    maxlength="300" placeholder="Quick summary shown on product cards/listings."><?= htmlspecialchars($form['short_description']) ?></textarea>
                                <small class="text-muted">Max 300 characters.</small>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Full Description</label>
                                <textarea class="form-control" id="description" name="description" rows="8"><?= htmlspecialchars($form['description']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SEO -->
                    <div class="card card-outline card-warning mb-3">
                        <div class="card-header"><h5 class="card-title">SEO &amp; Social Sharing</h5></div>
                        <div class="card-body">
                            <div class="alert alert-light border small mb-3">
                                <i class="fas fa-magic text-warning"></i>
                                Meta, Open Graph and Schema fields auto-generate from your product info. Type in any field to override.
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="meta_title" class="form-label">Meta Title <small class="text-muted">(max 60 chars)</small></label>
                                        <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="60"
                                            value="<?= htmlspecialchars($form['meta_title']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                        <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" maxlength="255"
                                            value="<?= htmlspecialchars($form['meta_keywords']) ?>" placeholder="almonds, dry fruits, nuts">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="meta_description" class="form-label">Meta Description <small class="text-muted">(max 160 chars)</small></label>
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="160"><?= htmlspecialchars($form['meta_description']) ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="og_title" class="form-label">OG Title (Open Graph)</label>
                                        <input type="text" class="form-control" id="og_title" name="og_title" maxlength="200"
                                            value="<?= htmlspecialchars($form['og_title']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="og_image" class="form-label">OG Image URL</label>
                                        <input type="text" class="form-control" id="og_image" name="og_image"
                                            value="<?= htmlspecialchars($form['og_image']) ?>" placeholder="Auto = main image URL">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="og_description" class="form-label">OG Description</label>
                                <textarea class="form-control" id="og_description" name="og_description" rows="2"><?= htmlspecialchars($form['og_description']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="schema_json" class="form-label">Schema JSON-LD <small class="text-muted">(leave empty to auto-generate)</small></label>
                                <textarea class="form-control font-monospace" id="schema_json" name="schema_json" rows="6"
                                    placeholder='{"@context":"https://schema.org","@type":"Product",...}'><?= htmlspecialchars($form['schema_json']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Add Product
                    </button>
                    <a href="manage-products.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../src/trumbowyg/trumbowyg.min.css">
<script src="../src/trumbowyg/trumbowyg.min.js"></script>
<style>
    .trumbowyg-box { margin: 0; }
    .trumbowyg-editor { min-height: 220px; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#description').trumbowyg({
        lang: 'en',
        btns: [
            ['viewHTML'],
            ['undo', 'redo'],
            ['formatting'],
            ['strong', 'em', 'del'],
            ['superscript', 'subscript'],
            ['link'],
            ['insertImage'],
            ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
            ['unorderedList', 'orderedList'],
            ['horizontalRule'],
            ['removeformat'],
            ['fullscreen']
        ],
        autogrow: true
    });

    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                imagePreview.src = ev.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    const galleryInput = document.getElementById('gallery');
    const galleryPreview = document.getElementById('galleryPreview');
    galleryInput.addEventListener('change', function(e) {
        galleryPreview.innerHTML = '';
        Array.from(e.target.files).forEach(function(file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.style.cssText = 'width:90px;height:90px;object-fit:cover;border:2px solid #dee2e6;border-radius:8px;';
                galleryPreview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });

    const nameInput = document.getElementById('name');
    const shortDescInput = document.getElementById('short_description');
    const metaTitle = document.getElementById('meta_title');
    const metaDesc = document.getElementById('meta_description');
    const metaKeywords = document.getElementById('meta_keywords');
    const ogTitle = document.getElementById('og_title');
    const ogDesc = document.getElementById('og_description');
    const touched = {};

    function markTouched(el) { touched[el.id] = true; }
    [metaTitle, metaDesc, metaKeywords, ogTitle, ogDesc].forEach(function(el) {
        el.addEventListener('input', function() { markTouched(el); });
    });

    function autoFill() {
        const name = nameInput.value.trim();
        const short = shortDescInput.value.trim();
        if (!touched['meta_title'] && name) {
            metaTitle.value = name.slice(0, 60);
        }
        if (!touched['meta_description'] && short) {
            metaDesc.value = short.slice(0, 160);
        }
        if (!touched['meta_keywords'] && name) {
            metaKeywords.value = name.toLowerCase().split(/\s+/).filter(function(w) {
                return w.length > 2;
            }).slice(0, 8).join(', ');
        }
        if (!touched['og_title'] && metaTitle.value) {
            ogTitle.value = metaTitle.value;
        }
        if (!touched['og_description'] && metaDesc.value) {
            ogDesc.value = metaDesc.value;
        }
    }

    nameInput.addEventListener('input', autoFill);
    shortDescInput.addEventListener('input', autoFill);
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
