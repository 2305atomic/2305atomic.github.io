<?php
require_once 'config.php';
checkAdminLogin();

$page_title = 'Products Management';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        try {
            $stmt = getDBConnection()->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt->execute([$product_id])) {
                $_SESSION['admin_success'] = 'Product deleted successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to delete product.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error deleting product: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/products.php');
    }
    
    if (isset($_POST['toggle_status'])) {
        $product_id = (int)$_POST['product_id'];
        $new_status = $_POST['new_status'];
        try {
            $stmt = getDBConnection()->prepare("UPDATE products SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $product_id])) {
                $_SESSION['admin_success'] = 'Product status updated successfully!';
            } else {
                $_SESSION['admin_error'] = 'Failed to update product status.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating product: ' . $e->getMessage();
        }
        redirect(ADMIN_URL . '/products.php');
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_filter) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($stock_filter === 'low') {
    $where_conditions[] = "p.stock_quantity <= 5";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "p.stock_quantity = 0";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get products
    $sql = "
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        {$where_clause}
        ORDER BY p.created_at DESC
    ";
    $stmt = getDBConnection()->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = getDBConnection()->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
} catch (Exception $e) {
    $products = [];
    $categories = [];
    $_SESSION['admin_error'] = 'Error loading products: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Products Management</h2>
    <div class="d-flex gap-2">
        <a href="<?php echo ADMIN_URL; ?>/export.php?type=products&format=csv" class="btn btn-outline-success">
            <i class="fas fa-download me-2"></i>Export CSV
        </a>
        <a href="<?php echo ADMIN_URL; ?>/product-add.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Product
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Product name or SKU">
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stock</label>
                <select class="form-select" name="stock">
                    <option value="">All Stock</option>
                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock (≤5)</option>
                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="<?php echo ADMIN_URL; ?>/products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Products (<?php echo count($products); ?> items)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($products)): ?>
            <div class="text-center py-4">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h5>No products found</h5>
                <p class="text-muted">Try adjusting your filters or add a new product.</p>
                <a href="<?php echo ADMIN_URL; ?>/product-add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First Product
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $product['image'] ?: 'default-product.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                </td>
                                <td>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($product['category_name'] ?: 'No Category'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($product['sale_price']): ?>
                                        <div>
                                            <strong class="text-primary"><?php echo formatPrice($product['sale_price']); ?></strong><br>
                                            <small class="text-muted text-decoration-line-through"><?php echo formatPrice($product['price']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <strong><?php echo formatPrice($product['price']); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $stock_class = $product['stock_quantity'] <= 0 ? 'danger' : 
                                                  ($product['stock_quantity'] <= 5 ? 'warning' : 'success');
                                    ?>
                                    <span class="badge bg-<?php echo $stock_class; ?>">
                                        <?php echo $product['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'active' => 'success',
                                        'inactive' => 'secondary',
                                        'out_of_stock' => 'danger'
                                    ];
                                    $color = $status_colors[$product['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $product['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_URL; ?>/product-edit.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $product['slug']; ?>" 
                                           class="btn btn-outline-info" title="View" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                                    data-bs-toggle="dropdown" title="More">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="new_status" 
                                                               value="<?php echo $product['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" name="toggle_status" class="dropdown-item">
                                                            <i class="fas fa-toggle-<?php echo $product['status'] === 'active' ? 'off' : 'on'; ?> me-2"></i>
                                                            <?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <button type="submit" name="delete_product" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
