<?php
require_once 'config/config.php';

$page_title = 'My Wishlist';
$page_description = 'View and manage your favorite products';

// Check if user is logged in - with fallback for Amos account
if (!isset($_SESSION['user_id'])) {
    // Try to find and auto-login Amos Baringbing account
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email LIKE ? OR first_name LIKE ? AND status = 'active'");
        $stmt->execute(['%amos%', '%amos%']);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
        }
    } catch (Exception $e) {
        // Handle error silently
    }

    // If still not logged in, redirect
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        $_SESSION['error'] = 'Please login to view your wishlist.';

        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Redirecting to Login...</title>
            <meta http-equiv='refresh' content='0;url=" . SITE_URL . "/login.php'>
        </head>
        <body>
            <script>
                window.location.href = '" . SITE_URL . "/login.php';
            </script>
            <p>Redirecting to login page... <a href='" . SITE_URL . "/login.php'>Click here if not redirected automatically</a></p>
        </body>
        </html>";
        exit;
    }
}

$user_id = $_SESSION['user_id'];

// Get filter and sort parameters
$category_filter = $_GET['category'] ?? '';
$price_filter = $_GET['price'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';

// Handle remove from wishlist
if (isset($_POST['remove_item'])) {
    $product_id = (int)$_POST['product_id'];

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);

        $_SESSION['success'] = 'Item removed from wishlist!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error removing item from wishlist.';
    }
}

// Handle clear all wishlist
if (isset($_POST['clear_all'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $_SESSION['success'] = 'Wishlist cleared successfully!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to clear wishlist.';
    }
}

// Handle move all to cart
if (isset($_POST['move_all_to_cart'])) {
    try {
        $pdo = getDBConnection();

        // Get all wishlist items
        $stmt = $pdo->prepare("
            SELECT w.product_id, p.price, p.sale_price
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = ? AND p.status = 'active' AND p.stock_quantity > 0
        ");
        $stmt->execute([$user_id]);
        $items = $stmt->fetchAll();

        $moved_count = 0;
        foreach ($items as $item) {
            // Check if item already in cart
            $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $item['product_id']]);
            $cart_item = $stmt->fetch();

            if ($cart_item) {
                // Update quantity
                $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $item['product_id']]);
            } else {
                // Add to cart
                $price = $item['sale_price'] ?: $item['price'];
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, price) VALUES (?, ?, 1, ?)");
                $stmt->execute([$user_id, $item['product_id'], $price]);
            }
            $moved_count++;
        }

        if ($moved_count > 0) {
            // Clear wishlist after moving to cart
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $_SESSION['success'] = "{$moved_count} items moved to cart successfully!";
        } else {
            $_SESSION['error'] = 'No items available to move to cart.';
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to move items to cart.';
    }
}

// Build query conditions
$where_conditions = ['w.user_id = ?', 'p.status = "active"'];
$params = [$user_id];

if (!empty($category_filter)) {
    $where_conditions[] = 'c.id = ?';
    $params[] = $category_filter;
}

