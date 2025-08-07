<?php
require_once 'config/config.php';

$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    $_SESSION['error'] = 'Order not found.';
    redirect('index.php');
}

try {
    $pdo = getDBConnection();
    
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email 
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
    
    // Check if user owns this order (if logged in)
    if (isset($_SESSION['user_id']) && $order['user_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = 'Access denied.';
        redirect('my-orders.php');
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image, p.slug
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error loading order details.';
    redirect('index.php');
}

$page_title = 'Order Details - ' . $order['order_number'];
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <!-- Enhanced Header -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="h3 mb-2 text-primary">
                                <i class="fas fa-receipt me-2"></i>Order Details
                            </h1>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <span class="h5 mb-0">Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
                                <span class="badge bg-<?php
                                    echo match($order['status']) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'shipped' => 'primary',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                ?> fs-6">
                                    <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar me-1"></i>
                                Placed on <?php echo formatDate($order['created_at']); ?>
                                <span class="ms-3">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo timeAgo($order['created_at']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="my-orders.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                                </a>
                            <?php endif; ?>

                            <?php if ($order['status'] === 'shipped'): ?>
                                <button class="btn btn-outline-info" onclick="trackOrder('<?php echo $order['order_number']; ?>')">
                                    <i class="fas fa-truck me-2"></i>Track Package
                                </button>
                            <?php endif; ?>

                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h me-2"></i>Actions
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="window.print()">
                                        <i class="fas fa-print me-2"></i>Print Order
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="downloadInvoice('<?php echo $order['order_number']; ?>')">
                                        <i class="fas fa-download me-2"></i>Download Invoice
                                    </a></li>
                                    <?php if ($order['status'] === 'delivered'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#" onclick="reorderItems('<?php echo $order['id']; ?>')">
                                            <i class="fas fa-redo me-2"></i>Reorder Items
                                        </a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="contactSupport('<?php echo $order['order_number']; ?>')">
                                        <i class="fas fa-headset me-2"></i>Contact Support
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Status and Timeline -->
            <div class="row mb-4">
                <!-- Quick Status Overview -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Order Summary
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="mb-2">
                                        <i class="fas fa-shopping-cart fa-2x text-<?php
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'shipped' => 'primary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>"></i>
                                    </div>
                                    <h6 class="small text-muted">Order Status</h6>
                                    <span class="badge bg-<?php
                                        echo match($order['status']) {
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'shipped' => 'primary',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?> fs-6">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>

                                <div class="col-6 mb-3">
                                    <div class="mb-2">
                                        <i class="fas fa-credit-card fa-2x text-<?php
                                            echo match($order['payment_status']) {
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'failed' => 'danger',
                                                'refunded' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>"></i>
                                    </div>
                                    <h6 class="small text-muted">Payment Status</h6>
                                    <span class="badge bg-<?php
                                        echo match($order['payment_status']) {
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'secondary',
                                            default => 'secondary'
                                        };
                                    ?> fs-6">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>

                                <div class="col-6">
                                    <div class="mb-2">
                                        <i class="fas fa-calendar fa-2x text-info"></i>
                                    </div>
                                    <h6 class="small text-muted">Order Date</h6>
                                    <p class="mb-0 small"><?php echo formatDate($order['created_at']); ?></p>
                                </div>

                                <div class="col-6">
                                    <div class="mb-2">
                                        <i class="fas fa-money-bill fa-2x text-success"></i>
                                    </div>
                                    <h6 class="small text-muted">Total Amount</h6>
                                    <h6 class="text-primary mb-0"><?php echo formatPrice($order['total_amount']); ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Tracking Timeline -->
                <div class="col-lg-8 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-route me-2"></i>Order Tracking Timeline
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($order['status'] !== 'cancelled'): ?>
                                <div class="timeline">
                                    <?php
                                    $timeline_steps = [
                                        'pending' => ['icon' => 'clock', 'title' => 'Order Placed', 'desc' => 'Your order has been received and is being processed'],
                                        'processing' => ['icon' => 'cog', 'title' => 'Processing', 'desc' => 'Your order is being prepared for shipment'],
                                        'shipped' => ['icon' => 'truck', 'title' => 'Shipped', 'desc' => 'Your order is on its way to you'],
                                        'delivered' => ['icon' => 'check-circle', 'title' => 'Delivered', 'desc' => 'Your order has been delivered successfully']
                                    ];

                                    $current_step = array_search($order['status'], array_keys($timeline_steps));
                                    $step_index = 0;

                                    foreach ($timeline_steps as $step_key => $step_data):
                                        $is_completed = $step_index <= $current_step;
                                        $is_current = $step_index === $current_step;
                                        $step_index++;
                                    ?>
                                        <div class="timeline-item <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>">
                                            <div class="timeline-marker">
                                                <i class="fas fa-<?php echo $step_data['icon']; ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title"><?php echo $step_data['title']; ?></h6>
                                                <p class="timeline-desc"><?php echo $step_data['desc']; ?></p>
                                                <?php if ($is_current): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Updated <?php echo timeAgo($order['updated_at'] ?? $order['created_at']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="fas fa-times-circle fa-3x text-danger"></i>
                                    </div>
                                    <h5 class="text-danger">Order Cancelled</h5>
                                    <p class="text-muted">This order has been cancelled and will not be processed.</p>
                                    <small class="text-muted">
                                        Cancelled on <?php echo formatDate($order['updated_at'] ?? $order['created_at']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Order Items -->
                <div class="col-lg-8">
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
                                        <h6 class="mb-1">
                                            <?php if ($item['slug']): ?>
                                                <a href="product.php?slug=<?php echo $item['slug']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="text-muted mb-1">Quantity: <?php echo $item['quantity']; ?></p>
                                        <p class="text-muted mb-0">Unit Price: <?php echo formatPrice($item['price']); ?></p>
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
                </div>
                
                <!-- Order Info Sidebar -->
                <div class="col-lg-4">
                    <!-- Shipping Address -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-truck me-2"></i>Shipping Address
                            </h6>
                        </div>
                        <div class="card-body">
                            <address class="mb-0">
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </address>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i>Payment Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Payment Method:</strong><br>
                                <?php echo htmlspecialchars($order['payment_method']); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Payment Status:</strong><br>
                                <span class="badge bg-<?php 
                                    echo match($order['payment_status']) {
                                        'pending' => 'warning',
                                        'paid' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'secondary',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($order['payment_status'] === 'pending'): ?>
                                <div class="mt-3">
                                    <a href="order-confirmation.php?order=<?php echo $order['order_number']; ?>" 
                                       class="btn btn-warning w-100">
                                        <i class="fas fa-credit-card me-2"></i>Complete Payment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Notes -->
                    <?php if ($order['notes']): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-sticky-note me-2"></i>Order Notes
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Order Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-cog me-2"></i>Order Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                    <button class="btn btn-outline-danger" 
                                            onclick="cancelOrder('<?php echo $order['order_number']; ?>')">
                                        <i class="fas fa-times me-2"></i>Cancel Order
                                    </button>
                                <?php endif; ?>
                                
                                <a href="mailto:support@tewuneed.com?subject=Order <?php echo $order['order_number']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-envelope me-2"></i>Contact Support
                                </a>
                                
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <button class="btn btn-outline-success" onclick="leaveReview()">
                                        <i class="fas fa-star me-2"></i>Leave Review
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function cancelOrder(orderNumber) {
    if (confirm('Are you sure you want to cancel this order?')) {
        fetch('ajax/cancel-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_number: orderNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Order cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Error cancelling order');
            }
        })
        .catch(error => {
            alert('Error cancelling order');
        });
    }
}

function leaveReview() {
    alert('Review feature will be implemented soon!');
}
</script>

<style>
/* Timeline Styles */
.timeline {
    position: relative;
    padding: 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    padding-left: 60px;
    margin-bottom: 30px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    border: 3px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 14px;
    z-index: 1;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
    color: white;
}

.timeline-item.current .timeline-marker {
    background: #007bff;
    color: white;
    animation: pulse 2s infinite;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #e9ecef;
}

.timeline-item.completed .timeline-content {
    border-left-color: #28a745;
}

.timeline-item.current .timeline-content {
    border-left-color: #007bff;
    background: #e3f2fd;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
}

.timeline-desc {
    margin-bottom: 5px;
    color: #6c757d;
    font-size: 0.9rem;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(0, 123, 255, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
    }
}

/* Print Styles */
@media print {
    .btn, .dropdown, .no-print {
        display: none !important;
    }

    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }

    .timeline-marker {
        animation: none !important;
    }
}

/* Enhanced Card Hover Effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

/* Status Badge Enhancements */
.badge {
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* Responsive Timeline */
@media (max-width: 768px) {
    .timeline-item {
        padding-left: 50px;
    }

    .timeline-marker {
        width: 35px;
        height: 35px;
        font-size: 12px;
    }

    .timeline::before {
        left: 17px;
    }
}
</style>

<script>
// Enhanced order detail functions
function trackOrder(orderNumber) {
    showNotification('Opening tracking information...', 'info');
    // Simulate tracking - replace with actual tracking implementation
    setTimeout(() => {
        alert(`Tracking information for order ${orderNumber} will be available soon.`);
    }, 1000);
}

function downloadInvoice(orderNumber) {
    showNotification('Preparing invoice download...', 'info');
    // Implement invoice download
    setTimeout(() => {
        alert(`Invoice download for order ${orderNumber} will be available soon.`);
    }, 1000);
}

function reorderItems(orderId) {
    if (confirm('Add all items from this order to your cart?')) {
        showNotification('Adding items to cart...', 'info');
        // Implement reorder functionality
        setTimeout(() => {
            alert('Reorder feature will be implemented soon.');
        }, 1000);
    }
}

function contactSupport(orderNumber) {
    showNotification('Opening support chat...', 'info');
    // Implement support contact
    setTimeout(() => {
        alert(`Support contact for order ${orderNumber} will be available soon.`);
    }, 1000);
}

// Notification system
function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    };

    const notification = document.createElement('div');
    notification.className = `alert ${alertClass[type]} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Real-time order status updates
function checkOrderStatus() {
    const orderNumber = '<?php echo $order['order_number']; ?>';

    fetch('<?php echo SITE_URL; ?>/ajax/check-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ order_number: orderNumber })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.status_changed) {
            showNotification(`Order status updated to ${data.status}`, 'success');
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(error => {
        console.log('Status check error:', error);
    });
}

// Auto-refresh every 60 seconds
setInterval(checkOrderStatus, 60000);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling to timeline
    const timelineItems = document.querySelectorAll('.timeline-item');
    timelineItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.2}s`;
    });

    // Add print-friendly class
    document.body.classList.add('order-detail-page');

    // Enhanced print functionality
    window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing');
    });

    window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing');
    });
});

// Enhanced print function
function printOrder() {
    // Hide non-essential elements
    const elementsToHide = document.querySelectorAll('.btn, .dropdown, .no-print');
    elementsToHide.forEach(el => el.style.display = 'none');

    window.print();

    // Restore elements
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
    }, 1000);
}
</script>

<?php include 'includes/footer.php'; ?>
