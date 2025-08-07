<?php
require_once 'config/config.php';

// Check if order data is in session (from checkout form)
if (!isset($_SESSION['checkout_data'])) {
    // For debugging purposes, create test data if in debug mode
    if (isset($_GET['debug']) || isset($_GET['test'])) {
        $_SESSION['checkout_data'] = [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+62123456789',
            'address_line_1' => 'Test Address 123',
            'address_line_2' => '',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12345',
            'country' => 'Indonesia',
            'payment_method' => 'bank_transfer',
            'notes' => 'Test order',
            'coupon_code' => '',
            'discount_amount' => 0,
            'coupon_id' => null
        ];

        // Also create test cart items
        try {
            $pdo = getDBConnection();
            $session_id = session_id();

            // Check if cart is empty and add test item
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE session_id = ? AND user_id IS NULL");
            $stmt->execute([$session_id]);
            $cart_count = $stmt->fetch()['count'];

            if ($cart_count == 0) {
                // Get a test product
                $stmt = $pdo->query("SELECT * FROM products WHERE status = 'active' LIMIT 1");
                $test_product = $stmt->fetch();

                if ($test_product) {
                    $stmt = $pdo->prepare("INSERT INTO cart (session_id, product_id, quantity, created_at) VALUES (?, ?, 1, NOW())");
                    $stmt->execute([$session_id, $test_product['id']]);
                }
            }
        } catch (Exception $e) {
            // Ignore errors in test mode
        }
    } else {
        $_SESSION['error'] = 'Please complete the checkout form first.';
        redirect('checkout.php');
    }
}

// Debug: Log session data
error_log("Order review page loaded. Session ID: " . session_id());
error_log("Checkout data exists: " . (isset($_SESSION['checkout_data']) ? 'Yes' : 'No'));

$checkout_data = $_SESSION['checkout_data'];

// Get cart items from database (same as checkout.php)
$cart_items = [];
$subtotal = 0;

try {
    $pdo = getDBConnection();
    
    if (isset($_SESSION['user_id'])) {
        // Logged-in user cart
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.slug, p.short_description
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
            SELECT c.*, p.name, p.price, p.sale_price, p.image, p.stock_quantity, p.slug, p.short_description
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
        // In debug/test mode, don't redirect
        if (isset($_GET['debug']) || isset($_GET['test'])) {
            // Create dummy cart items for testing
            $db_cart_items = [
                [
                    'id' => 1,
                    'product_id' => 1,
                    'quantity' => 1,
                    'name' => 'Test Product',
                    'price' => 100000,
                    'sale_price' => null,
                    'image' => null,
                    'stock_quantity' => 10,
                    'slug' => 'test-product',
                    'short_description' => 'This is a test product for debugging'
                ]
            ];
        } else {
            $_SESSION['error'] = 'Your cart is empty.';
            redirect('cart.php');
        }
    }
    
    // Process cart items
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
                'slug' => $item['slug'],
                'short_description' => $item['short_description']
            ],
            'quantity' => $item['quantity'],
            'price' => $price,
            'total' => $total
        ];
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error loading cart items.';
    redirect('cart.php');
}

// Calculate totals
$shipping_fee = $subtotal >= 100000 ? 0 : 15000;
$tax_rate = 0.10;
$tax_amount = $subtotal * $tax_rate;
$discount_amount = $checkout_data['discount_amount'] ?? 0;
$total_amount = $subtotal + $shipping_fee + $tax_amount - $discount_amount;

