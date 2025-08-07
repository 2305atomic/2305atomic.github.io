<?php
require_once 'config/config.php';

$page_title = 'My Orders';
$page_description = 'View and track your order history';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the current page for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['error'] = 'Please login to view your orders.';

    // Use JavaScript redirect instead of PHP redirect to avoid header issues
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Redirecting to Login...</title>
        <meta http-equiv='refresh' content='0;url=" . SITE_URL . "/login.php'>
    </head>
    <body>
        <script>
            window.location.href = '" . SITE_URL . "/login.php';
        </script>
        <p>Redirecting to login page... <a href='" . SITE_URL . "/login.php'>Click here if not redirected automatically</a></p>
    </body>
    </html>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = ['o.user_id = ?'];
$params = [$user_id];

if (!empty($status_filter)) {
    $where_conditions[] = 'o.status = ?';
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = '(o.order_number LIKE ? OR p.name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $pdo = getDBConnection();
    
    // Get orders with item count
    $sql = "
        SELECT o.*, 
               COUNT(oi.id) as item_count,
               GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        {$where_clause}
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $orders = [];
    $error_message = 'Error loading orders: ' . $e->getMessage();
    error_log("My Orders Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<!-- Error Display -->
<?php if (isset($error_message)): ?>
<div class="container mt-3">
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<div class="container-lg my-5">
    <div class="row">
        <div class="col-12">

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 mb-1 text-primary">
                        <i class="fas fa-shopping-bag me-2"></i>My Orders
                    </h1>
                    <p class="text-muted">Track and manage your order history</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary fs-6 mb-2"><?php echo count($orders); ?> Orders</span>
                    <br>
                    <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Shop More
                    </a>
                </div>
            </div>

            <!-- Enhanced Filters and Quick Stats -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <!-- Quick Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="small text-muted">Pending</div>
                                <div class="fw-bold" id="pending-count">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-info">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="small text-muted">Processing</div>
                                <div class="fw-bold" id="processing-count">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-primary">
                                    <i class="fas fa-shipping-fast"></i>
                                </div>
                                <div class="small text-muted">Shipped</div>
                                <div class="fw-bold" id="shipped-count">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 mb-1 text-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="small text-muted">Delivered</div>
                                <div class="fw-bold" id="delivered-count">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="GET" class="row g-3" id="filter-form">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Orders</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_range" class="form-label">Date Range</label>
                            <select name="date_range" id="date_range" class="form-select">
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="3months">Last 3 Months</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Orders</label>
                            <input type="text" name="search" id="search" class="form-control"
                                   placeholder="Search by order number or product name..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Quick Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="refreshOrders()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
                            <label class="form-check-label small text-muted" for="auto-refresh">
                                Auto-refresh (30s)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders List -->
            <?php if (empty($orders)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <i class="fas fa-shopping-bag fa-3x text-muted"></i>
                            </div>
                        </div>
                        <h3 class="text-gray-900 mb-3">No Orders Found</h3>
                        <p class="text-muted mb-4 lead">
                            <?php if (!empty($status_filter) || !empty($search)): ?>
                                No orders match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                You haven't placed any orders yet. Start shopping to see your orders here!
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($status_filter) || !empty($search)): ?>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="<?php echo SITE_URL; ?>/my-orders.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-filter me-2"></i>Clear Filters
                                </a>
                                <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart me-2"></i>Continue Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="<?php echo SITE_URL; ?>/products.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-shopping-cart me-2"></i>Start Shopping
                                </a>
                                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-home me-2"></i>Browse Categories
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $status_colors = [
                            'pending' => 'warning',
                            'processing' => 'info',
                            'shipped' => 'primary',
                            'delivered' => 'success',
                            'cancelled' => 'danger'
                        ];
                        
                        $payment_colors = [
                            'pending' => 'warning',
                            'paid' => 'success',
                            'failed' => 'danger',
                            'refunded' => 'secondary'
                        ];
                        
                        $status_color = $status_colors[$order['status']] ?? 'secondary';
                        $payment_color = $payment_colors[$order['payment_status']] ?? 'secondary';
                        ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100 shadow-sm border-0 hover-card order-card" data-order-id="<?php echo $order['id']; ?>">
                                <div class="card-header bg-white border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 text-primary fw-bold">
                                                <i class="fas fa-receipt me-2"></i>
                                                <?php echo htmlspecialchars($order['order_number']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatDate($order['created_at']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $status_color; ?> mb-1 px-3 py-2 order-status-badge">
                                                <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                                <span class="status-text"><?php echo ucfirst($order['status']); ?></span>
                                            </span>
                                            <br>
                                            <span class="badge bg-<?php echo $payment_color; ?> px-3 py-1 payment-status-badge">
                                                <i class="fas fa-credit-card me-1"></i>
                                                <span class="payment-text"><?php echo ucfirst($order['payment_status']); ?></span>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Order Progress Bar -->
                                    <?php if ($order['status'] !== 'cancelled'): ?>
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">Order Progress</small>
                                                <small class="text-muted">
                                                    <?php
                                                    $progress_percentage = [
                                                        'pending' => 25,
                                                        'processing' => 50,
                                                        'shipped' => 75,
                                                        'delivered' => 100
                                                    ];
                                                    echo ($progress_percentage[$order['status']] ?? 0) . '%';
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $status_color; ?>"
                                                     style="width: <?php echo ($progress_percentage[$order['status']] ?? 0); ?>%"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small class="text-<?php echo $order['status'] === 'pending' ? 'primary' : 'muted'; ?>">Pending</small>
                                                <small class="text-<?php echo $order['status'] === 'processing' ? 'primary' : 'muted'; ?>">Processing</small>
                                                <small class="text-<?php echo $order['status'] === 'shipped' ? 'primary' : 'muted'; ?>">Shipped</small>
                                                <small class="text-<?php echo $order['status'] === 'delivered' ? 'primary' : 'muted'; ?>">Delivered</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-4">
                                            <small class="text-muted">Items</small>
                                            <div class="fw-bold">
                                                <i class="fas fa-box me-1 text-primary"></i>
                                                <?php echo $order['item_count']; ?> item(s)
                                            </div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <small class="text-muted">Total</small>
                                            <div class="fw-bold text-success"><?php echo formatPrice($order['total_amount']); ?></div>
                                        </div>
                                        <div class="col-4 text-end">
                                            <small class="text-muted">Est. Delivery</small>
                                            <div class="fw-bold small">
                                                <?php
                                                if ($order['status'] === 'delivered') {
                                                    echo '<span class="text-success">Delivered</span>';
                                                } elseif ($order['status'] === 'cancelled') {
                                                    echo '<span class="text-danger">Cancelled</span>';
                                                } else {
                                                    $delivery_days = [
                                                        'pending' => '5-7 days',
                                                        'processing' => '3-5 days',
                                                        'shipped' => '1-3 days'
                                                    ];
                                                    echo $delivery_days[$order['status']] ?? 'TBD';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">Products</small>
                                        <div class="small">
                                            <?php
                                            $product_names = $order['product_names'] ?? 'No products';
                                            echo strlen($product_names) > 60 ? substr($product_names, 0, 60) . '...' : $product_names;
                                            ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <?php if (!empty($order['payment_method'])): ?>
                                        <div class="col-6">
                                            <small class="text-muted">Payment Method</small>
                                            <div class="small">
                                                <i class="fas fa-credit-card me-1"></i>
                                                <?php echo htmlspecialchars($order['payment_method']); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($order['shipping_address'])): ?>
                                        <div class="col-6">
                                            <small class="text-muted">Shipping To</small>
                                            <div class="small">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php
                                                $address = json_decode($order['shipping_address'], true);
                                                echo isset($address['city']) ? htmlspecialchars($address['city']) : 'Address on file';
                                                ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <span class="order-time"><?php echo timeAgo($order['created_at']); ?></span>
                                        </small>
                                        <div class="d-flex gap-1">
                                            <a href="order-detail.php?order=<?php echo $order['order_number']; ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>Details
                                            </a>

                                            <?php if ($order['status'] === 'shipped'): ?>
                                                <button class="btn btn-sm btn-outline-info"
                                                        onclick="trackOrder('<?php echo $order['order_number']; ?>')">
                                                    <i class="fas fa-truck me-1"></i>Track
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($order['status'] === 'delivered'): ?>
                                                <button class="btn btn-sm btn-outline-warning"
                                                        onclick="rateOrder('<?php echo $order['id']; ?>')">
                                                    <i class="fas fa-star me-1"></i>Rate
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($order['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="cancelOrder('<?php echo $order['id']; ?>', '<?php echo $order['order_number']; ?>')">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </button>
                                            <?php endif; ?>

                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                        type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="order-detail.php?order=<?php echo $order['order_number']; ?>">
                                                        <i class="fas fa-receipt me-2"></i>View Invoice
                                                    </a></li>
                                                    <?php if ($order['status'] === 'delivered'): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="reorderItems('<?php echo $order['id']; ?>')">
                                                            <i class="fas fa-redo me-2"></i>Reorder
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#" onclick="downloadInvoice('<?php echo $order['order_number']; ?>')">
                                                        <i class="fas fa-download me-2"></i>Download Invoice
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" onclick="contactSupport('<?php echo $order['order_number']; ?>')">
                                                        <i class="fas fa-headset me-2"></i>Contact Support
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Real-time status indicator -->
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="small text-muted">
                                            Last updated: <span class="last-updated" data-time="<?php echo $order['updated_at'] ?? $order['created_at']; ?>">
                                                <?php echo timeAgo($order['updated_at'] ?? $order['created_at']); ?>
                                            </span>
                                        </div>
                                        <div class="status-indicator">
                                            <span class="badge badge-sm bg-light text-dark">
                                                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>
                                                Live
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: all 0.3s ease;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.text-gray-900 {
    color: #1a202c !important;
}

.order-status-pending { color: #f59e0b; }
.order-status-processing { color: #3b82f6; }
.order-status-shipped { color: #8b5cf6; }
.order-status-delivered { color: #10b981; }
.order-status-cancelled { color: #ef4444; }

.payment-status-pending { color: #f59e0b; }
.payment-status-paid { color: #10b981; }
.payment-status-failed { color: #ef4444; }
.payment-status-refunded { color: #6b7280; }
</style>

<script>
// Enhanced order management functions
function cancelOrder(orderId, orderNumber) {
    if (confirm(`Are you sure you want to cancel order ${orderNumber}?`)) {
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cancelling...';
        button.disabled = true;

        fetch('<?php echo SITE_URL; ?>/ajax/cancel-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId,
                order_number: orderNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Order cancelled successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to cancel order', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            showNotification('An error occurred while cancelling the order', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
}

function trackOrder(orderNumber) {
    showNotification('Opening tracking information...', 'info');
    // Simulate tracking - replace with actual tracking implementation
    setTimeout(() => {
        alert(`Tracking information for order ${orderNumber} will be available soon.`);
    }, 1000);
}

function rateOrder(orderId) {
    showNotification('Opening rating form...', 'info');
    // Implement rating functionality
    setTimeout(() => {
        alert('Rating feature will be implemented soon.');
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

function downloadInvoice(orderNumber) {
    showNotification('Preparing invoice download...', 'info');
    // Implement invoice download
    setTimeout(() => {
        alert(`Invoice download for order ${orderNumber} will be available soon.`);
    }, 1000);
}

function contactSupport(orderNumber) {
    showNotification('Opening support chat...', 'info');
    // Implement support contact
    setTimeout(() => {
        alert(`Support contact for order ${orderNumber} will be available soon.`);
    }, 1000);
}

function clearFilters() {
    window.location.href = '<?php echo SITE_URL; ?>/my-orders.php';
}

function refreshOrders() {
    showNotification('Refreshing orders...', 'info');
    location.reload();
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
let autoRefreshInterval;

function startAutoRefresh() {
    const autoRefreshCheckbox = document.getElementById('auto-refresh');
    if (autoRefreshCheckbox && autoRefreshCheckbox.checked) {
        autoRefreshInterval = setInterval(checkOrderUpdates, 30000);
    }
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function checkOrderUpdates() {
    const orderCards = document.querySelectorAll('.order-card');
    const orderIds = Array.from(orderCards).map(card => card.dataset.orderId);

    if (orderIds.length === 0) return;

    fetch('<?php echo SITE_URL; ?>/ajax/check-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ order_ids: orderIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.updates) {
            updateOrderStatuses(data.updates);
        }
    })
    .catch(error => {
        console.log('Auto-refresh error:', error);
    });
}

function updateOrderStatuses(updates) {
    updates.forEach(update => {
        const orderCard = document.querySelector(`[data-order-id="${update.id}"]`);
        if (orderCard) {
            // Update status badge
            const statusBadge = orderCard.querySelector('.order-status-badge');
            const statusText = orderCard.querySelector('.status-text');
            if (statusBadge && statusText) {
                statusBadge.className = `badge bg-${getStatusColor(update.status)} mb-1 px-3 py-2 order-status-badge`;
                statusText.textContent = update.status.charAt(0).toUpperCase() + update.status.slice(1);
            }

            // Update payment status
            const paymentBadge = orderCard.querySelector('.payment-status-badge');
            const paymentText = orderCard.querySelector('.payment-text');
            if (paymentBadge && paymentText && update.payment_status) {
                paymentBadge.className = `badge bg-${getPaymentColor(update.payment_status)} px-3 py-1 payment-status-badge`;
                paymentText.textContent = update.payment_status.charAt(0).toUpperCase() + update.payment_status.slice(1);
            }

            // Update last updated time
            const lastUpdated = orderCard.querySelector('.last-updated');
            if (lastUpdated) {
                lastUpdated.textContent = 'Just now';
            }

            // Show notification for status changes
            if (update.status_changed) {
                showNotification(`Order ${update.order_number} status updated to ${update.status}`, 'success');
            }
        }
    });
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'processing': 'info',
        'shipped': 'primary',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function getPaymentColor(status) {
    const colors = {
        'pending': 'warning',
        'paid': 'success',
        'failed': 'danger',
        'refunded': 'secondary'
    };
    return colors[status] || 'secondary';
}

// Update order statistics
function updateOrderStats() {
    const orders = document.querySelectorAll('.order-card');
    const stats = {
        pending: 0,
        processing: 0,
        shipped: 0,
        delivered: 0
    };

    orders.forEach(order => {
        const statusText = order.querySelector('.status-text');
        if (statusText) {
            const status = statusText.textContent.toLowerCase();
            if (stats.hasOwnProperty(status)) {
                stats[status]++;
            }
        }
    });

    // Update stat displays
    Object.keys(stats).forEach(status => {
        const element = document.getElementById(`${status}-count`);
        if (element) {
            element.textContent = stats[status];
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Update initial stats
    updateOrderStats();

    // Start auto-refresh if enabled
    startAutoRefresh();

    // Handle auto-refresh toggle
    const autoRefreshCheckbox = document.getElementById('auto-refresh');
    if (autoRefreshCheckbox) {
        autoRefreshCheckbox.addEventListener('change', function() {
            if (this.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
    }

    // Add loading states to filter form
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Filtering...';
                submitBtn.disabled = true;
            }
        });
    }

    // Update time displays every minute
    setInterval(() => {
        document.querySelectorAll('.order-time, .last-updated').forEach(element => {
            const time = element.dataset.time || element.getAttribute('data-time');
            if (time) {
                element.textContent = timeAgo(new Date(time));
            }
        });
    }, 60000);
});

// Helper function for time ago display
function timeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
    return `${Math.floor(diffInSeconds / 86400)} days ago`;
}
</script>

<?php include 'includes/footer.php'; ?>
