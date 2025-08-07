<?php
require_once 'config/config.php';

// Get order data from session or create a sample order
$order_data = $_SESSION['last_order'] ?? null;

// If no order data, redirect to home
if (!$order_data) {
    $_SESSION['error'] = 'No order data found. Please place an order first.';
    redirect('index.php');
}

$page_title = 'Order Confirmation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .success-icon {
            font-size: 4rem;
            color: #28a745;
        }
        .order-card {
            border: 2px solid #28a745;
            border-radius: 15px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body class="bg-light">

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
            <i class="fas fa-shopping-bag me-2"></i><?php echo SITE_NAME; ?>
        </a>
    </div>
</nav>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Success Message -->
            <div class="text-center mb-5">
                <div class="success-icon mb-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="text-success mb-3">Thank You for Your Order!</h1>
                <p class="lead text-muted">Your order has been successfully placed and is being processed.</p>
            </div>

            <!-- Order Details Card -->
            <div class="card order-card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Details</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Order Number</h6>
                            <p class="fw-bold fs-5"><?php echo $order_data['order_number']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Order Date</h6>
                            <p><?php echo date('d M Y, H:i', strtotime($order_data['order_date'])); ?></p>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Order Status</h6>
                            <span class="badge bg-warning status-badge">
                                <i class="fas fa-clock me-1"></i><?php echo ucfirst($order_data['status']); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Payment Status</h6>
                            <span class="badge bg-info status-badge">
                                <i class="fas fa-credit-card me-1"></i><?php echo ucfirst($order_data['payment_status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Customer Name</h6>
                            <p><?php echo $order_data['customer_name']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Email</h6>
                            <p><?php echo $order_data['customer_email']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-box me-2"></i>Order Items</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($order_data['items'] as $item): ?>
                    <div class="row align-items-center py-3 border-bottom">
                        <div class="col-md-6">
                            <h6 class="mb-1"><?php echo $item['name']; ?></h6>
                            <small class="text-muted">Quantity: <?php echo $item['quantity']; ?></small>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="text-muted">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="col-md-3 text-end">
                            <strong>Rp <?php echo number_format($item['total'], 0, ',', '.'); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="row mt-3">
                        <div class="col-md-9 text-end">
                            <h5>Total Amount:</h5>
                        </div>
                        <div class="col-md-3 text-end">
                            <h5 class="text-success">Rp <?php echo number_format($order_data['total_amount'], 0, ',', '.'); ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>What's Next?</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="text-info mb-2">
                                <i class="fas fa-envelope fa-2x"></i>
                            </div>
                            <h6>Email Confirmation</h6>
                            <p class="small text-muted">You'll receive an email confirmation shortly</p>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="text-info mb-2">
                                <i class="fas fa-cog fa-2x"></i>
                            </div>
                            <h6>Processing</h6>
                            <p class="small text-muted">We'll prepare your order for shipping</p>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="text-info mb-2">
                                <i class="fas fa-truck fa-2x"></i>
                            </div>
                            <h6>Delivery</h6>
                            <p class="small text-muted">Your order will be delivered in 2-5 business days</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center">
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-home me-2"></i>Continue Shopping
                </a>
                <a href="my-orders.php" class="btn btn-outline-primary btn-lg me-3">
                    <i class="fas fa-list me-2"></i>View My Orders
                </a>

                <?php if (isset($order_data['order_id'])): ?>
                <a href="order-confirmation.php?order=<?php echo $order_data['order_number']; ?>" class="btn btn-outline-success btn-lg">
                    <i class="fas fa-receipt me-2"></i>View Order Details
                </a>
                <?php endif; ?>
            </div>

            <!-- Admin Quick Link (if admin is logged in) -->
            <?php if (isset($_SESSION['admin_id']) && isset($order_data['order_id'])): ?>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/admin/order-detail.php?id=<?php echo $order_data['order_id']; ?>"
                   class="btn btn-warning btn-sm">
                    <i class="fas fa-cog me-2"></i>Admin: Manage This Order
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Clear the last order from session after displaying
<?php unset($_SESSION['last_order']); ?>

// Auto-scroll to top
window.scrollTo(0, 0);
</script>

</body>
</html>
