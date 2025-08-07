<?php
require_once 'config/config.php';

$page_title = 'Products';
$page_description = 'Browse our wide selection of quality products at great prices.';

// Initialize variables
$current_category = null;

// Get filter parameters
$category_slug = $_GET['category'] ?? '';
$search_query = $_GET['q'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE clause
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($category_slug) {
    $where_conditions[] = "c.slug = ?";
    $params[] = $category_slug;
}

if ($search_query) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
    $search_term = "%{$search_query}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_by = match($sort_by) {
    'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
    'name' => 'p.name ASC',
    'oldest' => 'p.created_at ASC',
    default => 'p.created_at DESC'
};

// Calculate pagination
$offset = ($page - 1) * PRODUCTS_PER_PAGE;

try {
    $pdo = getDBConnection();

    // Debug: Check if products table exists and has data
    $debug_stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $debug_total = $debug_stmt->fetch()['total'];

    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE {$where_clause}
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_products = $stmt->fetch()['total'];
    $total_pages = ceil($total_products / PRODUCTS_PER_PAGE);

    // Get products
    $sql = "
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE {$where_clause}
        ORDER BY {$order_by}
        LIMIT " . PRODUCTS_PER_PAGE . " OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Get current category info
    $current_category = null;
    if ($category_slug) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND status = 'active'");
        $stmt->execute([$category_slug]);
        $current_category = $stmt->fetch();
    }

    // Get all categories for filter
    $stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
    $categories = $stmt->fetchAll();

    // Debug information
    $debug_info = [
        'total_in_db' => $debug_total,
        'where_clause' => $where_clause,
        'params' => $params,
        'total_found' => $total_products,
        'products_returned' => count($products)
    ];

} catch (Exception $e) {
    $products = [];
    $categories = [];
    $current_category = null;
    $total_products = 0;
    $total_pages = 0;
    $debug_info = ['error' => $e->getMessage()];
}

include 'includes/header.php';
include 'includes/product_card.php';
?>

<!-- Page Header -->
<div class="bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="h2 mb-0">
                    <?php if ($current_category): ?>
                        <?php echo htmlspecialchars($current_category['name']); ?>
                    <?php elseif ($search_query): ?>
                        Search Results for "<?php echo htmlspecialchars($search_query); ?>"
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h1>
                <p class="text-muted mb-0"><?php echo $total_products; ?> products found</p>
            </div>
            <div class="col-md-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-md-end mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                        <?php if ($current_category): ?>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($current_category['name']); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active">Products</li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Products -->
<div class="container py-4">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <!-- Categories Filter -->
                    <div class="mb-4">
                        <h6 class="fw-bold">Categories</h6>
                        <div class="list-group list-group-flush">
                            <a href="<?php echo SITE_URL; ?>/products.php<?php echo $search_query ? '?q=' . urlencode($search_query) : ''; ?>" 
                               class="list-group-item list-group-item-action <?php echo !$category_slug ? 'active' : ''; ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $category): ?>
                                <a href="<?php echo SITE_URL; ?>/products.php?category=<?php echo $category['slug']; ?><?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $category_slug === $category['slug'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="col-lg-9">
            <!-- Sort and View Options -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <label for="sort-select" class="form-label me-2 mb-0">Sort by:</label>
                    <select id="sort-select" class="form-select form-select-sm" style="width: auto;">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </div>
                
                <div class="text-muted">
                    Showing <?php echo min($offset + 1, $total_products); ?>-<?php echo min($offset + PRODUCTS_PER_PAGE, $total_products); ?> of <?php echo $total_products; ?> products
                </div>
            </div>
            
            <!-- Products Grid -->
            <?php if (!empty($products)): ?>
                <div class="row g-4" id="products-grid">
                    <?php foreach ($products as $product): ?>
                        <?php echo renderProductCard($product); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Loading indicator for AJAX -->
                <div id="loading-indicator" class="text-center py-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading products...</p>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Products pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <h3>No products found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria.</p>

                    <!-- Debug Information -->
                    <?php if (isset($debug_info)): ?>
                        <div class="alert alert-info mt-4 text-start">
                            <h6>Debug Information:</h6>
                            <ul class="mb-0">
                                <li>Total products in database: <?php echo $debug_info['total_in_db'] ?? 'Unknown'; ?></li>
                                <li>Where clause: <?php echo htmlspecialchars($debug_info['where_clause'] ?? 'None'); ?></li>
                                <li>Parameters: <?php echo htmlspecialchars(json_encode($debug_info['params'] ?? [])); ?></li>
                                <li>Total found: <?php echo $debug_info['total_found'] ?? 0; ?></li>
                                <li>Products returned: <?php echo $debug_info['products_returned'] ?? 0; ?></li>
                                <?php if (isset($debug_info['error'])): ?>
                                    <li class="text-danger">Error: <?php echo htmlspecialchars($debug_info['error']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary">View All Products</a>
                    <a href="<?php echo SITE_URL; ?>/setup_complete_database.php" class="btn btn-warning ms-2">Setup Database</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Handle sort change
document.getElementById('sort-select').addEventListener('change', function() {
    const url = new URL(window.location);
    url.searchParams.set('sort', this.value);
    url.searchParams.delete('page'); // Reset to first page
    window.location.href = url.toString();
});
</script>

<?php include 'includes/footer.php'; ?>
