<?php
require_once 'config/config.php';

// Get product slug from URL
$product_slug = $_GET['slug'] ?? '';

if (empty($product_slug)) {
    redirect(SITE_URL . '/products.php');
}

try {
    // Get product details
    $stmt = getDBConnection()->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.slug = ? AND p.status = 'active'
    ");
    $stmt->execute([$product_slug]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirect(SITE_URL . '/products.php');
    }
    
    // Get product attributes
    $stmt = getDBConnection()->prepare("
        SELECT attribute_name, attribute_value 
        FROM product_attributes 
        WHERE product_id = ?
    ");
    $stmt->execute([$product['id']]);
    $attributes = $stmt->fetchAll();
    
    // Get product reviews
    $stmt = getDBConnection()->prepare("
        SELECT pr.*, u.first_name, u.last_name 
        FROM product_reviews pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.product_id = ? AND pr.status = 'approved' 
        ORDER BY pr.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$product['id']]);
    $reviews = $stmt->fetchAll();
    
    // Calculate average rating
    $stmt = getDBConnection()->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
        FROM product_reviews 
        WHERE product_id = ? AND status = 'approved'
    ");
    $stmt->execute([$product['id']]);
    $rating_data = $stmt->fetch();
    $avg_rating = round($rating_data['avg_rating'], 1);
    $total_reviews = $rating_data['total_reviews'];
    
    // Get related products
    $stmt = getDBConnection()->prepare("
        SELECT * FROM products 
        WHERE category_id = ? AND id != ? AND status = 'active' 
        ORDER BY RAND() 
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product['id']]);
    $related_products = $stmt->fetchAll();
    
} catch (Exception $e) {
    redirect(SITE_URL . '/products.php');
}

