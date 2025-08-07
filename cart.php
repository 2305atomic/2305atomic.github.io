<?php
// Shopping Cart Page
require_once 'config/config.php';

$page_title = 'Shopping Cart - ' . SITE_NAME;

try {
    $pdo = getDBConnection();
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();

    // Get cart items
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.slug,
                   cat.name as category_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE c.user_id = ? AND p.status = 'active'
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.slug,
                   cat.name as category_name
            FROM cart c
            JOIN products p ON c.product_id = p.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE c.session_id = ? AND p.status = 'active'
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$session_id]);
    }

    $cart_items = $stmt->fetchAll();

    // Calculate totals
    $subtotal = 0;
    $total_items = 0;
    foreach ($cart_items as $item) {
        $item_price = $item['sale_price'] ?? $item['price'];
        $subtotal += $item_price * $item['quantity'];
        $total_items += $item['quantity'];
    }

    $shipping_cost = $subtotal >= 500000 ? 0 : 25000; // Free shipping over Rp 500,000
    $tax = $subtotal * 0.11; // 11% tax
    $total = $subtotal + $shipping_cost + $tax;

} catch (Exception $e) {
    $cart_items = [];
    $subtotal = $total_items = $shipping_cost = $tax = $total = 0;
}

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
            <li class="breadcrumb-item active">Shopping Cart</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Shopping Cart (<?php echo count($cart_items); ?> items)
                    </h4>
                </div>
                
                <div class="card-body">
                    <?php if (empty($cart_items)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                            <h3>Your cart is empty</h3>
                            <p class="text-muted">Add some products to your cart to get started.</p>
                            <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary">
                                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <?php
                                        $price = $item['sale_price'] ?: $item['price'];
                                        $item_total = $price * $item['quantity'];
                                        ?>
                                        <tr data-cart-id="<?php echo $item['id']; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $item['image'] ?: 'default-product.jpg'; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                         class="me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                        <small class="text-muted">
                                                            Stock: <?php echo $item['stock_quantity']; ?> available
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['sale_price']): ?>
                                                    <span class="fw-bold text-primary"><?php echo formatPrice($item['sale_price']); ?></span>
                                                    <br><small class="text-muted text-decoration-line-through"><?php echo formatPrice($item['price']); ?></small>
                                                <?php else: ?>
                                                    <span class="fw-bold"><?php echo formatPrice($item['price']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="input-group" style="width: 120px;">
                                                    <button class="btn btn-outline-secondary btn-sm quantity-btn" 
                                                            data-action="decrease" data-cart-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" class="form-control form-control-sm text-center quantity-input" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" max="<?php echo $item['stock_quantity']; ?>"
                                                           data-cart-id="<?php echo $item['id']; ?>">
                                                    <button class="btn btn-outline-secondary btn-sm quantity-btn" 
                                                            data-action="increase" data-cart-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold item-total"><?php echo formatPrice($item_total); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-danger btn-sm remove-item" 
                                                        data-cart-id="<?php echo $item['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                            </a>
                            <button class="btn btn-outline-secondary" id="clear-cart">
                                <i class="fas fa-trash me-2"></i>Clear Cart
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($cart_items)): ?>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="cart-subtotal"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span class="text-muted"><?php echo formatPrice($shipping_cost); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (11%):</span>
                        <span><?php echo formatPrice($tax); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong id="cart-total" class="text-primary"><?php echo formatPrice($total); ?></strong>
                    </div>
                    
                    <!-- Checkout Button -->
                    <a href="checkout.php" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                    </a>

                    <!-- Guest Checkout Info -->
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                You can checkout as guest or <a href="login.php">login</a> for faster checkout
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            Secure checkout with SSL encryption
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Coupon Code -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">Have a coupon code?</h6>
                    <div class="input-group">
                        <input type="text" class="form-control" id="coupon-code" placeholder="Enter coupon code">
                        <button class="btn btn-outline-primary" id="apply-coupon">Apply</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Update quantity
    $('.quantity-btn').click(function() {
        const action = $(this).data('action');
        const cartId = $(this).data('cart-id');
        const input = $(this).siblings('.quantity-input');
        let quantity = parseInt(input.val());
        
        if (action === 'increase') {
            quantity++;
        } else if (action === 'decrease' && quantity > 1) {
            quantity--;
        }
        
        input.val(quantity);
        updateCartItem(cartId, quantity);
    });
    
    // Direct quantity input
    $('.quantity-input').change(function() {
        const cartId = $(this).data('cart-id');
        const quantity = parseInt($(this).val());
        
        if (quantity > 0) {
            updateCartItem(cartId, quantity);
        }
    });
    
    // Remove item
    $('.remove-item').click(function() {
        const cartId = $(this).data('cart-id');
        removeCartItem(cartId);
    });
    
    // Clear cart
    $('#clear-cart').click(function() {
        if (confirm('Are you sure you want to clear your cart?')) {
            clearCart();
        }
    });
});

function updateCartItem(cartId, quantity) {
    $.ajax({
        url: '<?php echo SITE_URL; ?>/api/update-cart.php',
        method: 'POST',
        data: {
            cart_id: cartId,
            quantity: quantity
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload(); // Reload to update totals
            } else {
                alert(response.message || 'Failed to update cart');
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
}

function removeCartItem(cartId) {
    $.ajax({
        url: '<?php echo SITE_URL; ?>/api/remove-from-cart.php',
        method: 'POST',
        data: {
            cart_id: cartId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Failed to remove item');
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
}

function clearCart() {
    $.ajax({
        url: '<?php echo SITE_URL; ?>/api/clear-cart.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Failed to clear cart');
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
