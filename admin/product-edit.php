<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Edit Product';

// Get product ID
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) {
    $_SESSION['admin_error'] = 'Product not found.';
    redirect(ADMIN_URL . '/products.php');
}

// Get product data
try {
    $stmt = getDBConnection()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['admin_error'] = 'Product not found.';
        redirect(ADMIN_URL . '/products.php');
    }
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Error loading product: ' . $e->getMessage();
    redirect(ADMIN_URL . '/products.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $short_description = sanitize($_POST['short_description']);
    $price = (float)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $sku = sanitize($_POST['sku']);
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $status = $_POST['status'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : 0;
    $dimensions = sanitize($_POST['dimensions']);
    
    // Generate slug
    $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9 ]/', '', $name)));
    
    // Handle image upload
    $image = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = $slug . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            // Delete old image if it's not the default
            if ($product['image'] && $product['image'] !== 'default-product.jpg') {
                $old_image_path = $upload_dir . $product['image'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $image = $filename;
        }
    }
    
    if (empty($name) || $price <= 0) {
        $_SESSION['admin_error'] = 'Product name and valid price are required.';
    } else {
        try {
            // Check if weight and dimensions columns exist
            $pdo = getDBConnection();
            $stmt_check = $pdo->query("DESCRIBE products");
            $columns = $stmt_check->fetchAll(PDO::FETCH_COLUMN);

            $has_weight = in_array('weight', $columns);
            $has_dimensions = in_array('dimensions', $columns);

            if ($has_weight && $has_dimensions) {
                // Use full query with weight and dimensions
                $stmt = $pdo->prepare("
                    UPDATE products
                    SET name = ?, slug = ?, description = ?, short_description = ?, price = ?, sale_price = ?,
                        sku = ?, stock_quantity = ?, category_id = ?, image = ?, status = ?, featured = ?,
                        weight = ?, dimensions = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $params = [$name, $slug, $description, $short_description, $price, $sale_price,
                          $sku, $stock_quantity, $category_id, $image, $status, $featured,
                          $weight, $dimensions, $product_id];
            } else {
                // Use basic query without weight and dimensions
                $stmt = $pdo->prepare("
                    UPDATE products
                    SET name = ?, slug = ?, description = ?, short_description = ?, price = ?, sale_price = ?,
                        sku = ?, stock_quantity = ?, category_id = ?, image = ?, status = ?, featured = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $params = [$name, $slug, $description, $short_description, $price, $sale_price,
                          $sku, $stock_quantity, $category_id, $image, $status, $featured, $product_id];
            }

            if ($stmt->execute($params)) {
                $_SESSION['admin_success'] = 'Product updated successfully!';
                redirect(ADMIN_URL . '/products.php');
            } else {
                $_SESSION['admin_error'] = 'Failed to update product.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating product: ' . $e->getMessage();
        }
    }
}

// Get categories
try {
    $stmt = getDBConnection()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Product: <?php echo htmlspecialchars($product['name']); ?></h2>
    <div class="d-flex gap-2">
        <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $product['slug']; ?>" 
           class="btn btn-outline-info" target="_blank">
            <i class="fas fa-eye me-2"></i>View Product
        </a>
        <a href="<?php echo ADMIN_URL; ?>/products.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Products
        </a>
    </div>
</div>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <!-- Basic Information -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="short_description" class="form-label">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description" rows="2" 
                                  placeholder="Brief product description for listings"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Full Description</label>
                        <textarea class="form-control" id="description" name="description" rows="6" 
                                  placeholder="Detailed product description"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Pricing -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Pricing</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Regular Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" value="<?php echo $product['price']; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sale_price" class="form-label">Sale Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="sale_price" name="sale_price" 
                                           step="0.01" min="0" value="<?php echo $product['sale_price']; ?>">
                                </div>
                                <small class="text-muted">Leave empty if no sale price</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Inventory -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Inventory</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" 
                                       value="<?php echo htmlspecialchars($product['sku']); ?>" 
                                       placeholder="Product SKU code">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                       min="0" value="<?php echo $product['stock_quantity']; ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Shipping -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Shipping</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="weight" class="form-label">Weight (kg)</label>
                                <input type="number" class="form-control" id="weight" name="weight" 
                                       step="0.01" min="0" value="<?php echo $product['weight']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dimensions" class="form-label">Dimensions</label>
                                <input type="text" class="form-control" id="dimensions" name="dimensions" 
                                       value="<?php echo htmlspecialchars($product['dimensions']); ?>" 
                                       placeholder="L x W x H (cm)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Product Image -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Product Image</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="image" class="form-label">Upload New Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="text-muted">Recommended: 800x800px, max 2MB</small>
                    </div>
                    
                    <!-- Current Image -->
                    <div class="text-center mb-3">
                        <label class="form-label">Current Image:</label>
                        <div>
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $product['image']; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="img-fluid rounded" style="max-height: 200px;" id="current-image">
                        </div>
                    </div>
                    
                    <!-- Preview -->
                    <div id="image-preview" class="text-center" style="display: none;">
                        <label class="form-label">New Image Preview:</label>
                        <div>
                            <img id="preview-img" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Category -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Category</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Product Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Product Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="out_of_stock" <?php echo $product['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="featured" name="featured" 
                               <?php echo $product['featured'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="featured">
                            <strong>Featured Product</strong>
                        </label>
                        <div class="text-muted small">Show on homepage and featured sections</div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-save me-2"></i>Update Product
                    </button>
                    <a href="<?php echo ADMIN_URL; ?>/products.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Image preview
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('image-preview').style.display = 'none';
    }
});

// Validate sale price
document.getElementById('sale_price').addEventListener('input', function() {
    const regularPrice = parseFloat(document.getElementById('price').value) || 0;
    const salePrice = parseFloat(this.value) || 0;
    
    if (salePrice > 0 && salePrice >= regularPrice) {
        this.setCustomValidity('Sale price must be less than regular price');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
