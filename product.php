<?php
// Product Detail Page
require_once 'config/config.php';

// Get product by slug or ID
$product_slug = $_GET['slug'] ?? '';
$product_id = $_GET['id'] ?? '';

if (empty($product_slug) && empty($product_id)) {
    redirect(SITE_URL . '/products.php');
}

try {
    $pdo = getDBConnection();
    
    // Get product details
    if ($product_slug) {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.slug = ? AND p.status = 'active'
        ");
        $stmt->execute([$product_slug]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, c.slug as category_slug
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ? AND p.status = 'active'
        ");
        $stmt->execute([$product_id]);
    }
    
    $product = $stmt->fetch();
    
    if (!$product) {
        redirect(SITE_URL . '/products.php');
    }
    
    // Get related products
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
        ORDER BY RAND() 
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product['id']]);
    $related_products = $stmt->fetchAll();
    
    // Get product reviews
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name 
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$product['id']]);
    $reviews = $stmt->fetchAll();
    
    // Calculate average rating
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
        FROM reviews 
        WHERE product_id = ? AND status = 'approved'
    ");
    $stmt->execute([$product['id']]);
    $rating_data = $stmt->fetch();
    $avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
    $total_reviews = $rating_data['total_reviews'] ?? 0;
    
} catch (Exception $e) {
    redirect(SITE_URL . '/products.php');
}