// Handle final order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    error_log("Order processing started for session: " . session_id());
    error_log("POST data: " . print_r($_POST, true));
    error_log("Checkout data available: " . (isset($_SESSION['checkout_data']) ? 'Yes' : 'No'));
    error_log("Cart items count: " . count($cart_items));

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Generate sequential order number
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(order_number, 10) AS UNSIGNED)) as max_num FROM orders WHERE order_number LIKE 'TWN-" . date('Y') . "-%'");
        $result = $stmt->fetch();
        $next_number = ($result['max_num'] ?? 0) + 1;
        $order_number = 'TWN-' . date('Y') . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        
        // Create shipping address
        $shipping_address = "{$checkout_data['first_name']} {$checkout_data['last_name']}\n{$checkout_data['address_line_1']}";
        if ($checkout_data['address_line_2']) $shipping_address .= "\n{$checkout_data['address_line_2']}";
        $shipping_address .= "\n{$checkout_data['city']}, {$checkout_data['state']} {$checkout_data['postal_code']}\n{$checkout_data['country']}";
        if ($checkout_data['phone']) $shipping_address .= "\nPhone: {$checkout_data['phone']}";
        
        // Customer info for guest checkout
        $customer_info = json_encode([
            'first_name' => $checkout_data['first_name'],
            'last_name' => $checkout_data['last_name'],
            'email' => $checkout_data['email'],
            'phone' => $checkout_data['phone']
        ]);
        
        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, user_id, status, total_amount, shipping_amount,
                              tax_amount, discount_amount, payment_method, payment_status,
                              shipping_address, notes, coupon_id, customer_info, created_at)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
        ");

        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $stmt->execute([
            $order_number,
            $user_id,
            $total_amount,
            $shipping_fee,
            $tax_amount,
            $discount_amount,
            $checkout_data['payment_method'],
            $shipping_address,
            $checkout_data['notes'] ?? '',
            $checkout_data['coupon_id'] ?? null,
            $customer_info
        ]);
        
        $order_id = $pdo->lastInsertId();

        if (!$order_id) {
            throw new Exception('Failed to create order record');
        }

        error_log("Order created with ID: " . $order_id);

        // Insert order items
        if (empty($cart_items)) {
            throw new Exception('No items in cart');
        }

        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price, total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $order_id,
                $item['product']['id'],
                $item['quantity'],
                $item['price'],
                $item['total']
            ]);

            if (!$result) {
                throw new Exception('Failed to insert order item for product ID: ' . $item['product']['id']);
            }

            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product']['id']]);
        }
        
        // Update coupon usage if applicable
        if (isset($checkout_data['coupon_id']) && $checkout_data['coupon_id']) {
            $stmt = $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
            $stmt->execute([$checkout_data['coupon_id']]);
        }
        
        // Clear cart from database
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
            $stmt->execute([session_id()]);
        }
        
        $pdo->commit();

        error_log("Order successfully created: " . $order_number);

        // Clear checkout session data
        unset($_SESSION['checkout_data']);

        // Store order data in session for the success page
        $_SESSION['last_order'] = [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'order_date' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => $total_amount,
            'customer_name' => $checkout_data['first_name'] . ' ' . $checkout_data['last_name'],
            'customer_email' => $checkout_data['email'],
            'items' => array_map(function($item) {
                return [
                    'name' => $item['product']['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total']
                ];
            }, $cart_items)
        ];

        // Clear checkout session data
        unset($_SESSION['checkout_data']);

        // Redirect to order success page
        $_SESSION['success'] = 'Order placed successfully!';
        error_log("About to redirect to: order-success.php");

        // Use multiple redirect methods to ensure it works
        header("Location: order-success.php");
        echo "<script>window.location.href = 'order-success.php';</script>";
        echo "<meta http-equiv='refresh' content='0;url=order-success.php'>";
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("Order processing error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        $_SESSION['error'] = 'Error processing order: ' . $e->getMessage();

        // Stay on the same page to show error
    }
}

