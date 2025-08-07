<?php
// Suppress any output before headers
ob_start();
error_reporting(0);

require_once 'config/config.php';

// Get cart items from database (same as cart.php)
$cart_items = [];
$subtotal = 0;

try {
    $pdo = getDBConnection();

    if (isset($_SESSION['user_id'])) {
        // Logged-in user cart
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.slug
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ? AND p.status = 'active'
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Guest user cart
        $session_id = session_id();
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.slug
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.session_id = ? AND c.user_id IS NULL AND p.status = 'active'
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$session_id]);
    }

    $db_cart_items = $stmt->fetchAll();

    // Check if cart is empty
    if (empty($db_cart_items)) {
        $_SESSION['error'] = 'Your cart is empty. Please add some products first.';
        $page_title = 'Checkout - Cart Empty';
        include 'includes/header.php';
        echo '<div class="container my-5">';
        echo '<div class="alert alert-warning">';
        echo '<h4>Cart is Empty</h4>';
        echo '<p>Your cart is empty. Please add some products first.</p>';
        echo '<a href="products.php" class="btn btn-primary">Shop Now</a>';
        echo '</div>';
        echo '</div>';
        include 'includes/footer.php';
        exit;
    }

    // Process cart items for checkout
    foreach ($db_cart_items as $item) {
        $price = $item['sale_price'] ?: $item['price'];
        $total = $price * $item['quantity'];
        $subtotal += $total;

        $cart_items[] = [
            'product' => [
                'id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'sale_price' => $item['sale_price'],
                'image' => $item['image'],
                'slug' => $item['slug']
            ],
            'quantity' => $item['quantity'],
            'price' => $price,
            'total' => $total
        ];
    }

} catch (Exception $e) {
    error_log("Checkout cart loading error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading cart items. Please try again.';
    redirect('cart.php');
}

// Calculate shipping and tax
$shipping_fee = $subtotal >= 100000 ? 0 : 15000; // Free shipping over Rp 100,000
$tax_rate = 0.10; // 10% tax
$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $shipping_fee + $tax_amount;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address_line_1 = sanitize($_POST['address_line_1']);
    $address_line_2 = sanitize($_POST['address_line_2']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $postal_code = sanitize($_POST['postal_code']);
    $country = sanitize($_POST['country']);
    $payment_method = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes']);
    $coupon_code = sanitize($_POST['coupon_code']);
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) ||
        empty($address_line_1) || empty($city) || empty($state) || empty($postal_code) ||
        empty($payment_method)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
    } else {
        // Store checkout data in session for review page
        $_SESSION['checkout_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'address_line_1' => $address_line_1,
            'address_line_2' => $address_line_2,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal_code,
            'country' => $country,
            'payment_method' => $payment_method,
            'notes' => $notes,
            'coupon_code' => $coupon_code
        ];

        // Apply coupon if provided
        $discount_amount = 0;
        $coupon_id = null;

        if (!empty($coupon_code)) {
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM coupons
                    WHERE code = ? AND status = 'active'
                    AND (expires_at IS NULL OR expires_at > NOW())
                    AND (max_uses = 0 OR used_count < max_uses)
                ");
                $stmt->execute([$coupon_code]);
                $coupon = $stmt->fetch();

                if ($coupon && $subtotal >= $coupon['min_amount']) {
                    if ($coupon['type'] === 'percentage') {
                        $discount_amount = $subtotal * ($coupon['value'] / 100);
                    } else {
                        $discount_amount = $coupon['value'];
                    }
                    $coupon_id = $coupon['id'];
                }
            } catch (Exception $e) {
                // Ignore coupon errors for now
            }
        }

        // Store coupon data
        $_SESSION['checkout_data']['discount_amount'] = $discount_amount;
        $_SESSION['checkout_data']['coupon_id'] = $coupon_id;

        // Redirect to order review page
        redirect('order-review.php');
    }
}

$page_title = 'Checkout';

