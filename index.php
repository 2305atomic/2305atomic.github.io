<?php
require_once 'config/config.php';

$page_title = 'Home';
$page_description = 'Welcome to TeWuNeed - Your trusted online marketplace for quality products at affordable prices. Shop electronics, fashion, home goods and more with fast delivery.';

// Get dynamic content for homepage
try {
    $pdo = getDBConnection();

    // Get featured products
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' AND p.featured = 1
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $featured_products = $stmt->fetchAll();

    // Get best selling products
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY total_sold DESC, p.created_at DESC
        LIMIT 8
    ");
    $bestsellers = $stmt->fetchAll();

    // Get newest products
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $newest_products = $stmt->fetchAll();

    // Get categories with product count
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY product_count DESC, c.name
        LIMIT 8
    ");
    $categories = $stmt->fetchAll();

    // Get stats for hero section
    $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $recent_orders = $stmt->fetch()['total_orders'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
    $total_products = $stmt->fetch()['total_products'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $total_users = $stmt->fetch()['total_users'] ?? 0;

} catch (Exception $e) {
    $featured_products = [];
    $bestsellers = [];
    $newest_products = [];
    $categories = [];
    $recent_orders = 0;
    $total_products = 0;
    $total_users = 0;
}

include 'includes/header.php';
?>

<!-- Messages -->
<?php if (isset($_SESSION['logout_message'])): ?>
    <div class="container-lg mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['logout_message']; unset($_SESSION['logout_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['welcome']) && isset($_SESSION['user_id'])): ?>
    <div class="container-lg mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-party-horn me-2"></i>
            Welcome to TeWuNeed, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!
            Start exploring our amazing products.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<!-- Hero Section -->
<section class="hero-section bg-primary text-white position-relative overflow-hidden py-5">
    <div class="hero-background position-absolute w-100 h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);"></div>

    <div class="container-lg position-relative">
        <div class="row align-items-center py-5">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1 class="display-4 fw-bold mb-4 text-white">
                        Welcome to <span class="text-warning">TeWuNeed</span>
                    </h1>
                    <p class="lead mb-4" style="color: rgba(255,255,255,0.9);">
                        Your trusted online marketplace for quality products at affordable prices.
                        Discover thousands of products from electronics to fashion, all in one place.
                    </p>

                    <!-- Hero Stats -->
                    <div class="row g-4 mb-5">
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h3 fw-bold text-warning mb-1"><?php echo number_format($total_products); ?>+</div>
                                <small style="color: rgba(255,255,255,0.8);">Products</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h3 fw-bold text-warning mb-1"><?php echo number_format($total_users); ?>+</div>
                                <small style="color: rgba(255,255,255,0.8);">Happy Customers</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center">
                                <div class="h3 fw-bold text-warning mb-1"><?php echo number_format($recent_orders); ?>+</div>
                                <small style="color: rgba(255,255,255,0.8);">Orders This Month</small>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Actions -->
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-warning btn-lg">
                            <i class="fas fa-shopping-cart me-2"></i>Shop Now
                        </a>
                        <a href="<?php echo SITE_URL; ?>/products.php?featured=1" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-star me-2"></i>Featured Products
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="hero-image text-center">
                    <div class="position-relative">
                        <?php if (!empty($featured_products)): ?>
                            <div class="hero-product-showcase">
                                <?php foreach (array_slice($featured_products, 0, 3) as $index => $product): ?>
                                    <div class="floating-product position-absolute" style="
                                        top: <?php echo 20 + ($index * 25); ?>%;
                                        left: <?php echo 10 + ($index * 30); ?>%;
                                        animation-delay: <?php echo $index * 0.5; ?>s;
                                    ">
                                        <div class="card bg-white shadow-lg border-0 rounded-xl" style="width: 120px;">
                                            <img src="<?php echo SITE_URL . '/uploads/' . ($product['image'] ?: 'default-product.jpg'); ?>"
                                                 class="card-img-top rounded-top" style="height: 80px; object-fit: cover;"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <div class="card-body p-2 text-center">
                                                <small class="text-primary fw-bold"><?php echo formatPrice($product['price']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <img src="<?php echo SITE_URL; ?>/Images/home gambar/hero-shopping.png"
                                 alt="Shopping" class="img-fluid" style="max-height: 400px;">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="fw-bold mb-3">Shop by Category</h2>
                <p class="text-muted">Explore our wide range of product categories</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php foreach ($categories as $category): ?>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card category-card h-100 text-center">
                        <div class="card-body p-4">
                            <div class="category-icon mb-3">
                                <?php
                                $icons = [
                                    'electronics' => 'fas fa-laptop',
                                    'cosmetics' => 'fas fa-palette',
                                    'sports' => 'fas fa-dumbbell',
                                    'food-snacks' => 'fas fa-cookie-bite',
                                    'health-medicine' => 'fas fa-pills',
                                    'vegetables' => 'fas fa-carrot'
                                ];
                                $icon = $icons[$category['slug']] ?? 'fas fa-box';
                                ?>
                                <i class="<?php echo $icon; ?> fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="card-text text-muted small">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </p>
                            <a href="<?php echo SITE_URL; ?>/products.php?category=<?php echo $category['slug']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                Browse
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<?php if (!empty($featured_products)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="fw-bold mb-3">Featured Products</h2>
                <p class="text-muted">Discover our handpicked selection of premium products</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php foreach ($featured_products as $product): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card product-card h-100">
                        <?php if ($product['sale_price']): ?>
                            <span class="badge bg-danger">Sale</span>
                        <?php endif; ?>
                        
                        <div class="card-img-container">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $product['image'] ?: 'default-product.jpg'; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                            </div>
                            
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            
                            <p class="card-text text-muted small flex-grow-1">
                                <?php echo htmlspecialchars(substr($product['short_description'], 0, 100)); ?>...
                            </p>
                            
                            <div class="product-price mb-3">
                                <?php if ($product['sale_price']): ?>
                                    <span class="fw-bold text-primary"><?php echo formatPrice($product['sale_price']); ?></span>
                                    <span class="product-price-old"><?php echo formatPrice($product['price']); ?></span>
                                <?php else: ?>
                                    <span class="fw-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-actions">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $product['slug']; ?>"
                                           class="btn btn-outline-primary btn-sm w-100">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary btn-sm add-to-cart-btn w-100"
                                                data-product-id="<?php echo $product['id']; ?>">
                                            <i class="fas fa-cart-plus me-1"></i>Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary btn-lg">
                <i class="fas fa-eye me-2"></i>View All Products
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-shipping-fast fa-3x text-primary"></i>
                    </div>
                    <h5 class="fw-bold">Fast Shipping</h5>
                    <p class="text-muted">Free shipping on orders over Rp 100,000</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-shield-alt fa-3x text-primary"></i>
                    </div>
                    <h5 class="fw-bold">Secure Payment</h5>
                    <p class="text-muted">Your payment information is safe with us</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-undo fa-3x text-primary"></i>
                    </div>
                    <h5 class="fw-bold">Easy Returns</h5>
                    <p class="text-muted">30-day return policy for your peace of mind</p>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-headset fa-3x text-primary"></i>
                    </div>
                    <h5 class="fw-bold">24/7 Support</h5>
                    <p class="text-muted">Customer support available around the clock</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="py-5 bg-light">
    <div class="container-lg">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-gray-900">Why Choose TeWuNeed?</h2>
            <p class="text-gray-600">Experience the best online shopping with our premium services</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-shipping-fast fa-2x text-white"></i>
                    </div>
                    <h5 class="fw-bold text-gray-900">Fast Shipping</h5>
                    <p class="text-gray-600 small">Free shipping on orders over Rp 100,000. Get your products delivered within 1-3 business days.</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-shield-alt fa-2x text-white"></i>
                    </div>
                    <h5 class="fw-bold text-gray-900">Secure Payment</h5>
                    <p class="text-gray-600 small">Your payment information is safe with our SSL encryption and secure payment gateways.</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-undo-alt fa-2x text-white"></i>
                    </div>
                    <h5 class="fw-bold text-gray-900">Easy Returns</h5>
                    <p class="text-gray-600 small">30-day return policy for your peace of mind. No questions asked returns and exchanges.</p>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="text-center">
                    <div class="bg-info rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-headset fa-2x text-white"></i>
                    </div>
                    <h5 class="fw-bold text-gray-900">24/7 Support</h5>
                    <p class="text-gray-600 small">Customer support available around the clock. We're here to help whenever you need us.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Customer Testimonials Section -->
<section class="py-5">
    <div class="container-lg">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-gray-900">What Our Customers Say</h2>
            <p class="text-gray-600">Join thousands of satisfied customers who trust TeWuNeed</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex mb-3">
                            <?php for($i = 0; $i < 5; $i++): ?>
                                <i class="fas fa-star text-warning"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-700 mb-3">"Amazing quality products and super fast delivery! I've been shopping here for months and never disappointed."</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <span class="text-white fw-bold">SA</span>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Sarah Ahmad</h6>
                                <small class="text-muted">Verified Customer</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex mb-3">
                            <?php for($i = 0; $i < 5; $i++): ?>
                                <i class="fas fa-star text-warning"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-700 mb-3">"Great customer service and easy returns. The website is user-friendly and checkout process is smooth."</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <span class="text-white fw-bold">RH</span>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Rizki Hidayat</h6>
                                <small class="text-muted">Verified Customer</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex mb-3">
                            <?php for($i = 0; $i < 5; $i++): ?>
                                <i class="fas fa-star text-warning"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-700 mb-3">"Best prices in the market with authentic products. Highly recommend TeWuNeed for online shopping!"</p>
                        <div class="d-flex align-items-center">
                            <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <span class="text-white fw-bold">MP</span>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Maya Putri</h6>
                                <small class="text-muted">Verified Customer</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Badges Section -->
<section class="py-4 bg-light border-top">
    <div class="container-lg">
        <div class="row align-items-center text-center">
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-shield-check text-success fa-2x me-3"></i>
                    <div class="text-start">
                        <h6 class="mb-0 fw-bold">100% Secure</h6>
                        <small class="text-muted">SSL Protected</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-truck text-primary fa-2x me-3"></i>
                    <div class="text-start">
                        <h6 class="mb-0 fw-bold">Fast Delivery</h6>
                        <small class="text-muted">1-3 Business Days</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-medal text-warning fa-2x me-3"></i>
                    <div class="text-start">
                        <h6 class="mb-0 fw-bold">Quality Guaranteed</h6>
                        <small class="text-muted">Authentic Products</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-headset text-info fa-2x me-3"></i>
                    <div class="text-start">
                        <h6 class="mb-0 fw-bold">24/7 Support</h6>
                        <small class="text-muted">Always Here to Help</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
