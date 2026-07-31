<?php
// admin/products/edit-product.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/auth_check.php';

$success = '';
$error = '';
$productId = (int) ($_GET['id'] ?? 0);
$product = null;
$categories = [];
$gallery = [];

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $error = 'Product not found. It may have been deleted.';
    } else {
        $categories = $pdo->query("SELECT id, name FROM product_categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
        $stmt = $pdo->prepare('SELECT id, image FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['pid' => $productId]);
        $gallery = $stmt->fetchAll();
    }
} catch (\Throwable $e) {
    error_log('Edit product load error: ' . $e->getMessage());
    $error = 'Database error: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Session::csrfVerify('edit_product', $csrf)) {
        $error = 'Invalid request - session token expired, please click submit once more.';
    } else {
        $action = $_POST['action'] ?? 'update_product';

        try {
            $pdo = Database::getConnection();

            if (!$product) {
                $error = 'Product not found. It may have been deleted.';
            } elseif ($action === 'delete_image') {
                $imageId = (int) ($_POST['image_id'] ?? 0);
                $stmt = $pdo->prepare('SELECT id, image FROM product_images WHERE id = :id AND product_id = :pid LIMIT 1');
                $stmt->execute(['id' => $imageId, 'pid' => $product['id']]);
                $imageRow = $stmt->fetch();

                if (!$imageRow) {
                    $error = 'Image not found.';
                } else {
                    $pdo->prepare('DELETE FROM product_images WHERE id = :id')->execute(['id' => $imageId]);
                    $path = __DIR__ . '/../src/images/products/' . $imageRow['image'];
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                    $success = 'Gallery image deleted successfully.';

                    $stmt = $pdo->prepare('SELECT id, image FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
                    $stmt->execute(['pid' => $product['id']]);
                    $gallery = $stmt->fetchAll();
                    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $product['id']]);
                    $product = $stmt->fetch();
                }
            } elseif ($action === 'update_product') {
                $form = [
                    'name' => trim($_POST['name'] ?? ''),
                    'category_id' => (int) ($_POST['category_id'] ?? 0),
                    'short_description' => trim($_POST['short_description'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'price' => trim($_POST['price'] ?? ''),
                    'mrp' => trim($_POST['mrp'] ?? ''),
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

                if ($form['name'] === '' || mb_strlen($form['name']) > 200) {
                    $error = 'Product name is required and must be under 200 characters.';
                } elseif (!is_numeric($form['price']) || (float) $form['price'] < 0 || (float) $form['price'] > 99999999) {
                    $error = 'Please enter a valid price (INR).';
                } elseif ($form['mrp'] !== '' && (!is_numeric($form['mrp']) || (float) $form['mrp'] < 0 || (float) $form['mrp'] > 99999999)) {
                    $error = 'Please enter a valid MRP (INR).';
                } elseif ($form['quantity'] === '' || !is_numeric($form['quantity']) || (float) $form['quantity'] < 0) {
                    $error = 'Please enter a valid quantity.';
                } else {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $form['name']), '-'));
                    if ($slug === '') {
                        $slug = 'product-' . time();
                    }

                    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = :slug AND id != :id LIMIT 1');
                    $stmt->execute(['slug' => $slug, 'id' => $product['id']]);
                    if ($stmt->fetch()) {
                        $error = 'A product with this name already exists.';
                    } else {
                        $image = $product['image'];
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 2 * 1024 * 1024;

                        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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
                                    $oldImage = $image;
                                    $image = $filename;

                                    if ($oldImage !== 'default.png') {
                                        $oldPath = $uploadDir . $oldImage;
                                        if (file_exists($oldPath)) {
                                            @unlink($oldPath);
                                        }
                                    }
                                } else {
                                    $error = 'Failed to upload main image. Please try again.';
                                }
                            }
                        }

                        if ($error === '') {
                            $categoryId = $form['category_id'] > 0 ? $form['category_id'] : null;
                            $mrp = is_numeric($form['mrp']) ? (float) $form['mrp'] : 0.0;
                            if ($mrp < (float) $form['price']) {
                                $mrp = 0.0;
                            }
                            $form['mrp'] = number_format($mrp, 2, '.', '');
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
                                    'sku' => 'UNM-PROD-' . $product['id'],
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
                                'UPDATE products SET
                                 category_id = :category_id, name = :name, slug = :slug, image = :image,
                                 short_description = :short_description, description = :description,
                                 price = :price, mrp = :mrp, unit = :unit, quantity = :quantity,
                                 meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords,
                                 og_title = :og_title, og_description = :og_description, og_image = :og_image,
                                 schema_json = :schema_json, status = :status
                                 WHERE id = :id'
                            );
                            $stmt->execute([
                                'category_id' => $categoryId,
                                'name' => $form['name'],
                                'slug' => $slug,
                                'image' => $image,
                                'short_description' => $form['short_description'],
                                'description' => $form['description'],
                                'price' => number_format((float) $form['price'], 2, '.', ''),
                                'mrp' => $form['mrp'],
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
                                'id' => $product['id'],
                            ]);

                            $galleryDir = __DIR__ . '/../src/images/products/';
                            if (!is_dir($galleryDir)) {
                                @mkdir($galleryDir, 0755, true);
                            }

                            $galleryFiles = $_FILES['gallery'] ?? [];
                            $galleryError = '';

                            if (!empty($galleryFiles['name']) && is_array($galleryFiles['name'])) {
                                $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) AS mx FROM product_images WHERE product_id = :pid');
                                $stmt->execute(['pid' => $product['id']]);
                                $sort = (int) $stmt->fetch()['mx'] + 1;

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
                                        $stmt->execute(['pid' => $product['id'], 'img' => $galleryName, 'sort' => $sort++]);
                                    } else {
                                        $galleryError = 'Failed to upload one of the gallery images. Please try again.';
                                        break;
                                    }
                                }
                            }

                            if ($galleryError !== '') {
                                $error = $galleryError;
                            } else {
                                $success = 'Product "' . htmlspecialchars($form['name']) . '" updated successfully.';
                            }

                            $stmt = $pdo->prepare('SELECT id, image FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
                            $stmt->execute(['pid' => $product['id']]);
                            $gallery = $stmt->fetchAll();
                            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
                            $stmt->execute(['id' => $product['id']]);
                            $product = $stmt->fetch();
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Edit product error: ' . $e->getMessage());
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

$csrf_token = Session::csrfToken('edit_product');

$extraHeadCss = '<link rel="stylesheet" href="../src/trumbowyg/trumbowyg.min.css?v=2">';

include __DIR__ . '/../header.php';
?>

<div class="row">
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit"></i> Edit Product</h3>
            </div>
            <div class="card-body">
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$product): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">Product not found.</p>
                        <a href="manage-products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                    <!-- UNM-CSRF-V2 -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="update_product">

                        <!-- General Information -->
                        <div class="card card-outline card-primary mb-3">
                            <div class="card-header"><h5 class="card-title">General Information</h5></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" required maxlength="200"
                                                value="<?= htmlspecialchars($product['name']) ?>">
                                            <small class="text-muted">Slug: <code><?= htmlspecialchars($product['slug']) ?></code> (auto-updates on save)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category_id" class="form-label">Category</label>
                                            <select class="form-select" id="category_id" name="category_id">
                                                <option value="">-- Uncategorized --</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= (int) $category['id'] ?>"
                                                        <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($category['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="price" class="form-label">Price (INR) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">&#8377;</span>
                                                <input type="number" class="form-control" id="price" name="price" required
                                                    step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="mrp" class="form-label">MRP (INR)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">&#8377;</span>
                                                <input type="number" class="form-control" id="mrp" name="mrp"
                                                    step="0.01" min="0" value="<?= htmlspecialchars($product['mrp'] ?? '') ?>">
                                            </div>
                                            <small class="text-muted">Leave empty for no discount.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="unit" class="form-label">Unit</label>
                                            <select class="form-select" id="unit" name="unit">
                                                <?php foreach (['gram', 'kg', 'piece', 'packet', 'box'] as $unit): ?>
                                                    <option value="<?= $unit ?>" <?= $product['unit'] === $unit ? 'selected' : '' ?>><?= $unit ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="quantity" class="form-label">Quantity / Stock <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="quantity" name="quantity" required
                                                step="0.01" min="0" value="<?= htmlspecialchars($product['quantity']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <span id="discountPreview" class="badge bg-success fs-6" style="display:none;"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" id="statusActive" value="active"
                                            <?= $product['status'] === 'active' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="statusActive">Active</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive"
                                            <?= $product['status'] === 'inactive' ? 'checked' : '' ?>>
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
                                            <label class="form-label">Main Image</label>
                                            <div class="mb-2">
                                                <?php if ($product['image'] !== 'default.png' && file_exists(__DIR__ . '/../src/images/products/' . $product['image'])): ?>
                                                    <img src="../src/images/products/<?= htmlspecialchars($product['image']) ?>"
                                                        alt="<?= htmlspecialchars($product['name']) ?>" class="img-thumbnail"
                                                        style="width:150px;height:150px;object-fit:cover;">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light border"
                                                        style="width:150px;height:150px;">
                                                        <i class="fas fa-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <input type="file" class="form-control" id="image" name="image"
                                                accept="image/jpeg,image/png,image/gif,image/webp">
                                            <div class="mt-2">
                                                <img id="imagePreview" src="#" alt="New image preview"
                                                    style="display:none;width:150px;height:150px;object-fit:cover;border:2px solid #dee2e6;border-radius:8px;">
                                            </div>
                                            <small class="text-muted">Upload a new image to replace the current one. JPG, PNG, GIF or WebP. Max 2MB.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="gallery" class="form-label">Add More Gallery Images</label>
                                            <input type="file" class="form-control" id="gallery" name="gallery[]" multiple
                                                accept="image/jpeg,image/png,image/gif,image/webp">
                                            <div class="d-flex flex-wrap gap-2 mt-2" id="galleryPreview"></div>
                                            <small class="text-muted">Select multiple files at once to add them to the gallery below.</small>
                                        </div>
                                    </div>
                                </div>

                                <label class="form-label">Gallery (<?= count($gallery) ?> images)</label>
                                <?php if (count($gallery) === 0): ?>
                                    <p class="text-muted small"><i class="fas fa-images"></i> No gallery images yet. Upload some above.</p>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($gallery as $gImage): ?>
                                            <div class="position-relative border rounded p-1 bg-white">
                                                <img src="../src/images/products/<?= htmlspecialchars($gImage['image']) ?>"
                                                    alt="Gallery image" style="width:100px;height:100px;object-fit:cover;border-radius:6px;">
                                                <form method="POST" style="position:absolute;top:4px;right:4px;" class="delete-gallery-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="delete_image">
                                                    <input type="hidden" name="image_id" value="<?= (int) $gImage['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-xs" style="padding:2px 6px;font-size:11px;" title="Delete image">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Descriptions -->
                        <div class="card card-outline card-success mb-3">
                            <div class="card-header"><h5 class="card-title">Descriptions</h5></div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" rows="3"
                                        maxlength="300"><?= htmlspecialchars($product['short_description']) ?></textarea>
                                    <small class="text-muted">Max 300 characters.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Full Description
                                        <span id="editorStatus" class="badge bg-secondary">Editor loading...</span>
                                    </label>
                                    <textarea class="form-control" id="description" name="description" rows="8"><?= htmlspecialchars($product['description']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- SEO -->
                        <div class="card card-outline card-warning mb-3">
                            <div class="card-header"><h5 class="card-title">SEO &amp; Social Sharing</h5></div>
                            <div class="card-body">
                                <div class="alert alert-light border small mb-3">
                                    <i class="fas fa-magic text-warning"></i>
                                    Leave fields empty to keep auto-generated values.
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="meta_title" class="form-label">Meta Title <small class="text-muted">(max 60 chars)</small></label>
                                            <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="60"
                                                value="<?= htmlspecialchars($product['meta_title']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                            <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" maxlength="255"
                                                value="<?= htmlspecialchars($product['meta_keywords']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="meta_description" class="form-label">Meta Description <small class="text-muted">(max 160 chars)</small></label>
                                    <textarea class="form-control" id="meta_description" name="meta_description" rows="2" maxlength="160"><?= htmlspecialchars($product['meta_description']) ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="og_title" class="form-label">OG Title (Open Graph)</label>
                                            <input type="text" class="form-control" id="og_title" name="og_title" maxlength="200"
                                                value="<?= htmlspecialchars($product['og_title']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="og_image" class="form-label">OG Image URL</label>
                                            <input type="text" class="form-control" id="og_image" name="og_image"
                                                value="<?= htmlspecialchars($product['og_image']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="og_description" class="form-label">OG Description</label>
                                    <textarea class="form-control" id="og_description" name="og_description" rows="2"><?= htmlspecialchars($product['og_description']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="schema_json" class="form-label">Schema JSON-LD <small class="text-muted">(leave empty to auto-generate)</small></label>
                                    <textarea class="form-control font-monospace" id="schema_json" name="schema_json" rows="6"><?= htmlspecialchars($product['schema_json']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                        <a href="manage-products.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../src/trumbowyg/trumbowyg.min.css">
<script src="../src/trumbowyg/trumbowyg.min.js?v=2"></script>
<style>
    .trumbowyg-box { margin: 0; }
    .trumbowyg-editor { min-height: 220px; }
</style>
<script>
window.addEventListener('load', function() {
    var status = document.getElementById('editorStatus');
    var $desc = $('#description');

    if (typeof $.fn.trumbowyg === 'function') {
        $.trumbowyg.svgPath = '../src/trumbowyg/ui/icons.svg';
        $desc.trumbowyg({
            autogrow: true
        });
        status.textContent = 'Editor loaded';
        status.className = 'badge bg-success';
    } else {
        status.textContent = 'Editor failed to load - hard refresh (Ctrl+Shift+R)';
        status.className = 'badge bg-danger';
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {

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

    const priceInput = document.getElementById('price');
    const mrpInput = document.getElementById('mrp');
    const discountPreview = document.getElementById('discountPreview');

    function updateDiscount() {
        const price = parseFloat(priceInput.value);
        const mrp = parseFloat(mrpInput.value);
        if (isNaN(price) || isNaN(mrp) || mrp <= 0 || mrp <= price) {
            discountPreview.style.display = 'none';
            return;
        }
        const percent = Math.round((mrp - price) / mrp * 100);
        const youSave = (mrp - price).toFixed(2);
        discountPreview.textContent = percent + '% OFF - Save Rs. ' + youSave;
        discountPreview.style.display = 'inline-block';
    }

    priceInput.addEventListener('input', updateDiscount);
    mrpInput.addEventListener('input', updateDiscount);
    updateDiscount();

    document.querySelectorAll('.delete-gallery-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Delete this image?',
                text: 'The image will be permanently removed from the gallery.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