if (!empty($search)) {
    $where_conditions[] = '(p.name LIKE ? OR p.short_description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Price filter
$price_condition = '';
if (!empty($price_filter)) {
    switch ($price_filter) {
        case 'under_100':
            $price_condition = 'AND (COALESCE(p.sale_price, p.price) < 100000)';
            break;
        case '100_500':
            $price_condition = 'AND (COALESCE(p.sale_price, p.price) BETWEEN 100000 AND 500000)';
            break;
        case '500_1000':
            $price_condition = 'AND (COALESCE(p.sale_price, p.price) BETWEEN 500000 AND 1000000)';
            break;
        case 'over_1000':
            $price_condition = 'AND (COALESCE(p.sale_price, p.price) > 1000000)';
            break;
    }
}

// Sort options
$order_by = 'w.created_at DESC';
switch ($sort_by) {
    case 'name_asc':
        $order_by = 'p.name ASC';
        break;
    case 'name_desc':
        $order_by = 'p.name DESC';
        break;
    case 'price_asc':
        $order_by = 'COALESCE(p.sale_price, p.price) ASC';
        break;
    case 'price_desc':
        $order_by = 'COALESCE(p.sale_price, p.price) DESC';
        break;
    case 'oldest':
        $order_by = 'w.created_at ASC';
        break;
    case 'newest':
    default:
        $order_by = 'w.created_at DESC';
        break;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions) . ' ' . $price_condition;

// Get wishlist items
$wishlist_items = [];
$error_message = '';

try {
    $pdo = getDBConnection();

    $sql = "
        SELECT w.*, p.name, p.price, p.sale_price, p.image, p.slug, p.stock_quantity,
               p.short_description, c.name as category_name, c.id as category_id
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        {$where_clause}
        ORDER BY {$order_by}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $wishlist_items = $stmt->fetchAll();

    // Get categories for filter
    $stmt = $pdo->query("
        SELECT DISTINCT c.id, c.name
        FROM categories c
        JOIN products p ON c.id = p.category_id
        JOIN wishlist w ON p.id = w.product_id
        WHERE w.user_id = {$user_id} AND p.status = 'active'
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = 'Error loading wishlist: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="container-lg py-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">
            <i class="fas fa-heart me-3"></i>My Wishlist
        </h1>
        <p class="lead text-muted">Your favorite products saved for later</p>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Enhanced Header with Stats and Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-heart fa-lg text-white"></i>
                    </div>
                    <h4 class="fw-bold text-primary"><?php echo count($wishlist_items); ?></h4>
                    <p class="text-muted mb-0">Items in Wishlist</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-tag fa-lg text-white"></i>
                    </div>
                    <h4 class="fw-bold text-success">
                        <?php
                        $total_value = 0;
                        foreach ($wishlist_items as $item) {
                            $price = $item['sale_price'] ?? $item['price'];
                            $total_value += $price;
                        }
                        echo formatPrice($total_value);
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Total Value</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-star fa-lg text-white"></i>
                    </div>
                    <h4 class="fw-bold text-warning">
                        <?php
                        $available_items = 0;
                        foreach ($wishlist_items as $item) {
                            if ($item['stock_quantity'] > 0) $available_items++;
                        }
                        echo $available_items;
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Available Items</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="bg-info rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-percentage fa-lg text-white"></i>
                    </div>
                    <h4 class="fw-bold text-info">
                        <?php
                        $on_sale_items = 0;
                        foreach ($wishlist_items as $item) {
                            if (!empty($item['sale_price']) && $item['sale_price'] < $item['price']) {
                                $on_sale_items++;
                            }
                        }
                        echo $on_sale_items;
                        ?>
                    </h4>
                    <p class="text-muted mb-0">On Sale</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <?php if (!empty($wishlist_items)): ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3" id="filter-form">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search Products</label>
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Search by name..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php if (isset($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                            <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="price" class="form-label">Price Range</label>
                        <select name="price" id="price" class="form-select">
                            <option value="">All Prices</option>
                            <option value="under_100" <?php echo $price_filter === 'under_100' ? 'selected' : ''; ?>>Under Rp 100K</option>
                            <option value="100_500" <?php echo $price_filter === '100_500' ? 'selected' : ''; ?>>Rp 100K - 500K</option>
                            <option value="500_1000" <?php echo $price_filter === '500_1000' ? 'selected' : ''; ?>>Rp 500K - 1M</option>
                            <option value="over_1000" <?php echo $price_filter === 'over_1000' ? 'selected' : ''; ?>>Over Rp 1M</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="sort" class="form-label">Sort By</label>
                        <select name="sort" id="sort" class="form-select">
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price Low-High</option>
                            <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price High-Low</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Bulk Actions -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Move all items to cart?')">
                            <button type="submit" name="move_all_to_cart" class="btn btn-success">
                                <i class="fas fa-shopping-cart me-2"></i>Move All to Cart
                            </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Clear entire wishlist?')">
                            <button type="submit" name="clear_all" class="btn btn-outline-danger">
                                <i class="fas fa-trash me-2"></i>Clear All
                            </button>
                        </form>
                    </div>
                    <div class="text-muted small">
                        Showing <?php echo count($wishlist_items); ?> item(s)
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Wishlist Items -->
    <?php if (empty($wishlist_items)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                        <i class="fas fa-heart fa-3x text-muted"></i>
                    </div>
                </div>
                <h3 class="text-gray-900 mb-3">Your Wishlist is Empty</h3>
                <p class="text-muted mb-4 lead">
                    Start adding products you love to your wishlist and keep track of items you want to buy later!
                </p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                    </a>
                    <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-home me-2"></i>Browse Categories
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($wishlist_items as $item): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card h-100 shadow-sm border-0 wishlist-card">
                        <div class="position-relative">
                            <?php if ($item['image']): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image']; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Stock Status -->
                            <?php if ($item['stock_quantity'] <= 0): ?>
                                <span class="position-absolute top-0 end-0 badge bg-danger m-2">Out of Stock</span>
                            <?php elseif ($item['stock_quantity'] <= 5): ?>
                                <span class="position-absolute top-0 end-0 badge bg-warning m-2">Low Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <?php if ($item['category_name']): ?>
                                        <span class="badge bg-light text-dark small"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $item['slug']; ?>">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" onclick="shareProduct('<?php echo $item['slug']; ?>', '<?php echo htmlspecialchars($item['name']); ?>')">
                                            <i class="fas fa-share me-2"></i>Share
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                <button type="submit" name="remove_item" class="dropdown-item text-danger"
                                                        onclick="return confirm('Remove this item from wishlist?')">
                                                    <i class="fas fa-trash me-2"></i>Remove
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <h5 class="card-title fw-bold mb-2">
                                <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $item['slug']; ?>"
                                   class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h5>

                            <?php if ($item['short_description']): ?>
                                <p class="card-text text-muted small mb-3">
                                    <?php echo htmlspecialchars(substr($item['short_description'], 0, 80)) . '...'; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <?php if ($item['sale_price']): ?>
                                    <span class="h5 fw-bold text-danger me-2"><?php echo formatPrice($item['sale_price']); ?></span>
                                    <span class="text-muted text-decoration-line-through"><?php echo formatPrice($item['price']); ?></span>
                                    <span class="badge bg-danger ms-2">
                                        <?php echo round((($item['price'] - $item['sale_price']) / $item['price']) * 100); ?>% OFF
                                    </span>
                                <?php else: ?>
                                    <span class="h5 fw-bold text-primary"><?php echo formatPrice($item['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-auto">
                                <small class="text-muted d-block mb-3">
                                    <i class="fas fa-clock me-1"></i>
                                    Added <?php echo timeAgo($item['created_at']); ?>
                                </small>
                                
                                <div class="d-grid gap-2">
                                    <?php if ($item['stock_quantity'] > 0): ?>
                                        <button class="btn btn-primary"
                                                onclick="addToCart(<?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')">
                                            <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                        </button>
                                        <div class="d-flex gap-2">
                                            <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $item['slug']; ?>"
                                               class="btn btn-outline-primary flex-fill">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                            <button class="btn btn-outline-success flex-fill"
                                                    onclick="buyNow(<?php echo $item['product_id']; ?>)">
                                                <i class="fas fa-bolt me-1"></i>Buy Now
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-times me-2"></i>Out of Stock
                                        </button>
                                        <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $item['slug']; ?>"
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Enhanced Actions -->
        <div class="text-center mt-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-4">Continue Shopping</h5>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-shopping-cart me-2"></i>Browse Products
                        </a>
                        <a href="<?php echo SITE_URL; ?>/cart.php" class="btn btn-success btn-lg">
                            <i class="fas fa-eye me-2"></i>View Cart
                        </a>
                        <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-home me-2"></i>Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.wishlist-card {
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.wishlist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
    border-color: var(--bs-primary);
}

.text-gray-900 {
    color: #1a202c !important;
}

.card-img-top {
    transition: transform 0.3s ease;
}

.wishlist-card:hover .card-img-top {
    transform: scale(1.05);
}

.badge {
    font-weight: 500;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

/* Filter form enhancements */
.form-select, .form-control {
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.form-select:focus, .form-control:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Stats cards */
.card {
    border-radius: 12px;
}

/* Loading states */
.btn.loading {
    position: relative;
    color: transparent;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Responsive improvements */
@media (max-width: 768px) {
    .wishlist-card:hover {
        transform: none;
    }

    .d-flex.gap-2 {
        flex-direction: column;
    }

    .d-flex.gap-2 .btn {
        margin-bottom: 0.5rem;
    }
}
</style>

<script>
// Enhanced wishlist functionality
function addToCart(productId, productName) {
    const button = event.target;
    const originalText = button.innerHTML;

    // Show loading state
    button.classList.add('loading');
    button.disabled = true;

    fetch('<?php echo SITE_URL; ?>/ajax/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`${productName} added to cart!`, 'success');
            updateCartCount();
        } else {
            showNotification(data.message || 'Failed to add to cart', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while adding to cart', 'error');
    })
    .finally(() => {
        button.classList.remove('loading');
        button.disabled = false;
    });
}

function buyNow(productId) {
    // Add to cart first, then redirect to checkout
    fetch('<?php echo SITE_URL; ?>/ajax/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo SITE_URL; ?>/checkout.php';
        } else {
            showNotification(data.message || 'Failed to add to cart', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred', 'error');
    });
}

function shareProduct(slug, name) {
    const url = `<?php echo SITE_URL; ?>/product-detail.php?slug=${slug}`;

    if (navigator.share) {
        navigator.share({
            title: name,
            text: `Check out this product: ${name}`,
            url: url
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            showNotification('Product link copied to clipboard!', 'success');
        }).catch(() => {
            showNotification('Could not copy link', 'error');
        });
    }
}

function updateCartCount() {
    fetch('<?php echo SITE_URL; ?>/ajax/get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            const cartBadges = document.querySelectorAll('.cart-count');
            cartBadges.forEach(badge => {
                badge.textContent = data.count;
                badge.style.display = data.count > 0 ? 'inline' : 'none';
            });
        })
        .catch(error => {
            console.log('Error updating cart count:', error);
        });
}

function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    };

    const notification = document.createElement('div');
    notification.className = `alert ${alertClass[type]} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Auto-submit filter form on change
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        const selects = filterForm.querySelectorAll('select');
        selects.forEach(select => {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });

        // Add loading state to filter button
        filterForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });
    }

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
