<?php
require_once 'config/config.php';

$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    $_SESSION['error'] = 'Order not found.';
    redirect('index.php');
}

try {
    $pdo = getDBConnection();

    // Get order details with enhanced customer info
    $stmt = $pdo->prepare("
        SELECT o.*,
               CASE
                   WHEN o.user_id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name)
                   ELSE JSON_UNQUOTE(JSON_EXTRACT(o.customer_info, '$.first_name'))
               END as customer_name,
               CASE
                   WHEN o.user_id IS NOT NULL THEN u.email
                   ELSE JSON_UNQUOTE(JSON_EXTRACT(o.customer_info, '$.email'))
               END as customer_email,
               CASE
                   WHEN o.user_id IS NOT NULL THEN u.phone
                   ELSE JSON_UNQUOTE(JSON_EXTRACT(o.customer_info, '$.phone'))
               END as customer_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.order_number = ?
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        redirect('index.php');
    }

    // Get order items with enhanced product info
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image_url, p.sku, p.slug
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll();

    // Get customer info from order or user
    $customer_info = null;
    if ($order['customer_info']) {
        $customer_info = json_decode($order['customer_info'], true);
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Error loading order details.';
    redirect('index.php');
}

$page_title = 'Order Confirmation';
include 'includes/header.php';
?>

<!-- Custom Styles -->
<style>
.order-success-animation {
    animation: bounceIn 1s ease-out;
}

@keyframes bounceIn {
    0% { transform: scale(0.3); opacity: 0; }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { transform: scale(1); opacity: 1; }
}

.order-timeline {
    position: relative;
    padding-left: 30px;
}

.order-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #28a745, #20c997);
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #28a745;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #28a745;
}

.timeline-item.pending::before {
    background: #ffc107;
    box-shadow: 0 0 0 3px #ffc107;
}

.payment-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
}

.order-summary-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border-radius: 15px;
}

.success-badge {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    display: inline-block;
    margin-bottom: 20px;
}

.order-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.order-card:hover {
    transform: translateY(-5px);
}

.print-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 12px 25px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.print-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