$page_title = $product['name'] . ' - ' . SITE_NAME;
include 'includes/header.php';
include 'includes/product_card.php';
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/products.php">Products</a></li>
            <?php if ($product['category_name']): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/products.php?category=<?php echo urlencode($product['category_slug']); ?>">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Product Images -->
        <div class="col-lg-6 mb-4">
            <div class="product-images">
                <div class="main-image mb-3">
                    <?php 
                    $image_path = 'uploads/products/' . ($product['image'] ?? 'default-product.jpg');
                    if (!file_exists($image_path)) {
                        $image_path = 'uploads/products/default-product.jpg';
                    }
                    ?>
                    <img src="<?php echo SITE_URL . '/' . $image_path; ?>" 
                         class="img-fluid rounded shadow" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         id="main-product-image"
                         style="width: 100%; height: 500px; object-fit: cover;">
                </div>
                
                <!-- Thumbnail images (placeholder for future implementation) -->
                <div class="thumbnail-images d-flex gap-2">
                    <img src="<?php echo SITE_URL . '/' . $image_path; ?>" 
                         class="img-thumbnail" 
                         style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;"
                         onclick="changeMainImage(this.src)">
                </div>
            </div>
        </div>
        
        <!-- Product Details -->
        <div class="col-lg-6">
            <div class="product-details">
                <!-- Category -->
                <?php if ($product['category_name']): ?>
                    <div class="mb-2">
                        <a href="<?php echo SITE_URL; ?>/products.php?category=<?php echo urlencode($product['category_slug']); ?>" 
                           class="badge bg-secondary text-decoration-none">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Product Name -->
                <h1 class="h2 mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- Rating -->
                <div class="rating mb-3">
                    <div class="d-flex align-items-center">
                        <div class="stars text-warning me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-muted">(<?php echo $avg_rating; ?>/5 from <?php echo $total_reviews; ?> reviews)</span>
                    </div>
                </div>
                
                <!-- Price -->
                <div class="price mb-4">
                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                        <div class="d-flex align-items-center gap-3">
                            <span class="h3 text-danger mb-0">
                                <?php echo formatPrice($product['sale_price']); ?>
                            </span>
                            <span class="h5 text-muted text-decoration-line-through mb-0">
                                <?php echo formatPrice($product['price']); ?>
                            </span>
                            <span class="badge bg-danger">
                                <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                            </span>
                        </div>
                    <?php else: ?>
                        <span class="h3 text-primary mb-0"><?php echo formatPrice($product['price']); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Stock Status -->
                <div class="stock-status mb-4">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <span class="text-success">In Stock (<?php echo $product['stock_quantity']; ?> available)</span>
                        </div>
                        <?php if ($product['stock_quantity'] <= 5): ?>
                            <small class="text-warning">⚠️ Only <?php echo $product['stock_quantity']; ?> left in stock!</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-times-circle text-danger me-2"></i>
                            <span class="text-danger">Out of Stock</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Short Description -->
                <?php if ($product['short_description']): ?>
                    <div class="short-description mb-4">
                        <p class="lead"><?php echo htmlspecialchars($product['short_description']); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Add to Cart Form -->
                <form id="add-to-cart-form" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="quantity" 
                                   name="quantity" 
                                   value="1" 
                                   min="1" 
                                   max="<?php echo $product['stock_quantity']; ?>"
                                   <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-9">
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <button type="submit" class="btn btn-primary btn-lg me-2">
                                    <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-lg" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-heart me-2"></i>Wishlist
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-lg" disabled>
                                    <i class="fas fa-times me-2"></i>Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <!-- Product Features -->
                <div class="product-features">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-truck text-primary me-2"></i>
                                <small>Free Shipping</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-undo text-primary me-2"></i>
                                <small>30-Day Returns</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-shield-alt text-primary me-2"></i>
                                <small>Warranty Included</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-headset text-primary me-2"></i>
                                <small>24/7 Support</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                        Description
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                        Reviews (<?php echo $total_reviews; ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab">
                        Shipping Info
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="productTabsContent">
                <!-- Description Tab -->
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <div class="p-4">
                        <?php if ($product['description']): ?>
                            <div class="product-description">
                                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No detailed description available for this product.</p>
                        <?php endif; ?>
                        
                        <!-- Product Specifications -->
                        <div class="mt-4">
                            <h5>Product Specifications</h5>
                            <table class="table table-striped">
                                <tr>
                                    <td><strong>SKU</strong></td>
                                    <td><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Category</strong></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Stock</strong></td>
                                    <td><?php echo $product['stock_quantity']; ?> units</td>
                                </tr>
                                <tr>
                                    <td><strong>Added</strong></td>
                                    <td><?php echo formatDate($product['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <div class="p-4">
                        <?php if (!empty($reviews)): ?>
                            <div class="reviews-list">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item border-bottom pb-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                                <div class="stars text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo formatDate($review['created_at']); ?></small>
                                        </div>
                                        <p><?php echo htmlspecialchars($review['comment']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No reviews yet. Be the first to review this product!</p>
                        <?php endif; ?>
                        
                        <!-- Add Review Form -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="add-review mt-4">
                                <h5>Write a Review</h5>
                                <form id="review-form">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Rating</label>
                                        <div class="rating-input">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>">
                                                <label for="star<?php echo $i; ?>" class="star">★</label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="4" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Submit Review</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="mt-4">
                                <p><a href="<?php echo SITE_URL; ?>/login_firebase.php">Login</a> to write a review.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Tab -->
                <div class="tab-pane fade" id="shipping" role="tabpanel">
                    <div class="p-4">
                        <h5>Shipping Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Delivery Options</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-truck text-primary me-2"></i>Standard Delivery (3-5 days) - Free</li>
                                    <li><i class="fas fa-shipping-fast text-primary me-2"></i>Express Delivery (1-2 days) - Rp 25,000</li>
                                    <li><i class="fas fa-motorcycle text-primary me-2"></i>Same Day Delivery - Rp 50,000</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Return Policy</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-undo text-success me-2"></i>30-day return policy</li>
                                    <li><i class="fas fa-shield-alt text-success me-2"></i>Original packaging required</li>
                                    <li><i class="fas fa-money-bill-wave text-success me-2"></i>Full refund guaranteed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">Related Products</h3>
                <div class="row g-4">
                    <?php foreach ($related_products as $related_product): ?>
                        <?php echo renderProductCard($related_product); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Add to Cart Form Handler
document.getElementById('add-to-cart-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const quantity = document.getElementById('quantity').value;
    const productId = <?php echo $product['id']; ?>;
    
    fetch('api/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_id=' + productId + '&quantity=' + parseInt(quantity)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Product added to cart!', 'success');
            updateCartCount();
        } else {
            showToast(data.message || 'Failed to add to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
    });
});

// Change main image
function changeMainImage(src) {
    document.getElementById('main-product-image').src = src;
}

// Add to wishlist function
function addToWishlist(productId) {
    fetch('ajax/add_to_wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Failed to add to wishlist', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Please login to add to wishlist', 'info');
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 3000);
}

// Update cart count
function updateCartCount() {
    fetch('ajax/get_cart_count.php')
    .then(response => response.json())
    .then(data => {
        const cartBadge = document.querySelector('.cart-count');
        if (cartBadge && data.count) {
            cartBadge.textContent = data.count;
            cartBadge.style.display = 'inline';
        }
    });
}
</script>

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input .star {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.rating-input input[type="radio"]:checked ~ .star,
.rating-input .star:hover,
.rating-input .star:hover ~ .star {
    color: #ffc107;
}

.product-images img {
    transition: transform 0.3s ease;
}

.product-images img:hover {
    transform: scale(1.05);
}

.stars i {
    font-size: 1rem;
}

.product-features {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
}
</style>

<?php include 'includes/footer.php'; ?>