$page_title = $product['name'];
$page_description = $product['short_description'];

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/products.php">Products</a></li>
            <?php if ($product['category_name']): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/products.php?category=<?php echo $product['category_slug']; ?>">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Product Images -->
        <div class="col-lg-6 mb-4">
            <div class="product-image-container">
                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $product['image'] ?: 'default-product.jpg'; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     class="img-fluid rounded shadow" id="main-product-image">
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="col-lg-6">
            <div class="product-info">
                <div class="mb-2">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <?php if ($product['featured']): ?>
                        <span class="badge bg-warning">Featured</span>
                    <?php endif; ?>
                </div>
                
                <h1 class="h2 mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- Rating -->
                <?php if ($total_reviews > 0): ?>
                    <div class="rating mb-3">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $avg_rating ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="ms-2"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</span>
                    </div>
                <?php endif; ?>
                
                <!-- Price -->
                <div class="price mb-4">
                    <?php if ($product['sale_price']): ?>
                        <span class="h3 text-primary fw-bold"><?php echo formatPrice($product['sale_price']); ?></span>
                        <span class="h5 text-muted text-decoration-line-through ms-2"><?php echo formatPrice($product['price']); ?></span>
                        <span class="badge bg-danger ms-2">
                            <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                        </span>
                    <?php else: ?>
                        <span class="h3 text-primary fw-bold"><?php echo formatPrice($product['price']); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Description -->
                <div class="description mb-4">
                    <p class="lead"><?php echo htmlspecialchars($product['short_description']); ?></p>
                </div>
                
                <!-- Stock Status -->
                <div class="stock-status mb-4">
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check me-1"></i>
                            <?php echo $product['stock_quantity']; ?> in stock
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-times me-1"></i>
                            Out of stock
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Add to Cart -->
                <?php if ($product['stock_quantity'] > 0): ?>
                    <div class="add-to-cart mb-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label class="form-label mb-2">Quantity:</label>
                                <div class="input-group" style="width: 140px;">
                                    <button class="btn btn-outline-secondary qty-btn" type="button" id="decrease-qty">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center" id="quantity"
                                           value="1" min="1" max="<?php echo $product['stock_quantity']; ?>"
                                           style="width: 60px; font-weight: bold;">
                                    <button class="btn btn-outline-secondary qty-btn" type="button" id="increase-qty">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Max: <?php echo $product['stock_quantity']; ?> available</small>
                            </div>
                            <div class="col">
                                <button class="btn btn-primary btn-lg add-to-cart-btn"
                                        data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                                <button class="btn btn-success btn-lg ms-2 buy-now-btn"
                                        data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-bolt me-2"></i>Buy Now
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Out of Stock</strong> - This product is currently unavailable.
                    </div>
                <?php endif; ?>

                <!-- Wishlist -->
                <div class="wishlist mb-4">
                    <button class="btn btn-outline-danger btn-lg wishlist-btn"
                            data-product-id="<?php echo $product['id']; ?>"
                            title="Add to Wishlist">
                        <i class="fas fa-heart me-2"></i>Add to Wishlist
                    </button>
                    <small class="text-muted ms-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Save for later and get notified of price changes
                    </small>
                </div>
                
                <!-- Product Attributes -->
                <?php if (!empty($attributes)): ?>
                    <div class="product-attributes">
                        <h5>Specifications</h5>
                        <table class="table table-sm">
                            <?php foreach ($attributes as $attr): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($attr['attribute_name']); ?>:</td>
                                    <td><?php echo htmlspecialchars($attr['attribute_value']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Product Details Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" 
                            data-bs-target="#description" type="button" role="tab">
                        Description
                    </button>
                </li>
                <?php if (!empty($reviews)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" 
                                data-bs-target="#reviews" type="button" role="tab">
                            Reviews (<?php echo $total_reviews; ?>)
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content" id="productTabsContent">
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <div class="p-4">
                        <?php if ($product['description']): ?>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        <?php else: ?>
                            <p><?php echo htmlspecialchars($product['short_description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($reviews)): ?>
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="p-4">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review mb-4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h6>
                                            <div class="stars mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($review['created_at']); ?></small>
                                    </div>
                                    <?php if ($review['title']): ?>
                                        <h6><?php echo htmlspecialchars($review['title']); ?></h6>
                                    <?php endif; ?>
                                    <p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    <?php if ($review['verified_purchase']): ?>
                                        <span class="badge bg-success">Verified Purchase</span>
                                    <?php endif; ?>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">Related Products</h3>
                <div class="row g-4">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-lg-3 col-md-6">
                            <div class="card product-card h-100">
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $related['image'] ?: 'default-product.jpg'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h6>
                                    <div class="price">
                                        <?php if ($related['sale_price']): ?>
                                            <span class="fw-bold text-primary"><?php echo formatPrice($related['sale_price']); ?></span>
                                            <small class="text-muted text-decoration-line-through"><?php echo formatPrice($related['price']); ?></small>
                                        <?php else: ?>
                                            <span class="fw-bold text-primary"><?php echo formatPrice($related['price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="<?php echo SITE_URL; ?>/product-detail.php?slug=<?php echo $related['slug']; ?>" 
                                       class="btn btn-outline-primary btn-sm w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Use vanilla JavaScript instead of jQuery for better compatibility
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const increaseBtn = document.getElementById('increase-qty');
    const decreaseBtn = document.getElementById('decrease-qty');
    const addToCartBtn = document.querySelector('.add-to-cart-btn');

    if (!quantityInput || !increaseBtn || !decreaseBtn) {
        console.log('Quantity controls not found');
        return;
    }

    // Increase quantity
    increaseBtn.addEventListener('click', function() {
        const max = parseInt(quantityInput.getAttribute('max'));
        const current = parseInt(quantityInput.value) || 1;

        if (current < max) {
            quantityInput.value = current + 1;
            // Add visual feedback
            this.classList.add('btn-success');
            this.classList.remove('btn-outline-secondary');
            setTimeout(() => {
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-secondary');
            }, 200);
        } else {
            // Show max reached feedback
            this.classList.add('btn-warning');
            this.classList.remove('btn-outline-secondary');
            setTimeout(() => {
                this.classList.remove('btn-warning');
                this.classList.add('btn-outline-secondary');
            }, 500);
            showToast('Maximum quantity reached!', 'warning');
        }
        updateQuantityDisplay();
    });

    // Decrease quantity
    decreaseBtn.addEventListener('click', function() {
        const current = parseInt(quantityInput.value) || 1;

        if (current > 1) {
            quantityInput.value = current - 1;
            // Add visual feedback
            this.classList.add('btn-danger');
            this.classList.remove('btn-outline-secondary');
            setTimeout(() => {
                this.classList.remove('btn-danger');
                this.classList.add('btn-outline-secondary');
            }, 200);
        } else {
            // Show minimum reached feedback
            this.classList.add('btn-warning');
            this.classList.remove('btn-outline-secondary');
            setTimeout(() => {
                this.classList.remove('btn-warning');
                this.classList.add('btn-outline-secondary');
            }, 500);
            showToast('Minimum quantity is 1!', 'warning');
        }
        updateQuantityDisplay();
    });

    // Handle manual quantity input
    quantityInput.addEventListener('input', function() {
        const max = parseInt(this.getAttribute('max'));
        const min = parseInt(this.getAttribute('min'));
        let current = parseInt(this.value) || 1;

        if (current > max) {
            this.value = max;
            showToast('Maximum quantity is ' + max, 'warning');
        } else if (current < min) {
            this.value = min;
            showToast('Minimum quantity is ' + min, 'warning');
        }
        updateQuantityDisplay();
    });

    quantityInput.addEventListener('change', function() {
        updateQuantityDisplay();
    });

    function updateQuantityDisplay() {
        const qty = parseInt(quantityInput.value) || 1;
        const max = parseInt(quantityInput.getAttribute('max'));

        // Update button states
        decreaseBtn.disabled = qty <= 1;
        increaseBtn.disabled = qty >= max;

        // Update add to cart button text
        if (addToCartBtn) {
            addToCartBtn.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Add ' + qty + ' to Cart';
        }
    }

    // Add to cart with quantity
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(quantityInput.value) || 1;

            // Show loading state
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';

            // Send fetch request
            fetch('<?php echo SITE_URL; ?>/api/add-to-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=' + quantity
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Added ' + quantity + ' item(s) to cart!', 'success');

                    // Update cart count if function exists
                    if (typeof updateCartCount === 'function') {
                        updateCartCount();
                    }

                    // Show success state
                    this.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-success');

                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-primary');
                        this.disabled = false;
                    }, 2000);
                } else {
                    showToast(data.message || 'Failed to add to cart', 'error');
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    }

    // Buy Now functionality
    $('.buy-now-btn').click(function() {
        const productId = $(this).data('product-id');
        const quantity = parseInt($('#quantity').val()) || 1;
        const button = $(this);

        // Disable button and show loading
        button.prop('disabled', true);
        const originalText = button.html();
        button.html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

        // Add to cart first
        $.ajax({
            url: '<?php echo SITE_URL; ?>/api/add-to-cart.php',
            method: 'POST',
            data: {
                product_id: productId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Product added to cart! Redirecting to checkout...', 'success');
                    // Redirect to checkout after short delay
                    setTimeout(() => {
                        window.location.href = '<?php echo SITE_URL; ?>/checkout.php';
                    }, 1000);
                } else {
                    showToast(response.message || 'Failed to add product to cart', 'error');
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                showToast('An error occurred. Please try again.', 'error');
                button.html(originalText);
                button.prop('disabled', false);
            }
        });
    });

    // Initialize quantity display
    updateQuantityDisplay();

    // Toast notification function
    function showToast(message, type = 'info') {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        const toastId = 'toast-' + Date.now();
        const bgClass = type === 'success' ? 'bg-success' :
                       type === 'error' ? 'bg-danger' :
                       type === 'warning' ? 'bg-warning' : 'bg-info';

        const iconClass = type === 'success' ? 'check-circle' :
                         type === 'error' ? 'exclamation-triangle' :
                         type === 'warning' ? 'exclamation-triangle' : 'info-circle';

        const toastHtml = `
            <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
                <div class="toast-header ${bgClass} text-white border-0">
                    <i class="fas fa-${iconClass} me-2"></i>
                    <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                    <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        // Auto remove after 5 seconds
        setTimeout(() => {
            const toastElement = document.getElementById(toastId);
            if (toastElement) {
                toastElement.remove();
            }
        }, 5000);
    }
});
</script>

<style>
/* Enhanced quantity controls */
.qty-btn {
    border-radius: 0;
    border: 2px solid #dee2e6;
    transition: all 0.3s ease;
    font-weight: bold;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qty-btn:hover {
    background-color: #f8f9fa;
    border-color: #adb5bd;
    transform: scale(1.05);
}

.qty-btn:active {
    transform: scale(0.95);
}

.qty-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

#quantity {
    border: 2px solid #dee2e6;
    border-left: 0;
    border-right: 0;
    font-size: 1.1rem;
    font-weight: bold;
    height: 40px;
}

#quantity:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Wishlist button enhancement */
.wishlist-btn {
    transition: all 0.3s ease;
    border: 2px solid #dc3545;
}

.wishlist-btn:hover {
    background-color: #dc3545;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.wishlist-btn.in-wishlist {
    background-color: #dc3545;
    color: white;
    border-color: #dc3545;
}

/* Add to cart button enhancement */
.add-to-cart-btn, .buy-now-btn {
    transition: all 0.3s ease;
    font-weight: bold;
}

.add-to-cart-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
}

.buy-now-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
}

/* Toast enhancements */
.toast {
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.toast-header {
    border-bottom: none;
}
</style>
</script>

<?php include 'includes/footer.php'; ?>
