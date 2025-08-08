<?php
// Product Card Component
function renderProductCard($product) {
    $image_path = 'uploads/products/' . ($product['image'] ?? 'default-product.jpg');
    if (!file_exists($image_path)) {
        $image_path = 'uploads/products/default-product.jpg';
    }
    
    $sale_price = $product['sale_price'] ?? null;
    $regular_price = $product['price'];
    $discount_percentage = 0;
    
    if ($sale_price && $sale_price < $regular_price) {
        $discount_percentage = round((($regular_price - $sale_price) / $regular_price) * 100);
    }
    
    $final_price = $sale_price ?? $regular_price;
    $stock_status = $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock';
    $stock_class = $product['stock_quantity'] <= 5 ? 'low-stock' : '';
    
    ob_start();
    ?>
    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
        <div class="card product-card h-100 shadow-sm">
            <div class="position-relative">
                <img src="<?php echo SITE_URL . '/' . $image_path; ?>" 
                     class="card-img-top product-image" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="height: 250px; object-fit: cover;">
                
                <!-- Badges -->
                <div class="position-absolute top-0 start-0 p-2">
                    <?php if ($product['featured']): ?>
                        <span class="badge bg-warning text-dark mb-1">
                            <i class="fas fa-star me-1"></i>Featured
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($discount_percentage > 0): ?>
                        <span class="badge bg-danger">
                            -<?php echo $discount_percentage; ?>%
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Stock Status -->
                <div class="position-absolute top-0 end-0 p-2">
                    <?php if ($product['stock_quantity'] <= 0): ?>
                        <span class="badge bg-secondary">Out of Stock</span>
                    <?php elseif ($product['stock_quantity'] <= 5): ?>
                        <span class="badge bg-warning text-dark">Low Stock</span>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="position-absolute bottom-0 end-0 p-2 product-actions" style="opacity: 0; transition: opacity 0.3s;">
                    <button class="btn btn-sm btn-outline-light rounded-circle me-1" 
                            onclick="addToWishlist(<?php echo $product['id']; ?>)"
                            title="Add to Wishlist">
                        <i class="fas fa-heart"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-light rounded-circle" 
                            onclick="quickView(<?php echo $product['id']; ?>)"
                            title="Quick View">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body d-flex flex-column">
                <!-- Category -->
                <?php if (!empty($product['category_name'])): ?>
                    <small class="text-muted mb-1">
                        <a href="products.php?category=<?php echo urlencode($product['category_slug'] ?? ''); ?>" 
                           class="text-decoration-none">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </small>
                <?php endif; ?>
                
                <!-- Product Name -->
                <h6 class="card-title mb-2">
                    <a href="product.php?slug=<?php echo urlencode($product['slug']); ?>" 
                       class="text-decoration-none text-dark">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </a>
                </h6>
                
                <!-- Short Description -->
                <?php if (!empty($product['short_description'])): ?>
                    <p class="card-text text-muted small mb-2" style="font-size: 0.85rem;">
                        <?php echo htmlspecialchars(substr($product['short_description'], 0, 80)) . '...'; ?>
                    </p>
                <?php endif; ?>
                
                <!-- Rating (if available) -->
                <div class="mb-2">
                    <div class="d-flex align-items-center">
                        <div class="text-warning me-1">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= 4 ? '' : '-o'; ?>" style="font-size: 0.8rem;"></i>
                            <?php endfor; ?>
                        </div>
                        <small class="text-muted">(4.0)</small>
                    </div>
                </div>
                
                <!-- Price -->
                <div class="mt-auto">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="price-section">
                            <?php if ($sale_price && $sale_price < $regular_price): ?>
                                <span class="h6 text-danger mb-0">Rp <?php echo number_format($sale_price, 0, ',', '.'); ?></span>
                                <small class="text-muted text-decoration-line-through ms-1">
                                    Rp <?php echo number_format($regular_price, 0, ',', '.'); ?>
                                </small>
                            <?php else: ?>
                                <span class="h6 text-primary mb-0">Rp <?php echo number_format($regular_price, 0, ',', '.'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stock Info -->
                        <small class="text-muted">
                            Stock: <?php echo $product['stock_quantity']; ?>
                        </small>
                    </div>
                    
                    <!-- Add to Cart Button -->
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <button class="btn btn-primary btn-sm w-100" 
                                onclick="addToCart(<?php echo $product['id']; ?>)">
                            <i class="fas fa-shopping-cart me-1"></i>Add to Cart
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-sm w-100" disabled>
                            <i class="fas fa-times me-1"></i>Out of Stock
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .product-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #e0e0e0;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    
    .product-card:hover .product-actions {
        opacity: 1 !important;
    }
    
    .product-image {
        transition: transform 0.3s ease;
    }
    
    .product-card:hover .product-image {
        transform: scale(1.05);
    }
    
    .price-section {
        flex-grow: 1;
    }
    
    .low-stock {
        border-left: 3px solid #ffc107;
    }
    
    .out-of-stock {
        opacity: 0.7;
    }
    
    .badge {
        font-size: 0.7rem;
    }
    </style>
    
    <script>
    function addToCart(productId) {
        // Add to cart functionality
        fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'product_id=' + productId + '&quantity=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
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
    }
    
    function addToWishlist(productId) {
        // Add to wishlist functionality
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
                showToast('Added to wishlist!', 'success');
            } else {
                showToast(data.message || 'Failed to add to wishlist', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Please login to add to wishlist', 'info');
        });
    }
    
    function quickView(productId) {
        // Quick view functionality
        window.open('product.php?id=' + productId, '_blank');
    }
    
    function showToast(message, type = 'info') {
        // Simple toast notification
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
    
    function updateCartCount() {
        // Update cart count in header
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
    <?php
    return ob_get_clean();
}
?>