@media print {
    .no-print { display: none !important; }
    .order-card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Message with Animation -->
            <div class="text-center mb-5 order-success-animation">
                <div class="mb-4">
                    <div class="success-badge">
                        <i class="fas fa-check-circle me-2"></i>Order Confirmed
                    </div>
                </div>
                <h1 class="text-success mb-3">🎉 Thank You for Your Order!</h1>
                <p class="lead">Your order has been successfully placed and is being processed.</p>
                <div class="alert alert-success border-0" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <strong>Order Number:</strong><br>
                            <span class="h5 text-success"><?php echo htmlspecialchars($order['order_number']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Order Date:</strong><br>
                            <span class="h6"><?php echo formatDate($order['created_at']); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($customer_info): ?>
                <div class="alert alert-info border-0 mt-3">
                    <i class="fas fa-user me-2"></i>
                    <strong>Order for:</strong> <?php echo htmlspecialchars($customer_info['first_name'] . ' ' . $customer_info['last_name']); ?>
                    <?php if ($customer_info['email']): ?>
                        <br><small>Confirmation sent to: <?php echo htmlspecialchars($customer_info['email']); ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment Instructions -->
            <?php if ($order['payment_status'] === 'pending'): ?>
            <div class="order-card mb-4">
                <div class="payment-card">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-credit-card me-3" style="font-size: 2rem;"></i>
                        <div>
                            <h5 class="mb-0">Payment Required</h5>
                            <small class="opacity-75">Complete your payment to process your order</small>
                        </div>
                    </div>

                    <?php
                    $payment_method = $order['payment_method'];
                    $total_amount = $order['total_amount'];
                    ?>

                    <?php if (strpos($payment_method, 'Bank Transfer') !== false): ?>
                        <div class="payment-instructions">
                            <h6 class="mb-3"><i class="fas fa-university me-2"></i>Bank Transfer Instructions</h6>
                            <?php if (strpos($payment_method, 'BCA') !== false): ?>
                                <div class="bg-white bg-opacity-20 p-4 rounded-3 mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Bank BCA</strong><br>
                                            <small class="opacity-75">Account Number</small><br>
                                            <span class="h5">1234567890</span><br>
                                            <small class="opacity-75">Account Name</small><br>
                                            <strong>PT TeWuNeed Indonesia</strong>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <small class="opacity-75">Amount to Transfer</small><br>
                                            <span class="h4"><?php echo formatPrice($total_amount); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (strpos($payment_method, 'Mandiri') !== false): ?>
                                <div class="bg-white bg-opacity-20 p-4 rounded-3 mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Bank Mandiri</strong><br>
                                            <small class="opacity-75">Account Number</small><br>
                                            <span class="h5">9876543210</span><br>
                                            <small class="opacity-75">Account Name</small><br>
                                            <strong>PT TeWuNeed Indonesia</strong>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <small class="opacity-75">Amount to Transfer</small><br>
                                            <span class="h4"><?php echo formatPrice($total_amount); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="alert alert-light border-0 bg-white bg-opacity-20">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Important:</strong> Include order number <strong><?php echo $order['order_number']; ?></strong> in transfer description
                            </div>
                        </div>

                    <?php elseif (in_array($payment_method, ['GoPay', 'OVO', 'Dana'])): ?>
                        <div class="payment-instructions">
                            <h6 class="mb-3"><i class="fas fa-mobile-alt me-2"></i><?php echo $payment_method; ?> Payment</h6>
                            <div class="bg-white bg-opacity-20 p-4 rounded-3 text-center">
                                <div class="qr-placeholder mb-3">
                                    <div class="bg-white p-4 rounded-3 d-inline-block">
                                        <i class="fas fa-qrcode text-dark" style="font-size: 4rem;"></i>
                                    </div>
                                    <p class="mt-2 mb-0"><small>Scan QR Code with <?php echo $payment_method; ?> App</small></p>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="opacity-75">Amount</small><br>
                                        <span class="h5"><?php echo formatPrice($total_amount); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="opacity-75">Merchant</small><br>
                                        <strong>TeWuNeed</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($payment_method === 'Credit Card'): ?>
                        <div class="payment-instructions">
                            <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Credit Card Payment</h6>
                            <div class="bg-white bg-opacity-20 p-4 rounded-3 text-center">
                                <p class="mb-3">Secure payment gateway will process your credit card</p>
                                <button class="btn btn-light btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>Pay Now - <?php echo formatPrice($total_amount); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Order Status Timeline -->
            <div class="order-card mb-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0">
                        <i class="fas fa-truck me-2 text-primary"></i>Order Status & Timeline
                    </h5>
                </div>
                <div class="card-body">
                    <div class="order-timeline">
                        <div class="timeline-item">
                            <h6 class="mb-1">Order Placed</h6>
                            <small class="text-muted"><?php echo formatDate($order['created_at']); ?></small>
                            <p class="mb-0 text-success"><i class="fas fa-check me-1"></i>Completed</p>
                        </div>

                        <div class="timeline-item <?php echo $order['payment_status'] === 'pending' ? 'pending' : ''; ?>">
                            <h6 class="mb-1">Payment Confirmation</h6>
                            <small class="text-muted">
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    Payment confirmed
                                <?php else: ?>
                                    Waiting for payment
                                <?php endif; ?>
                            </small>
                            <p class="mb-0 <?php echo $order['payment_status'] === 'paid' ? 'text-success' : 'text-warning'; ?>">
                                <i class="fas <?php echo $order['payment_status'] === 'paid' ? 'fa-check' : 'fa-clock'; ?> me-1"></i>
                                <?php echo $order['payment_status'] === 'paid' ? 'Completed' : 'Pending'; ?>
                            </p>
                        </div>

                        <div class="timeline-item pending">
                            <h6 class="mb-1">Order Processing</h6>
                            <small class="text-muted">Preparing your items</small>
                            <p class="mb-0 text-muted"><i class="fas fa-clock me-1"></i>Pending</p>
                        </div>

                        <div class="timeline-item pending">
                            <h6 class="mb-1">Shipped</h6>
                            <small class="text-muted">On the way to you</small>
                            <p class="mb-0 text-muted"><i class="fas fa-clock me-1"></i>Pending</p>
                        </div>

                        <div class="timeline-item pending">
                            <h6 class="mb-1">Delivered</h6>
                            <small class="text-muted">Estimated: 2-5 business days</small>
                            <p class="mb-0 text-muted"><i class="fas fa-clock me-1"></i>Pending</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="order-card mb-4">
                <div class="card-header bg-light border-0">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2 text-primary"></i>Order Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-hashtag text-primary"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Order Number</small><br>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-calendar text-info"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Order Date</small><br>
                                    <strong><?php echo formatDate($order['created_at']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-credit-card text-success"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Payment Method</small><br>
                                    <strong><?php echo htmlspecialchars($order['payment_method']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="fas fa-money-bill text-warning"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Total Amount</small><br>
                                    <strong class="text-primary h5"><?php echo formatPrice($order['total_amount']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="text-center">
                                <?php
                                $status_class = [
                                    'pending' => 'bg-warning',
                                    'processing' => 'bg-info',
                                    'shipped' => 'bg-primary',
                                    'delivered' => 'bg-success',
                                    'cancelled' => 'bg-danger'
                                ];
                                $status_text = ucfirst($order['status']);
                                $badge_class = $status_class[$order['status']] ?? 'bg-secondary';
                                ?>
                                <small class="text-muted">Order Status</small><br>
                                <span class="badge <?php echo $badge_class; ?> px-3 py-2"><?php echo $status_text; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <?php
                                $payment_status_class = [
                                    'pending' => 'bg-warning',
                                    'paid' => 'bg-success',
                                    'failed' => 'bg-danger',
                                    'refunded' => 'bg-info'
                                ];
                                $payment_status_text = [
                                    'pending' => 'Pending Payment',
                                    'paid' => 'Paid',
                                    'failed' => 'Payment Failed',
                                    'refunded' => 'Refunded'
                                ];
                                $payment_badge_class = $payment_status_class[$order['payment_status']] ?? 'bg-secondary';
                                $payment_text = $payment_status_text[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                ?>
                                <small class="text-muted">Payment Status</small><br>
                                <span class="badge <?php echo $payment_badge_class; ?> px-3 py-2"><?php echo $payment_text; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-bag me-2"></i>Order Items
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($order_items as $item): ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <img src="uploads/<?php echo $item['image'] ?: 'default-product.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                 class="rounded me-3" style="width: 80px; height: 80px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                <p class="text-muted mb-1">Quantity: <?php echo $item['quantity']; ?></p>
                                <p class="text-muted mb-0">Price: <?php echo formatPrice($item['price']); ?></p>
                            </div>
                            <div class="text-end">
                                <strong><?php echo formatPrice($item['total']); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Order Summary -->
                    <div class="row mt-3">
                        <div class="col-md-6 offset-md-6">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?php echo formatPrice($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount'] + $order['discount_amount']); ?></span>
                            </div>
                            
                            <?php if ($order['shipping_amount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <span><?php echo formatPrice($order['shipping_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['tax_amount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax:</span>
                                    <span><?php echo formatPrice($order['tax_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Discount:</span>
                                    <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong class="text-primary"><?php echo formatPrice($order['total_amount']); ?></strong>
                            </div>
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
                    <address class="mb-0">
                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                    </address>
                </div>
            </div>
            
            <!-- Payment Instructions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>Payment Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (strpos($order['payment_method'], 'Bank Transfer') !== false): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Bank Transfer Instructions</h6>
                            <p class="mb-2">Please transfer the exact amount to one of our bank accounts:</p>
                            
                            <?php if ($order['payment_method'] === 'Bank Transfer BCA'): ?>
                                <div class="border p-3 rounded mb-2">
                                    <strong>Bank BCA</strong><br>
                                    Account Number: <strong>1234567890</strong><br>
                                    Account Name: <strong>PT TeWuNeed Indonesia</strong><br>
                                    Amount: <strong><?php echo formatPrice($order['total_amount']); ?></strong>
                                </div>
                            <?php elseif ($order['payment_method'] === 'Bank Transfer Mandiri'): ?>
                                <div class="border p-3 rounded mb-2">
                                    <strong>Bank Mandiri</strong><br>
                                    Account Number: <strong>0987654321</strong><br>
                                    Account Name: <strong>PT TeWuNeed Indonesia</strong><br>
                                    Amount: <strong><?php echo formatPrice($order['total_amount']); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <p class="mb-0">
                                <strong>Important:</strong> Please include your order number 
                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong> 
                                in the transfer description.
                            </p>
                        </div>
                    <?php elseif (in_array($order['payment_method'], ['GoPay', 'OVO', 'Dana'])): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-mobile-alt me-2"></i><?php echo $order['payment_method']; ?> Payment</h6>
                            <p class="mb-2">Please complete your payment using <?php echo $order['payment_method']; ?>:</p>
                            <div class="border p-3 rounded mb-2">
                                <strong>Amount:</strong> <?php echo formatPrice($order['total_amount']); ?><br>
                                <strong>Merchant:</strong> TeWuNeed<br>
                                <strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_number']); ?>
                            </div>
                            <p class="mb-0">You will receive a payment notification on your mobile app.</p>
                        </div>
                    <?php elseif ($order['payment_method'] === 'Credit Card'): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-credit-card me-2"></i>Credit Card Payment</h6>
                            <p class="mb-0">Your credit card will be charged automatically. You will receive a confirmation email shortly.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ol me-2"></i>What's Next?
                    </h5>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">Complete your payment using the instructions above</li>
                        <li class="mb-2">We will verify your payment within 1-2 business hours</li>
                        <li class="mb-2">Your order will be processed and prepared for shipping</li>
                        <li class="mb-2">You will receive tracking information once your order ships</li>
                        <li class="mb-0">Estimated delivery: 2-5 business days</li>
                    </ol>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="text-center no-print">
                <div class="row g-3 justify-content-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="col-md-4">
                            <a href="my-orders.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-list me-2"></i>View My Orders
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <a href="products.php" class="btn btn-outline-primary btn-lg w-100">
                            <i class="fas fa-shopping-cart me-2"></i>Continue Shopping
                        </a>
                    </div>
                    <div class="col-md-4">
                        <button onclick="window.print()" class="btn print-button btn-lg w-100">
                            <i class="fas fa-print me-2"></i>Print Order
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="order-card mt-4">
                <div class="card-body text-center">
                    <h6 class="mb-3">
                        <i class="fas fa-headset me-2 text-primary"></i>Need Help?
                    </h6>
                    <p class="mb-3">Our customer support team is here to help you with any questions about your order.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="mailto:support@tewuneed.com" class="btn btn-outline-primary w-100">
                                <i class="fas fa-envelope me-2"></i>support@tewuneed.com
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="tel:+6212345678" class="btn btn-outline-primary w-100">
                                <i class="fas fa-phone me-2"></i>+62 123 456 78
                            </a>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>Support Hours: Monday - Friday, 9:00 AM - 6:00 PM (WIB)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh order status (optional)
let refreshInterval;

function startAutoRefresh() {
    refreshInterval = setInterval(function() {
        // Check for payment status updates
        fetch('ajax/check-order-status.php?order=' + '<?php echo $order['order_number']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.payment_status !== '<?php echo $order['payment_status']; ?>') {
                    // Payment status changed, reload page
                    location.reload();
                }
            })
            .catch(error => console.log('Status check failed:', error));
    }, 30000); // Check every 30 seconds
}

// Start auto-refresh only if payment is pending
<?php if ($order['payment_status'] === 'pending'): ?>
startAutoRefresh();
<?php endif; ?>

// Stop auto-refresh when page is hidden (user switches tabs)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(refreshInterval);
    } else {
        <?php if ($order['payment_status'] === 'pending'): ?>
        startAutoRefresh();
        <?php endif; ?>
    }
});

// Copy order number to clipboard
function copyOrderNumber() {
    const orderNumber = '<?php echo $order['order_number']; ?>';
    navigator.clipboard.writeText(orderNumber).then(function() {
        // Show success message
        const alert = document.createElement('div');
        alert.className = 'alert alert-success position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; animation: fadeInOut 3s ease-in-out;';
        alert.innerHTML = '<i class="fas fa-check me-2"></i>Order number copied to clipboard!';
        document.body.appendChild(alert);

        setTimeout(() => {
            document.body.removeChild(alert);
        }, 3000);
    });
}

// Add click handler to order number
document.addEventListener('DOMContentLoaded', function() {
    const orderNumberElements = document.querySelectorAll('.order-number-clickable');
    orderNumberElements.forEach(element => {
        element.style.cursor = 'pointer';
        element.title = 'Click to copy';
        element.addEventListener('click', copyOrderNumber);
    });
});

// Print functionality with custom styles
function printOrder() {
    window.print();
}

// Real-time order status updates
let currentStatus = '<?php echo $order['status']; ?>';
let statusCheckInterval;

function checkOrderStatus() {
    fetch(`api/order-status.php?order_number=<?php echo urlencode($order_number); ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.status && data.status !== currentStatus) {
                // Status changed - update the page
                updateOrderStatus(data.status);
                currentStatus = data.status;

                // Show notification
                showStatusNotification(data.status);
            }
        })
        .catch(error => {
            console.log('Status check failed:', error);
        });
}

function updateOrderStatus(newStatus) {
    // Update status badge
    const statusBadge = document.querySelector('.status-badge');
    if (statusBadge) {
        statusBadge.className = `badge bg-${getStatusColor(newStatus)} status-badge`;
        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    }

    // Update timeline if exists
    updateTimeline(newStatus);
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'confirmed': 'info',
        'processing': 'primary',
        'shipped': 'success',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function updateTimeline(status) {
    const timelineItems = document.querySelectorAll('.timeline-item');
    const statusOrder = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const currentIndex = statusOrder.indexOf(status);

    timelineItems.forEach((item, index) => {
        if (index <= currentIndex) {
            item.classList.remove('pending');
            item.classList.add('completed');
        } else {
            item.classList.add('pending');
            item.classList.remove('completed');
        }
    });
}

function showStatusNotification(status) {
    const notification = document.createElement('div');
    notification.className = 'alert alert-success position-fixed';
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; animation: slideInRight 0.5s ease-out;';
    notification.innerHTML = `
        <i class="fas fa-bell me-2"></i>
        <strong>Order Status Updated!</strong><br>
        Your order is now: <span class="badge bg-${getStatusColor(status)} ms-1">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.5s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 500);
    }, 5000);
}

// Start status checking when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check status every 30 seconds
    statusCheckInterval = setInterval(checkOrderStatus, 30000);

    // Stop checking when page is hidden/closed
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(statusCheckInterval);
        } else {
            statusCheckInterval = setInterval(checkOrderStatus, 30000);
        }
    });
});

// Add fade in animation on page load
document.addEventListener('DOMContentLoaded', function() {
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease-in';
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);
});
</script>

<style>
@keyframes fadeInOut {
    0% { opacity: 0; transform: translateX(100%); }
    20% { opacity: 1; transform: translateX(0); }
    80% { opacity: 1; transform: translateX(0); }
    100% { opacity: 0; transform: translateX(100%); }
}
</style>

<?php include 'includes/footer.php'; ?>