$page_title = 'Review Your Order';
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="text-center mb-4">
                <h2><i class="fas fa-clipboard-check me-2"></i>Review Your Order</h2>
                <p class="text-muted">Please review your order details before confirming your purchase</p>

                <!-- Error Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Success Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Progress Steps -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="progress-steps d-flex justify-content-between">
                        <div class="step completed">
                            <div class="step-circle"><i class="fas fa-shopping-cart"></i></div>
                            <div class="step-label">Cart</div>
                        </div>
                        <div class="step completed">
                            <div class="step-circle"><i class="fas fa-user"></i></div>
                            <div class="step-label">Checkout</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle"><i class="fas fa-clipboard-check"></i></div>
                            <div class="step-label">Review</div>
                        </div>
                        <div class="step">
                            <div class="step-circle"><i class="fas fa-check"></i></div>
                            <div class="step-label">Complete</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Order Items -->
                <div class="col-lg-8">
                    <!-- Product Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-bag me-2"></i>Order Items (<?php echo count($cart_items); ?> items)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="row mb-4 pb-3 border-bottom last-item-no-border">
                                    <div class="col-md-3">
                                        <div class="product-image-wrapper">
                                            <img src="uploads/<?php echo $item['product']['image'] ?: 'default-product.jpg'; ?>"
                                                 alt="<?php echo htmlspecialchars($item['product']['name']); ?>"
                                                 class="img-fluid rounded shadow-sm" style="width: 100%; height: 120px; object-fit: cover;">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-2 fw-bold">
                                            <a href="product-detail.php?slug=<?php echo $item['product']['slug']; ?>"
                                               class="text-decoration-none text-dark" target="_blank">
                                                <?php echo htmlspecialchars($item['product']['name']); ?>
                                                <i class="fas fa-external-link-alt ms-1 small text-primary"></i>
                                            </a>
                                        </h6>
                                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($item['product']['short_description']); ?></p>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2">Qty: <?php echo $item['quantity']; ?></span>
                                            <span class="text-muted small">Unit Price: <?php echo formatPrice($item['price']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <h5 class="text-primary fw-bold mb-0"><?php echo formatPrice($item['total']); ?></h5>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2"></i>Customer Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <i class="fas fa-user text-primary me-2"></i>
                                        <strong>Name:</strong> <?php echo htmlspecialchars($checkout_data['first_name'] . ' ' . $checkout_data['last_name']); ?>
                                    </div>
                                    <div class="info-item mb-3">
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($checkout_data['email']); ?>
                                    </div>
                                    <div class="info-item mb-3">
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($checkout_data['phone']); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <i class="fas fa-credit-card text-primary me-2"></i>
                                        <strong>Payment Method:</strong>
                                        <span class="badge bg-success ms-2"><?php echo htmlspecialchars($checkout_data['payment_method']); ?></span>
                                    </div>
                                    <?php if (!empty($checkout_data['notes'])): ?>
                                        <div class="info-item mb-3">
                                            <i class="fas fa-sticky-note text-primary me-2"></i>
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($checkout_data['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-truck me-2"></i>Shipping Address
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="shipping-address bg-light p-3 rounded">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-map-marker-alt text-primary me-3 mt-1"></i>
                                    <address class="mb-0">
                                        <strong><?php echo htmlspecialchars($checkout_data['first_name'] . ' ' . $checkout_data['last_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($checkout_data['address_line_1']); ?><br>
                                        <?php if ($checkout_data['address_line_2']): ?>
                                            <?php echo htmlspecialchars($checkout_data['address_line_2']); ?><br>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($checkout_data['city'] . ', ' . $checkout_data['state'] . ' ' . $checkout_data['postal_code']); ?><br>
                                        <?php echo htmlspecialchars($checkout_data['country']); ?><br>
                                        <i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($checkout_data['phone']); ?>
                                    </address>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary Sidebar -->
                <div class="col-lg-4">
                    <div class="card sticky-top" style="top: 20px;">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>Order Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Order Totals -->
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo count($cart_items); ?> items):</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <span>
                                    <?php if ($shipping_fee > 0): ?>
                                        <?php echo formatPrice($shipping_fee); ?>
                                    <?php else: ?>
                                        <span class="text-success">FREE</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (10%):</span>
                                <span><?php echo formatPrice($tax_amount); ?></span>
                            </div>

                            <?php if ($discount_amount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Discount:</span>
                                    <span>-<?php echo formatPrice($discount_amount); ?></span>
                                </div>
                            <?php endif; ?>

                            <hr>

                            <div class="d-flex justify-content-between mb-4">
                                <strong>Total:</strong>
                                <strong class="text-primary h5"><?php echo formatPrice($total_amount); ?></strong>
                            </div>

                            <!-- Action Buttons -->
                            <form method="POST" id="orderForm">
                                <div class="d-grid gap-2">
                                    <button type="submit" name="confirm_order" value="1" class="btn btn-success btn-lg" id="confirmButton">
                                        <i class="fas fa-check me-2"></i>Confirm & Place Order
                                    </button>
                                    <a href="checkout.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Checkout
                                    </a>
                                    <?php if (!isset($_GET['debug']) && !isset($_GET['test'])): ?>
                                    <a href="?debug=1" class="btn btn-outline-info btn-sm mt-2">
                                        <i class="fas fa-bug me-2"></i>Debug Mode
                                    </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Debug Info -->
                                <?php if (isset($_GET['debug']) || isset($_GET['test'])): ?>
                                <div class="mt-3 p-3 bg-light border rounded">
                                    <h6>Debug Information:</h6>
                                    <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                                    <p><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                                    <p><strong>POST Data:</strong> <?php echo empty($_POST) ? 'None' : 'Available'; ?></p>
                                    <p><strong>Checkout Data:</strong> <?php echo isset($_SESSION['checkout_data']) ? 'Available' : 'Missing'; ?></p>
                                    <p><strong>Cart Items:</strong> <?php echo count($cart_items); ?></p>
                                    <?php if (!empty($_POST)): ?>
                                        <p><strong>POST Content:</strong></p>
                                        <pre style="font-size: 12px;"><?php echo htmlspecialchars(print_r($_POST, true)); ?></pre>
                                    <?php endif; ?>
                                    <p><strong>POST Data:</strong> <?php echo empty($_POST) ? 'None' : print_r($_POST, true); ?></p>
                                    <p><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                                    <p><strong>Cart Items:</strong> <?php echo count($cart_items); ?></p>
                                    <p><strong>Total Amount:</strong> <?php echo formatPrice($total_amount); ?></p>
                                </div>
                                <?php endif; ?>
                            </form>

                            <!-- Security Info -->
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    Your order is secure and encrypted
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Estimated Delivery -->
                    <div class="card mt-3">
                        <div class="card-body text-center">
                            <h6><i class="fas fa-truck text-primary me-2"></i>Estimated Delivery</h6>
                            <p class="mb-0">
                                <strong><?php echo date('M d', strtotime('+2 days')); ?> - <?php echo date('M d', strtotime('+5 days')); ?></strong><br>
                                <small class="text-muted">2-5 business days</small>
                            </p>
                        </div>
                    </div>

                    <!-- Customer Support -->
                    <div class="card mt-3">
                        <div class="card-body text-center">
                            <h6><i class="fas fa-headset text-info me-2"></i>Need Help?</h6>
                            <p class="mb-2">
                                <a href="mailto:support@tewuneed.com" class="text-decoration-none">
                                    <i class="fas fa-envelope me-1"></i>support@tewuneed.com
                                </a>
                            </p>
                            <p class="mb-0">
                                <a href="tel:+6212345678" class="text-decoration-none">
                                    <i class="fas fa-phone me-1"></i>+62 123 456 78
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.progress-steps {
    position: relative;
}

.progress-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.step {
    text-align: center;
    position: relative;
    z-index: 2;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.step.completed .step-circle {
    background: #28a745;
    color: white;
}

.step.active .step-circle {
    background: #007bff;
    color: white;
    box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.25);
}

.step-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.step.completed .step-label,
.step.active .step-label {
    color: #495057;
    font-weight: 600;
}

/* Product items styling */
.last-item-no-border:last-child {
    border-bottom: none !important;
    padding-bottom: 0 !important;
}

.product-image-wrapper {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
}

.product-image-wrapper img {
    transition: transform 0.3s ease;
}

.product-image-wrapper:hover img {
    transform: scale(1.05);
}

/* Info items styling */
.info-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item i {
    width: 20px;
    text-align: center;
}

/* Shipping address styling */
.shipping-address {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}

.shipping-address:hover {
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
}

/* Card enhancements */
.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
}

/* Button styling */
.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(40, 167, 69, 0.4);
}

@media (max-width: 768px) {
    .step-label {
        font-size: 10px;
    }

    .step-circle {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }

    .info-item {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }

    .info-item i {
        margin-bottom: 4px;
    }
}
</style>

<script>
// Enhanced order form handling with debugging
document.addEventListener('DOMContentLoaded', function() {
    console.log('Order review page loaded');

    const form = document.getElementById('orderForm');
    const button = document.getElementById('confirmButton');

    console.log('Form found:', !!form);
    console.log('Button found:', !!button);

    if (form && button) {
        // Add click handler for debugging
        button.addEventListener('click', function(e) {
            console.log('Confirm button clicked');

            // Prevent double submission
            if (this.disabled) {
                console.log('Button already disabled, preventing submission');
                e.preventDefault();
                return false;
            }
        });

        // Form submit handler with loading state
        form.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');

            // Validate form data
            const formData = new FormData(this);
            console.log('Form data:', Object.fromEntries(formData));

            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Order...';
            button.disabled = true;

            // Add a small delay to ensure the loading state is visible
            setTimeout(() => {
                console.log('Submitting form...');
            }, 100);

            // Let the form submit normally
            return true;
        });

        console.log('Event handlers attached successfully');
    } else {
        console.error('Form or button not found!');

        // Try to find elements with different selectors
        const allForms = document.querySelectorAll('form');
        const allButtons = document.querySelectorAll('button[type="submit"]');
        console.log('All forms found:', allForms.length);
        console.log('All submit buttons found:', allButtons.length);
    }
});

// Show any error messages
<?php if (isset($_SESSION['error'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        alert('Error: <?php echo addslashes($_SESSION['error']); unset($_SESSION['error']); ?>');
    });
<?php endif; ?>

// Show any success messages
<?php if (isset($_SESSION['success'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Success: <?php echo addslashes($_SESSION['success']); unset($_SESSION['success']); ?>');
    });
<?php endif; ?>
</script>

<!-- Footer removed for cleaner checkout experience -->
</body>
</html>