// Clean output buffer before rendering
ob_end_clean();

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <!-- Checkout Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>Checkout Information
                    </h4>
                </div>
                <div class="card-body">
                    <form method="POST" id="checkoutForm">
                        <!-- Customer Information -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Customer Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_SESSION['user_first_name']) ? htmlspecialchars($_SESSION['user_first_name']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_SESSION['user_last_name']) ? htmlspecialchars($_SESSION['user_last_name']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Shipping Address -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Shipping Address</h5>
                            <div class="mb-3">
                                <label for="address_line_1" class="form-label">Address Line 1 *</label>
                                <input type="text" class="form-control" id="address_line_1" name="address_line_1" 
                                       placeholder="Street address, P.O. box, company name, c/o" required>
                            </div>
                            <div class="mb-3">
                                <label for="address_line_2" class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line_2" name="address_line_2" 
                                       placeholder="Apartment, suite, unit, building, floor, etc.">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="state" class="form-label">State/Province *</label>
                                    <input type="text" class="form-control" id="state" name="state" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="postal_code" class="form-label">Postal Code *</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="country" class="form-label">Country *</label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="">Select Country</option>
                                    <option value="Indonesia" selected>Indonesia</option>
                                    <option value="Malaysia">Malaysia</option>
                                    <option value="Singapore">Singapore</option>
                                    <option value="Thailand">Thailand</option>
                                    <option value="Philippines">Philippines</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Payment Method</h5>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="Bank Transfer BCA" required>
                                        <label class="form-check-label" for="bank_transfer">
                                            <i class="fas fa-university me-2"></i>Bank Transfer BCA
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="bank_mandiri" value="Bank Transfer Mandiri" required>
                                        <label class="form-check-label" for="bank_mandiri">
                                            <i class="fas fa-university me-2"></i>Bank Transfer Mandiri
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="gopay" value="GoPay" required>
                                        <label class="form-check-label" for="gopay">
                                            <i class="fas fa-mobile-alt me-2"></i>GoPay
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="ovo" value="OVO" required>
                                        <label class="form-check-label" for="ovo">
                                            <i class="fas fa-mobile-alt me-2"></i>OVO
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="dana" value="Dana" required>
                                        <label class="form-check-label" for="dana">
                                            <i class="fas fa-mobile-alt me-2"></i>Dana
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="Credit Card" required>
                                        <label class="form-check-label" for="credit_card">
                                            <i class="fas fa-credit-card me-2"></i>Credit Card
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Notes -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Order Notes (Optional)</h5>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="Special instructions for your order..."></textarea>
                        </div>
                        
                        <!-- Coupon Code -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Coupon Code</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" class="form-control" name="coupon_code" id="coupon_code" 
                                           placeholder="Enter coupon code">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary w-100" onclick="applyCoupon()">
                                        Apply Coupon
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-bag me-2"></i>Order Summary
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Cart Items -->
                    <div class="mb-3">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex align-items-center mb-3">
                                <img src="uploads/<?php echo $item['product']['image'] ?: 'default-product.jpg'; ?>"
                                     alt="<?php echo htmlspecialchars($item['product']['name']); ?>"
                                     class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product']['name']); ?></h6>
                                    <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo formatPrice($item['total']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <!-- Order Totals -->
                    <div class="mb-2 d-flex justify-content-between">
                        <span>Subtotal:</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>

                    <div class="mb-2 d-flex justify-content-between">
                        <span>Shipping:</span>
                        <span>
                            <?php if ($shipping_fee > 0): ?>
                                <?php echo formatPrice($shipping_fee); ?>
                            <?php else: ?>
                                <span class="text-success">FREE</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="mb-2 d-flex justify-content-between">
                        <span>Tax (10%):</span>
                        <span><?php echo formatPrice($tax_amount); ?></span>
                    </div>

                    <div id="discount-row" class="mb-2 d-flex justify-content-between text-success" style="display: none !important;">
                        <span>Discount:</span>
                        <span id="discount-amount">-<?php echo formatPrice(0); ?></span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong class="text-primary" id="total-amount"><?php echo formatPrice($total_amount); ?></strong>
                    </div>

                    <!-- Place Order Button -->
                    <button type="submit" form="checkoutForm" class="btn btn-primary w-100 btn-lg">
                        <i class="fas fa-lock me-2"></i>Place Order
                    </button>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            Your payment information is secure and encrypted
                        </small>
                    </div>

                    <!-- Continue Shopping -->
                    <div class="text-center mt-3">
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
            </div>

            <!-- Security Info -->
            <div class="card mt-3">
                <div class="card-body text-center">
                    <h6><i class="fas fa-shield-alt text-success me-2"></i>Secure Checkout</h6>
                    <small class="text-muted">
                        Your personal and payment information is protected with SSL encryption.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Apply coupon functionality
function applyCoupon() {
    const couponCode = document.getElementById('coupon_code').value.trim();

    if (!couponCode) {
        alert('Please enter a coupon code');
        return;
    }

    // Show loading
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Applying...';
    button.disabled = true;

    // AJAX request to validate coupon
    fetch('ajax/validate-coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            coupon_code: couponCode,
            subtotal: <?php echo $subtotal; ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show discount
            document.getElementById('discount-row').style.display = 'flex';
            document.getElementById('discount-amount').textContent = '-' + data.discount_formatted;

            // Update total
            const newTotal = <?php echo $total_amount; ?> - data.discount_amount;
            document.getElementById('total-amount').textContent = data.new_total_formatted;

            // Show success message
            showAlert('Coupon applied successfully!', 'success');

            // Disable coupon input
            document.getElementById('coupon_code').disabled = true;
            button.innerHTML = '<i class="fas fa-check me-2"></i>Applied';
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
        } else {
            showAlert(data.message, 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        showAlert('Error applying coupon. Please try again.', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Show alert messages
function showAlert(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Insert alert at the top of the form
    const form = document.getElementById('checkoutForm');
    form.insertAdjacentHTML('afterbegin', alertHtml);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = form.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Form validation
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    if (!isValid) {
        e.preventDefault();
        showAlert('Please fill in all required fields.', 'error');
        return false;
    }

    // Show loading state
    const submitButton = this.querySelector('button[type="submit"]');
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Order...';
    submitButton.disabled = true;
});

// Auto-fill user data if logged in
<?php if (isset($_SESSION['user_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fill user information if available
    <?php if (isset($_SESSION['user_phone'])): ?>
        document.getElementById('phone').value = '<?php echo htmlspecialchars($_SESSION['user_phone']); ?>';
    <?php endif; ?>
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
